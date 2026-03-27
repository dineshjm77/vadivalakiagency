<?php
// Check if session is already started

include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];

// Handle search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$min_qty = isset($_GET['min_qty']) ? intval($_GET['min_qty']) : 0;
$max_qty = isset($_GET['max_qty']) ? intval($_GET['max_qty']) : 0;
$availability = isset($_GET['availability']) ? mysqli_real_escape_string($conn, $_GET['availability']) : 'all';

// --- EXISTING: Stock request handling (unchanged) --- //
// Handle stock alert - need to create stock_requests table
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_stock'])) {
    $product_id = intval($_POST['product_id']);
    $request_qty = intval($_POST['request_qty']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Check if product exists
    $product_sql = "SELECT product_name, quantity FROM products WHERE id = $product_id";
    $product_result = mysqli_query($conn, $product_sql);
    $product_data = mysqli_fetch_assoc($product_result);
    
    if ($product_data && $request_qty > 0) {
        $request_id = 'REQ' . date('Ymd') . rand(100, 999);
        
        // Check if stock_requests table exists, if not create it
        $check_table_sql = "SHOW TABLES LIKE 'stock_requests'";
        $table_result = mysqli_query($conn, $check_table_sql);
        
        if (mysqli_num_rows($table_result) == 0) {
            // Create stock_requests table
            $create_table_sql = "CREATE TABLE IF NOT EXISTS stock_requests (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(50) NOT NULL,
                product_id INT(11) NOT NULL,
                requested_qty INT(11) NOT NULL,
                current_qty INT(11) NOT NULL,
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                notes TEXT,
                requested_by INT(11) DEFAULT NULL,
                status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
                approved_by INT(11) DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )";
            mysqli_query($conn, $create_table_sql);
        }
        
        $request_sql = "INSERT INTO stock_requests (request_id, product_id, requested_qty, 
                          current_qty, priority, notes, requested_by, status, created_at) 
                          VALUES ('$request_id', $product_id, $request_qty, 
                          '{$product_data['quantity']}', '$priority', '$notes', 
                          $lineman_id, 'pending', NOW())";
        
        if (mysqli_query($conn, $request_sql)) {
            $success_message = "Stock request submitted successfully!";
        } else {
            $error_message = "Failed to submit stock request: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Invalid product or quantity";
    }
}
// --- END existing block --- //


