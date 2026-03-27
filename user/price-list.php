<?php

include('config/config.php');
include('includes/auth-check.php');

// Ensure only authorized users can access this page
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin', 'lineman'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$brand_id = isset($_GET['brand_id']) ? intval($_GET['brand_id']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$sort_by = isset($_GET['sort_by']) ? mysqli_real_escape_string($conn, $_GET['sort_by']) : 'product_name';
$sort_order = isset($_GET['sort_order']) ? mysqli_real_escape_string($conn, $_GET['sort_order']) : 'asc';

// Handle bulk price update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['bulk_update_prices'])) {
        $price_changes = [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($_POST['product_id'] as $index => $product_id) {
            $product_id = intval($product_id);
            $new_stock_price = floatval($_POST['new_stock_price'][$index]);
            $new_customer_price = floatval($_POST['new_customer_price'][$index]);
            
            if ($new_customer_price > $new_stock_price && $product_id > 0) {
                // Calculate new profit
                $profit = $new_customer_price - $new_stock_price;
                $profit_percentage = $new_stock_price > 0 ? ($profit / $new_stock_price) * 100 : 0;
                
                $update_sql = "UPDATE products SET 
                               stock_price = $new_stock_price,
                               customer_price = $new_customer_price,
                               profit = $profit,
                               profit_percentage = $profit_percentage,
                               updated_at = NOW()
                               WHERE id = $product_id";
                
                if (mysqli_query($conn, $update_sql)) {
                    $success_count++;
                    $price_changes[] = [
                        'product_id' => $product_id,
                        'stock_price' => $new_stock_price,
                        'customer_price' => $new_customer_price
                    ];
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            $success_message = "Successfully updated prices for $success_count products!";
            if ($error_count > 0) {
                $error_message = "$error_count products failed to update.";
            }
        } else {
            $error_message = "No prices were updated. Please check your inputs.";
        }
    }
    
    // Handle apply percentage increase
    if (isset($_POST['apply_percentage'])) {
        $percentage = floatval($_POST['percentage']);
        $apply_to = mysqli_real_escape_string($conn, $_POST['apply_to']);
        
        if ($percentage != 0) {
            if ($apply_to == 'customer_price') {
                // Increase selling price only
                $update_sql = "UPDATE products SET 
                               customer_price = customer_price + (customer_price * $percentage / 100),
                               profit = (customer_price + (customer_price * $percentage / 100)) - stock_price,
                               profit_percentage = ((customer_price + (customer_price * $percentage / 100)) - stock_price) / stock_price * 100,
                               updated_at = NOW()
                               WHERE status = 'active'";
            } else {
                // Increase both stock and customer prices
                $update_sql = "UPDATE products SET 
                               stock_price = stock_price + (stock_price * $percentage / 100),
                               customer_price = customer_price + (customer_price * $percentage / 100),
                               profit = customer_price - stock_price,
                               profit_percentage = (customer_price - stock_price) / stock_price * 100,
                               updated_at = NOW()
                               WHERE status = 'active'";
            }
            
            if (mysqli_query($conn, $update_sql)) {
                $affected_rows = mysqli_affected_rows($conn);
                $success_message = "Applied $percentage% increase to $affected_rows products!";
            } else {
                $error_message = "Failed to apply percentage increase: " . mysqli_error($conn);
            }
        }
    }
}

// Build query for products
$sql = "SELECT p.*, 
               c.category_name,
               b.brand_name,
               COALESCE(SUM(oi.quantity), 0) as total_sold
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.status = 'active'";

$conditions = [];

// Add search condition
if (!empty($search)) {
    $conditions[] = "(p.product_name LIKE '%$search%' OR 
                     p.product_code LIKE '%$search%' OR 
                     p.description LIKE '%$search%')";
}

// Add category filter
if ($category_id > 0) {
    $conditions[] = "p.category_id = $category_id";
}

// Add brand filter
if ($brand_id > 0) {
    $conditions[] = "p.brand_id = $brand_id";
}

