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
                
                // Get threshold - with error handling
                $threshold = 10; // default value
                
                // Check if settings table exists without causing error
                $table_check = mysqli_query($conn, "SELECT 1 FROM information_schema.tables 
                                                    WHERE table_schema = DATABASE() 
                                                    AND table_name = 'settings' LIMIT 1");
                
                if ($table_check && mysqli_num_rows($table_check) > 0) {
                    // Table exists, try to get threshold
                    $threshold_sql = "SELECT setting_value FROM settings WHERE setting_key = 'low_stock_threshold'";
                    $threshold_result = mysqli_query($conn, $threshold_sql);
                    if ($threshold_result && mysqli_num_rows($threshold_result) > 0) {
                        $setting = mysqli_fetch_assoc($threshold_result);
                        $threshold = (int)$setting['setting_value'];
                    }
                }
                
                // Get filter parameters
                $filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'low_stock';
                $filter_category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
                $filter_brand = isset($_GET['brand']) ? mysqli_real_escape_string($conn, $_GET['brand']) : '';
                
                // Build WHERE clause
                $where_conditions = [];
                
                if ($filter_status == 'low_stock') {
                    $where_conditions[] = "p.quantity < $threshold AND p.quantity > 0 AND p.status = 'active'";
                } elseif ($filter_status == 'out_of_stock') {
                    $where_conditions[] = "p.quantity = 0 AND p.status = 'active'";
                } elseif ($filter_status == 'critical') {
                    $where_conditions[] = "p.quantity <= 3 AND p.quantity > 0 AND p.status = 'active'";
                } elseif ($filter_status == 'all') {
                    $where_conditions[] = "(p.quantity < $threshold OR p.quantity = 0) AND p.status = 'active'";
                }
                
                if ($filter_category != '') {
                    $where_conditions[] = "c.id = '$filter_category'";
                }
                
                if ($filter_brand != '') {
                    $where_conditions[] = "b.id = '$filter_brand'";
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                }
                
                // Get low stock summary
                $summary_sql = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                    SUM(CASE WHEN quantity > 0 AND quantity <= 3 THEN 1 ELSE 0 END) as critical_stock,
                    SUM(CASE WHEN quantity > 3 AND quantity < $threshold THEN 1 ELSE 0 END) as low_stock,
                    SUM(quantity) as total_quantity,
                    SUM(stock_price * quantity) as total_stock_value,
                    SUM(customer_price * quantity) as total_selling_value
                    FROM products p
                    $where_clause";
                
                $summary_result = mysqli_query($conn, $summary_sql);
                $summary = mysqli_fetch_assoc($summary_result) ?: [
                    'total_products' => 0,
                    'out_of_stock' => 0,
                    'critical_stock' => 0,
                    'low_stock' => 0,
                    'total_quantity' => 0,
                    'total_stock_value' => 0,
                    'total_selling_value' => 0
                ];
                
                // Get low stock products
                $products_sql = "SELECT 
                    p.*,
                    c.category_name,
                    b.brand_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN brands b ON p.brand_id = b.id
                    $where_clause
                    ORDER BY 
                    CASE 
                        WHEN p.quantity = 0 THEN 1
                        WHEN p.quantity <= 3 THEN 2
                        ELSE 3
                    END,
                    p.quantity ASC,
                    p.product_name ASC";
                
                $products_result = mysqli_query($conn, $products_sql);
                $total_products = $products_result ? mysqli_num_rows($products_result) : 0;
                
                // Get categories for filter
                $categories_sql = "SELECT DISTINCT c.id, c.category_name 
                                   FROM products p
                                   LEFT JOIN categories c ON p.category_id = c.id
                                   WHERE (p.quantity < $threshold OR p.quantity = 0) 
                                   AND p.status = 'active'
                                   AND c.category_name IS NOT NULL
                                   ORDER BY c.category_name";
                $categories_result = mysqli_query($conn, $categories_sql);
                
                // Get brands for filter
                $brands_sql = "SELECT DISTINCT b.id, b.brand_name 
                               FROM products p
                               LEFT JOIN brands b ON p.brand_id = b.id
                               WHERE (p.quantity < $threshold OR p.quantity = 0) 
                               AND p.status = 'active'
                               AND b.brand_name IS NOT NULL
                               ORDER BY b.brand_name";
                $brands_result = mysqli_query($conn, $brands_sql);
                ?>

                <!-- Summary Cards -->
                <div class="row">
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Critical (≤3)</p>
                                        <h4 class="mb-0 text-danger"><?php echo number_format($summary['critical_stock']); ?></h4>
                                        <small class="text-muted">Urgent restocking needed</small>
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
                                            <i class="mdi mdi-alert"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Low Stock</p>
                                        <h4 class="mb-0 text-warning"><?php echo number_format($summary['low_stock']); ?></h4>
                                        <small class="text-muted">Below <?php echo $threshold; ?> units</small>
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
                                        <span class="avatar-title bg-dark-subtle text-dark rounded-2 fs-2">
                                            <i class="mdi mdi-package-variant-closed"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p>
                                        <h4 class="mb-0"><?php echo number_format($summary['out_of_stock']); ?></h4>
                                        <small class="text-muted">Zero units available</small>
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
                                            <i class="mdi mdi-currency-inr"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Stock Value</p>
                                        <h4 class="mb-0 text-success">₹<?php echo number_format($summary['total_stock_value'], 2); ?></h4>
                                        <small class="text-muted">At risk value</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <!-- Filter Panel -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-filter me-1"></i> Filter Alerts
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="low-stock-alerts.php" id="filterForm">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Alert Type</label>
                                            <select class="form-select" name="status" id="statusFilter">
                                                <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Alerts</option>
                                                <option value="low_stock" <?php echo $filter_status == 'low_stock' ? 'selected' : ''; ?>>Low Stock Only</option>
                                                <option value="critical" <?php echo $filter_status == 'critical' ? 'selected' : ''; ?>>Critical (≤3 units)</option>
                                                <option value="out_of_stock" <?php echo $filter_status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock Only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="category" id="categoryFilter">
                                                <option value="">All Categories</option>
                                                <?php 
                                                if ($categories_result) {
                                                    while ($cat = mysqli_fetch_assoc($categories_result)): 
                                                ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $cat['category_name']; ?>
                                                </option>
                                                <?php 
                                                    endwhile; 
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Brand</label>
                                            <select class="form-select" name="brand" id="brandFilter">
                                                <option value="">All Brands</option>
                                                <?php 
                                                if ($brands_result) {
                                                    while ($brand = mysqli_fetch_assoc($brands_result)): 
                                                ?>
                                                <option value="<?php echo $brand['id']; ?>" <?php echo $filter_brand == $brand['id'] ? 'selected' : ''; ?>>
                                                    <?php echo $brand['brand_name']; ?>
                                                </option>
                                                <?php 
                                                    endwhile; 
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="mdi mdi-filter me-1"></i> Apply Filters
                                                </button>
                                                <a href="low-stock-alerts.php" class="btn btn-light">
                                                    <i class="mdi mdi-refresh"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($filter_status != 'low_stock' || $filter_category != '' || $filter_brand != ''): ?>
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="alert alert-info py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="mdi mdi-information-outline me-2"></i>
                                                        <small>
                                                            Active Filters: 
                                                            <?php if ($filter_status != 'low_stock'): 
                                                                $status_text = '';
                                                                switch($filter_status) {
                                                                    case 'all': $status_text = 'All Alerts'; break;
                                                                    case 'critical': $status_text = 'Critical Only'; break;
                                                                    case 'out_of_stock': $status_text = 'Out of Stock Only'; break;
                                                                }
                                                            ?>
                                                            <span class="badge bg-primary ms-2">Type: <?php echo $status_text; ?></span>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <small>
                                                            Showing <?php echo number_format($total_products); ?> products
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

                <div class="row">
                    <!-- Low Stock Products Table -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">
                                        <i class="mdi mdi-alert-circle-outline text-warning me-1"></i> 
                                        Low Stock Products
                                    </h4>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-success" onclick="bulkAddStock()">
                                            <i class="mdi mdi-plus-circle-multiple me-1"></i> Bulk Restock
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="printAlerts()">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($total_products > 0 && $products_result): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="alertsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40px;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                                    </div>
                                                </th>
                                                <th>Product</th>
                                                <th>Current Stock</th>
                                                <th>Threshold</th>
                                                <th>Status</th>
                                                <th>Stock Price</th>
                                                <th>Customer Price</th>
                                                <th>Last Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            while ($product = mysqli_fetch_assoc($products_result)): 
                                                // Determine alert level
                                                $alert_level = '';
                                                $alert_class = '';
                                                $alert_icon = '';
                                                $progress_color = '';
                                                
                                                if ($product['quantity'] == 0) {
                                                    $alert_level = 'Out of Stock';
                                                    $alert_class = 'bg-danger';
                                                    $alert_icon = 'mdi-close-circle';
                                                    $progress_color = 'bg-danger';
                                                    $progress_percent = 0;
                                                } elseif ($product['quantity'] <= 3) {
                                                    $alert_level = 'Critical';
                                                    $alert_class = 'bg-danger';
                                                    $alert_icon = 'mdi-alert-circle';
                                                    $progress_color = 'bg-danger';
                                                    $progress_percent = ($product['quantity'] / $threshold) * 100;
                                                } elseif ($product['quantity'] < $threshold) {
                                                    $alert_level = 'Low Stock';
                                                    $alert_class = 'bg-warning';
                                                    $alert_icon = 'mdi-alert';
                                                    $progress_color = 'bg-warning';
                                                    $progress_percent = ($product['quantity'] / $threshold) * 100;
                                                }
                                                
                                                // Calculate days since last update
                                                $last_updated = date('Y-m-d', strtotime($product['updated_at']));
                                                $today = date('Y-m-d');
                                                $days_diff = (strtotime($today) - strtotime($last_updated)) / (60 * 60 * 24);
                                                $days_text = $days_diff == 0 ? 'Today' : ($days_diff == 1 ? 'Yesterday' : $days_diff . ' days ago');
                                                
                                                // Calculate recommended restock quantity
                                                $recommended_qty = max(10, $threshold * 2);
                                                $needed_qty = $recommended_qty - $product['quantity'];
                                            ?>
                                            <tr data-product-id="<?php echo $product['id']; ?>" data-quantity="<?php echo $product['quantity']; ?>">
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input product-checkbox" type="checkbox" value="<?php echo $product['id']; ?>">
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
                                                    <div class="text-center">
                                                        <h5 class="mb-1 <?php echo $alert_level == 'Critical' || $alert_level == 'Out of Stock' ? 'text-danger' : 'text-warning'; ?>">
                                                            <?php echo $product['quantity']; ?>
                                                        </h5>
                                                        <small class="text-muted">units</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 6px; width: 80px;" data-bs-toggle="tooltip" title="<?php echo $product['quantity']; ?> of <?php echo $threshold; ?> units">
                                                        <div class="progress-bar <?php echo $progress_color; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo min($progress_percent, 100); ?>%" 
                                                             aria-valuenow="<?php echo $product['quantity']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="<?php echo $threshold; ?>">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted"><?php echo $threshold; ?> needed</small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $alert_class; ?>">
                                                        <i class="mdi <?php echo $alert_icon; ?> me-1"></i> <?php echo $alert_level; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-warning">₹<?php echo number_format($product['stock_price'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-success">₹<?php echo number_format($product['customer_price'], 2); ?></span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo $days_text; ?></small><br>
                                                    <small><?php echo date('d M', strtotime($product['updated_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="add-stock.php?product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Add Stock">
                                                            <i class="mdi mdi-plus-circle"></i>
                                                        </a>
                                                        <a href="product-view.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View Product">
                                                            <i class="mdi mdi-eye-outline"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="quickRestock(<?php echo $product['id']; ?>, <?php echo $needed_qty; ?>)" data-bs-toggle="tooltip" title="Quick Restock (<?php echo $needed_qty; ?> units)">
                                                            <i class="mdi mdi-rocket-launch-outline"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Selected Products Actions -->
                                <div class="row mt-3" id="bulkActions" style="display: none;">
                                    <div class="col-md-12">
                                        <div class="card border-primary">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span id="selectedCount">0</span> products selected
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-sm btn-success" onclick="addStockToSelected()">
                                                            <i class="mdi mdi-plus-circle me-1"></i> Add Stock to Selected
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-primary" onclick="generateOrderList()">
                                                            <i class="mdi mdi-clipboard-text-outline me-1"></i> Generate Order List
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" onclick="clearSelection()">
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
                                        <i class="mdi mdi-check-circle text-success display-4"></i>
                                        <h4 class="mt-3">No Low Stock Alerts!</h4>
                                        <p class="mb-0">All products are adequately stocked.</p>
                                        <div class="mt-3">
                                            <a href="products-list.php" class="btn btn-primary me-2">
                                                <i class="mdi mdi-view-list me-1"></i> View All Products
                                            </a>
                                            <a href="inventory-dashboard.php" class="btn btn-light">
                                                <i class="mdi mdi-arrow-left me-1"></i> Back to Dashboard
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Settings & Actions -->
                    <div class="col-lg-4">
                        <!-- Threshold Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-cog text-primary me-1"></i> Alert Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Low Stock Threshold</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="thresholdInput" value="<?php echo $threshold; ?>" min="1" max="100">
                                        <span class="input-group-text">units</span>
                                        <button class="btn btn-primary" type="button" onclick="updateThreshold()">
                                            <i class="mdi mdi-content-save"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Products below this quantity will trigger alerts</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="mdi mdi-information-outline me-2"></i>
                                    <small>Current threshold: <?php echo $threshold; ?> units<br>
                                    Products with ≤3 units are marked as "Critical"</small>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-rocket-launch text-info me-1"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-success" onclick="quickRestockAll()">
                                        <i class="mdi mdi-plus-circle-multiple me-1"></i> Quick Restock All
                                    </button>
                                    <a href="add-stock.php" class="btn btn-primary">
                                        <i class="mdi mdi-plus-circle me-1"></i> Add Stock Manually
                                    </a>
                                    <a href="inventory-dashboard.php" class="btn btn-warning">
                                        <i class="mdi mdi-chart-bar me-1"></i> Inventory Dashboard
                                    </a>
                                    <a href="products-list.php" class="btn btn-light">
                                        <i class="mdi mdi-view-list me-1"></i> View All Products
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Guidelines -->

                    </div>
                </div>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php 
        if (isset($conn)) {
            mysqli_close($conn);
        }
        include('includes/footer.php') 
        ?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Bulk Restock Modal -->
<div class="modal fade" id="bulkRestockModal" tabindex="-1" aria-labelledby="bulkRestockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkRestockModalLabel">Bulk Stock Addition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This feature requires additional setup. For now, please add stock to individual products.</p>
                <div class="alert alert-info">
                    <i class="mdi mdi-information-outline me-2"></i>
                    <small>To enable bulk actions, please contact your system administrator.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Global variables
let selectedProducts = [];
let currentThreshold = <?php echo $threshold; ?>;

// Function to check/uncheck all products
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelection();
});

// Function to update product selection
function updateSelection() {
    selectedProducts = [];
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    
    checkboxes.forEach(checkbox => {
        selectedProducts.push(checkbox.value);
    });
    
    const selectedCount = document.getElementById('selectedCount');
    const bulkActions = document.getElementById('bulkActions');
    
    if (selectedCount) {
        selectedCount.textContent = selectedProducts.length;
    }
    
    if (bulkActions) {
        if (selectedProducts.length > 0) {
            bulkActions.style.display = 'block';
        } else {
            bulkActions.style.display = 'none';
        }
    }
}

// Add event listeners to all checkboxes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });
});

// Function to clear selection
function clearSelection() {
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.checked = false;
    }
    updateSelection();
}

// Function for bulk add stock (simplified)
function bulkAddStock() {
    if (selectedProducts.length === 0) {
        alert('Please select at least one product');
        return;
    }
    
    // Show simple modal
    const modal = new bootstrap.Modal(document.getElementById('bulkRestockModal'));
    modal.show();
}

// Function to generate order list (simplified)
function generateOrderList() {
    if (selectedProducts.length === 0) {
        alert('Please select at least one product');
        return;
    }
    
    // Create a simple order list
    let orderList = "Purchase Order List:\n\n";
    
    selectedProducts.forEach(productId => {
        const row = document.querySelector(`tr[data-product-id="${productId}"]`);
        if (row) {
            const productName = row.cells[1].querySelector('.font-size-14').textContent;
            const currentQty = row.cells[2].querySelector('h5').textContent;
            const neededQty = Math.max(10, currentThreshold * 2 - parseInt(currentQty));
            
            orderList += `• ${productName}: ${neededQty} units (Current: ${currentQty})\n`;
        }
    });
    
    orderList += `\nTotal Products: ${selectedProducts.length}\n`;
    orderList += `Generated: ${new Date().toLocaleString()}`;
    
    alert(orderList);
}

// Function to update threshold
function updateThreshold() {
    const newThreshold = document.getElementById('thresholdInput').value;
    
    if (newThreshold < 1 || newThreshold > 100) {
        alert('Please enter a valid threshold between 1 and 100');
        return;
    }
    
    if (!confirm(`Change low stock threshold to ${newThreshold} units?`)) {
        return;
    }
    
    // Simple implementation - would normally use AJAX
    alert('Threshold update feature requires backend setup.\nFor now, using threshold in current session only.');
    currentThreshold = parseInt(newThreshold);
    window.location.reload();
}

// Function for quick restock of a single product
function quickRestock(productId, quantity) {
    if (!confirm(`Add ${quantity} units to this product?`)) {
        return;
    }
    
    window.location.href = `add-stock.php?product_id=${productId}&quick_qty=${quantity}`;
}

// Function for quick restock all
function quickRestockAll() {
    const lowStockProducts = document.querySelectorAll('tr[data-quantity]');
    const productIds = [];
    
    lowStockProducts.forEach(row => {
        const productId = row.getAttribute('data-product-id');
        const quantity = parseInt(row.getAttribute('data-quantity'));
        const neededQty = Math.max(10, currentThreshold * 2 - quantity);
        
        if (neededQty > 0) {
            productIds.push({ id: productId, qty: neededQty });
        }
    });
    
    if (productIds.length === 0) {
        alert('No products need restocking');
        return;
    }
    
    if (!confirm(`Quick restock ${productIds.length} products?\nYou'll be redirected to add stock to the first product.`)) {
        return;
    }
    
    // Redirect to first product
    window.location.href = `add-stock.php?product_id=${productIds[0].id}&quick_qty=${productIds[0].qty}`;
}

// Function to print alerts
function printAlerts() {
    const printContent = document.querySelector('#alertsTable')?.outerHTML;
    if (!printContent) {
        alert('No data to print');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Low Stock Alerts - APR Water Agencies</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { color: #666; margin-top: 10px; }
                .table { width: 100%; border-collapse: collapse; }
                .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; text-align: left; }
                .table td { border: 1px solid #dee2e6; padding: 8px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Low Stock Alerts Report</div>
                <div class="subtitle">APR Water Agencies</div>
                <div class="subtitle">Generated on: ${new Date().toLocaleString()}</div>
                <div class="subtitle">Threshold: ${currentThreshold} units</div>
            </div>
            
            ${printContent}
            
            <div class="footer">
                Total Products: ${<?php echo $total_products; ?>}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

// Function to add stock to selected products
function addStockToSelected() {
    if (selectedProducts.length === 0) {
        alert('Please select products first');
        return;
    }
    
    // Redirect to add-stock with first product
    window.location.href = `add-stock.php?product_id=${selectedProducts[0]}`;
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-submit filters
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+A to select all
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.click();
            }
        }
        // Ctrl+P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printAlerts();
        }
        // Escape to clear filters
        if (e.key === 'Escape') {
            window.location.href = 'low-stock-alerts.php';
        }
    });
});
</script>

</body>

</html>