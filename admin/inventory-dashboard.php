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
                
                // Get inventory summary
                $summary_sql = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_products,
                    SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) as outofstock_products,
                    SUM(CASE WHEN quantity < 10 AND status = 'active' THEN 1 ELSE 0 END) as low_stock_products,
                    SUM(quantity) as total_quantity,
                    SUM(stock_price * quantity) as total_stock_value,
                    SUM(customer_price * quantity) as total_selling_value,
                    SUM((customer_price - stock_price) * quantity) as total_profit_potential
                    FROM products";
                
                $summary_result = mysqli_query($conn, $summary_sql);
                $summary = mysqli_fetch_assoc($summary_result);
                
                // Get recent stock additions
                $recent_stock_sql = "SELECT st.*, p.product_name, p.product_code 
                                   FROM stock_transactions st
                                   JOIN products p ON st.product_id = p.id
                                   WHERE st.transaction_type = 'purchase'
                                   ORDER BY st.created_at DESC 
                                   LIMIT 5";
                $recent_stock_result = mysqli_query($conn, $recent_stock_sql);
                
                // Get low stock products
                $low_stock_sql = "SELECT * FROM products 
                                WHERE quantity < 10 AND status = 'active'
                                ORDER BY quantity ASC 
                                LIMIT 10";
                $low_stock_result = mysqli_query($conn, $low_stock_sql);
                
                // Get top products by quantity
                $top_products_sql = "SELECT * FROM products 
                                   WHERE status = 'active' 
                                   ORDER BY quantity DESC 
                                   LIMIT 5";
                $top_products_result = mysqli_query($conn, $top_products_sql);
                
                // Get monthly stock additions (for chart)
                $monthly_sql = "SELECT 
                    DATE_FORMAT(created_at, '%b') as month,
                    SUM(quantity) as total_added
                    FROM stock_transactions 
                    WHERE transaction_type = 'purchase'
                    AND YEAR(created_at) = YEAR(CURDATE())
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY DATE_FORMAT(created_at, '%Y-%m')";
                $monthly_result = mysqli_query($conn, $monthly_sql);
                
                // Get category-wise stock
                $category_sql = "SELECT 
                    c.category_name,
                    COUNT(p.id) as product_count,
                    SUM(p.quantity) as total_quantity,
                    SUM(p.stock_price * p.quantity) as stock_value
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.status = 'active'
                    GROUP BY c.category_name
                    ORDER BY total_quantity DESC";
                $category_result = mysqli_query($conn, $category_sql);
                ?>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                                        <h4 class="mb-0"><?php echo number_format($summary['total_products']); ?></h4>
                                        <small class="text-muted">
                                            <span class="text-success"><?php echo number_format($summary['active_products']); ?> Active</span> | 
                                            <span class="text-danger"><?php echo number_format($summary['inactive_products']); ?> Inactive</span>
                                        </small>
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
                                            <i class="mdi mdi-alert-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Low Stock</p>
                                        <h4 class="mb-0"><?php echo number_format($summary['low_stock_products']); ?></h4>
                                        <small class="text-muted">Products below 10 units</small>
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
                                            <i class="mdi mdi-package-variant-closed"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p>
                                        <h4 class="mb-0"><?php echo number_format($summary['outofstock_products']); ?></h4>
                                        <small class="text-muted">Need restocking</small>
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
                                        <h4 class="mb-0">₹<?php echo number_format($summary['total_stock_value'], 2); ?></h4>
                                        <small class="text-muted">Total cost value</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <!-- Low Stock Products -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">
                                        <i class="mdi mdi-alert-circle-outline text-warning me-1"></i> Low Stock Alert
                                    </h4>
                                    <span class="badge bg-warning"><?php echo number_format($summary['low_stock_products']); ?> Products</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($low_stock_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Current Stock</th>
                                                <th>Stock Price</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($product = mysqli_fetch_assoc($low_stock_result)): 
                                                $stock_class = $product['quantity'] == 0 ? 'bg-danger' : 'bg-warning';
                                                $stock_status = $product['quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                                            ?>
                                            <tr>
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
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-medium <?php echo $product['quantity'] == 0 ? 'text-danger' : 'text-warning'; ?>">
                                                        <?php echo $product['quantity']; ?>
                                                    </span>
                                                </td>
                                                <td>₹<?php echo number_format($product['stock_price'], 2); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $stock_class; ?>">
                                                        <?php echo $stock_status; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="add-stock.php?product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-check-circle text-success display-4"></i>
                                        <h5 class="mt-2">All Products in Good Stock!</h5>
                                        <p>No low stock products at the moment</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="mt-3 text-center">
                                    <a href="low-stock-alerts.php" class="text-primary">
                                        <i class="mdi mdi-arrow-right me-1"></i> View All Low Stock Products
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Stock Additions -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">
                                        <i class="mdi mdi-history text-info me-1"></i> Recent Stock Additions
                                    </h4>
                                    <a href="inventory-history.php" class="btn btn-sm btn-light">View All</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($recent_stock_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($transaction = mysqli_fetch_assoc($recent_stock_result)): 
                                                $date = date('d M', strtotime($transaction['created_at']));
                                                $total_cost = $transaction['quantity'] * $transaction['stock_price'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <small class="text-muted"><?php echo $date; ?></small>
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="font-size-14 mb-1"><?php echo htmlspecialchars($transaction['product_name']); ?></h6>
                                                        <p class="text-muted mb-0 small"><?php echo $transaction['product_code']; ?></p>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success">
                                                        +<?php echo $transaction['quantity']; ?>
                                                    </span>
                                                </td>
                                                <td>₹<?php echo number_format($transaction['stock_price'], 2); ?></td>
                                                <td class="text-warning">₹<?php echo number_format($total_cost, 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-package-variant-closed display-4"></i>
                                        <h5 class="mt-2">No Stock Additions Yet</h5>
                                        <p>Start adding stock to see history here</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Inventory Quick Stats</h5>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <h6 class="text-muted">Total Quantity</h6>
                                            <h4 class="mb-0"><?php echo number_format($summary['total_quantity']); ?></h4>
                                            <small class="text-muted">Units in stock</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-3">
                                            <h6 class="text-muted">Selling Value</h6>
                                            <h4 class="mb-0 text-success">₹<?php echo number_format($summary['total_selling_value'], 2); ?></h4>
                                            <small class="text-muted">Potential revenue</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-0">
                                            <h6 class="text-muted">Profit Potential</h6>
                                            <h4 class="mb-0 text-primary">₹<?php echo number_format($summary['total_profit_potential'], 2); ?></h4>
                                            <small class="text-muted">If all sold</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="mb-0">
                                            <h6 class="text-muted">Avg. Stock/Product</h6>
                                            <h4 class="mb-0 text-info"><?php echo $summary['total_products'] > 0 ? number_format($summary['total_quantity'] / $summary['total_products'], 0) : 0; ?></h4>
                                            <small class="text-muted">Units per product</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Top Products by Quantity -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">
                                    <i class="mdi mdi-trophy text-success me-1"></i> Top Stocked Products
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($top_products_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Stock Value</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $counter = 1;
                                            while ($product = mysqli_fetch_assoc($top_products_result)): 
                                                $stock_value = $product['stock_price'] * $product['quantity'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="avatar-xs">
                                                        <span class="avatar-title <?php echo $counter <= 3 ? 'bg-success-subtle text-success' : 'bg-light text-secondary'; ?> rounded-circle">
                                                            <?php echo $counter; ?>
                                                        </span>
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
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="fw-medium text-success"><?php echo number_format($product['quantity']); ?></span>
                                                </td>
                                                <td class="text-warning">₹<?php echo number_format($stock_value, 2); ?></td>
                                                <td>
                                                    <a href="product-view.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-light">
                                                        <i class="mdi mdi-eye-outline"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php 
                                            $counter++;
                                            endwhile; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-package-variant display-4"></i>
                                        <h5 class="mt-2">No Active Products</h5>
                                        <p>Add products to see top stocked items</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Category-wise Stock -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">
                                    <i class="mdi mdi-chart-pie text-info me-1"></i> Stock by Category
                                </h4>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($category_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Category</th>
                                                <th>Products</th>
                                                <th>Quantity</th>
                                                <th>Value</th>
                                                <th>% of Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_category_qty = 0;
                                            mysqli_data_seek($category_result, 0);
                                            while ($category = mysqli_fetch_assoc($category_result)) {
                                                $total_category_qty += $category['total_quantity'];
                                            }
                                            
                                            mysqli_data_seek($category_result, 0);
                                            while ($category = mysqli_fetch_assoc($category_result)): 
                                                $percentage = $total_category_qty > 0 ? ($category['total_quantity'] / $total_category_qty * 100) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-info-subtle text-info">
                                                        <?php echo !empty($category['category_name']) ? $category['category_name'] : 'Uncategorized'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($category['product_count']); ?></td>
                                                <td>
                                                    <span class="fw-medium"><?php echo number_format($category['total_quantity']); ?></span>
                                                </td>
                                                <td class="text-warning">₹<?php echo number_format($category['stock_value'], 2); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-info" role="progressbar" 
                                                             style="width: <?php echo $percentage; ?>%" 
                                                             aria-valuenow="<?php echo $percentage; ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-tag-outline display-4"></i>
                                        <h5 class="mt-2">No Category Data</h5>
                                        <p>Add categories to products to see distribution</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-rocket-launch text-primary me-1"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="add-product.php" class="btn btn-outline-primary w-100">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Product
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="add-stock.php" class="btn btn-outline-success w-100">
                                            <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="products-list.php" class="btn btn-outline-info w-100">
                                            <i class="mdi mdi-view-list me-1"></i> View All Products
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="inventory-reports.php" class="btn btn-outline-warning w-100">
                                            <i class="mdi mdi-file-chart me-1"></i> Generate Reports
                                        </a>
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

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to refresh dashboard data
function refreshDashboard() {
    // Show loading indicator
    const refreshBtn = document.getElementById('refreshBtn');
    const originalHtml = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Reload page after 1 second
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Function to export inventory data
function exportInventory() {
    alert('Inventory data export feature would be implemented here.\nThis would generate an Excel/PDF report.');
}

// Function to show low stock report
function showLowStockReport() {
    window.location.href = 'low-stock-alerts.php';
}

// Function to show inventory history
function showInventoryHistory() {
    window.location.href = 'inventory-history.php';
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+R to refresh
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        refreshDashboard();
    }
    // Ctrl+E to export
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportInventory();
    }
    // Ctrl+L for low stock
    if (e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        showLowStockReport();
    }
});

// Auto-refresh every 5 minutes (optional)
setTimeout(() => {
    // Uncomment to enable auto-refresh
    // window.location.reload();
}, 300000); // 5 minutes = 300000 milliseconds

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips if needed
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Update time display
    updateTime();
    setInterval(updateTime, 60000); // Update every minute
});

// Update current time display
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const dateStr = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
    
    // Create or update time display
    let timeDisplay = document.getElementById('timeDisplay');
    if (!timeDisplay) {
        timeDisplay = document.createElement('div');
        timeDisplay.id = 'timeDisplay';
        timeDisplay.className = 'text-muted small text-end';
        document.querySelector('.page-title-right').appendChild(timeDisplay);
    }
    timeDisplay.innerHTML = `<i class="mdi mdi-clock-outline me-1"></i>${dateStr} ${timeStr}`;
}
</script>

</body>

</html>