// ------------------- NEW: Quick Increase / Decrease Stock Handling ------------------- //
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_increase'])) {
    // Quick Increase
    $product_id = intval($_POST['product_id']);
    $add_qty = intval($_POST['increase_qty']);
    $purchase_date = isset($_POST['purchase_date']) ? mysqli_real_escape_string($conn, $_POST['purchase_date']) : '';
    $remark = isset($_POST['increase_remark']) ? mysqli_real_escape_string($conn, $_POST['increase_remark']) : '';
    
    if ($add_qty <= 0) {
        $error_message = "Increase quantity must be greater than zero.";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        try {
            $p_sql = "SELECT quantity, stock_price FROM products WHERE id = $product_id FOR UPDATE";
            $p_res = mysqli_query($conn, $p_sql);
            if ($p_res && mysqli_num_rows($p_res) > 0) {
                $p = mysqli_fetch_assoc($p_res);
                $prev_qty = intval($p['quantity']);
                $new_qty = $prev_qty + $add_qty;
                $stock_price = floatval($p['stock_price'] ?? 0);
                
                // Update product quantity
                $update_sql = "UPDATE products SET quantity = $new_qty, updated_at = NOW() WHERE id = $product_id";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Failed to update product quantity: " . mysqli_error($conn));
                }
                
                // Prepare notes including optional purchase_date and remark
                $note_parts = [];
                if (!empty($purchase_date)) {
                    // Basic validation for date format YYYY-MM-DD
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date)) {
                        $note_parts[] = "Purchase Date: $purchase_date";
                    } else {
                        // still accept but mark it
                        $note_parts[] = "Purchase Date (raw): $purchase_date";
                    }
                }
                if (!empty($remark)) {
                    $note_parts[] = "Remark: $remark";
                }
                $notes_combined = mysqli_real_escape_string($conn, implode(' | ', $note_parts));
                
                // Insert into stock_transactions
                $trans_sql = "INSERT INTO stock_transactions 
                    (product_id, transaction_type, quantity, stock_price, previous_quantity, new_quantity, notes, created_by, created_at)
                    VALUES ($product_id, 'purchase', $add_qty, $stock_price, $prev_qty, $new_qty, '$notes_combined', $lineman_id, NOW())";
                if (!mysqli_query($conn, $trans_sql)) {
                    throw new Exception("Failed to insert stock transaction: " . mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                $success_message = "Stock increased by $add_qty units. New quantity: $new_qty";
            } else {
                throw new Exception("Product not found.");
            }
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $error_message = $ex->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['quick_decrease'])) {
    // Quick Decrease
    $product_id = intval($_POST['product_id']);
    $dec_qty = intval($_POST['decrease_qty']);
    $reason = isset($_POST['decrease_remark']) ? mysqli_real_escape_string($conn, $_POST['decrease_remark']) : '';
    
    if ($dec_qty <= 0) {
        $error_message = "Decrease quantity must be greater than zero.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            $p_sql = "SELECT quantity, stock_price FROM products WHERE id = $product_id FOR UPDATE";
            $p_res = mysqli_query($conn, $p_sql);
            if ($p_res && mysqli_num_rows($p_res) > 0) {
                $p = mysqli_fetch_assoc($p_res);
                $prev_qty = intval($p['quantity']);
                
                if ($dec_qty > $prev_qty) {
                    throw new Exception("Insufficient stock. Current: $prev_qty, requested: $dec_qty.");
                }
                
                $new_qty = $prev_qty - $dec_qty;
                $stock_price = floatval($p['stock_price'] ?? 0);
                
                // Update product quantity
                $update_sql = "UPDATE products SET quantity = $new_qty, updated_at = NOW() WHERE id = $product_id";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Failed to update product quantity: " . mysqli_error($conn));
                }
                
                // Notes
                $note_parts = [];
                if (!empty($reason)) {
                    $note_parts[] = "Reason: $reason";
                }
                $notes_combined = mysqli_real_escape_string($conn, implode(' | ', $note_parts));
                
                // Insert into stock_transactions (adjustment)
                $trans_sql = "INSERT INTO stock_transactions 
                    (product_id, transaction_type, quantity, stock_price, previous_quantity, new_quantity, notes, created_by, created_at)
                    VALUES ($product_id, 'adjustment', $dec_qty, $stock_price, $prev_qty, $new_qty, '$notes_combined', $lineman_id, NOW())";
                if (!mysqli_query($conn, $trans_sql)) {
                    throw new Exception("Failed to insert stock transaction: " . mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                $success_message = "Stock decreased by $dec_qty units. New quantity: $new_qty";
            } else {
                throw new Exception("Product not found.");
            }
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $error_message = $ex->getMessage();
        }
    }
}
// ------------------- END NEW HANDLING ------------------- //


// Build query for available stock - UPDATED to use products.quantity instead of stock table
$sql = "SELECT p.*, 
               c.category_name,
               b.brand_name,
               COALESCE(SUM(oi.quantity), 0) as total_sold_last_30_days,
               p.quantity as current_quantity,
               (p.quantity - COALESCE(SUM(oi.quantity), 0)) as available_quantity
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN order_items oi ON p.id = oi.product_id 
            AND oi.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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

// Add quantity filters
if ($min_qty > 0) {
    $conditions[] = "p.quantity >= $min_qty";
}
if ($max_qty > 0) {
    $conditions[] = "p.quantity <= $max_qty";
}

