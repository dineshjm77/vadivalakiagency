<!doctype html>
<html lang="en">

<?php include('includes/head.php')?>

<body data-sidebar="dark">

<!-- Loader -->
<?php include('includes/pre-loader.php')?>

<!-- Begin page -->
<div id="layout-wrapper">

<?php include('includes/topbar.php')?>    

    <!-- ========== Left Sidebar Start ========== -->
    <div class="vertical-menu">

        <div data-simplebar class="h-100">

            <!--- Sidemenu -->
            <?php include('includes/sidebar.php')?>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- Left Sidebar End -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
           
            <div class="container-fluid">


                <!-- end page title -->

                <?php
                // Database connection
                include('config/config.php');
                
                // Handle form submissions
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    if (isset($_POST['action'])) {
                        $action = $_POST['action'];
                        
                        // Bulk update pricing
                        if ($action == 'bulk_update') {
                            if (isset($_POST['products']) && is_array($_POST['products'])) {
                                $updated_count = 0;
                                $error_count = 0;
                                $errors = [];
                                
                                foreach ($_POST['products'] as $product_id => $prices) {
                                    $product_id = mysqli_real_escape_string($conn, $product_id);
                                    $stock_price = mysqli_real_escape_string($conn, $prices['stock_price']);
                                    $customer_price = mysqli_real_escape_string($conn, $prices['customer_price']);
                                    
                                    // Validate prices
                                    if ($stock_price < 0 || $customer_price < 0) {
                                        $error_count++;
                                        $errors[] = "Product ID $product_id: Prices cannot be negative";
                                        continue;
                                    }
                                    
                                    if ($customer_price <= $stock_price) {
                                        $error_count++;
                                        $errors[] = "Product ID $product_id: Customer price must be greater than stock price";
                                        continue;
                                    }
                                    
                                    // Calculate profit
                                    $profit = $customer_price - $stock_price;
                                    $profit_percentage = $stock_price > 0 ? ($profit / $stock_price * 100) : 0;
                                    
                                    // Update product
                                    $update_sql = "UPDATE products SET 
                                        stock_price = '$stock_price',
                                        customer_price = '$customer_price',
                                        profit = '$profit',
                                        profit_percentage = '$profit_percentage',
                                        updated_at = NOW()
                                        WHERE id = '$product_id'";
                                    
                                    if (mysqli_query($conn, $update_sql)) {
                                        $updated_count++;
                                    } else {
                                        $error_count++;
                                        $errors[] = "Product ID $product_id: " . mysqli_error($conn);
                                    }
                                }
                                
                                if ($updated_count > 0) {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Successfully updated ' . $updated_count . ' product(s)';
                                    
                                    if ($error_count > 0) {
                                        echo ' (' . $error_count . ' errors)';
                                    }
                                    
                                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                    
                                    if (!empty($errors)) {
                                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                                <strong>Errors:</strong><br>';
                                        foreach ($errors as $error) {
                                            echo '<small>' . $error . '</small><br>';
                                        }
                                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>';
                                    }
                                } else if ($error_count > 0) {
                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-block-helper me-2"></i>
                                            Failed to update products. Please check the errors below.
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                }
                            }
                        }
                        
                        // Apply percentage increase
                        elseif ($action == 'percentage_increase') {
                            $percentage = mysqli_real_escape_string($conn, $_POST['percentage']);
                            $apply_to = mysqli_real_escape_string($conn, $_POST['apply_to']);
                            
                            if ($percentage > 0) {
                                $percentage = $percentage / 100; // Convert to decimal
                                
                                // Build WHERE clause
                                $where_clause = '';
                                if ($apply_to == 'low_profit') {
                                    $where_clause = "WHERE profit_percentage < 30";
                                } elseif ($apply_to == 'all_active') {
                                    $where_clause = "WHERE status = 'active'";
                                } elseif ($apply_to == 'specific_category') {
                                    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
                                    $where_clause = "WHERE category_id = '$category_id' AND status = 'active'";
                                } elseif ($apply_to == 'specific_brand') {
                                    $brand_id = mysqli_real_escape_string($conn, $_POST['brand_id']);
                                    $where_clause = "WHERE brand_id = '$brand_id' AND status = 'active'";
                                }
                                
                                // Update query
                                $update_sql = "UPDATE products SET 
                                    customer_price = customer_price * (1 + $percentage),
                                    profit = (customer_price * (1 + $percentage)) - stock_price,
                                    profit_percentage = ((customer_price * (1 + $percentage)) - stock_price) / stock_price * 100,
                                    updated_at = NOW()
                                    $where_clause";
                                
                                if (mysqli_query($conn, $update_sql)) {
                                    $affected_rows = mysqli_affected_rows($conn);
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Applied ' . ($percentage * 100) . '% increase to ' . $affected_rows . ' products!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                } else {
                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-block-helper me-2"></i>
                                            Error: ' . mysqli_error($conn) . '
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                }
                            }
                        }
                        
                        // Reset pricing
                        elseif ($action == 'reset_pricing') {
                            $target = mysqli_real_escape_string($conn, $_POST['reset_target']);
                            
                            // Build WHERE clause
                            $where_clause = '';
                            if ($target == 'all_active') {
                                $where_clause = "WHERE status = 'active'";
                            } elseif ($target == 'out_of_stock') {
                                $where_clause = "WHERE quantity = 0 AND status = 'active'";
                            } elseif ($target == 'low_stock') {
                                $where_clause = "WHERE quantity < 10 AND status = 'active'";
                            }
                            
                            // Reset to standard profit margin (30%)
                            $update_sql = "UPDATE products SET 
                                customer_price = stock_price * 1.3,
                                profit = stock_price * 0.3,
                                profit_percentage = 30,
                                updated_at = NOW()
                                $where_clause";
                            
                            if (mysqli_query($conn, $update_sql)) {
                                $affected_rows = mysqli_affected_rows($conn);
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-check-all me-2"></i>
                                        Reset pricing for ' . $affected_rows . ' products to 30% profit margin!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-block-helper me-2"></i>
                                        Error: ' . mysqli_error($conn) . '
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        }
                    }
                }
                
                // Get filter parameters
                $filter_category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
                $filter_brand = isset($_GET['brand']) ? mysqli_real_escape_string($conn, $_GET['brand']) : '';
                $filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'active';
                $filter_profit = isset($_GET['profit']) ? mysqli_real_escape_string($conn, $_GET['profit']) : '';
                
                // Build WHERE clause
                $where_conditions = [];
                
                if ($filter_status == 'active') {
                    $where_conditions[] = "p.status = 'active'";
                } elseif ($filter_status == 'inactive') {
                    $where_conditions[] = "p.status = 'inactive'";
                } elseif ($filter_status == 'out_of_stock') {
                    $where_conditions[] = "p.quantity = 0 AND p.status = 'active'";
                }
                
                if ($filter_category != '') {
                    $where_conditions[] = "p.category_id = '$filter_category'";
                }
                
                if ($filter_brand != '') {
                    $where_conditions[] = "p.brand_id = '$filter_brand'";
                }
                
                if ($filter_profit == 'high') {
                    $where_conditions[] = "p.profit_percentage > 50";
                } elseif ($filter_profit == 'medium') {
                    $where_conditions[] = "p.profit_percentage BETWEEN 20 AND 50";
                } elseif ($filter_profit == 'low') {
                    $where_conditions[] = "p.profit_percentage < 20";
                } elseif ($filter_profit == 'negative') {
                    $where_conditions[] = "p.profit_percentage < 0";
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                }
                
                // Get pricing statistics
                $stats_sql = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN profit_percentage > 50 THEN 1 ELSE 0 END) as high_profit,
                    SUM(CASE WHEN profit_percentage BETWEEN 20 AND 50 THEN 1 ELSE 0 END) as medium_profit,
                    SUM(CASE WHEN profit_percentage < 20 AND profit_percentage >= 0 THEN 1 ELSE 0 END) as low_profit,
                    SUM(CASE WHEN profit_percentage < 0 THEN 1 ELSE 0 END) as negative_profit,
                    AVG(profit_percentage) as avg_profit_percentage,
                    SUM(profit * quantity) as total_profit_value,
                    SUM(stock_price * quantity) as total_stock_value,
                    SUM(customer_price * quantity) as total_selling_value
                    FROM products p
                    $where_clause";
                
                $stats_result = mysqli_query($conn, $stats_sql);
                $stats = mysqli_fetch_assoc($stats_result);
                
                // Get categories for filter
                $categories_sql = "SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name";
                $categories_result = mysqli_query($conn, $categories_sql);
                
                // Get brands for filter
                $brands_sql = "SELECT id, brand_name FROM brands WHERE status = 'active' ORDER BY brand_name";
                $brands_result = mysqli_query($conn, $brands_sql);
                
                // Get products with pricing info
                $products_sql = "SELECT 
                    p.*,
                    c.category_name,
                    b.brand_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN brands b ON p.brand_id = b.id
                    $where_clause
                    ORDER BY p.profit_percentage DESC, p.product_name ASC";
                
                $products_result = mysqli_query($conn, $products_sql);
                ?>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-cash-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Avg. Profit %</p>
                                        <h4 class="mb-0 <?php echo $stats['avg_profit_percentage'] >= 30 ? 'text-success' : ($stats['avg_profit_percentage'] >= 20 ? 'text-warning' : 'text-danger'); ?>">
                                            <?php echo number_format($stats['avg_profit_percentage'] ?? 0, 1); ?>%
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle text-success rounded-2 fs-2">
                                            <i class="mdi mdi-chart-line"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Profit Value</p>
                                        <h4 class="mb-0 text-success">₹<?php echo number_format($stats['total_profit_value'] ?? 0, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2">
                                            <i class="mdi mdi-percent"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">High Profit Items</p>
                                        <h4 class="mb-0 text-warning"><?php echo number_format($stats['high_profit'] ?? 0); ?></h4>
                                        <small class="text-muted">>50% margin</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2">
                                            <i class="mdi mdi-alert-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Low/Negative</p>
                                        <h4 class="mb-0 text-danger"><?php echo number_format(($stats['low_profit'] ?? 0) + ($stats['negative_profit'] ?? 0)); ?></h4>
                                        <small class="text-muted"><20% or negative</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <!-- Pricing Tools -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-tools text-primary me-1"></i> Pricing Tools
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Bulk Update -->
                                    <div class="col-md-4">
                                        <div class="card border-primary h-100">
                                            <div class="card-header bg-primary-subtle">
                                                <h6 class="card-title mb-0 text-primary">
                                                    <i class="mdi mdi-update me-1"></i> Bulk Update
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted small">Update prices for multiple products at once</p>
                                                <button type="button" class="btn btn-primary w-100" onclick="enableBulkEdit()">
                                                    <i class="mdi mdi-pencil-outline me-1"></i> Enable Bulk Edit
                                                </button>
                                                <button type="button" class="btn btn-success w-100 mt-2" onclick="applyStandardMargin()">
                                                    <i class="mdi mdi-percent me-1"></i> Apply 30% Margin
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Percentage Increase -->
                                    <div class="col-md-4">
                                        <div class="card border-success h-100">
                                            <div class="card-header bg-success-subtle">
                                                <h6 class="card-title mb-0 text-success">
                                                    <i class="mdi mdi-arrow-up-bold me-1"></i> Percentage Increase
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="product-pricing.php" id="percentageForm">
                                                    <input type="hidden" name="action" value="percentage_increase">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Increase Percentage</label>
                                                        <div class="input-group">
                                                            <input type="number" class="form-control" name="percentage" 
                                                                   value="10" min="1" max="100" step="0.1">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Apply To</label>
                                                        <select class="form-select" name="apply_to" id="applyToSelect">
                                                            <option value="all_active">All Active Products</option>
                                                            <option value="low_profit">Products with <30% Profit</option>
                                                            <option value="specific_category">Specific Category</option>
                                                            <option value="specific_brand">Specific Brand</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div id="categoryBrandSelect" style="display: none;">
                                                        <div class="mb-3">
                                                            <label class="form-label" id="selectLabel">Select</label>
                                                            <select class="form-select" name="category_id" id="categorySelect">
                                                                <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                                                <option value="<?php echo $cat['id']; ?>">
                                                                    <?php echo $cat['category_name']; ?>
                                                                </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                            <select class="form-select" name="brand_id" id="brandSelect" style="display: none;">
                                                                <?php mysqli_data_seek($brands_result, 0); ?>
                                                                <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                                                <option value="<?php echo $brand['id']; ?>">
                                                                    <?php echo $brand['brand_name']; ?>
                                                                </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-success w-100">
                                                        <i class="mdi mdi-check-circle me-1"></i> Apply Increase
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Reset Pricing -->
                                    <div class="col-md-4">
                                        <div class="card border-warning h-100">
                                            <div class="card-header bg-warning-subtle">
                                                <h6 class="card-title mb-0 text-warning">
                                                    <i class="mdi mdi-refresh me-1"></i> Reset Pricing
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="product-pricing.php" id="resetForm">
                                                    <input type="hidden" name="action" value="reset_pricing">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Reset Target</label>
                                                        <select class="form-select" name="reset_target">
                                                            <option value="all_active">All Active Products</option>
                                                            <option value="out_of_stock">Out of Stock Items</option>
                                                            <option value="low_stock">Low Stock Items (<10 units)</option>
                                                        </select>
                                                        <small class="text-muted">Resets to 30% profit margin</small>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-warning w-100">
                                                        <i class="mdi mdi-restart me-1"></i> Reset Selected
                                                    </button>
                                                </form>
                                                
                                                <div class="mt-3">
                                                    <p class="text-muted small mb-2">
                                                        <i class="mdi mdi-information-outline me-1"></i>
                                                        Standard water business margins:
                                                    </p>
                                                    <ul class="list-unstyled mb-0 small">
                                                        <li class="mb-1">
                                                            <span class="badge bg-success">30-40%</span> Normal margin
                                                        </li>
                                                        <li class="mb-1">
                                                            <span class="badge bg-warning">20-30%</span> Competitive
                                                        </li>
                                                        <li>
                                                            <span class="badge bg-danger">Below 20%</span> Review needed
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Panel -->
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-filter me-1"></i> Filter Products
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="product-pricing.php" id="filterForm">
                                    <div class="row g-3">
                                        <div class="col-md-2">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="out_of_stock" <?php echo $filter_status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="category">
                                                <option value="">All Categories</option>
                                                <?php mysqli_data_seek($categories_result, 0); ?>
                                                <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $cat['category_name']; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Brand</label>
                                            <select class="form-select" name="brand">
                                                <option value="">All Brands</option>
                                                <?php mysqli_data_seek($brands_result, 0); ?>
                                                <?php while ($brand = mysqli_fetch_assoc($brands_result)): ?>
                                                <option value="<?php echo $brand['id']; ?>" <?php echo $filter_brand == $brand['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $brand['brand_name']; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Profit Level</label>
                                            <select class="form-select" name="profit">
                                                <option value="">All Levels</option>
                                                <option value="high" <?php echo $filter_profit == 'high' ? 'selected' : ''; ?>>High (>50%)</option>
                                                <option value="medium" <?php echo $filter_profit == 'medium' ? 'selected' : ''; ?>>Medium (20-50%)</option>
                                                <option value="low" <?php echo $filter_profit == 'low' ? 'selected' : ''; ?>>Low (<20%)</option>
                                                <option value="negative" <?php echo $filter_profit == 'negative' ? 'selected' : ''; ?>>Negative</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="mdi mdi-filter me-1"></i> Apply
                                                </button>
                                                <a href="product-pricing.php" class="btn btn-light">
                                                    <i class="mdi mdi-refresh"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-info w-100" onclick="exportPricing()">
                                                <i class="mdi mdi-file-export me-1"></i> Export
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php if ($filter_status != 'active' || $filter_category != '' || $filter_brand != '' || $filter_profit != ''): ?>
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="alert alert-info py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="mdi mdi-information-outline me-2"></i>
                                                        <small>
                                                            Active Filters: 
                                                            <?php if ($filter_status != 'active'): ?>
                                                            <span class="badge bg-primary ms-2">Status: <?php echo ucfirst(str_replace('_', ' ', $filter_status)); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($filter_category != ''): 
                                                                $category_name = '';
                                                                mysqli_data_seek($categories_result, 0);
                                                                while ($cat = mysqli_fetch_assoc($categories_result)) {
                                                                    if ($cat['id'] == $filter_category) {
                                                                        $category_name = $cat['category_name'];
                                                                        break;
                                                                    }
                                                                }
                                                            ?>
                                                            <span class="badge bg-info ms-2">Category: <?php echo $category_name; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($filter_brand != ''): 
                                                                $brand_name = '';
                                                                mysqli_data_seek($brands_result, 0);
                                                                while ($brand = mysqli_fetch_assoc($brands_result)) {
                                                                    if ($brand['id'] == $filter_brand) {
                                                                        $brand_name = $brand['brand_name'];
                                                                        break;
                                                                    }
                                                                }
                                                            ?>
                                                            <span class="badge bg-warning ms-2">Brand: <?php echo $brand_name; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($filter_profit != ''): 
                                                                $profit_text = '';
                                                                switch($filter_profit) {
                                                                    case 'high': $profit_text = 'High (>50%)'; break;
                                                                    case 'medium': $profit_text = 'Medium (20-50%)'; break;
                                                                    case 'low': $profit_text = 'Low (<20%)'; break;
                                                                    case 'negative': $profit_text = 'Negative'; break;
                                                                }
                                                            ?>
                                                            <span class="badge bg-danger ms-2">Profit: <?php echo $profit_text; ?></span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <small>
                                                            <?php echo number_format(mysqli_num_rows($products_result)); ?> products found
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Pricing Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="mdi mdi-currency-inr text-success me-1"></i> 
                                        Products Pricing
                                    </h5>
                                    <div class="d-flex gap-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="bulkEditToggle" onchange="toggleBulkEdit(this.checked)">
                                            <label class="form-check-label" for="bulkEditToggle">Bulk Edit Mode</label>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-success" onclick="saveBulkChanges()" id="saveBulkBtn" style="display: none;">
                                            <i class="mdi mdi-content-save me-1"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="cancelBulkEdit()" id="cancelBulkBtn" style="display: none;">
                                            <i class="mdi mdi-close me-1"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="product-pricing.php" id="bulkUpdateForm">
                                    <input type="hidden" name="action" value="bulk_update">
                                    
                                    <?php if (mysqli_num_rows($products_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="pricingTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 40px;">
                                                        <div class="form-check" id="selectAllCheckbox" style="display: none;">
                                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                                        </div>
                                                    </th>
                                                    <th>Product</th>
                                                    <th>Stock Price</th>
                                                    <th>Customer Price</th>
                                                    <th>Profit</th>
                                                    <th>Margin %</th>
                                                    <th>Stock</th>
                                                    <th>Profit Value</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                while ($product = mysqli_fetch_assoc($products_result)): 
                                                    $profit_class = $product['profit_percentage'] > 50 ? 'text-success' : 
                                                                   ($product['profit_percentage'] >= 20 ? 'text-warning' : 'text-danger');
                                                    $stock_class = $product['quantity'] == 0 ? 'text-danger' : 
                                                                  ($product['quantity'] < 10 ? 'text-warning' : 'text-success');
                                                    $profit_value = $product['profit'] * $product['quantity'];
                                                ?>
                                                <tr id="productRow<?php echo $product['id']; ?>" 
                                                    data-profit-percentage="<?php echo $product['profit_percentage']; ?>"
                                                    data-quantity="<?php echo $product['quantity']; ?>">
                                                    <td>
                                                        <div class="form-check product-checkbox" style="display: none;">
                                                            <input class="form-check-input" type="checkbox" name="products[<?php echo $product['id']; ?>][selected]" 
                                                                   value="1" onchange="updateSelectedCount()">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-3">
                                                                <div class="avatar-xs">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($product['product_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="font-size-14 mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                                                <p class="text-muted mb-0 small"><?php echo $product['product_code']; ?></p>
                                                                <?php if (!empty($product['category_name']) || !empty($product['brand_name'])): ?>
                                                                <small class="text-muted">
                                                                    <?php if (!empty($product['category_name'])): ?>
                                                                    <i class="mdi mdi-tag-outline"></i> <?php echo $product['category_name']; ?>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($product['brand_name'])): ?>
                                                                    <?php if (!empty($product['category_name'])): ?> | <?php endif; ?>
                                                                    <i class="mdi mdi-tag-text-outline"></i> <?php echo $product['brand_name']; ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm" style="width: 120px;">
                                                            <span class="input-group-text">₹</span>
                                                            <input type="number" class="form-control form-control-sm stock-price-input" 
                                                                   name="products[<?php echo $product['id']; ?>][stock_price]" 
                                                                   value="<?php echo number_format($product['stock_price'], 2); ?>" 
                                                                   step="0.01" min="0" readonly
                                                                   onchange="calculateProfit(<?php echo $product['id']; ?>)">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm" style="width: 120px;">
                                                            <span class="input-group-text">₹</span>
                                                            <input type="number" class="form-control form-control-sm customer-price-input" 
                                                                   name="products[<?php echo $product['id']; ?>][customer_price]" 
                                                                   value="<?php echo number_format($product['customer_price'], 2); ?>" 
                                                                   step="0.01" min="0" readonly
                                                                   onchange="calculateProfit(<?php echo $product['id']; ?>)">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="<?php echo $profit_class; ?>" id="profitDisplay<?php echo $product['id']; ?>">
                                                            ₹<?php echo number_format($product['profit'], 2); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1" style="height: 6px; width: 80px;">
                                                                <div class="progress-bar <?php echo $product['profit_percentage'] > 50 ? 'bg-success' : 
                                                                                         ($product['profit_percentage'] >= 20 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                     style="width: <?php echo min($product['profit_percentage'], 100); ?>%">
                                                                </div>
                                                            </div>
                                                            <span class="ms-2 <?php echo $profit_class; ?>" id="marginDisplay<?php echo $product['id']; ?>">
                                                                <?php echo number_format($product['profit_percentage'], 1); ?>%
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="<?php echo $stock_class; ?>">
                                                            <?php echo number_format($product['quantity']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="text-success">
                                                            ₹<?php echo number_format($profit_value, 2); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $product['status'] == 'active' ? 'badge-soft-success' : 
                                                                           ($product['status'] == 'inactive' ? 'badge-soft-danger' : 'badge-soft-warning'); ?>">
                                                            <?php echo ucfirst($product['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <a href="product-view.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Product">
                                                                <i class="mdi mdi-eye-outline"></i>
                                                            </a>
                                                            <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit Product">
                                                                <i class="mdi mdi-pencil-outline"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Bulk Edit Actions Bar -->
                                    <div class="row mt-3" id="bulkActionsBar" style="display: none;">
                                        <div class="col-md-12">
                                            <div class="card border-primary">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span id="selectedCount">0</span> products selected
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <button type="button" class="btn btn-sm btn-primary" onclick="applyMarginToSelected(30)">
                                                                <i class="mdi mdi-percent me-1"></i> Set 30% Margin
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-warning" onclick="increaseSelectedPrices(10)">
                                                                <i class="mdi mdi-arrow-up-bold me-1"></i> +10% Price
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="resetSelectedSelection()">
                                                                <i class="mdi mdi-close me-1"></i> Clear Selection
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="mdi mdi-currency-usd-off display-4"></i>
                                            <h4 class="mt-3">No Products Found</h4>
                                            <p class="mb-0">No products match your filter criteria.</p>
                                            <div class="mt-3">
                                                <a href="product-pricing.php" class="btn btn-primary me-2">
                                                    <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                                </a>
                                                <a href="add-product.php" class="btn btn-success">
                                                    <i class="mdi mdi-plus-circle me-1"></i> Add Product
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

               

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php 
        mysqli_close($conn);
        include('includes/footer.php') 
        ?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Pricing Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Export Format</label>
                    <select class="form-select" id="exportFormat">
                        <option value="csv">CSV (Excel)</option>
                        <option value="pdf">PDF Report</option>
                        <option value="print">Print Preview</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Include</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeAll" checked>
                        <label class="form-check-label" for="includeAll">All filtered products</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeCalculations" checked>
                        <label class="form-check-label" for="includeCalculations">Profit calculations</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeGuidelines" checked>
                        <label class="form-check-label" for="includeGuidelines">Pricing guidelines</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="performExport()">
                    <i class="mdi mdi-download me-1"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Margin Calculator Modal -->
<div class="modal fade" id="marginCalculatorModal" tabindex="-1" aria-labelledby="marginCalculatorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="marginCalculatorModalLabel">
                    <i class="mdi mdi-calculator me-1"></i> Margin Calculator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" class="form-control" id="costPrice" placeholder="0.00" step="0.01" oninput="calculateMargin()">
                </div>
                <div class="mb-3">
                    <label class="form-label">Selling Price (₹)</label>
                    <input type="number" class="form-control" id="sellingPrice" placeholder="0.00" step="0.01" oninput="calculateMargin()">
                </div>
                <div class="mb-3">
                    <label class="form-label">Target Margin %</label>
                    <input type="number" class="form-control" id="targetMargin" placeholder="30" step="0.1" oninput="calculateFromMargin()">
                </div>
                
                <div class="alert alert-info">
                    <h6 class="mb-2">Results:</h6>
                    <div class="row">
                        <div class="col-6">
                            <small>Profit Amount:</small><br>
                            <strong id="profitAmount">₹0.00</strong>
                        </div>
                        <div class="col-6">
                            <small>Margin Percentage:</small><br>
                            <strong id="marginPercentage">0%</strong>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <small>Recommended Selling Price:</small><br>
                            <strong id="recommendedPrice">₹0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="applyCalculatorResult()">
                    Apply to Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Bulk edit state
let bulkEditMode = false;
let selectedProducts = new Set();

// Toggle bulk edit mode
function toggleBulkEdit(enabled) {
    bulkEditMode = enabled;
    
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const priceInputs = document.querySelectorAll('.stock-price-input, .customer-price-input');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const saveBtn = document.getElementById('saveBulkBtn');
    const cancelBtn = document.getElementById('cancelBulkBtn');
    
    // Show/hide checkboxes
    checkboxes.forEach(cb => {
        cb.style.display = enabled ? 'block' : 'none';
    });
    
    // Enable/disable price inputs
    priceInputs.forEach(input => {
        input.readOnly = !enabled;
    });
    
    // Show/hide bulk action elements
    selectAllCheckbox.style.display = enabled ? 'block' : 'none';
    bulkActionsBar.style.display = enabled ? 'block' : 'none';
    saveBtn.style.display = enabled ? 'inline-block' : 'none';
    cancelBtn.style.display = enabled ? 'inline-block' : 'none';
    
    if (!enabled) {
        selectedProducts.clear();
        updateSelectedCount();
        resetSelectAll();
    }
}

// Enable bulk edit
function enableBulkEdit() {
    document.getElementById('bulkEditToggle').checked = true;
    toggleBulkEdit(true);
}

// Cancel bulk edit
function cancelBulkEdit() {
    if (confirm('Cancel bulk edit? All unsaved changes will be lost.')) {
        document.getElementById('bulkEditToggle').checked = false;
        toggleBulkEdit(false);
        // Reload form to reset changes
        document.getElementById('bulkUpdateForm').reset();
    }
}

// Update selected count
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.product-checkbox input:checked');
    selectedProducts.clear();
    
    checkboxes.forEach(cb => {
        const productId = cb.name.match(/\[(\d+)\]/)[1];
        selectedProducts.add(productId);
    });
    
    document.getElementById('selectedCount').textContent = selectedProducts.size;
    
    // Update select all checkbox
    const totalCheckboxes = document.querySelectorAll('.product-checkbox input').length;
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = selectedProducts.size === totalCheckboxes;
    }
}

// Select/deselect all
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.product-checkbox input');
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
        cb.dispatchEvent(new Event('change'));
    });
});

function resetSelectAll() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = false;
    }
}

// Reset selection
function resetSelectedSelection() {
    const checkboxes = document.querySelectorAll('.product-checkbox input:checked');
    checkboxes.forEach(cb => {
        cb.checked = false;
        cb.dispatchEvent(new Event('change'));
    });
}

// Calculate profit for a product
function calculateProfit(productId) {
    const stockPrice = parseFloat(document.querySelector(`input[name="products[${productId}][stock_price]"]`).value) || 0;
    const customerPrice = parseFloat(document.querySelector(`input[name="products[${productId}][customer_price]"]`).value) || 0;
    
    const profit = customerPrice - stockPrice;
    const margin = stockPrice > 0 ? (profit / stock_price * 100) : 0;
    
    // Update displays
    document.getElementById(`profitDisplay${productId}`).textContent = '₹' + profit.toFixed(2);
    document.getElementById(`marginDisplay${productId}`).textContent = margin.toFixed(1) + '%';
    
    // Update progress bar
    const progressBar = document.querySelector(`#productRow${productId} .progress-bar`);
    if (progressBar) {
        progressBar.style.width = Math.min(margin, 100) + '%';
        progressBar.className = 'progress-bar ' + 
            (margin > 50 ? 'bg-success' : (margin >= 20 ? 'bg-warning' : 'bg-danger'));
    }
    
    // Update profit display color
    const profitDisplay = document.getElementById(`profitDisplay${productId}`);
    profitDisplay.className = margin > 50 ? 'text-success' : (margin >= 20 ? 'text-warning' : 'text-danger');
}

// Apply margin to selected products
function applyMarginToSelected(marginPercent) {
    if (selectedProducts.size === 0) {
        alert('Please select products first');
        return;
    }
    
    selectedProducts.forEach(productId => {
        const stockPriceInput = document.querySelector(`input[name="products[${productId}][stock_price]"]`);
        const customerPriceInput = document.querySelector(`input[name="products[${productId}][customer_price]"]`);
        
        const stockPrice = parseFloat(stockPriceInput.value) || 0;
        const newCustomerPrice = stockPrice * (1 + marginPercent / 100);
        
        customerPriceInput.value = newCustomerPrice.toFixed(2);
        calculateProfit(productId);
    });
    
    alert(`Applied ${marginPercent}% margin to ${selectedProducts.size} products`);
}

// Increase selected prices by percentage
function increaseSelectedPrices(percent) {
    if (selectedProducts.size === 0) {
        alert('Please select products first');
        return;
    }
    
    selectedProducts.forEach(productId => {
        const customerPriceInput = document.querySelector(`input[name="products[${productId}][customer_price]"]`);
        const currentPrice = parseFloat(customerPriceInput.value) || 0;
        const newPrice = currentPrice * (1 + percent / 100);
        
        customerPriceInput.value = newPrice.toFixed(2);
        calculateProfit(productId);
    });
    
    alert(`Increased prices by ${percent}% for ${selectedProducts.size} products`);
}

// Apply standard 30% margin to all
function applyStandardMargin() {
    if (confirm('Apply 30% profit margin to all active products?')) {
        // This would typically be an AJAX call
        // For now, we'll select all and apply margin
        const checkboxes = document.querySelectorAll('.product-checkbox input');
        checkboxes.forEach(cb => {
            cb.checked = true;
            cb.dispatchEvent(new Event('change'));
        });
        
        setTimeout(() => {
            applyMarginToSelected(30);
        }, 100);
    }
}

// Save bulk changes
function saveBulkChanges() {
    if (selectedProducts.size === 0) {
        alert('No changes made. Please edit prices or select products.');
        return;
    }
    
    // Validate all edited products
    let hasErrors = false;
    let errors = [];
    
    document.querySelectorAll('.customer-price-input').forEach(input => {
        const productId = input.name.match(/\[(\d+)\]/)[1];
        const stockPrice = parseFloat(document.querySelector(`input[name="products[${productId}][stock_price]"]`).value) || 0;
        const customerPrice = parseFloat(input.value) || 0;
        
        if (customerPrice <= stockPrice) {
            hasErrors = true;
            errors.push(`Product ID ${productId}: Customer price must be greater than stock price`);
        }
    });
    
    if (hasErrors) {
        alert('Please fix the following errors:\n\n' + errors.join('\n'));
        return;
    }
    
    if (confirm(`Save pricing changes for ${selectedProducts.size} products?`)) {
        document.getElementById('bulkUpdateForm').submit();
    }
}

// Percentage form handling
document.getElementById('applyToSelect').addEventListener('change', function() {
    const categoryBrandSelect = document.getElementById('categoryBrandSelect');
    const categorySelect = document.getElementById('categorySelect');
    const brandSelect = document.getElementById('brandSelect');
    const selectLabel = document.getElementById('selectLabel');
    
    if (this.value === 'specific_category') {
        categoryBrandSelect.style.display = 'block';
        categorySelect.style.display = 'block';
        brandSelect.style.display = 'none';
        selectLabel.textContent = 'Select Category';
    } else if (this.value === 'specific_brand') {
        categoryBrandSelect.style.display = 'block';
        categorySelect.style.display = 'none';
        brandSelect.style.display = 'block';
        selectLabel.textContent = 'Select Brand';
    } else {
        categoryBrandSelect.style.display = 'none';
    }
});

// Export pricing data
function exportPricing() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
}

