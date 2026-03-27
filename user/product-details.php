<?php
// Check if session is already started

include('config/config.php');
include('includes/auth-check.php');

// Ensure only authorized users can access this page
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin', 'lineman'])) {
    header('Location: index.php');
    exit;
}

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: product-catalog.php');
    exit;
}

$product_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Fetch product details
$product_sql = "SELECT p.*, 
                       c.category_name,
                       b.brand_name,
                       COALESCE(SUM(oi.quantity), 0) as total_sold,
                       COUNT(DISTINCT oi.order_id) as order_count
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                WHERE p.id = $product_id
                GROUP BY p.id";

$product_result = mysqli_query($conn, $product_sql);

if (!$product_result || mysqli_num_rows($product_result) == 0) {
    header('Location: product-catalog.php');
    exit;
}

$product = mysqli_fetch_assoc($product_result);

// Fetch stock history
$stock_history_sql = "SELECT * FROM stock_transactions 
                      WHERE product_id = $product_id 
                      ORDER BY created_at DESC 
                      LIMIT 20";
$stock_history_result = mysqli_query($conn, $stock_history_sql);

// Fetch recent orders
$recent_orders_sql = "SELECT o.order_number, o.order_date, oi.quantity, oi.price, 
                             c.shop_name, c.customer_name,
                             o.status as order_status, o.payment_status
                      FROM order_items oi
                      JOIN orders o ON oi.order_id = o.id
                      JOIN customers c ON o.customer_id = c.id
                      WHERE oi.product_id = $product_id
                      ORDER BY o.order_date DESC 
                      LIMIT 10";
$recent_orders_result = mysqli_query($conn, $recent_orders_sql);

// Fetch stock requests
$stock_requests_sql = "SELECT sr.*, 
                              l.full_name as requested_by_name
                       FROM stock_requests sr
                       LEFT JOIN linemen l ON sr.requested_by = l.id
                       WHERE sr.product_id = $product_id
                       ORDER BY sr.created_at DESC 
                       LIMIT 10";
$stock_requests_result = mysqli_query($conn, $stock_requests_sql);

// Calculate sales statistics
$sales_stats_sql = "SELECT 
                    MONTH(o.order_date) as month,
                    YEAR(o.order_date) as year,
                    SUM(oi.quantity) as total_qty,
                    SUM(oi.total) as total_amount,
                    COUNT(DISTINCT o.id) as order_count
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE oi.product_id = $product_id
                    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY YEAR(o.order_date), MONTH(o.order_date)
                    ORDER BY year DESC, month DESC";
$sales_stats_result = mysqli_query($conn, $sales_stats_sql);