// Add price filters
if ($min_price > 0) {
    $conditions[] = "p.customer_price >= $min_price";
}
if ($max_price > 0) {
    $conditions[] = "p.customer_price <= $max_price";
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Group by product
$sql .= " GROUP BY p.id";

// Add sorting
$valid_sort_columns = ['product_name', 'product_code', 'category_name', 'customer_price', 'stock_price', 'profit_percentage', 'quantity'];
$valid_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'product_name';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'asc';
}

$sql .= " ORDER BY $sort_by $sort_order";

// Execute query
$result = mysqli_query($conn, $sql);

// Store result for later use
$products_data = [];
$total_products = 0;
if ($result) {
    // Store all products in array for multiple uses
    while ($row = mysqli_fetch_assoc($result)) {
        $products_data[] = $row;
    }
    $total_products = count($products_data);
    
    // Reset pointer for template use
    reset($products_data);
}

// Get categories for dropdowns
$categories_sql = "SELECT * FROM categories WHERE status = 'active' ORDER BY category_name";
$categories_result = mysqli_query($conn, $categories_sql);

// Get brands for dropdowns
$brands_sql = "SELECT * FROM brands WHERE status = 'active' ORDER BY brand_name";
$brands_result = mysqli_query($conn, $brands_sql);

// Calculate price statistics
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    AVG(stock_price) as avg_cost_price,
    AVG(customer_price) as avg_selling_price,
    AVG(profit_percentage) as avg_profit_percentage,
    MIN(customer_price) as min_price,
    MAX(customer_price) as max_price,
    SUM(quantity * customer_price) as total_stock_value
    FROM products 
    WHERE status = 'active'";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>