function performExport() {
    const format = document.getElementById('exportFormat').value;
    
    if (format === 'csv') {
        exportToCSV();
    } else if (format === 'pdf') {
        exportToPDF();
    } else if (format === 'print') {
        printPricingReport();
    }
    
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
}

function exportToCSV() {
    const table = document.getElementById('pricingTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "Product,Product Code,Stock Price,Customer Price,Profit,Margin %,Stock Qty,Profit Value,Status\n";
    
    // Add rows (skip header and skip rows that are in bulk edit mode but not selected)
    rows.forEach((row, index) => {
        if (index > 0) { // Skip header row
            const cols = row.querySelectorAll('td');
            if (cols.length >= 9) {
                const productName = cols[1].querySelector('.font-size-14')?.textContent || '';
                const productCode = cols[1].querySelector('.text-muted')?.textContent || '';
                const stockPrice = cols[2].querySelector('input')?.value || '0';
                const customerPrice = cols[3].querySelector('input')?.value || '0';
                const profit = cols[4].textContent.replace('₹', '');
                const margin = cols[5].querySelector('span:last-child')?.textContent || '0%';
                const stockQty = cols[6].textContent;
                const profitValue = cols[7].textContent.replace('₹', '');
                const status = cols[8].querySelector('span')?.textContent || '';
                
                csvContent += `"${productName}","${productCode}",${stockPrice},${customerPrice},${profit},${margin},${stockQty},${profitValue},${status}\n`;
            }
        }
    });
    
    // Download CSV
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `pricing_report_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    alert('PDF export would be implemented here. This feature requires a PDF library.');
}

function printPricingReport() {
    const printContent = document.getElementById('pricingTable').outerHTML;
    const filterInfo = document.querySelector('.alert-info')?.innerHTML || '';
    const guidelines = document.querySelector('.card .card-body')?.innerHTML || '';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Product Pricing Report - APR Water Agencies</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { color: #666; margin-top: 10px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; text-align: left; }
                .table td { border: 1px solid #dee2e6; padding: 8px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                @media print {
                    .no-print { display: none; }
                    @page { size: landscape; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Product Pricing Report</div>
                <div class="subtitle">APR Water Agencies</div>
                <div class="subtitle">Generated on: ${new Date().toLocaleString()}</div>
            </div>
            
            ${filterInfo ? `<div class="info-box">${filterInfo}</div>` : ''}
            
            ${printContent}
            
            <div class="footer">
                Report generated from Product Pricing Management System
            </div>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Print Now
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
}

// Margin Calculator
function openMarginCalculator() {
    const modal = new bootstrap.Modal(document.getElementById('marginCalculatorModal'));
    modal.show();
}

function calculateMargin() {
    const costPrice = parseFloat(document.getElementById('costPrice').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('sellingPrice').value) || 0;
    
    if (costPrice > 0 && sellingPrice > 0) {
        const profit = sellingPrice - costPrice;
        const margin = (profit / costPrice) * 100;
        
        document.getElementById('profitAmount').textContent = '₹' + profit.toFixed(2);
        document.getElementById('marginPercentage').textContent = margin.toFixed(1) + '%';
    }
}

function calculateFromMargin() {
    const costPrice = parseFloat(document.getElementById('costPrice').value) || 0;
    const targetMargin = parseFloat(document.getElementById('targetMargin').value) || 0;
    
    if (costPrice > 0) {
        const sellingPrice = costPrice * (1 + targetMargin / 100);
        const profit = sellingPrice - costPrice;
        
        document.getElementById('sellingPrice').value = sellingPrice.toFixed(2);
        document.getElementById('profitAmount').textContent = '₹' + profit.toFixed(2);
        document.getElementById('marginPercentage').textContent = targetMargin + '%';
        document.getElementById('recommendedPrice').textContent = '₹' + sellingPrice.toFixed(2);
    }
}

function applyCalculatorResult() {
    const recommendedPrice = parseFloat(document.getElementById('recommendedPrice').textContent.replace('₹', '')) || 0;
    
    if (selectedProducts.size === 0) {
        alert('Please select products to apply this price');
        return;
    }
    
    if (confirm(`Apply recommended price ₹${recommendedPrice.toFixed(2)} to ${selectedProducts.size} selected products?`)) {
        selectedProducts.forEach(productId => {
            const customerPriceInput = document.querySelector(`input[name="products[${productId}][customer_price]"]`);
            customerPriceInput.value = recommendedPrice.toFixed(2);
            calculateProfit(productId);
        });
        
        bootstrap.Modal.getInstance(document.getElementById('marginCalculatorModal')).hide();
        alert(`Applied recommended price to ${selectedProducts.size} products`);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-submit filters on change
    const filterSelects = document.querySelectorAll('#filterForm select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+E to toggle bulk edit
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            const toggle = document.getElementById('bulkEditToggle');
            toggle.checked = !toggle.checked;
            toggle.dispatchEvent(new Event('change'));
        }
        // Ctrl+S to save bulk changes
        if (e.ctrlKey && e.key === 's' && bulkEditMode) {
            e.preventDefault();
            saveBulkChanges();
        }
        // Ctrl+F to focus on filter
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('#filterForm select[name="status"]').focus();
        }
        // Ctrl+P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printPricingReport();
        }
        // Ctrl+M for margin calculator
        if (e.ctrlKey && e.key === 'm') {
            e.preventDefault();
            openMarginCalculator();
        }
        // Escape to cancel bulk edit
        if (e.key === 'Escape' && bulkEditMode) {
            cancelBulkEdit();
        }
    });
    
    // Auto-calculate on page load for any pre-filled values
    document.querySelectorAll('.customer-price-input').forEach(input => {
        const productId = input.name.match(/\[(\d+)\]/)[1];
        if (productId) {
            calculateProfit(productId);
        }
    });
});

// Quick pricing templates
function applyPricingTemplate(template) {
    const templates = {
        'water_1l': { stock: 8, margin: 50 },
        'water_20l': { stock: 35, margin: 60 },
        'cooler': { stock: 4000, margin: 35 },
        'filter': { stock: 2500, margin: 40 }
    };
    
    if (selectedProducts.size === 0) {
        alert('Please select products first');
        return;
    }
    
    const selectedTemplate = templates[template];
    if (selectedTemplate) {
        selectedProducts.forEach(productId => {
            const stockPriceInput = document.querySelector(`input[name="products[${productId}][stock_price]"]`);
            const customerPriceInput = document.querySelector(`input[name="products[${productId}][customer_price]"]`);
            
            stockPriceInput.value = selectedTemplate.stock.toFixed(2);
            customerPriceInput.value = (selectedTemplate.stock * (1 + selectedTemplate.margin / 100)).toFixed(2);
            calculateProfit(productId);
        });
        
        alert(`Applied ${template.replace('_', ' ')} template to ${selectedProducts.size} products`);
    }
}
</script>

</body>

</html>