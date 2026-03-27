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
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new product
    if (isset($_POST['add_product'])) {
        $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
        $product_code = mysqli_real_escape_string($conn, $_POST['product_code']);
        $category_id = intval($_POST['category_id']);
        $brand_id = intval($_POST['brand_id']);
        $stock_price = floatval($_POST['stock_price']);
        $customer_price = floatval($_POST['customer_price']);
        $quantity = intval($_POST['quantity']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Calculate profit
        $profit = $customer_price - $stock_price;
        $profit_percentage = $stock_price > 0 ? ($profit / $stock_price) * 100 : 0;
        
        $insert_sql = "INSERT INTO products (product_code, product_name, category_id, brand_id, 
                       stock_price, customer_price, quantity, profit, profit_percentage, 
                       description, status, created_at) 
                       VALUES ('$product_code', '$product_name', $category_id, $brand_id, 
                       $stock_price, $customer_price, $quantity, $profit, $profit_percentage, 
                       '$description', 'active', NOW())";
        
        if (mysqli_query($conn, $insert_sql)) {
            $product_id = mysqli_insert_id($conn);
            $success_message = "Product added successfully!";
            
            // Record stock transaction if quantity > 0
            if ($quantity > 0) {
                $transaction_sql = "INSERT INTO stock_transactions (product_id, transaction_type, quantity, 
                                    stock_price, previous_quantity, new_quantity, notes, created_by, created_at) 
                                    VALUES ($product_id, 'purchase', $quantity, $stock_price, 
                                    0, $quantity, 'Initial stock from product creation', $user_id, NOW())";
                mysqli_query($conn, $transaction_sql);
            }
            
            // Refresh to show new product
            header("Location: product-catalog.php?success=1");
            exit;
        } else {
            $error_message = "Failed to add product: " . mysqli_error($conn);
        }
    }
    
    // Update product
    if (isset($_POST['update_product'])) {
        $product_id = intval($_POST['product_id']);
        $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
        $category_id = intval($_POST['category_id']);
        $brand_id = intval($_POST['brand_id']);
        $stock_price = floatval($_POST['stock_price']);
        $customer_price = floatval($_POST['customer_price']);
        $quantity = intval($_POST['quantity']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        // Calculate profit
        $profit = $customer_price - $stock_price;
        $profit_percentage = $stock_price > 0 ? ($profit / $stock_price) * 100 : 0;
        
        // Get current quantity
        $current_sql = "SELECT quantity FROM products WHERE id = $product_id";
        $current_result = mysqli_query($conn, $current_sql);
        $current_data = mysqli_fetch_assoc($current_result);
        $current_qty = $current_data['quantity'] ?? 0;
        
        // Update product
        $update_sql = "UPDATE products SET 
                       product_name = '$product_name',
                       category_id = $category_id,
                       brand_id = $brand_id,
                       stock_price = $stock_price,
                       customer_price = $customer_price,
                       quantity = $quantity,
                       profit = $profit,
                       profit_percentage = $profit_percentage,
                       description = '$description',
                       status = '$status',
                       updated_at = NOW()
                       WHERE id = $product_id";
        
        if (mysqli_query($conn, $update_sql)) {
            // Record quantity adjustment if changed
            if ($quantity != $current_qty) {
                $adjustment_qty = abs($quantity - $current_qty);
                $adjustment_type = $quantity > $current_qty ? 'purchase' : 'adjustment';
                $notes = "Quantity updated from $current_qty to $quantity";
                
                $transaction_sql = "INSERT INTO stock_transactions (product_id, transaction_type, quantity, 
                                    stock_price, previous_quantity, new_quantity, notes, created_by, created_at) 
                                    VALUES ($product_id, '$adjustment_type', $adjustment_qty, $stock_price, 
                                    $current_qty, $quantity, '$notes', $user_id, NOW())";
                mysqli_query($conn, $transaction_sql);
            }
            
            $success_message = "Product updated successfully!";
            header("Location: product-catalog.php?success=1");
            exit;
        } else {
            $error_message = "Failed to update product: " . mysqli_error($conn);
        }
    }
    
    // Delete product
    if (isset($_POST['delete_product'])) {
        $product_id = intval($_POST['product_id']);
        
        // Check if product has sales history
        $check_sql = "SELECT COUNT(*) as count FROM order_items WHERE product_id = $product_id";
        $check_result = mysqli_query($conn, $check_sql);
        $check_data = mysqli_fetch_assoc($check_result);
        
        if ($check_data['count'] == 0) {
            // Safe to delete (no sales history)
            $delete_sql = "DELETE FROM products WHERE id = $product_id";
            if (mysqli_query($conn, $delete_sql)) {
                $success_message = "Product deleted successfully!";
                header("Location: product-catalog.php?success=1");
                exit;
            } else {
                $error_message = "Failed to delete product: " . mysqli_error($conn);
            }
        } else {
            // Mark as inactive instead of deleting
            $update_sql = "UPDATE products SET status = 'inactive', updated_at = NOW() WHERE id = $product_id";
            if (mysqli_query($conn, $update_sql)) {
                $success_message = "Product has sales history. Marked as inactive instead of deleting.";
                header("Location: product-catalog.php?success=1");
                exit;
            } else {
                $error_message = "Failed to update product status: " . mysqli_error($conn);
            }
        }
    }
    
    // Import products from CSV
    if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $file['tmp_name'];
            $csv_data = array_map('str_getcsv', file($file_tmp));
            
            // Skip header row
            array_shift($csv_data);
            
            $import_count = 0;
            $import_errors = [];
            
            mysqli_begin_transaction($conn);
            
            try {
                foreach ($csv_data as $row) {
                    if (count($row) >= 8) {
                        $product_name = mysqli_real_escape_string($conn, trim($row[0]));
                        $product_code = mysqli_real_escape_string($conn, trim($row[1]));
                        $category_name = mysqli_real_escape_string($conn, trim($row[2]));
                        $brand_name = mysqli_real_escape_string($conn, trim($row[3]));
                        $stock_price = floatval($row[4]);
                        $customer_price = floatval($row[5]);
                        $quantity = intval($row[6]);
                        $description = mysqli_real_escape_string($conn, trim($row[7]));
                        
                        // Get or create category
                        $category_sql = "SELECT id FROM categories WHERE category_name = '$category_name'";
                        $category_result = mysqli_query($conn, $category_sql);
                        if (mysqli_num_rows($category_result) > 0) {
                            $category_row = mysqli_fetch_assoc($category_result);
                            $category_id = $category_row['id'];
                        } else {
                            $category_sql = "INSERT INTO categories (category_name, status) VALUES ('$category_name', 'active')";
                            mysqli_query($conn, $category_sql);
                            $category_id = mysqli_insert_id($conn);
                        }
                        
                        // Get or create brand
                        $brand_sql = "SELECT id FROM brands WHERE brand_name = '$brand_name'";
                        $brand_result = mysqli_query($conn, $brand_sql);
                        if (mysqli_num_rows($brand_result) > 0) {
                            $brand_row = mysqli_fetch_assoc($brand_result);
                            $brand_id = $brand_row['id'];
                        } else {
                            $brand_sql = "INSERT INTO brands (brand_name, status) VALUES ('$brand_name', 'active')";
                            mysqli_query($conn, $brand_sql);
                            $brand_id = mysqli_insert_id($conn);
                        }
                        
                        // Calculate profit
                        $profit = $customer_price - $stock_price;
                        $profit_percentage = $stock_price > 0 ? ($profit / $stock_price) * 100 : 0;
                        
                        // Insert product
                        $insert_sql = "INSERT INTO products (product_code, product_name, category_id, brand_id, 
                                       stock_price, customer_price, quantity, profit, profit_percentage, 
                                       description, status) 
                                       VALUES ('$product_code', '$product_name', $category_id, $brand_id, 
                                       $stock_price, $customer_price, $quantity, $profit, $profit_percentage, 
                                       '$description', 'active')";
                        
                        if (mysqli_query($conn, $insert_sql)) {
                            $import_count++;
                        } else {
                            $import_errors[] = "Failed to import: $product_name - " . mysqli_error($conn);
                        }
                    }
                }
                
                mysqli_commit($conn);
                
                if ($import_count > 0) {
                    $success_message = "Successfully imported $import_count products!";
                }
                
                if (!empty($import_errors)) {
                    $error_message = implode('<br>', $import_errors);
                }
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = "Import failed: " . $e->getMessage();
            }
        } else {
            $error_message = "File upload error!";
        }
    }
}