<body data-sidebar="dark">

    <!-- Loader -->
    <?php include('includes/pre-loader.php') ?>

    <!-- Begin page -->
    <div id="layout-wrapper">

        <?php include('includes/topbar.php') ?>

        <!-- ========== Left Sidebar Start ========== -->
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <!--- Sidemenu -->
                <?php 
                $current_page = 'price-list';
                include('includes/sidebar.php');
                ?>
            </div>
        </div>
        <!-- Left Sidebar End -->

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Messages -->
                    <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Header -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h4 class="card-title mb-0">Price List</h4>
                            <p class="card-title-desc">View and manage product prices</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                                <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                                    <i class="mdi mdi-pencil-box-multiple me-1"></i> Bulk Update
                                </button>
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#percentageModal">
                                    <i class="mdi mdi-percent me-1"></i> Apply Percentage
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-info" onclick="printPriceList()">
                                    <i class="mdi mdi-printer me-1"></i> Print Price List
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportPriceList()">
                                    <i class="mdi mdi-download me-1"></i> Export
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Price Statistics -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Avg. Cost Price</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['avg_cost_price'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-info me-2">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                                <span>Average cost</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-cash-minus"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Avg. Selling Price</h5>
                                            <h3 class="my-2 py-1 text-success">₹<?php echo number_format($stats['avg_selling_price'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                                <span>Average selling</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                    <i class="mdi mdi-cash-plus"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Avg. Profit Margin</h5>
                                            <h3 class="my-2 py-1 text-primary"><?php echo number_format($stats['avg_profit_percentage'] ?? 0, 1); ?>%</h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-primary me-2">
                                                    <i class="mdi mdi-chart-line"></i>
                                                </span>
                                                <span>Average margin</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-chart-bar"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Stock Value</h5>
                                            <h3 class="my-2 py-1 text-warning">₹<?php echo number_format($stats['total_stock_value'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-warning me-2">
                                                    <i class="mdi mdi-warehouse"></i>
                                                </span>
                                                <span>At selling price</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-warning bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-warning text-warning">
                                                    <i class="mdi mdi-package-variant"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Price Range Chart -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Price Distribution</h5>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="border rounded p-3 text-center">
                                                <h6 class="text-muted mb-2">Lowest Price</h6>
                                                <h3 class="mb-0 text-info">₹<?php echo number_format($stats['min_price'] ?? 0, 2); ?></h3>
                                                <p class="text-muted mb-0">Minimum selling price</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-3 text-center">
                                                <h6 class="text-muted mb-2">Highest Price</h6>
                                                <h3 class="mb-0 text-danger">₹<?php echo number_format($stats['max_price'] ?? 0, 2); ?></h3>
                                                <p class="text-muted mb-0">Maximum selling price</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3">
                                                <h6 class="text-muted mb-2">Price Range Analysis</h6>
                                                <?php
                                                // Calculate price ranges
                                                $range_sql = "SELECT 
                                                    COUNT(CASE WHEN customer_price < 50 THEN 1 END) as under_50,
                                                    COUNT(CASE WHEN customer_price >= 50 AND customer_price < 100 THEN 1 END) as between_50_100,
                                                    COUNT(CASE WHEN customer_price >= 100 AND customer_price < 200 THEN 1 END) as between_100_200,
                                                    COUNT(CASE WHEN customer_price >= 200 AND customer_price < 500 THEN 1 END) as between_200_500,
                                                    COUNT(CASE WHEN customer_price >= 500 THEN 1 END) as over_500
                                                    FROM products WHERE status = 'active'";
                                                $range_result = mysqli_query($conn, $range_sql);
                                                $range_data = mysqli_fetch_assoc($range_result);
                                                ?>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="mb-2">
                                                            <small class="float-end text-muted"><?php echo $range_data['under_50'] ?? 0; ?> products</small>
                                                            <span class="text-muted">Under ₹50</span>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar" style="width: <?php echo ($range_data['under_50'] / max(1, $total_products)) * 100; ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <small class="float-end text-muted"><?php echo $range_data['between_50_100'] ?? 0; ?> products</small>
                                                            <span class="text-muted">₹50 - ₹100</span>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-success" style="width: <?php echo ($range_data['between_50_100'] / max(1, $total_products)) * 100; ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <small class="float-end text-muted"><?php echo $range_data['between_100_200'] ?? 0; ?> products</small>
                                                            <span class="text-muted">₹100 - ₹200</span>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-info" style="width: <?php echo ($range_data['between_100_200'] / max(1, $total_products)) * 100; ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <small class="float-end text-muted"><?php echo $range_data['between_200_500'] ?? 0; ?> products</small>
                                                            <span class="text-muted">₹200 - ₹500</span>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-warning" style="width: <?php echo ($range_data['between_200_500'] / max(1, $total_products)) * 100; ?>%"></div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <small class="float-end text-muted"><?php echo $range_data['over_500'] ?? 0; ?> products</small>
                                                            <span class="text-muted">Over ₹500</span>
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-danger" style="width: <?php echo ($range_data['over_500'] / max(1, $total_products)) * 100; ?>%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Search & Filter -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="search" 
                                                   value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search products...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="category_id">
                                                <option value="0">All Categories</option>
                                                <?php 
                                                while ($cat = mysqli_fetch_assoc($categories_result)): 
                                                ?>
                                                <option value="<?php echo $cat['id']; ?>" 
                                                    <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <?php 
                                            mysqli_data_seek($brands_result, 0);
                                            ?>
                                            <select class="form-select" name="brand_id">
                                                <option value="0">All Brands</option>
                                                <?php 
                                                while ($brand = mysqli_fetch_assoc($brands_result)): 
                                                ?>
                                                <option value="<?php echo $brand['id']; ?>" 
                                                    <?php echo $brand_id == $brand['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($brand['brand_name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="sort_by">
                                                <option value="product_name" <?php echo $sort_by == 'product_name' ? 'selected' : ''; ?>>Sort by Name</option>
                                                <option value="customer_price" <?php echo $sort_by == 'customer_price' ? 'selected' : ''; ?>>Sort by Price</option>
                                                <option value="profit_percentage" <?php echo $sort_by == 'profit_percentage' ? 'selected' : ''; ?>>Sort by Margin</option>
                                                <option value="quantity" <?php echo $sort_by == 'quantity' ? 'selected' : ''; ?>>Sort by Stock</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="sort_order">
                                                <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>Ascending</option>
                                                <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>Descending</option>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="mdi mdi-filter"></i>
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <?php if (!empty($search) || $category_id > 0 || $brand_id > 0): ?>
                                    <div class="mt-3">
                                        <a href="price-list.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Price List Table -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                    <form method="POST" id="priceListForm">
                                    <?php endif; ?>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Category & Brand</th>
                                                    <th class="text-center">Stock</th>
                                                    <th class="text-end">Cost Price</th>
                                                    <th class="text-end">Selling Price</th>
                                                    <th class="text-end">Profit</th>
                                                    <th class="text-center">Margin</th>
                                                    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                    <th class="text-center">Update Prices</th>
                                                    <?php endif; ?>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (!empty($products_data)) {
                                                    foreach ($products_data as $row) {
                                                        $stock_class = $row['quantity'] <= 10 ? 'danger' : ($row['quantity'] <= 50 ? 'warning' : 'success');
                                                        $margin_class = $row['profit_percentage'] < 20 ? 'danger' : ($row['profit_percentage'] < 30 ? 'warning' : 'success');
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($row['product_name']); ?></h5>
                                                                    <p class="text-muted mb-0">Code: <?php echo $row['product_code']; ?></p>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary-subtle text-primary mb-1">
                                                                    <?php echo !empty($row['category_name']) ? $row['category_name'] : 'Uncategorized'; ?>
                                                                </span>
                                                                <?php if (!empty($row['brand_name'])): ?>
                                                                <br>
                                                                <span class="badge bg-info-subtle text-info">
                                                                    <?php echo $row['brand_name']; ?>
                                                                </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-<?php echo $stock_class; ?>-subtle text-<?php echo $stock_class; ?>">
                                                                    <?php echo number_format($row['quantity']); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-end">
                                                                <h6 class="mb-0">₹<?php echo number_format($row['stock_price'], 2); ?></h6>
                                                                <small class="text-muted">Cost</small>
                                                            </td>
                                                            <td class="text-end">
                                                                <h6 class="mb-0 text-success">₹<?php echo number_format($row['customer_price'], 2); ?></h6>
                                                                <small class="text-muted">Selling</small>
                                                            </td>
                                                            <td class="text-end">
                                                                <h6 class="mb-0 text-primary">₹<?php echo number_format($row['profit'], 2); ?></h6>
                                                                <small class="text-muted">Profit</small>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-<?php echo $margin_class; ?>-subtle text-<?php echo $margin_class; ?>">
                                                                    <?php echo number_format($row['profit_percentage'], 1); ?>%
                                                                </span>
                                                            </td>
                                                            <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                            <td class="text-center">
                                                                <input type="hidden" name="product_id[]" value="<?php echo $row['id']; ?>">
                                                                <div class="row g-2">
                                                                    <div class="col-6">
                                                                        <input type="number" class="form-control form-control-sm" 
                                                                               name="new_stock_price[]" 
                                                                               value="<?php echo $row['stock_price']; ?>"
                                                                               step="0.01" min="0" placeholder="Cost">
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <input type="number" class="form-control form-control-sm" 
                                                                               name="new_customer_price[]" 
                                                                               value="<?php echo $row['customer_price']; ?>"
                                                                               step="0.01" min="0" placeholder="Selling">
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <?php endif; ?>
                                                            <td class="text-center">
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <a href="product-details.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-info" title="View Details">
                                                                        <i class="mdi mdi-eye"></i>
                                                                    </a>
                                                                    <?php if ($user_role == 'lineman'): ?>
                                                                    <a href="quick-order.php?product_id=<?php echo $row['id']; ?>" class="btn btn-outline-success" title="Create Order">
                                                                        <i class="mdi mdi-cart-plus"></i>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                                    <button type="button" class="btn btn-outline-warning" 
                                                                            data-bs-toggle="modal" data-bs-target="#updateSinglePriceModal"
                                                                            data-product-id="<?php echo $row['id']; ?>"
                                                                            data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                                            data-stock-price="<?php echo $row['stock_price']; ?>"
                                                                            data-customer-price="<?php echo $row['customer_price']; ?>"
                                                                            title="Update Price">
                                                                        <i class="mdi mdi-currency-inr"></i>
                                                                    </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="<?php echo in_array($user_role, ['admin', 'super_admin']) ? '10' : '8'; ?>" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-currency-inr display-4"></i>
                                                                <h5 class="mt-2">No Products Found</h5>
                                                                <p>No products match your search criteria</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                    <div class="mt-3">
                                        <button type="submit" name="bulk_update_prices" class="btn btn-primary">
                                            <i class="mdi mdi-content-save me-1"></i> Save All Changes
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetPriceChanges()">
                                            <i class="mdi mdi-refresh me-1"></i> Reset Changes
                                        </button>
                                    </div>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <!-- Pagination -->
                                    <div class="row mt-3">
                                        <div class="col-sm-12 col-md-5">
                                            <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                                Showing <?php echo $total_products; ?> products
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Bulk Update Modal -->
    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkUpdateForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkUpdateModalLabel">Bulk Price Update</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Products to Update</label>
                            <select class="form-select" id="bulk_category" onchange="filterBulkProducts()">
                                <option value="0">All Categories</option>
                                <?php 
                                mysqli_data_seek($categories_result, 0);
                                while ($cat = mysqli_fetch_assoc($categories_result)): 
                                ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Products</label>
                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($products_data as $row): ?>
                                <div class="form-check bulk-product" data-category="<?php echo $row['category_id']; ?>">
                                    <input class="form-check-input" type="checkbox" 
                                           id="product_<?php echo $row['id']; ?>" 
                                           name="bulk_products[]" 
                                           value="<?php echo $row['id']; ?>">
                                    <label class="form-check-label" for="product_<?php echo $row['id']; ?>">
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                        (₹<?php echo number_format($row['customer_price'], 2); ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text">
                                <a href="javascript:void(0)" onclick="selectAllBulkProducts()">Select All</a> | 
                                <a href="javascript:void(0)" onclick="deselectAllBulkProducts()">Deselect All</a>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Update Type</label>
                            <select class="form-select" id="update_type" onchange="toggleUpdateFields()">
                                <option value="fixed">Fixed Amount</option>
                                <option value="percentage">Percentage</option>
                                <option value="custom">Custom Value</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="fixed_amount_fields">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Increase Cost Price By (₹)</label>
                                    <input type="number" class="form-control" id="cost_increase" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Increase Selling Price By (₹)</label>
                                    <input type="number" class="form-control" id="selling_increase" step="0.01" min="0" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="percentage_fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Cost Price Increase (%)</label>
                                    <input type="number" class="form-control" id="cost_percentage" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Selling Price Increase (%)</label>
                                    <input type="number" class="form-control" id="selling_percentage" step="0.01" min="0" value="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="custom_fields" style="display: none;">
                            <div class="alert alert-info">
                                <i class="mdi mdi-information-outline me-2"></i>
                                For custom updates, edit prices directly in the table.
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            This action will update prices for all selected products.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="applyBulkUpdate()">Apply Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Percentage Update Modal -->
    <div class="modal fade" id="percentageModal" tabindex="-1" aria-labelledby="percentageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="percentageForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="percentageModalLabel">Apply Percentage Increase</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="percentage" class="form-label">Percentage Increase (%) *</label>
                            <input type="number" class="form-control" id="percentage" name="percentage" 
                                   step="0.01" min="0.01" max="100" required placeholder="Enter percentage">
                        </div>
                        
                        <div class="mb-3">
                            <label for="apply_to" class="form-label">Apply To *</label>
                            <select class="form-select" id="apply_to" name="apply_to" required>
                                <option value="customer_price">Selling Price Only</option>
                                <option value="both">Both Cost and Selling Prices</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <div id="price_preview">
                                <!-- Price preview will be shown here -->
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            This will affect all active products in the system.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="apply_percentage" class="btn btn-primary">Apply Percentage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Single Price Modal -->
    <div class="modal fade" id="updateSinglePriceModal" tabindex="-1" aria-labelledby="updateSinglePriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="updateSinglePriceForm">
                    <input type="hidden" name="product_id" id="single_product_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateSinglePriceModalLabel">Update Product Price</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong id="single_product_name"></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="single_stock_price" class="form-label">Cost Price (₹) *</label>
                            <input type="number" class="form-control" id="single_stock_price" name="stock_price" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="single_customer_price" class="form-label">Selling Price (₹) *</label>
                            <input type="number" class="form-control" id="single_customer_price" name="customer_price" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between">
                                <span>Profit:</span>
                                <span id="single_profit_display"></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Profit Percentage:</span>
                                <span id="single_profit_percentage_display"></span>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning" id="single_price_warning" style="display: none;">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            <span>Selling price must be greater than cost price!</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_price" class="btn btn-primary">Update Price</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Print price list
        function printPriceList() {
            const printWindow = window.open('', '_blank');
            const now = new Date();
            const formattedDate = now.toLocaleDateString('en-IN', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true 
            });
            
            let tableContent = '';
            <?php foreach ($products_data as $row): ?>
            tableContent += `
                <tr>
                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                    <td><?php echo $row['product_code']; ?></td>
                    <td><?php echo $row['category_name']; ?></td>
                    <td class="text-right">₹<?php echo number_format($row['stock_price'], 2); ?></td>
                    <td class="text-right">₹<?php echo number_format($row['customer_price'], 2); ?></td>
                    <td class="text-right">₹<?php echo number_format($row['profit'], 2); ?></td>
                    <td class="text-center"><?php echo number_format($row['profit_percentage'], 1); ?>%</td>
                    <td class="text-center"><?php echo number_format($row['quantity']); ?></td>
                </tr>
            `;
            <?php endforeach; ?>
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Price List - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Price List</h1>
                    <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                    <p><strong>Date:</strong> ${formattedDate}</p>
                    <p><strong>Total Products:</strong> <?php echo $total_products; ?></p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th class="text-right">Cost Price</th>
                                <th class="text-right">Selling Price</th>
                                <th class="text-right">Profit</th>
                                <th class="text-center">Margin %</th>
                                <th class="text-center">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableContent}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right"><strong>Average:</strong></td>
                                <td class="text-right"><strong>₹<?php echo number_format($stats['avg_cost_price'] ?? 0, 2); ?></strong></td>
                                <td class="text-right"><strong>₹<?php echo number_format($stats['avg_selling_price'] ?? 0, 2); ?></strong></td>
                                <td class="text-right"></td>
                                <td class="text-center"><strong><?php echo number_format($stats['avg_profit_percentage'] ?? 0, 1); ?>%</strong></td>
                                <td class="text-center"></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: ${formattedDate}</p>
                        <p>Total Stock Value: <strong>₹<?php echo number_format($stats['total_stock_value'] ?? 0, 2); ?></strong></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        
        // Export price list
        function exportPriceList() {
            const search = '<?php echo $search; ?>';
            const categoryId = '<?php echo $category_id; ?>';
            const brandId = '<?php echo $brand_id; ?>';
            const sortBy = '<?php echo $sort_by; ?>';
            const sortOrder = '<?php echo $sort_order; ?>';
            
            window.location.href = `export-prices.php?search=${encodeURIComponent(search)}&category_id=${categoryId}&brand_id=${brandId}&sort_by=${sortBy}&sort_order=${sortOrder}`;
        }
        
        <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
        // Reset price changes
        function resetPriceChanges() {
            if (confirm('Are you sure you want to reset all price changes?')) {
                const form = document.getElementById('priceListForm');
                const inputs = form.querySelectorAll('input[type="number"]');
                inputs.forEach(input => {
                    const originalValue = input.getAttribute('data-original') || input.defaultValue;
                    input.value = originalValue;
                });
            }
        }
        
        // Store original values
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('priceListForm');
            if (form) {
                const inputs = form.querySelectorAll('input[type="number"]');
                inputs.forEach(input => {
                    input.setAttribute('data-original', input.value);
                });
            }
        });
        
        // Bulk update functions
        function toggleUpdateFields() {
            const updateType = document.getElementById('update_type').value;
            document.getElementById('fixed_amount_fields').style.display = updateType === 'fixed' ? 'block' : 'none';
            document.getElementById('percentage_fields').style.display = updateType === 'percentage' ? 'block' : 'none';
            document.getElementById('custom_fields').style.display = updateType === 'custom' ? 'block' : 'none';
        }
        
        function filterBulkProducts() {
            const categoryId = document.getElementById('bulk_category').value;
            const products = document.querySelectorAll('.bulk-product');
            
            products.forEach(product => {
                if (categoryId === '0' || product.getAttribute('data-category') === categoryId) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }
        
        function selectAllBulkProducts() {
            const checkboxes = document.querySelectorAll('.bulk-product input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('.bulk-product').style.display !== 'none') {
                    checkbox.checked = true;
                }
            });
        }
        
        function deselectAllBulkProducts() {
            const checkboxes = document.querySelectorAll('.bulk-product input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }
        
        function applyBulkUpdate() {
            const updateType = document.getElementById('update_type').value;
            const selectedProducts = [];
            const checkboxes = document.querySelectorAll('.bulk-product input[type="checkbox"]:checked');
            
            checkboxes.forEach(checkbox => {
                selectedProducts.push(checkbox.value);
            });
            
            if (selectedProducts.length === 0) {
                alert('Please select at least one product to update.');
                return;
            }
            
            if (updateType === 'fixed') {
                const costIncrease = parseFloat(document.getElementById('cost_increase').value) || 0;
                const sellingIncrease = parseFloat(document.getElementById('selling_increase').value) || 0;
                
                if (costIncrease === 0 && sellingIncrease === 0) {
                    alert('Please enter at least one amount to increase.');
                    return;
                }
                
                if (confirm(`Apply ₹${costIncrease} increase to cost price and ₹${sellingIncrease} increase to selling price for ${selectedProducts.length} products?`)) {
                    // Update the table inputs
                    selectedProducts.forEach(productId => {
                        const row = document.querySelector(`input[value="${productId}"]`).closest('tr');
                        const costInput = row.querySelector('input[name="new_stock_price[]"]');
                        const sellingInput = row.querySelector('input[name="new_customer_price[]"]');
                        
                        if (costInput) {
                            const currentCost = parseFloat(costInput.value) || 0;
                            costInput.value = (currentCost + costIncrease).toFixed(2);
                        }
                        if (sellingInput) {
                            const currentSelling = parseFloat(sellingInput.value) || 0;
                            sellingInput.value = (currentSelling + sellingIncrease).toFixed(2);
                        }
                    });
                    
                    alert(`Updated ${selectedProducts.length} products successfully! Click "Save All Changes" to save.`);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkUpdateModal'));
                    modal.hide();
                }
            } else if (updateType === 'percentage') {
                const costPercentage = parseFloat(document.getElementById('cost_percentage').value) || 0;
                const sellingPercentage = parseFloat(document.getElementById('selling_percentage').value) || 0;
                
                if (costPercentage === 0 && sellingPercentage === 0) {
                    alert('Please enter at least one percentage to increase.');
                    return;
                }
                
                if (confirm(`Apply ${costPercentage}% increase to cost price and ${sellingPercentage}% increase to selling price for ${selectedProducts.length} products?`)) {
                    // Update the table inputs
                    selectedProducts.forEach(productId => {
                        const row = document.querySelector(`input[value="${productId}"]`).closest('tr');
                        const costInput = row.querySelector('input[name="new_stock_price[]"]');
                        const sellingInput = row.querySelector('input[name="new_customer_price[]"]');
                        
                        if (costInput && costPercentage > 0) {
                            const currentCost = parseFloat(costInput.value) || 0;
                            costInput.value = (currentCost + (currentCost * costPercentage / 100)).toFixed(2);
                        }
                        if (sellingInput && sellingPercentage > 0) {
                            const currentSelling = parseFloat(sellingInput.value) || 0;
                            sellingInput.value = (currentSelling + (currentSelling * sellingPercentage / 100)).toFixed(2);
                        }
                    });
                    
                    alert(`Updated ${selectedProducts.length} products successfully! Click "Save All Changes" to save.`);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkUpdateModal'));
                    modal.hide();
                }
            }
        }
        
        // Single price update modal
        const updateSinglePriceModal = document.getElementById('updateSinglePriceModal');
        if (updateSinglePriceModal) {
            updateSinglePriceModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('single_product_id').value = button.getAttribute('data-product-id');
                document.getElementById('single_product_name').textContent = button.getAttribute('data-product-name');
                document.getElementById('single_stock_price').value = button.getAttribute('data-stock-price');
                document.getElementById('single_customer_price').value = button.getAttribute('data-customer-price');
                
                calculateSingleProfit();
            });
        }
        
        function calculateSingleProfit() {
            const stockPrice = parseFloat(document.getElementById('single_stock_price').value) || 0;
            const customerPrice = parseFloat(document.getElementById('single_customer_price').value) || 0;
            const profit = customerPrice - stockPrice;
            const profitPercentage = stockPrice > 0 ? ((profit / stockPrice) * 100).toFixed(2) : 0;
            
            document.getElementById('single_profit_display').textContent = '₹' + profit.toFixed(2);
            document.getElementById('single_profit_percentage_display').textContent = profitPercentage + '%';
            
            const warningDiv = document.getElementById('single_price_warning');
            if (customerPrice <= stockPrice) {
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        }
        
        document.getElementById('single_stock_price').addEventListener('input', calculateSingleProfit);
        document.getElementById('single_customer_price').addEventListener('input', calculateSingleProfit);
        
        // Percentage update preview
        document.getElementById('percentage').addEventListener('input', function() {
            const percentage = parseFloat(this.value) || 0;
            const applyTo = document.getElementById('apply_to').value;
            const avgCost = <?php echo $stats['avg_cost_price'] ?? 0; ?>;
            const avgSelling = <?php echo $stats['avg_selling_price'] ?? 0; ?>;
            
            let preview = '';
            if (applyTo === 'customer_price') {
                const newSelling = avgSelling + (avgSelling * percentage / 100);
                const newProfit = newSelling - avgCost;
                const newMargin = avgCost > 0 ? (newProfit / avgCost * 100) : 0;
                
                preview = `
                    <strong>Average Example:</strong><br>
                    Current: ₹${avgSelling.toFixed(2)} → New: ₹${newSelling.toFixed(2)}<br>
                    New Profit: ₹${newProfit.toFixed(2)} (${newMargin.toFixed(1)}%)
                `;
            } else {
                const newCost = avgCost + (avgCost * percentage / 100);
                const newSelling = avgSelling + (avgSelling * percentage / 100);
                const newProfit = newSelling - newCost;
                const newMargin = newCost > 0 ? (newProfit / newCost * 100) : 0;
                
                preview = `
                    <strong>Average Example:</strong><br>
                    Cost: ₹${avgCost.toFixed(2)} → ₹${newCost.toFixed(2)}<br>
                    Selling: ₹${avgSelling.toFixed(2)} → ₹${newSelling.toFixed(2)}<br>
                    New Profit: ₹${newProfit.toFixed(2)} (${newMargin.toFixed(1)}%)
                `;
            }
            
            document.getElementById('price_preview').innerHTML = preview;
        });
        
        document.getElementById('apply_to').addEventListener('change', function() {
            document.getElementById('percentage').dispatchEvent(new Event('input'));
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            toggleUpdateFields();
            document.getElementById('percentage').dispatchEvent(new Event('input'));
        });
        <?php endif; ?>
        
        // Quick filters
        function filterByMargin(min, max) {
            window.location.href = `price-list.php?min_price=${min}&max_price=${max}&sort_by=profit_percentage&sort_order=desc`;
        }
        
        function filterLowStock() {
            window.location.href = 'price-list.php?sort_by=quantity&sort_order=asc';
        }
        
        function filterHighMargin() {
            window.location.href = 'price-list.php?sort_by=profit_percentage&sort_order=desc';
        }
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}