// Store stock history in array for multiple use
$stock_history_data = [];
if ($stock_history_result) {
    while ($row = mysqli_fetch_assoc($stock_history_result)) {
        $stock_history_data[] = $row;
    }
    // Reset pointer
    reset($stock_history_data);
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['adjust_stock'])) {
        $adjustment_type = mysqli_real_escape_string($conn, $_POST['adjustment_type']);
        $adjustment_qty = intval($_POST['adjustment_qty']);
        $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
        
        $current_qty = $product['quantity'];
        $new_qty = $adjustment_type == 'increase' ? $current_qty + $adjustment_qty : $current_qty - $adjustment_qty;
        
        // Ensure stock doesn't go negative
        if ($new_qty < 0) {
            $error_message = "Cannot decrease stock below 0!";
        } else {
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update product quantity
                $update_sql = "UPDATE products SET quantity = $new_qty WHERE id = $product_id";
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Failed to update product quantity: " . mysqli_error($conn));
                }
                
                // Record stock transaction
                $transaction_type = $adjustment_type == 'increase' ? 'purchase' : 'adjustment';
                
                $transaction_sql = "INSERT INTO stock_transactions (product_id, transaction_type, quantity, 
                                    stock_price, previous_quantity, new_quantity, notes, created_by, created_at) 
                                    VALUES ($product_id, '$transaction_type', $adjustment_qty, 
                                    '{$product['stock_price']}', $current_qty, $new_qty, 
                                    '$remarks', $user_id, NOW())";
                
                if (!mysqli_query($conn, $transaction_sql)) {
                    throw new Exception("Failed to record stock transaction: " . mysqli_error($conn));
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Update product data
                $product['quantity'] = $new_qty;
                
                // Refresh stock history
                $stock_history_result = mysqli_query($conn, $stock_history_sql);
                $stock_history_data = [];
                if ($stock_history_result) {
                    while ($row = mysqli_fetch_assoc($stock_history_result)) {
                        $stock_history_data[] = $row;
                    }
                    reset($stock_history_data);
                }
                
                $success_message = "Stock adjusted successfully! " . 
                    ($adjustment_type == 'increase' ? "+" : "-") . 
                    "$adjustment_qty units. New quantity: $new_qty";
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = $e->getMessage();
            }
        }
    }
    
    // Handle stock request
    if (isset($_POST['request_stock'])) {
        $request_qty = intval($_POST['request_qty']);
        $priority = mysqli_real_escape_string($conn, $_POST['priority']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        if ($request_qty > 0) {
            $request_id = 'REQ' . date('Ymd') . rand(100, 999);
            
            $request_sql = "INSERT INTO stock_requests (request_id, product_id, requested_qty, 
                              current_qty, priority, notes, requested_by, status, created_at) 
                              VALUES ('$request_id', $product_id, $request_qty, 
                              '{$product['quantity']}', '$priority', '$notes', 
                              $user_id, 'pending', NOW())";
            
            if (mysqli_query($conn, $request_sql)) {
                $success_message = "Stock request submitted successfully!";
            } else {
                $error_message = "Failed to submit stock request: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Invalid quantity";
        }
    }
    
    // Handle price update
    if (isset($_POST['update_price'])) {
        $new_stock_price = floatval($_POST['stock_price']);
        $new_customer_price = floatval($_POST['customer_price']);
        
        if ($new_customer_price > $new_stock_price) {
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
                // Update product data
                $product['stock_price'] = $new_stock_price;
                $product['customer_price'] = $new_customer_price;
                $product['profit'] = $profit;
                $product['profit_percentage'] = $profit_percentage;
                
                $success_message = "Price updated successfully!";
            } else {
                $error_message = "Failed to update price: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Selling price must be greater than cost price!";
        }
    }
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


                    <!-- Product Header -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h4 class="card-title mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                            <p class="card-title-desc mb-0">
                                                <span class="badge bg-light text-dark me-2">Code: <?php echo $product['product_code']; ?></span>
                                                <span class="badge bg-primary-subtle text-primary me-2"><?php echo $product['category_name']; ?></span>
                                                <?php if (!empty($product['brand_name'])): ?>
                                                <span class="badge bg-info-subtle text-info"><?php echo $product['brand_name']; ?></span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-md-end mt-3 mt-md-0">
                                                <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                <a href="product-catalog.php?edit=<?php echo $product_id; ?>" class="btn btn-warning">
                                                    <i class="mdi mdi-pencil me-1"></i> Edit Product
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($user_role == 'lineman'): ?>
                                                <a href="quick-order.php?product_id=<?php echo $product_id; ?>" class="btn btn-primary">
                                                    <i class="mdi mdi-cart-plus me-1"></i> Create Order
                                                </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-info ms-2" onclick="printProductDetails()">
                                                    <i class="mdi mdi-printer me-1"></i> Print
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Product Info -->
                    <div class="row">
                        <!-- Left Column - Product Details -->
                        <div class="col-lg-8">
                            <!-- Product Statistics -->
                            <div class="row mb-4">
                                <div class="col-md-3 col-sm-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5 class="text-muted fw-normal mt-0">Current Stock</h5>
                                            <h3 class="my-2 py-1 <?php echo $product['quantity'] <= 10 ? 'text-danger' : ($product['quantity'] <= 50 ? 'text-warning' : 'text-success'); ?>">
                                                <?php echo number_format($product['quantity']); ?>
                                            </h3>
                                            <p class="mb-0 text-muted">Units available</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5 class="text-muted fw-normal mt-0">Total Sold</h5>
                                            <h3 class="my-2 py-1"><?php echo number_format($product['total_sold']); ?></h3>
                                            <p class="mb-0 text-muted">All time sales</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5 class="text-muted fw-normal mt-0">Total Orders</h5>
                                            <h3 class="my-2 py-1"><?php echo $product['order_count']; ?></h3>
                                            <p class="mb-0 text-muted">Times ordered</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h5 class="text-muted fw-normal mt-0">Stock Value</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($product['quantity'] * $product['customer_price'], 2); ?></h3>
                                            <p class="mb-0 text-muted">At selling price</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Price Information -->
                            <div class="row mb-4">
                                <div class="col-lg-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3">Price Information</h5>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 text-center">
                                                        <h6 class="text-muted mb-2">Cost Price</h6>
                                                        <h3 class="mb-0">₹<?php echo number_format($product['stock_price'], 2); ?></h3>
                                                        <p class="text-muted mb-0">Per unit</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 text-center">
                                                        <h6 class="text-muted mb-2">Selling Price</h6>
                                                        <h3 class="mb-0 text-success">₹<?php echo number_format($product['customer_price'], 2); ?></h3>
                                                        <p class="text-muted mb-0">Per unit</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 text-center">
                                                        <h6 class="text-muted mb-2">Profit Margin</h6>
                                                        <h3 class="mb-0 text-primary">₹<?php echo number_format($product['profit'], 2); ?></h3>
                                                        <p class="text-muted mb-0">(<?php echo number_format($product['profit_percentage'], 1); ?>%)</p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#updatePriceModal">
                                                    <i class="mdi mdi-currency-inr me-1"></i> Update Price
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock History -->
                            <div class="row mb-4">
                                <div class="col-lg-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="card-title mb-0">Stock History</h5>
                                                <?php if (in_array($user_role, ['admin', 'super_admin', 'lineman'])): ?>
                                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                                                    <i class="mdi mdi-sync me-1"></i> Adjust Stock
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($stock_history_data)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Date & Time</th>
                                                            <th>Type</th>
                                                            <th>Quantity</th>
                                                            <th>Previous</th>
                                                            <th>New</th>
                                                            <th>Notes</th>
                                                            <th>By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        // Get creator names
                                                        $creator_ids = [];
                                                        foreach ($stock_history_data as $history) {
                                                            if ($history['created_by']) {
                                                                $creator_ids[] = $history['created_by'];
                                                            }
                                                        }
                                                        
                                                        // Get creator names
                                                        $creators = [];
                                                        if (!empty($creator_ids)) {
                                                            $ids_str = implode(',', array_unique(array_filter($creator_ids)));
                                                            $creator_sql = "SELECT id, name FROM admin_users WHERE id IN ($ids_str)
                                                                            UNION 
                                                                            SELECT id, full_name as name FROM linemen WHERE id IN ($ids_str)";
                                                            $creator_result = mysqli_query($conn, $creator_sql);
                                                            if ($creator_result) {
                                                                while ($creator = mysqli_fetch_assoc($creator_result)) {
                                                                    $creators[$creator['id']] = $creator['name'];
                                                                }
                                                            }
                                                        }
                                                        
                                                        foreach ($stock_history_data as $history): 
                                                            $type_class = $history['transaction_type'] == 'purchase' ? 'success' : 
                                                                         ($history['transaction_type'] == 'sale' ? 'danger' : 
                                                                         ($history['transaction_type'] == 'adjustment' ? 'warning' : 'info'));
                                                        ?>
                                                        <tr>
                                                            <td><?php echo date('d M, Y h:i A', strtotime($history['created_at'])); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $type_class; ?>-subtle text-<?php echo $type_class; ?>">
                                                                    <?php echo ucfirst($history['transaction_type']); ?>
                                                                </span>
                                                            </td>
                                                            <td class="<?php echo $history['transaction_type'] == 'purchase' ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo $history['transaction_type'] == 'purchase' ? '+' : '-'; ?><?php echo $history['quantity']; ?>
                                                            </td>
                                                            <td><?php echo $history['previous_quantity']; ?></td>
                                                            <td><?php echo $history['new_quantity']; ?></td>
                                                            <td><?php echo htmlspecialchars($history['notes']); ?></td>
                                                            <td>
                                                                <?php 
                                                                if ($history['created_by'] && isset($creators[$history['created_by']])) {
                                                                    echo $creators[$history['created_by']];
                                                                } else {
                                                                    echo 'System';
                                                                }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="mdi mdi-history display-4 text-muted"></i>
                                                <p class="mt-2 text-muted">No stock history available</p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-center mt-3">
                                                <a href="stock-history.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline-info btn-sm">
                                                    <i class="mdi mdi-history me-1"></i> View Full History
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Actions & Info -->
                        <div class="col-lg-4">
                            <!-- Product Status & Actions -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Product Status</h5>
                                    
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Stock Status:</span>
                                            <span class="badge <?php echo $product['quantity'] <= 10 ? 'bg-danger' : ($product['quantity'] <= 50 ? 'bg-warning' : 'bg-success'); ?>">
                                                <?php 
                                                if ($product['quantity'] <= 10) {
                                                    echo 'Critical';
                                                } elseif ($product['quantity'] <= 50) {
                                                    echo 'Warning';
                                                } else {
                                                    echo 'Good';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        
                                        <div class="progress" style="height: 10px;">
                                            <?php
                                            $max_level = max($product['quantity'] * 2, 100);
                                            $percentage = min(100, ($product['quantity'] / $max_level) * 100);
                                            $progress_class = $percentage < 30 ? 'bg-danger' : ($percentage < 60 ? 'bg-warning' : 'bg-success');
                                            ?>
                                            <div class="progress-bar <?php echo $progress_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $product['quantity']; ?> units available</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Product Status:</span>
                                            <span class="badge <?php echo $product['status'] == 'active' ? 'bg-success' : ($product['status'] == 'inactive' ? 'bg-danger' : 'bg-warning'); ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Created:</span>
                                            <span><?php echo date('d M, Y', strtotime($product['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Last Updated:</span>
                                            <span><?php echo !empty($product['updated_at']) ? date('d M, Y', strtotime($product['updated_at'])) : 'Never'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Stock Adjustment -->
                                    <div class="mb-3">
                                        <h6 class="mb-2">Quick Adjust:</h6>
                                        <div class="btn-group btn-group-sm w-100" role="group">
                                            <button type="button" class="btn btn-outline-success" onclick="quickAdjust('increase', 10)">
                                                <i class="mdi mdi-plus"></i> +10
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="quickAdjust('increase', 50)">
                                                <i class="mdi mdi-plus"></i> +50
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="quickAdjust('decrease', 5)">
                                                <i class="mdi mdi-minus"></i> -5
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="quickAdjust('decrease', 10)">
                                                <i class="mdi mdi-minus"></i> -10
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-grid gap-2">
                                        <?php if ($user_role == 'lineman'): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestStockModal">
                                            <i class="mdi mdi-alarm-plus me-1"></i> Request Stock
                                        </button>
                                        <?php endif; ?>
                                        
                                        <a href="quick-order.php?product_id=<?php echo $product_id; ?>" class="btn btn-success">
                                            <i class="mdi mdi-cart-plus me-1"></i> Create Order
                                        </a>
                                        
                                        <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                        <a href="product-catalog.php?edit=<?php echo $product_id; ?>" class="btn btn-warning">
                                            <i class="mdi mdi-pencil me-1"></i> Edit Product
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales Statistics -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Sales Statistics (Last 6 Months)</h5>
                                    
                                    <?php if ($sales_stats_result && mysqli_num_rows($sales_stats_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Month</th>
                                                    <th class="text-end">Qty Sold</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($stat = mysqli_fetch_assoc($sales_stats_result)): 
                                                    $month_name = date('M', mktime(0, 0, 0, $stat['month'], 1));
                                                ?>
                                                <tr>
                                                    <td><?php echo $month_name . ' ' . $stat['year']; ?></td>
                                                    <td class="text-end"><?php echo number_format($stat['total_qty']); ?></td>
                                                    <td class="text-end">₹<?php echo number_format($stat['total_amount'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-chart-line display-4 text-muted"></i>
                                        <p class="mt-2 text-muted">No sales data available</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Orders -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Recent Orders</h5>
                                    
                                    <?php if ($recent_orders_result && mysqli_num_rows($recent_orders_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Order</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($order = mysqli_fetch_assoc($recent_orders_result)): ?>
                                                <tr>
                                                    <td>
                                                        <small class="d-block"><?php echo $order['order_number']; ?></small>
                                                        <small class="text-muted"><?php echo date('d M', strtotime($order['order_date'])); ?></small>
                                                    </td>
                                                    <td class="text-end"><?php echo $order['quantity']; ?></td>
                                                    <td class="text-end">₹<?php echo number_format($order['quantity'] * $order['price'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="order-history.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline-info btn-sm">
                                            <i class="mdi mdi-eye me-1"></i> View All Orders
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-cart-outline display-4 text-muted"></i>
                                        <p class="mt-2 text-muted">No recent orders</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Requests -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Recent Stock Requests</h5>
                                    
                                    <?php if ($stock_requests_result && mysqli_num_rows($stock_requests_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Request ID</th>
                                                    <th>Date</th>
                                                    <th>Requested By</th>
                                                    <th>Quantity</th>
                                                    <th>Current Stock</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($request = mysqli_fetch_assoc($stock_requests_result)): 
                                                    $priority_class = $request['priority'] == 'urgent' ? 'danger' : 
                                                                     ($request['priority'] == 'high' ? 'warning' : 
                                                                     ($request['priority'] == 'medium' ? 'info' : 'secondary'));
                                                    $status_class = $request['status'] == 'approved' ? 'success' : 
                                                                   ($request['status'] == 'rejected' ? 'danger' : 
                                                                   ($request['status'] == 'completed' ? 'primary' : 'warning'));
                                                ?>
                                                <tr>
                                                    <td><?php echo $request['request_id']; ?></td>
                                                    <td><?php echo date('d M, Y', strtotime($request['created_at'])); ?></td>
                                                    <td><?php echo $request['requested_by_name'] ?? 'Unknown'; ?></td>
                                                    <td class="text-success">+<?php echo $request['requested_qty']; ?></td>
                                                    <td><?php echo $request['current_qty']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $priority_class; ?>-subtle text-<?php echo $priority_class; ?>">
                                                            <?php echo ucfirst($request['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $status_class; ?>-subtle text-<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($request['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                                data-bs-toggle="modal" data-bs-target="#viewRequestModal"
                                                                data-request-id="<?php echo $request['id']; ?>"
                                                                data-notes="<?php echo htmlspecialchars($request['notes']); ?>">
                                                            <i class="mdi mdi-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-alarm-check display-4 text-muted"></i>
                                        <p class="mt-2 text-muted">No stock requests</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Description -->
                    <?php if (!empty($product['description'])): ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Product Description</h5>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-labelledby="adjustStockModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="adjustStockForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="adjustStockModalLabel">Adjust Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong><?php echo htmlspecialchars($product['product_name']); ?></strong></p>
                            <p class="text-muted mb-0">Current Stock: <span class="fw-bold"><?php echo $product['quantity']; ?></span></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adjustment_type" class="form-label">Adjustment Type *</label>
                            <select class="form-select" id="adjustment_type" name="adjustment_type" required>
                                <option value="increase">Increase Stock</option>
                                <option value="decrease">Decrease Stock</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="adjustment_qty" class="form-label">Adjustment Quantity *</label>
                            <input type="number" class="form-control" id="adjustment_qty" name="adjustment_qty" 
                                   min="1" max="10000" required placeholder="Enter quantity">
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks *</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                      placeholder="Enter reason for adjustment..." required></textarea>
                        </div>
                        
                        <div class="alert alert-warning" id="adjust_warning" style="display: none;">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            <span id="adjust_warning_message"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="adjust_stock" class="btn btn-primary">Apply Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong><?php echo htmlspecialchars($product['product_name']); ?></strong></p>
                            <p class="text-muted mb-0">Current Stock: <span class="fw-bold"><?php echo $product['quantity']; ?></span></p>
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

    <!-- Update Price Modal -->
    <div class="modal fade" id="updatePriceModal" tabindex="-1" aria-labelledby="updatePriceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="updatePriceForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updatePriceModalLabel">Update Product Price</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <p class="mb-2">Product: <strong><?php echo htmlspecialchars($product['product_name']); ?></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="stock_price" class="form-label">Cost Price (₹) *</label>
                            <input type="number" class="form-control" id="stock_price" name="stock_price" 
                                   step="0.01" min="0" value="<?php echo $product['stock_price']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="customer_price" class="form-label">Selling Price (₹) *</label>
                            <input type="number" class="form-control" id="customer_price" name="customer_price" 
                                   step="0.01" min="0" value="<?php echo $product['customer_price']; ?>" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between">
                                <span>Profit:</span>
                                <span id="profit_display"></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Profit Percentage:</span>
                                <span id="profit_percentage_display"></span>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning" id="price_warning" style="display: none;">
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

    <!-- View Request Modal -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1" aria-labelledby="viewRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewRequestModalLabel">Stock Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="request_details">
                        <!-- Details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Stock adjustment validation
        document.getElementById('adjustment_type').addEventListener('change', function() {
            const currentQty = <?php echo $product['quantity']; ?>;
            const warningDiv = document.getElementById('adjust_warning');
            
            if (this.value === 'decrease' && currentQty <= 10) {
                warningDiv.style.display = 'block';
                document.getElementById('adjust_warning_message').textContent = 
                    'Warning: Stock is already low! Current stock: ' + currentQty;
            } else {
                warningDiv.style.display = 'none';
            }
        });
        
        document.getElementById('adjustStockForm').addEventListener('submit', function(e) {
            const adjustmentType = document.getElementById('adjustment_type').value;
            const adjustmentQty = parseInt(document.getElementById('adjustment_qty').value);
            const currentQty = <?php echo $product['quantity']; ?>;
            
            if (adjustmentType === 'decrease' && adjustmentQty > currentQty) {
                e.preventDefault();
                alert('Cannot decrease more than current stock! Current: ' + currentQty);
            }
        });
        
        // Price update calculations
        function calculateProfit() {
            const stockPrice = parseFloat(document.getElementById('stock_price').value) || 0;
            const customerPrice = parseFloat(document.getElementById('customer_price').value) || 0;
            const profit = customerPrice - stockPrice;
            const profitPercentage = stockPrice > 0 ? ((profit / stockPrice) * 100).toFixed(2) : 0;
            
            document.getElementById('profit_display').textContent = '₹' + profit.toFixed(2);
            document.getElementById('profit_percentage_display').textContent = profitPercentage + '%';
            
            const warningDiv = document.getElementById('price_warning');
            if (customerPrice <= stockPrice) {
                warningDiv.style.display = 'block';
            } else {
                warningDiv.style.display = 'none';
            }
        }
        
        document.getElementById('stock_price').addEventListener('input', calculateProfit);
        document.getElementById('customer_price').addEventListener('input', calculateProfit);
        
        // Initialize profit calculation
        document.addEventListener('DOMContentLoaded', function() {
            calculateProfit();
        });
        
        // Price form validation
        document.getElementById('updatePriceForm').addEventListener('submit', function(e) {
            const stockPrice = parseFloat(document.getElementById('stock_price').value);
            const customerPrice = parseFloat(document.getElementById('customer_price').value);
            
            if (customerPrice <= stockPrice) {
                e.preventDefault();
                alert('Selling price must be greater than cost price!');
            }
        });
        
        // Request stock quantity suggestion
        document.getElementById('request_qty').addEventListener('focus', function() {
            const currentQty = <?php echo $product['quantity']; ?>;
            const suggestedQty = Math.max(10, Math.ceil(currentQty * 2));
            if (this.value === '') {
                this.value = suggestedQty;
            }
        });
        
        // Print product details - SIMPLIFIED VERSION
        function printProductDetails() {
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
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Product Details - <?php echo htmlspecialchars($product['product_name']); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .info-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                        .info-table td:first-child { font-weight: bold; width: 30%; }
                        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                        .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; border-radius: 5px; }
                        .stat-value { font-size: 24px; font-weight: bold; margin: 5px 0; }
                        .stat-label { color: #666; font-size: 14px; }
                        @media print { 
                            @page { margin: 0.5in; } 
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Product Details</h1>
                    
                    <table class="info-table">
                        <tr>
                            <td>Product Name:</td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        </tr>
                        <tr>
                            <td>Product Code:</td>
                            <td><?php echo $product['product_code']; ?></td>
                        </tr>
                        <tr>
                            <td>Category:</td>
                            <td><?php echo $product['category_name']; ?></td>
                        </tr>
                        <tr>
                            <td>Brand:</td>
                            <td><?php echo $product['brand_name'] ?? 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td>Current Stock:</td>
                            <td><?php echo number_format($product['quantity']); ?> units</td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td><?php echo ucfirst($product['status']); ?></td>
                        </tr>
                        <tr>
                            <td>Created:</td>
                            <td><?php echo date('d M, Y', strtotime($product['created_at'])); ?></td>
                        </tr>
                        <?php if (!empty($product['updated_at'])): ?>
                        <tr>
                            <td>Last Updated:</td>
                            <td><?php echo date('d M, Y', strtotime($product['updated_at'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <h2>Price Information</h2>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Cost Price</div>
                            <div class="stat-value">₹<?php echo number_format($product['stock_price'], 2); ?></div>
                            <div class="stat-label">Per unit</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Selling Price</div>
                            <div class="stat-value">₹<?php echo number_format($product['customer_price'], 2); ?></div>
                            <div class="stat-label">Per unit</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Profit per Unit</div>
                            <div class="stat-value">₹<?php echo number_format($product['profit'], 2); ?></div>
                            <div class="stat-label">Profit margin</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Profit Percentage</div>
                            <div class="stat-value"><?php echo number_format($product['profit_percentage'], 1); ?>%</div>
                            <div class="stat-label">Margin</div>
                        </div>
                    </div>
                    
                    <h2>Sales Statistics</h2>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Total Sold</div>
                            <div class="stat-value"><?php echo number_format($product['total_sold']); ?></div>
                            <div class="stat-label">Units sold</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-value"><?php echo $product['order_count']; ?></div>
                            <div class="stat-label">Orders placed</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Current Stock Value</div>
                            <div class="stat-value">₹<?php echo number_format($product['quantity'] * $product['customer_price'], 2); ?></div>
                            <div class="stat-label">At selling price</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Stock Status</div>
                            <div class="stat-value">
                                <?php 
                                if ($product['quantity'] <= 10) {
                                    echo 'Critical';
                                } elseif ($product['quantity'] <= 50) {
                                    echo 'Warning';
                                } else {
                                    echo 'Good';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Availability</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($product['description'])): ?>
                    <h2>Description</h2>
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($stock_history_data)): ?>
                    <h2>Recent Stock History</h2>
                    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                        <thead>
                            <tr style="background-color: #f8f9fa;">
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Date</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Type</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Quantity</th>
                                <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            foreach ($stock_history_data as $history):
                                if ($count >= 5) break;
                                $count++;
                            ?>
                            <tr>
                                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo date('d M, Y', strtotime($history['created_at'])); ?></td>
                                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo ucfirst($history['transaction_type']); ?></td>
                                <td style="border: 1px solid #ddd; padding: 8px; <?php echo $history['transaction_type'] == 'purchase' ? 'color: green;' : 'color: red;'; ?>">
                                    <?php echo $history['transaction_type'] == 'purchase' ? '+' : '-'; ?><?php echo $history['quantity']; ?>
                                </td>
                                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($history['notes']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid #ddd;">
                        <p>Generated on: ${formattedDate}</p>
                        <p>Generated by: <?php echo htmlspecialchars($_SESSION['name']); ?></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        
        // View request details
        const viewRequestModal = document.getElementById('viewRequestModal');
        if (viewRequestModal) {
            viewRequestModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const notes = button.getAttribute('data-notes');
                
                document.getElementById('request_details').innerHTML = `
                    <p><strong>Notes:</strong></p>
                    <p>${notes || 'No notes provided'}</p>
                `;
            });
        }
        
        // Quick stock adjustment
        function quickAdjust(type, qty) {
            const adjustmentType = type;
            const adjustmentQty = qty;
            const currentQty = <?php echo $product['quantity']; ?>;
            
            if (type === 'decrease' && qty > currentQty) {
                alert('Cannot decrease more than current stock! Current: ' + currentQty);
                return;
            }
            
            if (confirm(`Are you sure you want to ${type} stock by ${qty} units?`)) {
                // Create a form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'adjustment_type';
                typeInput.value = type;
                
                const qtyInput = document.createElement('input');
                qtyInput.type = 'hidden';
                qtyInput.name = 'adjustment_qty';
                qtyInput.value = qty;
                
                const remarksInput = document.createElement('input');
                remarksInput.type = 'hidden';
                remarksInput.name = 'remarks';
                remarksInput.value = `Quick ${type} by ${qty} units`;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'adjust_stock';
                submitInput.value = '1';
                
                form.appendChild(typeInput);
                form.appendChild(qtyInput);
                form.appendChild(remarksInput);
                form.appendChild(submitInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}