// Build query for products
$sql = "SELECT p.*, 
               c.category_name,
               b.brand_name,
               COALESCE(SUM(oi.quantity), 0) as total_sold,
               COUNT(DISTINCT oi.order_id) as order_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE 1=1";

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

// Add status filter
if ($status != 'all') {
    $conditions[] = "p.status = '$status'";
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

// Order by
$sql .= " ORDER BY p.created_at DESC, p.product_name";

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

// Generate new product code
function generateProductCode() {
    return 'PROD' . date('Ym') . rand(100, 999);
}
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
                $current_page = 'product-catalog';
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
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        Operation completed successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
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

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Products">Total Products</h5>
                                            <h3 class="my-2 py-1"><?php echo $total_products; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-package-variant"></i>
                                                </span>
                                                <span>In catalog</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-package"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Active Products">Active Products</h5>
                                            <?php
                                            $active_sql = "SELECT COUNT(*) as count FROM products WHERE status = 'active'";
                                            $active_result = mysqli_query($conn, $active_sql);
                                            $active_data = mysqli_fetch_assoc($active_result);
                                            ?>
                                            <h3 class="my-2 py-1"><?php echo $active_data['count']; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-check-circle"></i>
                                                </span>
                                                <span>Available for sale</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                    <i class="mdi mdi-check"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Out of Stock">Out of Stock</h5>
                                            <?php
                                            $out_of_stock_sql = "SELECT COUNT(*) as count FROM products WHERE status = 'active' AND quantity = 0";
                                            $out_of_stock_result = mysqli_query($conn, $out_of_stock_sql);
                                            $out_of_stock_data = mysqli_fetch_assoc($out_of_stock_result);
                                            ?>
                                            <h3 class="my-2 py-1"><?php echo $out_of_stock_data['count']; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-danger me-2">
                                                    <i class="mdi mdi-alert"></i>
                                                </span>
                                                <span>Need restocking</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-danger bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-danger text-danger">
                                                    <i class="mdi mdi-cancel"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Categories">Categories</h5>
                                            <?php
                                            $categories_count_sql = "SELECT COUNT(*) as count FROM categories WHERE status = 'active'";
                                            $categories_count_result = mysqli_query($conn, $categories_count_sql);
                                            $categories_count_data = mysqli_fetch_assoc($categories_count_result);
                                            ?>
                                            <h3 class="my-2 py-1"><?php echo $categories_count_data['count']; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-info me-2">
                                                    <i class="mdi mdi-tag-multiple"></i>
                                                </span>
                                                <span>Product categories</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-tag"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add New Product
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                                            <i class="mdi mdi-import me-1"></i> Import CSV
                                        </button>
                                        <a href="export-products.php" class="btn btn-outline-success">
                                            <i class="mdi mdi-export me-1"></i> Export Products
                                        </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-info" onclick="printCatalog()">
                                            <i class="mdi mdi-printer me-1"></i> Print Catalog
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='available-stock.php'">
                                            <i class="mdi mdi-warehouse me-1"></i> View Stock
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Product Catalog -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">Product Catalog</h4>
                                            <p class="card-title-desc">Manage all your products and inventory</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-end">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="filterStatus('active')">
                                                        Active
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="filterStatus('inactive')">
                                                        Inactive
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterStatus('out_of_stock')">
                                                        Out of Stock
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Search & Filter Form -->
                                    <form method="GET" class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="search" 
                                                   value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search products...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="category_id">
                                                <option value="0">All Categories</option>
                                                <?php 
                                                mysqli_data_seek($categories_result, 0);
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
                                            <select class="form-select" name="brand_id">
                                                <option value="0">All Brands</option>
                                                <?php 
                                                mysqli_data_seek($brands_result, 0);
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
                                            <select class="form-select" name="status">
                                                <option value="all">All Status</option>
                                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="out_of_stock" <?php echo $status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" class="form-control" name="min_price" 
                                                       value="<?php echo $min_price; ?>" placeholder="Min Price" min="0" step="0.01">
                                                <span class="input-group-text">to</span>
                                                <input type="number" class="form-control" name="max_price" 
                                                       value="<?php echo $max_price; ?>" placeholder="Max Price" min="0" step="0.01">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="mdi mdi-filter"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <?php if (!empty($search) || $category_id > 0 || $brand_id > 0 || $status != 'all' || $min_price > 0 || $max_price > 0): ?>
                                    <div class="mb-3">
                                        <a href="product-catalog.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Product Info</th>
                                                    <th>Category & Brand</th>
                                                    <th class="text-center">Stock</th>
                                                    <th class="text-center">Sold</th>
                                                    <th class="text-center">Status</th>
                                                    <th class="text-end">Prices</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (!empty($products_data)) {
                                                    $counter = 1;
                                                    foreach ($products_data as $row) {
                                                        $stock_class = '';
                                                        $stock_status = '';
                                                        $status_class = '';
                                                        
                                                        // Determine stock status
                                                        if ($row['quantity'] <= 0) {
                                                            $stock_class = 'danger';
                                                            $stock_status = 'Out of Stock';
                                                        } elseif ($row['quantity'] <= 10) {
                                                            $stock_class = 'warning';
                                                            $stock_status = 'Low Stock';
                                                        } else {
                                                            $stock_class = 'success';
                                                            $stock_status = 'In Stock';
                                                        }
                                                        
                                                        // Determine product status
                                                        if ($row['status'] == 'active') {
                                                            $status_class = 'success';
                                                        } else {
                                                            $status_class = 'danger';
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="product-details.php?id=<?php echo $row['id']; ?>" class="text-dark">
                                                                            <?php echo htmlspecialchars($row['product_name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <p class="text-muted mb-0">
                                                                        Code: <?php echo $row['product_code']; ?>
                                                                        <?php if (!empty($row['description'])): ?>
                                                                        <br><small><?php echo htmlspecialchars(substr($row['description'], 0, 100)); ?><?php echo strlen($row['description']) > 100 ? '...' : ''; ?></small>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <span class="badge bg-primary-subtle text-primary mb-1">
                                                                        <?php echo !empty($row['category_name']) ? $row['category_name'] : 'Uncategorized'; ?>
                                                                    </span>
                                                                    <?php if (!empty($row['brand_name'])): ?>
                                                                    <br>
                                                                    <span class="badge bg-info-subtle text-info">
                                                                        <?php echo $row['brand_name']; ?>
                                                                    </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <h5 class="font-size-14 mb-1 <?php echo 'text-' . $stock_class; ?>">
                                                                    <?php echo number_format($row['quantity']); ?>
                                                                </h5>
                                                                <small class="text-muted"><?php echo $stock_status; ?></small>
                                                            </td>
                                                            <td class="text-center">
                                                                <h5 class="font-size-14 mb-1"><?php echo number_format($row['total_sold']); ?></h5>
                                                                <small class="text-muted">in <?php echo $row['order_count']; ?> orders</small>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-<?php echo $status_class; ?>-subtle text-<?php echo $status_class; ?>">
                                                                    <?php echo ucfirst($row['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-end">
                                                                <div>
                                                                    <h6 class="mb-1">₹<?php echo number_format($row['customer_price'], 2); ?></h6>
                                                                    <small class="text-muted">Cost: ₹<?php echo number_format($row['stock_price'], 2); ?></small>
                                                                    <br>
                                                                    <small class="text-success">
                                                                        Profit: ₹<?php echo number_format($row['profit'], 2); ?> (<?php echo number_format($row['profit_percentage'], 1); ?>%)
                                                                    </small>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-outline-info" 
                                                                            onclick="window.location.href='product-details.php?id=<?php echo $row['id']; ?>'"
                                                                            title="View Details">
                                                                        <i class="mdi mdi-eye"></i>
                                                                    </button>
                                                                    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                                    <button type="button" class="btn btn-outline-warning" 
                                                                            data-bs-toggle="modal" data-bs-target="#editProductModal"
                                                                            data-product-id="<?php echo $row['id']; ?>"
                                                                            data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                                            data-product-code="<?php echo $row['product_code']; ?>"
                                                                            data-category-id="<?php echo $row['category_id']; ?>"
                                                                            data-brand-id="<?php echo $row['brand_id']; ?>"
                                                                            data-stock-price="<?php echo $row['stock_price']; ?>"
                                                                            data-customer-price="<?php echo $row['customer_price']; ?>"
                                                                            data-quantity="<?php echo $row['quantity']; ?>"
                                                                            data-description="<?php echo htmlspecialchars($row['description']); ?>"
                                                                            data-status="<?php echo $row['status']; ?>"
                                                                            title="Edit Product">
                                                                        <i class="mdi mdi-pencil"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-danger" 
                                                                            data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                                                            data-product-id="<?php echo $row['id']; ?>"
                                                                            data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                                            title="Delete Product">
                                                                        <i class="mdi mdi-delete"></i>
                                                                    </button>
                                                                    <?php endif; ?>
                                                                    <?php if ($user_role == 'lineman'): ?>
                                                                    <button type="button" class="btn btn-outline-primary" 
                                                                            onclick="window.location.href='quick-order.php?product_id=<?php echo $row['id']; ?>'"
                                                                            title="Create Order">
                                                                        <i class="mdi mdi-cart-plus"></i>
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
                                                        <td colspan="8" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-package-variant display-4"></i>
                                                                <h5 class="mt-2">No Products Found</h5>
                                                                <p>No products match your search criteria</p>
                                                                <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                                                    <i class="mdi mdi-plus-circle-outline me-1"></i> Add Your First Product
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
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

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="addProductForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="product_name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="product_name" name="product_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="product_code" class="form-label">Product Code *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="product_code" name="product_code" 
                                           value="<?php echo generateProductCode(); ?>" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateNewCode()">
                                        <i class="mdi mdi-refresh"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    mysqli_data_seek($categories_result, 0);
                                    while ($cat = mysqli_fetch_assoc($categories_result)): 
                                    ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="brand_id" class="form-label">Brand</label>
                                <select class="form-select" id="brand_id" name="brand_id">
                                    <option value="">Select Brand</option>
                                    <?php 
                                    mysqli_data_seek($brands_result, 0);
                                    while ($brand = mysqli_fetch_assoc($brands_result)): 
                                    ?>
                                    <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['brand_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="stock_price" class="form-label">Cost Price (₹) *</label>
                                <input type="number" class="form-control" id="stock_price" name="stock_price" 
                                       step="0.01" min="0" required onchange="calculateProfit()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="customer_price" class="form-label">Selling Price (₹) *</label>
                                <input type="number" class="form-control" id="customer_price" name="customer_price" 
                                       step="0.01" min="0" required onchange="calculateProfit()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Profit</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="profit_amount" readonly>
                                    <span class="input-group-text" id="profit_percentage"></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Initial Stock Quantity *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="0" required>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editProductForm">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_product_name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="edit_product_name" name="product_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_product_code" class="form-label">Product Code *</label>
                                <input type="text" class="form-control" id="edit_product_code" name="product_code" required readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_category_id" class="form-label">Category *</label>
                                <select class="form-select" id="edit_category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    mysqli_data_seek($categories_result, 0);
                                    while ($cat = mysqli_fetch_assoc($categories_result)): 
                                    ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_brand_id" class="form-label">Brand</label>
                                <select class="form-select" id="edit_brand_id" name="brand_id">
                                    <option value="">Select Brand</option>
                                    <?php 
                                    mysqli_data_seek($brands_result, 0);
                                    while ($brand = mysqli_fetch_assoc($brands_result)): 
                                    ?>
                                    <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['brand_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_stock_price" class="form-label">Cost Price (₹) *</label>
                                <input type="number" class="form-control" id="edit_stock_price" name="stock_price" 
                                       step="0.01" min="0" required onchange="calculateEditProfit()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_customer_price" class="form-label">Selling Price (₹) *</label>
                                <input type="number" class="form-control" id="edit_customer_price" name="customer_price" 
                                       step="0.01" min="0" required onchange="calculateEditProfit()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Profit</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="edit_profit_amount" readonly>
                                    <span class="input-group-text" id="edit_profit_percentage"></span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_quantity" class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_status" class="form-label">Status *</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="out_of_stock">Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Product Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteProductForm">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteProductModalLabel">Delete Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="mdi mdi-alert-circle-outline text-danger display-4"></i>
                        </div>
                        <h5 class="text-center mb-3">Are you sure you want to delete this product?</h5>
                        <p class="text-center text-muted">
                            Product: <strong id="delete_product_name"></strong>
                            <br>
                            <span class="text-danger">This action cannot be undone!</span>
                        </p>
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            Note: Products with sales history will be marked as inactive instead of being deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_product" class="btn btn-danger">Delete Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Products Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Import Products from CSV</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Select CSV File *</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                CSV format should be: Product Name, Product Code, Category, Brand, Cost Price, Selling Price, Quantity, Description
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="mdi mdi-information-outline me-2"></i>Sample CSV Format:</h6>
                            <pre class="mb-0">
Minaral Water 1L,PROD001,Water,Bisleri,8.00,12.00,100,1 liter mineral water
Minaral Water 20L,PROD002,Water,Aquafina,25.00,40.00,50,20 liter mineral water
                            </pre>
                        </div>
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            Existing products with the same product code will be skipped.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_csv" class="btn btn-primary">Import Products</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Generate new product code
        function generateNewCode() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const random = Math.floor(Math.random() * 900) + 100;
            document.getElementById('product_code').value = `PROD${year}${month}${random}`;
        }
        
        // Calculate profit
        function calculateProfit() {
            const stockPrice = parseFloat(document.getElementById('stock_price').value) || 0;
            const customerPrice = parseFloat(document.getElementById('customer_price').value) || 0;
            const profit = customerPrice - stockPrice;
            const profitPercentage = stockPrice > 0 ? ((profit / stockPrice) * 100).toFixed(2) : 0;
            
            document.getElementById('profit_amount').value = '₹' + profit.toFixed(2);
            document.getElementById('profit_percentage').textContent = profitPercentage + '%';
        }
        
        function calculateEditProfit() {
            const stockPrice = parseFloat(document.getElementById('edit_stock_price').value) || 0;
            const customerPrice = parseFloat(document.getElementById('edit_customer_price').value) || 0;
            const profit = customerPrice - stockPrice;
            const profitPercentage = stockPrice > 0 ? ((profit / stockPrice) * 100).toFixed(2) : 0;
            
            document.getElementById('edit_profit_amount').value = '₹' + profit.toFixed(2);
            document.getElementById('edit_profit_percentage').textContent = profitPercentage + '%';
        }
        
        // Initialize profit calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateProfit();
        });
        
        // Edit Product Modal Handler
        const editProductModal = document.getElementById('editProductModal');
        if (editProductModal) {
            editProductModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('edit_product_id').value = button.getAttribute('data-product-id');
                document.getElementById('edit_product_name').value = button.getAttribute('data-product-name');
                document.getElementById('edit_product_code').value = button.getAttribute('data-product-code');
                document.getElementById('edit_category_id').value = button.getAttribute('data-category-id');
                document.getElementById('edit_brand_id').value = button.getAttribute('data-brand-id');
                document.getElementById('edit_stock_price').value = button.getAttribute('data-stock-price');
                document.getElementById('edit_customer_price').value = button.getAttribute('data-customer-price');
                document.getElementById('edit_quantity').value = button.getAttribute('data-quantity');
                document.getElementById('edit_description').value = button.getAttribute('data-description');
                document.getElementById('edit_status').value = button.getAttribute('data-status');
                
                calculateEditProfit();
            });
        }
        
        // Delete Product Modal Handler
        const deleteProductModal = document.getElementById('deleteProductModal');
        if (deleteProductModal) {
            deleteProductModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('delete_product_id').value = button.getAttribute('data-product-id');
                document.getElementById('delete_product_name').textContent = button.getAttribute('data-product-name');
            });
        }
        
        // Filter functions
        function filterStatus(status) {
            window.location.href = 'product-catalog.php?status=' + status;
        }
        
        // Print catalog
        function printCatalog() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Product Catalog - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
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
                    <h1>Product Catalog</h1>
                    <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <p><strong>Total Products:</strong> <?php echo $total_products; ?></p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th>Brand</th>
                                <th class="text-center">Stock</th>
                                <th class="text-right">Cost Price</th>
                                <th class="text-right">Selling Price</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $print_counter = 1;
                            if (!empty($products_data)) {
                                foreach ($products_data as $row) {
                                    echo '<tr>';
                                    echo '<td>' . $print_counter++ . '</td>';
                                    echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                                    echo '<td>' . $row['product_code'] . '</td>';
                                    echo '<td>' . (!empty($row['category_name']) ? $row['category_name'] : 'Uncategorized') . '</td>';
                                    echo '<td>' . ($row['brand_name'] ?? '-') . '</td>';
                                    echo '<td class="text-center">' . number_format($row['quantity']) . '</td>';
                                    echo '<td class="text-right">₹' . number_format($row['stock_price'], 2) . '</td>';
                                    echo '<td class="text-right">₹' . number_format($row['customer_price'], 2) . '</td>';
                                    echo '<td class="text-center">' . ucfirst($row['status']) . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        
        // Export catalog
        function exportCatalog() {
            const search = '<?php echo $search; ?>';
            const categoryId = '<?php echo $category_id; ?>';
            const brandId = '<?php echo $brand_id; ?>';
            const status = '<?php echo $status; ?>';
            const minPrice = '<?php echo $min_price; ?>';
            const maxPrice = '<?php echo $max_price; ?>';
            
            window.location.href = `export-products.php?search=${encodeURIComponent(search)}&category_id=${categoryId}&brand_id=${brandId}&status=${status}&min_price=${minPrice}&max_price=${maxPrice}`;
        }
        
        // Add export button to page
        document.addEventListener('DOMContentLoaded', function() {
            const quickActions = document.querySelector('.d-flex.flex-wrap.gap-2');
            if (quickActions) {
                const exportButton = document.createElement('button');
                exportButton.type = 'button';
                exportButton.className = 'btn btn-outline-success';
                exportButton.innerHTML = '<i class="mdi mdi-export me-1"></i> Export';
                exportButton.onclick = exportCatalog;
                quickActions.appendChild(exportButton);
            }
        });
        
        // Form validation
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const stockPrice = parseFloat(document.getElementById('stock_price').value);
            const customerPrice = parseFloat(document.getElementById('customer_price').value);
            
            if (stockPrice >= customerPrice) {
                e.preventDefault();
                alert('Selling price must be greater than cost price!');
                document.getElementById('customer_price').focus();
            }
        });
        
        document.getElementById('editProductForm').addEventListener('submit', function(e) {
            const stockPrice = parseFloat(document.getElementById('edit_stock_price').value);
            const customerPrice = parseFloat(document.getElementById('edit_customer_price').value);
            
            if (stockPrice >= customerPrice) {
                e.preventDefault();
                alert('Selling price must be greater than cost price!');
                document.getElementById('edit_customer_price').focus();
            }
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}
?>