// Add availability filter - using hardcoded thresholds since stock table doesn't exist
if ($availability != 'all') {
    if ($availability == 'low') {
        $conditions[] = "p.quantity <= 10"; // Default low stock threshold
    } elseif ($availability == 'medium') {
        $conditions[] = "p.quantity > 10 AND p.quantity <= 50";
    } elseif ($availability == 'good') {
        $conditions[] = "p.quantity > 50";
    } elseif ($availability == 'out') {
        $conditions[] = "p.quantity = 0";
    }
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Group by product
$sql .= " GROUP BY p.id";

// Order by quantity (lowest first)
$sql .= " ORDER BY 
            CASE 
                WHEN p.quantity <= 10 THEN 1
                WHEN p.quantity <= 50 THEN 2
                ELSE 3
            END,
            c.category_name,
            p.product_name";

// Execute query
$result = mysqli_query($conn, $sql);

// Calculate total statistics - UPDATED for products table
$stats_sql = "SELECT 
    COUNT(*) as total_products,
    SUM(p.quantity) as total_quantity,
    AVG(p.quantity) as avg_quantity,
    SUM(CASE WHEN p.quantity <= 10 THEN 1 ELSE 0 END) as low_stock_count,
    SUM(CASE WHEN p.quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
    SUM(CASE WHEN p.quantity > 50 THEN 1 ELSE 0 END) as good_stock_count,
    SUM(p.quantity * p.stock_price) as total_value_cost,
    SUM(p.quantity * p.customer_price) as total_value_selling
    FROM products p
    WHERE p.status = 'active'";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get categories for filter dropdown
$categories_sql = "SELECT c.*, 
                          COUNT(p.id) as product_count,
                          SUM(p.quantity) as total_qty
                   FROM categories c
                   LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                   GROUP BY c.id
                   ORDER BY c.category_name";
$categories_result = mysqli_query($conn, $categories_sql);

// Get recent sales data for popular products
$popular_sql = "SELECT p.id, p.product_name, p.product_code,
                       SUM(oi.quantity) as total_sold,
                       COUNT(DISTINCT oi.order_id) as order_count
                FROM products p
                LEFT JOIN order_items oi ON p.id = oi.product_id
                WHERE oi.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY p.id
                HAVING total_sold > 0
                ORDER BY total_sold DESC
                LIMIT 10";
$popular_result = mysqli_query($conn, $popular_sql);

// Get low stock products - UPDATED
$low_stock_sql = "SELECT p.*, c.category_name
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE p.status = 'active' 
                  AND p.quantity <= 10
                  ORDER BY p.quantity ASC
                  LIMIT 10";
$low_stock_result = mysqli_query($conn, $low_stock_sql);

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
                $current_page = 'available-stock';
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
                                            <h3 class="my-2 py-1"><?php echo $stats['total_products'] ?? 0; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-package-variant"></i>
                                                </span>
                                                <span>In stock</span>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Total Quantity">Total Quantity</h5>
                                            <h3 class="my-2 py-1"><?php echo number_format($stats['total_quantity'] ?? 0); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-info me-2">
                                                    <i class="mdi mdi-scale"></i>
                                                </span>
                                                <span>Units in stock</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-numeric"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Stock Value">Stock Value</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_value_selling'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                                <span>At selling price</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                    <i class="mdi mdi-cash"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Low Stock">Low Stock Items</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['low_stock_count'] ?? 0; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-warning me-2">
                                                    <i class="mdi mdi-alert"></i>
                                                </span>
                                                <span>Need reorder</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-warning bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-warning text-warning">
                                                    <i class="mdi mdi-alert-circle"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Stock Health Overview -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Stock Health Overview</h5>
                                    <div class="row">
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-danger text-danger font-size-18">
                                                            <i class="mdi mdi-alert-octagon"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Critical</h5>
                                                    <p class="text-muted mb-0">Below 10 units</p>
                                                    <h4 class="mt-2"><?php echo $stats['low_stock_count'] ?? 0; ?> items</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-warning text-warning font-size-18">
                                                            <i class="mdi mdi-alert"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Warning</h5>
                                                    <p class="text-muted mb-0">10-50 units</p>
                                                    <h4 class="mt-2">
                                                        <?php 
                                                        $warning_sql = "SELECT COUNT(*) as count FROM products 
                                                                       WHERE status = 'active' 
                                                                       AND quantity > 10 
                                                                       AND quantity <= 50";
                                                        $warning_result = mysqli_query($conn, $warning_sql);
                                                        $warning_row = mysqli_fetch_assoc($warning_result);
                                                        echo $warning_row['count'] ?? 0;
                                                        ?>
                                                    </h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-success text-success font-size-18">
                                                            <i class="mdi mdi-check-circle"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Good</h5>
                                                    <p class="text-muted mb-0">Above 50 units</p>
                                                    <h4 class="mt-2"><?php echo $stats['good_stock_count'] ?? 0; ?> items</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-secondary text-secondary font-size-18">
                                                            <i class="mdi mdi-cancel"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Out of Stock</h5>
                                                    <p class="text-muted mb-0">Zero quantity</p>
                                                    <h4 class="mt-2"><?php echo $stats['out_of_stock_count'] ?? 0; ?> items</h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Popular Products & Low Stock Side by Side -->
                    <div class="row mb-4">
                        <!-- Popular Products -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-fire me-2"></i> Popular Products (Last 30 Days)
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th class="text-end">Sold Qty</th>
                                                    <th class="text-end">Orders</th>
                                                    <th class="text-center">Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                if ($popular_result && mysqli_num_rows($popular_result) > 0) {
                                                    while ($popular = mysqli_fetch_assoc($popular_result)): 
                                                        // current stock may not be selected in popular query; fetch quick value
                                                        $current_stock_sql = "SELECT quantity FROM products WHERE id = " . intval($popular['id']);
                                                        $cs_res = mysqli_query($conn, $current_stock_sql);
                                                        $cs_row = mysqli_fetch_assoc($cs_res);
                                                        $current_stock = $cs_row['quantity'] ?? 0;
                                                        
                                                        // Stock status
                                                        $stock_class = $current_stock > 50 ? 'bg-success' : ($current_stock > 20 ? 'bg-warning' : 'bg-danger');
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($popular['product_name']); ?></h6>
                                                                <p class="text-muted mb-0"><?php echo $popular['product_code']; ?></p>
                                                            </div>
                                                        </td>
                                                        <td class="text-end fw-bold"><?php echo number_format($popular['total_sold']); ?></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-primary-subtle text-primary">
                                                                <?php echo $popular['order_count']; ?> orders
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar <?php echo $stock_class; ?>" 
                                                                     style="width: <?php echo min(100, ($current_stock / max(1, $popular['total_sold'] + 1)) * 100); ?>%"></div>
                                                            </div>
                                                            <small class="text-muted">Stock: <?php echo $current_stock; ?></small>
                                                        </td>
                                                    </tr>
                                                    <?php endwhile;
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-3">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-chart-line display-4"></i>
                                                                <p class="mt-2">No sales data available</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Alert -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-alert me-2"></i> Low Stock Alert
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th class="text-end">Current</th>
                                                    <th class="text-end">Min Level</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                if ($low_stock_result && mysqli_num_rows($low_stock_result) > 0) {
                                                    while ($low_stock = mysqli_fetch_assoc($low_stock_result)): 
                                                        $percentage = ($low_stock['quantity'] / 10) * 100; // 10 is the min level
                                                        $progress_class = $percentage < 30 ? 'bg-danger' : ($percentage < 60 ? 'bg-warning' : 'bg-info');
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($low_stock['product_name']); ?></h6>
                                                                <p class="text-muted mb-0"><?php echo $low_stock['product_code']; ?></p>
                                                            </div>
                                                        </td>
                                                        <td class="text-end fw-bold text-danger"><?php echo $low_stock['quantity']; ?></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-light text-dark">10</span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group" role="group">
                                                                <button class="btn btn-sm btn-outline-success" 
                                                                        data-bs-toggle="modal" data-bs-target="#quickIncreaseModal"
                                                                        data-product-id="<?php echo $low_stock['id']; ?>"
                                                                        data-product-name="<?php echo htmlspecialchars($low_stock['product_name']); ?>"
                                                                        data-current-qty="<?php echo $low_stock['quantity']; ?>">
                                                                    <i class="mdi mdi-plus me-1"></i> Add
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-warning" 
                                                                        data-bs-toggle="modal" data-bs-target="#requestStockModal"
                                                                        data-product-id="<?php echo $low_stock['id']; ?>"
                                                                        data-product-name="<?php echo htmlspecialchars($low_stock['product_name']); ?>"
                                                                        data-current-qty="<?php echo $low_stock['quantity']; ?>">
                                                                    <i class="mdi mdi-alarm-plus me-1"></i> Request
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endwhile;
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center py-3">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-check-circle display-4"></i>
                                                                <p class="mt-2">All stock levels are good!</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Available Stock Table -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">All Available Stock</h4>
                                            <p class="card-title-desc">View and manage product inventory</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                                <a href="stock-requests.php" class="btn btn-primary">
                                                    <i class="mdi mdi-alarm-check me-1"></i> Stock Requests
                                                </a>
                                                <button type="button" class="btn btn-success" onclick="printStock()">
                                                    <i class="mdi mdi-printer me-1"></i> Print Stock
                                                </button>
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
                                                <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                                <option value="<?php echo $cat['id']; ?>" 
                                                    <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                                    (<?php echo $cat['product_count']; ?>)
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="availability">
                                                <option value="all">All Status</option>
                                                <option value="low" <?php echo $availability == 'low' ? 'selected' : ''; ?>>Low Stock (≤10)</option>
                                                <option value="medium" <?php echo $availability == 'medium' ? 'selected' : ''; ?>>Medium Stock (10-50)</option>
                                                <option value="good" <?php echo $availability == 'good' ? 'selected' : ''; ?>>Good Stock (>50)</option>
                                                <option value="out" <?php echo $availability == 'out' ? 'selected' : ''; ?>>Out of Stock (0)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control" name="min_qty" 
                                                   value="<?php echo $min_qty; ?>"
                                                   placeholder="Min Qty" min="0">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control" name="max_qty" 
                                                   value="<?php echo $max_qty; ?>"
                                                   placeholder="Max Qty" min="0">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="mdi mdi-filter"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <?php if (!empty($search) || $category_id > 0 || $min_qty > 0 || $max_qty > 0 || $availability != 'all'): ?>
                                    <div class="mb-3">
                                        <a href="available-stock.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Quick Filters -->
                                    <div class="mb-3">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="filterLowStock()">
                                                <i class="mdi mdi-alert-octagon me-1"></i> Low Stock (≤10)
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterMediumStock()">
                                                <i class="mdi mdi-alert me-1"></i> Medium Stock (10-50)
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="filterGoodStock()">
                                                <i class="mdi mdi-check-circle me-1"></i> Good Stock (>50)
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm" onclick="filterZeroStock()">
                                                <i class="mdi mdi-cancel me-1"></i> Out of Stock (0)
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Current Stock</th>
                                                    <th class="text-center">Min Level</th>
                                                    <th class="text-center">Reorder Level</th>
                                                    <th class="text-center">Stock Status</th>
                                                    <th class="text-end">Selling Price</th>
                                                    <th class="text-end">Stock Value</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result && mysqli_num_rows($result) > 0) {
                                                    while ($row = mysqli_fetch_assoc($result)) {
                                                        // Set default levels since stock table doesn't exist
                                                        $min_level = 10; // Default min level
                                                        $reorder_level = 50; // Default reorder level
                                                        $current_qty = $row['quantity'];
                                                        $available_qty = $row['available_quantity'] ?? $current_qty;
                                                        
                                                        // Determine stock status
                                                        $stock_status = '';
                                                        $status_class = '';
                                                        $status_icon = '';
                                                        
                                                        if ($current_qty <= $min_level) {
                                                            $stock_status = 'Critical';
                                                            $status_class = 'danger';
                                                            $status_icon = 'mdi-alert-octagon';
                                                        } elseif ($current_qty <= $reorder_level) {
                                                            $stock_status = 'Warning';
                                                            $status_class = 'warning';
                                                            $status_icon = 'mdi-alert';
                                                        } else {
                                                            $stock_status = 'Good';
                                                            $status_class = 'success';
                                                            $status_icon = 'mdi-check-circle';
                                                        }
                                                        
                                                        // Stock percentage for progress bar
                                                        $max_level = max($reorder_level * 2, 100);
                                                        $stock_percentage = min(100, ($current_qty / $max_level) * 100);
                                                        
                                                        // Progress bar color
                                                        $progress_class = '';
                                                        if ($stock_percentage < 30) {
                                                            $progress_class = 'bg-danger';
                                                        } elseif ($stock_percentage < 60) {
                                                            $progress_class = 'bg-warning';
                                                        } else {
                                                            $progress_class = 'bg-success';
                                                        }
                                                        
                                                        // Calculate stock value
                                                        $stock_value = $current_qty * $row['customer_price'];
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($row['product_name']); ?></h5>
                                                                    <p class="text-muted mb-0">
                                                                        Code: <?php echo $row['product_code']; ?>
                                                                        <?php if ($row['brand_name']): ?>
                                                                        <br>Brand: <?php echo $row['brand_name']; ?>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary-subtle text-primary">
                                                                    <?php echo $row['category_name']; ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-center">
                                                                <h5 class="font-size-14 mb-1"><?php echo number_format($current_qty); ?></h5>
                                                                <small class="text-muted">Available: <?php echo number_format($available_qty); ?></small>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-light text-dark"><?php echo $min_level; ?></span>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-info-subtle text-info"><?php echo $reorder_level; ?></span>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="mb-1">
                                                                    <span class="badge bg-<?php echo $status_class; ?>-subtle text-<?php echo $status_class; ?>">
                                                                        <i class="mdi <?php echo $status_icon; ?> me-1"></i>
                                                                        <?php echo $stock_status; ?>
                                                                    </span>
                                                                </div>
                                                                <div class="progress" style="height: 6px; width: 80px; margin: 0 auto;">
                                                                    <div class="progress-bar <?php echo $progress_class; ?>" 
                                                                         style="width: <?php echo $stock_percentage; ?>%"></div>
                                                                </div>
                                                            </td>
                                                            <td class="text-end">
                                                                <h5 class="font-size-14 mb-1">₹<?php echo number_format($row['customer_price'], 2); ?></h5>
                                                                <small class="text-muted">Cost: ₹<?php echo number_format($row['stock_price'], 2); ?></small>
                                                                <br><small class="text-success">Profit: ₹<?php echo number_format($row['profit'], 2); ?> (<?php echo $row['profit_percentage']; ?>%)</small>
                                                            </td>
                                                            <td class="text-end">
                                                                <h5 class="font-size-14 mb-1">₹<?php echo number_format($stock_value, 2); ?></h5>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="dropdown">
                                                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <i class="mdi mdi-dots-horizontal"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                                        <li>
                                                                            <a class="dropdown-item" href="product-details.php?id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-eye-outline me-1"></i> View Details
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item" type="button" 
                                                                                    data-bs-toggle="modal" data-bs-target="#requestStockModal"
                                                                                    data-product-id="<?php echo $row['id']; ?>"
                                                                                    data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                                                    data-current-qty="<?php echo $current_qty; ?>">
                                                                                <i class="mdi mdi-alarm-plus me-1"></i> Request Stock
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="quick-order.php?product_id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-cart-plus me-1"></i> Create Order
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item" type="button" 
                                                                                    data-bs-toggle="modal" data-bs-target="#quickIncreaseModal"
                                                                                    data-product-id="<?php echo $row['id']; ?>"
                                                                                    data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                                                    data-current-qty="<?php echo $current_qty; ?>">
                                                                                <i class="mdi mdi-plus-circle-outline me-1"></i> Quick Increase
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item" type="button" 
                                                                                    data-bs-toggle="modal" data-bs-target="#quickDecreaseModal"
                                                                                    data-product-id="<?php echo $row['id']; ?>"
                                                                                    data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                                                    data-current-qty="<?php echo $current_qty; ?>">
                                                                                <i class="mdi mdi-minus-circle-outline me-1"></i> Quick Decrease
                                                                            </button>
                                                                        </li>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li>
                                                                            <a class="dropdown-item text-info" href="#" onclick="viewStockHistory(<?php echo $row['id']; ?>)">
                                                                                <i class="mdi mdi-history me-1"></i> Stock History
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-package-variant-closed display-4"></i>
                                                                <h5 class="mt-2">No Stock Available</h5>
                                                                <p>No products found with current filters</p>
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
                                                Showing <?php echo mysqli_num_rows($result); ?> products
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

    <!-- Request Stock Modal -->
    <div class="modal fade" id="requestStockModal" tabindex="-1" aria-labelledby="requestStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="requestStockForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="requestStockModalLabel">Request Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="modal_product_id">
                        
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong id="modal_product_name"></strong></p>
                            <p class="text-muted mb-0">Current Stock: <span id="modal_current_qty" class="fw-bold"></span></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="request_qty" class="form-label">Request Quantity *</label>
                            <input type="number" class="form-control" id="request_qty" name="request_qty" 
                                   min="1" max="1000" required placeholder="Enter quantity">
                        </div>
                        
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority *</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Enter any notes about this request..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="request_stock" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Increase Modal -->
    <div class="modal fade" id="quickIncreaseModal" tabindex="-1" aria-labelledby="quickIncreaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="quickIncreaseForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="quickIncreaseModalLabel">Quick Increase Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="inc_product_id">
                        
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong id="inc_product_name"></strong></p>
                            <p class="text-muted mb-0">Current Stock: <span id="inc_current_qty" class="fw-bold"></span></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="increase_qty" class="form-label">Add Quantity *</label>
                            <input type="number" class="form-control" id="increase_qty" name="increase_qty" min="1" required placeholder="Enter quantity to add">
                        </div>

                        <div class="mb-3">
                            <label for="purchase_date" class="form-label">Purchase Date (optional)</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" placeholder="YYYY-MM-DD">
                        </div>

                        <div class="mb-3">
                            <label for="increase_remark" class="form-label">Remark (optional)</label>
                            <textarea class="form-control" id="increase_remark" name="increase_remark" rows="2" placeholder="Enter remark..."></textarea>
                        </div>

                        <div class="mb-2 text-muted small">
                            Purchase date and remark are optional. A stock transaction record will be created.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="quick_increase" class="btn btn-success">Increase Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Decrease Modal -->
    <div class="modal fade" id="quickDecreaseModal" tabindex="-1" aria-labelledby="quickDecreaseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="quickDecreaseForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="quickDecreaseModalLabel">Quick Decrease Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="dec_product_id">
                        
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong id="dec_product_name"></strong></p>
                            <p class="text-muted mb-0">Current Stock: <span id="dec_current_qty" class="fw-bold"></span></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="decrease_qty" class="form-label">Reduce Quantity *</label>
                            <input type="number" class="form-control" id="decrease_qty" name="decrease_qty" min="1" required placeholder="Enter quantity to reduce">
                        </div>

                        <div class="mb-3">
                            <label for="decrease_remark" class="form-label">Reason / Remark (optional)</label>
                            <textarea class="form-control" id="decrease_remark" name="decrease_remark" rows="2" placeholder="Enter reason (e.g., damaged, returned)..."></textarea>
                        </div>

                        <div class="mb-2 text-muted small">
                            If you attempt to reduce more than available quantity, the action will be blocked.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="quick_decrease" class="btn btn-danger">Decrease Stock</button>
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
        // Request Stock Modal population
        document.addEventListener('DOMContentLoaded', function() {
            const requestStockModal = document.getElementById('requestStockModal');
            
            if (requestStockModal) {
                requestStockModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const productId = button.getAttribute('data-product-id');
                    const productName = button.getAttribute('data-product-name');
                    const currentQty = button.getAttribute('data-current-qty');
                    
                    document.getElementById('modal_product_id').value = productId;
                    document.getElementById('modal_product_name').textContent = productName;
                    document.getElementById('modal_current_qty').textContent = currentQty;
                    
                    // Set default request quantity
                    const requestQtyInput = document.getElementById('request_qty');
                    const minQty = Math.max(10, Math.ceil(currentQty * 2));
                    requestQtyInput.value = minQty;
                    requestQtyInput.min = 1;
                    requestQtyInput.max = minQty * 5;
                });
            }

            // Quick Increase Modal population
            const incModal = document.getElementById('quickIncreaseModal');
            if (incModal) {
                incModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const productId = button.getAttribute('data-product-id');
                    const productName = button.getAttribute('data-product-name');
                    const currentQty = button.getAttribute('data-current-qty');

                    document.getElementById('inc_product_id').value = productId;
                    document.getElementById('inc_product_name').textContent = productName;
                    document.getElementById('inc_current_qty').textContent = currentQty;

                    const incQtyInput = document.getElementById('increase_qty');
                    // suggest a sensible default
                    incQtyInput.value = Math.max(10, Math.ceil(currentQty * 0.5));
                    incQtyInput.min = 1;
                });
            }

            // Quick Decrease Modal population
            const decModal = document.getElementById('quickDecreaseModal');
            if (decModal) {
                decModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const productId = button.getAttribute('data-product-id');
                    const productName = button.getAttribute('data-product-name');
                    const currentQty = button.getAttribute('data-current-qty');

                    document.getElementById('dec_product_id').value = productId;
                    document.getElementById('dec_product_name').textContent = productName;
                    document.getElementById('dec_current_qty').textContent = currentQty;

                    const decQtyInput = document.getElementById('decrease_qty');
                    decQtyInput.value = 1;
                    decQtyInput.min = 1;
                    decQtyInput.max = Math.max(1, parseInt(currentQty));
                });
            }
        });

        // Quick filter functions
        function filterLowStock() {
            window.location.href = 'available-stock.php?availability=low';
        }

        function filterMediumStock() {
            window.location.href = 'available-stock.php?availability=medium';
        }

        function filterGoodStock() {
            window.location.href = 'available-stock.php?availability=good';
        }

        function filterZeroStock() {
            window.location.href = 'available-stock.php?availability=out';
        }

        // Print stock list
        function printStock() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Stock Availability Report - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        .text-danger { color: #dc3545; }
                        .text-success { color: #28a745; }
                        .text-warning { color: #ffc107; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Stock Availability Report</h1>
                    <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <p><strong>Total Products:</strong> <?php echo $stats['total_products'] ?? 0; ?></p>
                    <p><strong>Total Quantity:</strong> <?php echo number_format($stats['total_quantity'] ?? 0); ?></p>
                    <p><strong>Total Value:</strong> ₹<?php echo number_format($stats['total_value_selling'] ?? 0, 2); ?></p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th>Code</th>
                                <th>Category</th>
                                <th class="text-center">Current Stock</th>
                                <th class="text-center">Min Level</th>
                                <th class="text-center">Status</th>
                                <th class="text-right">Selling Price</th>
                                <th class="text-right">Stock Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            mysqli_data_seek($result, 0);
                            $print_counter = 1;
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $current_qty = $row['quantity'];
                                    $min_level = 10; // Default min level
                                    $reorder_level = 50; // Default reorder level
                                    
                                    // Determine stock status
                                    $stock_status = '';
                                    $status_class = '';
                                    
                                    if ($current_qty <= $min_level) {
                                        $stock_status = 'Critical';
                                        $status_class = 'text-danger';
                                    } elseif ($current_qty <= $reorder_level) {
                                        $stock_status = 'Warning';
                                        $status_class = 'text-warning';
                                    } else {
                                        $stock_status = 'Good';
                                        $status_class = 'text-success';
                                    }
                                    
                                    $stock_value = $current_qty * $row['customer_price'];
                                    
                                    echo '<tr>';
                                    echo '<td>' . $print_counter++ . '</td>';
                                    echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                                    echo '<td>' . $row['product_code'] . '</td>';
                                    echo '<td>' . $row['category_name'] . '</td>';
                                    echo '<td class="text-center">' . number_format($current_qty) . '</td>';
                                    echo '<td class="text-center">' . $min_level . '</td>';
                                    echo '<td class="text-center ' . $status_class . '">' . $stock_status . '</td>';
                                    echo '<td class="text-right">₹' . number_format($row['customer_price'], 2) . '</td>';
                                    echo '<td class="text-right">₹' . number_format($stock_value, 2) . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                <td colspan="2"></td>
                                <td class="text-right"><strong>₹<?php echo number_format($stats['total_value_selling'] ?? 0, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                        <p>Low Stock Items: <strong class="text-warning"><?php echo $stats['low_stock_count'] ?? 0; ?></strong></p>
                        <p>Out of Stock: <strong class="text-danger"><?php echo $stats['out_of_stock_count'] ?? 0; ?></strong></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Export stock data
        function exportStock() {
            const search = '<?php echo $search; ?>';
            const categoryId = '<?php echo $category_id; ?>';
            const minQty = '<?php echo $min_qty; ?>';
            const maxQty = '<?php echo $max_qty; ?>';
            const availability = '<?php echo $availability; ?>';
            
            window.location.href = `export-stock.php?search=${encodeURIComponent(search)}&category_id=${categoryId}&min_qty=${minQty}&max_qty=${maxQty}&availability=${availability}`;
        }

        // View stock history
        function viewStockHistory(productId) {
            window.location.href = `stock-history.php?product_id=${productId}`;
        }

        // Auto-submit filters
        document.querySelectorAll('select[name="availability"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search on enter key
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        }

        // Add export button
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelector('.d-flex.flex-wrap');
            if (actionButtons) {
                const exportButton = document.createElement('button');
                exportButton.type = 'button';
                exportButton.className = 'btn btn-outline-secondary';
                exportButton.innerHTML = '<i class="mdi mdi-download me-1"></i> Export';
                exportButton.onclick = exportStock;
                actionButtons.appendChild(exportButton);
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
