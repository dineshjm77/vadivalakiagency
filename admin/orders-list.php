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

                <?php
                // Database connection
                include('config/config.php');
                
                // Initialize variables
                $message = '';
                $message_type = '';
                
                // Handle order status update
                if (isset($_POST['update_order_status'])) {
                    $order_id = intval($_POST['order_id']);
                    $new_status = mysqli_real_escape_string($conn, $_POST['order_status']);
                    
                    $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Order status updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating order status: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
                
                // Handle stock request status update
                if (isset($_POST['update_stock_request_status'])) {
                    $request_id = intval($_POST['request_id']);
                    $new_status = mysqli_real_escape_string($conn, $_POST['stock_request_status']);
                    $admin_id = $_SESSION['admin_id'] ?? 1; // Assuming admin is logged in
                    
                    $update_sql = "UPDATE stock_requests SET 
                                  status = ?, 
                                  approved_by = ?, 
                                  approved_at = NOW() 
                                  WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($stmt, "sii", $new_status, $admin_id, $request_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // If approved, update product stock
                        if ($new_status == 'approved' || $new_status == 'completed') {
                            // Get stock request details
                            $req_sql = "SELECT product_id, requested_qty FROM stock_requests WHERE id = ?";
                            $req_stmt = mysqli_prepare($conn, $req_sql);
                            mysqli_stmt_bind_param($req_stmt, "i", $request_id);
                            mysqli_stmt_execute($req_stmt);
                            $req_result = mysqli_stmt_get_result($req_stmt);
                            
                            if ($req_row = mysqli_fetch_assoc($req_result)) {
                                // Update product quantity
                                $update_stock_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_stock_sql);
                                mysqli_stmt_bind_param($update_stmt, "ii", $req_row['requested_qty'], $req_row['product_id']);
                                mysqli_stmt_execute($update_stmt);
                                
                                // Log stock transaction
                                $transaction_sql = "INSERT INTO stock_transactions 
                                                  (product_id, transaction_type, quantity, stock_price, 
                                                  previous_quantity, new_quantity, notes, created_by) 
                                                  SELECT 
                                                  ?, 'purchase', requested_qty, 
                                                  (SELECT stock_price FROM products WHERE id = ?),
                                                  (SELECT quantity FROM products WHERE id = ?),
                                                  (SELECT quantity + requested_qty FROM products WHERE id = ?),
                                                  'Stock request approved', 
                                                  ? 
                                                  FROM stock_requests WHERE id = ?";
                                $trans_stmt = mysqli_prepare($conn, $transaction_sql);
                                mysqli_stmt_bind_param($trans_stmt, "iiiiii", 
                                    $req_row['product_id'], $req_row['product_id'], 
                                    $req_row['product_id'], $req_row['product_id'], 
                                    $admin_id, $request_id);
                                mysqli_stmt_execute($trans_stmt);
                            }
                        }
                        
                        $message = 'Stock request status updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating stock request status: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
                
                // Handle delete order
                if (isset($_GET['delete_order'])) {
                    $order_id = intval($_GET['delete_order']);
                    
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Get order items first to update stock
                        $items_sql = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
                        $items_stmt = mysqli_prepare($conn, $items_sql);
                        mysqli_stmt_bind_param($items_stmt, "i", $order_id);
                        mysqli_stmt_execute($items_stmt);
                        $items_result = mysqli_stmt_get_result($items_stmt);
                        
                        // Update stock for each item
                        while ($item = mysqli_fetch_assoc($items_result)) {
                            $update_stock_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                            $update_stmt = mysqli_prepare($conn, $update_stock_sql);
                            mysqli_stmt_bind_param($update_stmt, "ii", $item['quantity'], $item['product_id']);
                            mysqli_stmt_execute($update_stmt);
                        }
                        
                        // Delete order items
                        $delete_items_sql = "DELETE FROM order_items WHERE order_id = ?";
                        $delete_items_stmt = mysqli_prepare($conn, $delete_items_sql);
                        mysqli_stmt_bind_param($delete_items_stmt, "i", $order_id);
                        mysqli_stmt_execute($delete_items_stmt);
                        
                        // Delete order
                        $delete_order_sql = "DELETE FROM orders WHERE id = ?";
                        $delete_order_stmt = mysqli_prepare($conn, $delete_order_sql);
                        mysqli_stmt_bind_param($delete_order_stmt, "i", $order_id);
                        mysqli_stmt_execute($delete_order_stmt);
                        
                        // Commit transaction
                        mysqli_commit($conn);
                        
                        $message = 'Order deleted successfully!';
                        $message_type = 'success';
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        mysqli_rollback($conn);
                        $message = 'Error deleting order: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }
                
                // Handle delete stock request
                if (isset($_GET['delete_stock_request'])) {
                    $request_id = intval($_GET['delete_stock_request']);
                    
                    $delete_sql = "DELETE FROM stock_requests WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $delete_sql);
                    mysqli_stmt_bind_param($stmt, "i", $request_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Stock request deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error deleting stock request: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- SUMMARY / STATS CARDS (TOP) -->
                <div class="row mb-3">
                    <?php
                    // Calculate summary values (these queries are lightweight)
                    $total_orders_sql = "SELECT COUNT(*) as total FROM orders";
                    $total_orders_result = mysqli_query($conn, $total_orders_sql);
                    $total_orders = mysqli_fetch_assoc($total_orders_result)['total'] ?? 0;

                    $pending_orders_sql = "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'";
                    $pending_orders_result = mysqli_query($conn, $pending_orders_sql);
                    $pending_orders = mysqli_fetch_assoc($pending_orders_result)['total'] ?? 0;

                    $pending_requests_sql = "SELECT COUNT(*) as total FROM stock_requests WHERE status = 'pending'";
                    $pending_requests_result = mysqli_query($conn, $pending_requests_sql);
                    $pending_requests = mysqli_fetch_assoc($pending_requests_result)['total'] ?? 0;

                    $total_sales_sql = "SELECT SUM(total_amount) as total FROM orders WHERE status = 'delivered'";
                    $total_sales_result = mysqli_query($conn, $total_sales_sql);
                    $total_sales = mysqli_fetch_assoc($total_sales_result)['total'] ?? 0;
                    $total_sales = $total_sales ?: 0;
                    ?>
                    <div class="col-sm-6 col-md-3">
                        <div class="card stats-card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted fw-normal mt-0 mb-1">Total Orders</h6>
                                    <h3 class="mt-0 mb-0"><?php echo $total_orders; ?></h3>
                                </div>
                                <div class="text-end">
                                    <i class="mdi mdi-cart-outline text-primary h2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card stats-card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted fw-normal mt-0 mb-1">Pending Orders</h6>
                                    <h3 class="mt-0 mb-0"><?php echo $pending_orders; ?></h3>
                                </div>
                                <div class="text-end">
                                    <i class="mdi mdi-clock-outline text-warning h2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card stats-card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted fw-normal mt-0 mb-1">Pending Requests</h6>
                                    <h3 class="mt-0 mb-0"><?php echo $pending_requests; ?></h3>
                                </div>
                                <div class="text-end">
                                    <i class="mdi mdi-alert-circle-outline text-danger h2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="card stats-card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="text-muted fw-normal mt-0 mb-1">Total Sales</h6>
                                    <h3 class="mt-0 mb-0">₹<?php echo number_format($total_sales, 2); ?></h3>
                                </div>
                                <div class="text-end">
                                    <i class="mdi mdi-currency-inr text-success h2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /SUMMARY CARDS -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h4 class="card-title mb-0">All Orders</h4>
                                <div class="d-flex gap-2">
                                    <a href="order-add.php" class="btn btn-success btn-sm">
                                        <i class="mdi mdi-plus me-1"></i> Create New Order
                                    </a>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                                        <i class="mdi mdi-printer me-1"></i> Print
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="ordersTable" class="table table-hover table-centered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order No.</th>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Items</th>
                                                <th>Total Amount</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                                <th>Created By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Fetch orders with lineman information
                                            $orders_sql = "SELECT o.*, 
                                                         c.shop_name, 
                                                         c.customer_name,
                                                         c.customer_contact,
                                                         l.full_name as lineman_name,
                                                         l.employee_id,
                                                         COUNT(oi.id) as item_count,
                                                         SUM(oi.total) as order_total
                                                         FROM orders o
                                                         LEFT JOIN customers c ON o.customer_id = c.id
                                                         LEFT JOIN linemen l ON o.created_by = l.id
                                                         LEFT JOIN order_items oi ON o.id = oi.order_id
                                                         GROUP BY o.id
                                                         ORDER BY o.order_date DESC, o.id DESC";
                                            $orders_result = mysqli_query($conn, $orders_sql);
                                            
                                            if (mysqli_num_rows($orders_result) > 0) {
                                                while ($order = mysqli_fetch_assoc($orders_result)) {
                                                    // Get payment status class
                                                    $payment_status_class = '';
                                                    if ($order['payment_status'] == 'paid') {
                                                        $payment_status_class = 'badge-soft-success';
                                                    } elseif ($order['payment_status'] == 'partial') {
                                                        $payment_status_class = 'badge-soft-warning';
                                                    } else {
                                                        $payment_status_class = 'badge-soft-danger';
                                                    }
                                                    
                                                    // Get order status class
                                                    $order_status_class = '';
                                                    if ($order['status'] == 'delivered') {
                                                        $order_status_class = 'badge-soft-success';
                                                    } elseif ($order['status'] == 'processing') {
                                                        $order_status_class = 'badge-soft-primary';
                                                    } elseif ($order['status'] == 'pending') {
                                                        $order_status_class = 'badge-soft-warning';
                                                    } else {
                                                        $order_status_class = 'badge-soft-danger';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <a href="order-view.php?id=<?php echo $order['id']; ?>" class="text-primary fw-bold">
                                                                <?php echo $order['order_number']; ?>
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-bold"><?php echo htmlspecialchars($order['shop_name']); ?></span>
                                                                <small class="text-muted"><?php echo htmlspecialchars($order['customer_name']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-info rounded-pill"><?php echo $order['item_count']; ?> items</span>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold">₹<?php echo number_format($order['order_total'], 2); ?></span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                <select name="order_status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                                                                    <option value="pending" <?php echo ($order['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="processing" <?php echo ($order['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                                                    <option value="delivered" <?php echo ($order['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                                                    <option value="cancelled" <?php echo ($order['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                                </select>
                                                                <input type="hidden" name="update_order_status" value="1">
                                                            </form>
                                                            <span class="badge <?php echo $order_status_class; ?> ms-1">
                                                                <?php echo ucfirst($order['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $payment_status_class; ?>">
                                                                <?php echo ucfirst($order['payment_status']); ?>
                                                            </span>
                                                            <br>
                                                            <small class="text-muted">
                                                                Paid: ₹<?php echo number_format($order['paid_amount'], 2); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <?php if ($order['lineman_name']): ?>
                                                                <div class="d-flex flex-column">
                                                                    <span class="fw-bold"><?php echo htmlspecialchars($order['lineman_name']); ?></span>
                                                                    <small class="text-muted">ID: <?php echo $order['employee_id']; ?></small>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">Admin</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1">
                                                                <a href="order-view.php?id=<?php echo $order['id']; ?>" class="btn btn-info btn-sm" title="View">
                                                                    <i class="mdi mdi-eye"></i>
                                                                </a>
                                                                <a href="order-edit.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                                                    <i class="mdi mdi-pencil"></i>
                                                                </a>
                                                                <a href="order-invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-success btn-sm" title="Invoice" target="_blank">
                                                                    <i class="mdi mdi-receipt"></i>
                                                                </a>
                                                                <a href="?delete_order=<?php echo $order['id']; ?>" 
                                                                   class="btn btn-danger btn-sm" 
                                                                   title="Delete"
                                                                   onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                                                    <i class="mdi mdi-delete"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">
                                                        <div class="py-4">
                                                            <i class="mdi mdi-cart-off display-4 text-muted"></i>
                                                            <h5 class="mt-2">No Orders Found</h5>
                                                            <p class="text-muted">Start by creating your first order</p>
                                                            <a href="order-add.php" class="btn btn-primary">
                                                                <i class="mdi mdi-plus me-1"></i> Create Order
                                                            </a>
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

                <!-- Stock Requests Section -->
                <div class="row mt-4">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h4 class="card-title">Stock Requests</h4>
                                <div class="d-flex gap-2">
                                    <a href="stock-request-add.php" class="btn btn-success btn-sm">
                                        <i class="mdi mdi-plus me-1"></i> New Request
                                    </a>
                                    <a href="stock-transactions.php" class="btn btn-info btn-sm">
                                        <i class="mdi mdi-format-list-bulleted me-1"></i> View All Transactions
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="stockRequestsTable" class="table table-hover table-centered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Request ID</th>
                                                <th>Product</th>
                                                <th>Requested By</th>
                                                <th>Current Stock</th>
                                                <th>Requested Qty</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Fetch stock requests with lineman information
                                            $requests_sql = "SELECT sr.*, 
                                                           p.product_name, 
                                                           p.product_code,
                                                           l.full_name as lineman_name,
                                                           l.employee_id,
                                                           a.full_name as approved_by_name
                                                           FROM stock_requests sr
                                                           LEFT JOIN products p ON sr.product_id = p.id
                                                           LEFT JOIN linemen l ON sr.requested_by = l.id
                                                           LEFT JOIN linemen a ON sr.approved_by = a.id
                                                           ORDER BY 
                                                           CASE sr.priority 
                                                               WHEN 'urgent' THEN 1
                                                               WHEN 'high' THEN 2
                                                               WHEN 'medium' THEN 3
                                                               WHEN 'low' THEN 4
                                                               ELSE 5
                                                           END,
                                                           sr.created_at DESC";
                                            $requests_result = mysqli_query($conn, $requests_sql);
                                            
                                            if (mysqli_num_rows($requests_result) > 0) {
                                                while ($request = mysqli_fetch_assoc($requests_result)) {
                                                    // Get priority badge class
                                                    $priority_class = '';
                                                    if ($request['priority'] == 'urgent') {
                                                        $priority_class = 'badge-soft-danger';
                                                    } elseif ($request['priority'] == 'high') {
                                                        $priority_class = 'badge-soft-warning';
                                                    } elseif ($request['priority'] == 'medium') {
                                                        $priority_class = 'badge-soft-primary';
                                                    } else {
                                                        $priority_class = 'badge-soft-secondary';
                                                    }
                                                    
                                                    // Get status badge class
                                                    $status_class = '';
                                                    if ($request['status'] == 'completed') {
                                                        $status_class = 'badge-soft-success';
                                                    } elseif ($request['status'] == 'approved') {
                                                        $status_class = 'badge-soft-primary';
                                                    } elseif ($request['status'] == 'rejected') {
                                                        $status_class = 'badge-soft-danger';
                                                    } else {
                                                        $status_class = 'badge-soft-warning';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <span class="fw-bold text-primary"><?php echo $request['request_id']; ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-bold"><?php echo htmlspecialchars($request['product_name']); ?></span>
                                                                <small class="text-muted"><?php echo $request['product_code']; ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($request['lineman_name']): ?>
                                                                <div class="d-flex flex-column">
                                                                    <span class="fw-bold"><?php echo htmlspecialchars($request['lineman_name']); ?></span>
                                                                    <small class="text-muted">ID: <?php echo $request['employee_id']; ?></small>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo ($request['current_qty'] < 20) ? 'bg-warning' : 'bg-success'; ?>">
                                                                <?php echo $request['current_qty']; ?> units
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold"><?php echo $request['requested_qty']; ?> units</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $priority_class; ?>">
                                                                <?php echo ucfirst($request['priority']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                <select name="stock_request_status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                                                                    <option value="pending" <?php echo ($request['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="approved" <?php echo ($request['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="rejected" <?php echo ($request['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                                    <option value="completed" <?php echo ($request['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                                </select>
                                                                <input type="hidden" name="update_stock_request_status" value="1">
                                                            </form>
                                                            <span class="badge <?php echo $status_class; ?> ms-1">
                                                                <?php echo ucfirst($request['status']); ?>
                                                            </span>
                                                            <?php if ($request['approved_by_name']): ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    By: <?php echo htmlspecialchars($request['approved_by_name']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d M, Y', strtotime($request['created_at'])); ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php echo date('h:i A', strtotime($request['created_at'])); ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1">
                                                                <button type="button" class="btn btn-info btn-sm" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#viewRequestModal<?php echo $request['id']; ?>"
                                                                        title="View Details">
                                                                    <i class="mdi mdi-eye"></i>
                                                                </button>
                                                                <a href="stock-request-edit.php?id=<?php echo $request['id']; ?>" 
                                                                   class="btn btn-primary btn-sm" title="Edit">
                                                                    <i class="mdi mdi-pencil"></i>
                                                                </a>
                                                                <a href="?delete_stock_request=<?php echo $request['id']; ?>" 
                                                                   class="btn btn-danger btn-sm" 
                                                                   title="Delete"
                                                                   onclick="return confirm('Are you sure you want to delete this stock request?');">
                                                                    <i class="mdi mdi-delete"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    
                                                    <!-- View Request Modal -->
                                                    <div class="modal fade" id="viewRequestModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Stock Request Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <table class="table table-borderless">
                                                                                <tr>
                                                                                    <th width="40%">Request ID:</th>
                                                                                    <td><?php echo $request['request_id']; ?></td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th>Product:</th>
                                                                                    <td>
                                                                                        <strong><?php echo htmlspecialchars($request['product_name']); ?></strong>
                                                                                        <br>
                                                                                        <small class="text-muted">Code: <?php echo $request['product_code']; ?></small>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th>Current Stock:</th>
                                                                                    <td>
                                                                                        <span class="badge <?php echo ($request['current_qty'] < 20) ? 'bg-warning' : 'bg-success'; ?>">
                                                                                            <?php echo $request['current_qty']; ?> units
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th>Requested Quantity:</th>
                                                                                    <td>
                                                                                        <span class="fw-bold"><?php echo $request['requested_qty']; ?> units</span>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <table class="table table-borderless">
                                                                                <tr>
                                                                                    <th width="40%">Requested By:</th>
                                                                                    <td>
                                                                                        <?php if ($request['lineman_name']): ?>
                                                                                            <strong><?php echo htmlspecialchars($request['lineman_name']); ?></strong>
                                                                                            <br>
                                                                                            <small class="text-muted">Employee ID: <?php echo $request['employee_id']; ?></small>
                                                                                        <?php else: ?>
                                                                                            <span class="text-muted">Not specified</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th>Priority:</th>
                                                                                    <td>
                                                                                        <span class="badge <?php echo $priority_class; ?>">
                                                                                            <?php echo ucfirst($request['priority']); ?>
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th>Status:</th>
                                                                                    <td>
                                                                                        <span class="badge <?php echo $status_class; ?>">
                                                                                            <?php echo ucfirst($request['status']); ?>
                                                                                        </span>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <th>Created Date:</th>
                                                                                    <td>
                                                                                        <?php echo date('d M, Y h:i A', strtotime($request['created_at'])); ?>
                                                                                    </td>
                                                                                </tr>
                                                                                <?php if ($request['approved_at']): ?>
                                                                                <tr>
                                                                                    <th>Approved Date:</th>
                                                                                    <td>
                                                                                        <?php echo date('d M, Y h:i A', strtotime($request['approved_at'])); ?>
                                                                                    </td>
                                                                                </tr>
                                                                                <?php endif; ?>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                    <?php if ($request['notes']): ?>
                                                                    <div class="row mt-3">
                                                                        <div class="col-md-12">
                                                                            <h6 class="mb-2">Notes:</h6>
                                                                            <div class="border rounded p-3">
                                                                                <?php echo nl2br(htmlspecialchars($request['notes'])); ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="modal-footer">

                                                                    <?php if ($request['status'] == 'pending'): ?>
                                                                    <a href="stock-request-edit.php?id=<?php echo $request['id']; ?>" class="btn btn-primary">
                                                                        <i class="mdi mdi-pencil me-1"></i> Edit Request
                                                                    </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="9" class="text-center">
                                                        <div class="py-4">
                                                            <i class="mdi mdi-package-variant-closed display-4 text-muted"></i>
                                                            <h5 class="mt-2">No Stock Requests Found</h5>
                                                            <p class="text-muted">No stock requests have been made yet</p>
                                                            <a href="stock-request-add.php" class="btn btn-primary">
                                                                <i class="mdi mdi-plus me-1"></i> Create Request
                                                            </a>
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

                <!-- Keep any additional summary/cards here if required later -->

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include('includes/footer.php')?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<!-- Inline CSS tweaks specific to this page to improve alignment & responsive cards -->
<style>
    /* Ensure stat cards have consistent height and spacing */
    .stats-card .card-body {
        min-height: 78px;
        padding: 16px;
    }

    /* Make sure icons align vertically with text on small screens */
    .stats-card .h2 {
        line-height: 1;
    }

    /* Reduce table cell padding on small screens for better fit */
    @media (max-width: 575.98px) {
        .table td, .table th {
            padding: .4rem .5rem;
        }
        .stats-card .card-body {
            padding: 12px;
        }
    }
</style>

<script>
    // Initialize DataTables if needed
    $(document).ready(function() {
        // Initialize orders table
        $('#ordersTable').DataTable({
            "order": [[2, "desc"]],
            "pageLength": 10,
            "language": {
                "paginate": {
                    "previous": "<i class='mdi mdi-chevron-left'>",
                    "next": "<i class='mdi mdi-chevron-right'>"
                }
            },
            "drawCallback": function () {
                $('.dataTables_paginate > .pagination').addClass('pagination-rounded');
            },
            "columnDefs": [
                { "orderable": false, "targets": [8] } // disable ordering on Actions column
            ]
        });

        // Initialize stock requests table
        $('#stockRequestsTable').DataTable({
            "order": [[7, "desc"]],
            "pageLength": 10,
            "language": {
                "paginate": {
                    "previous": "<i class='mdi mdi-chevron-left'>",
                    "next": "<i class='mdi mdi-chevron-right'>"
                }
            },
            "drawCallback": function () {
                $('.dataTables_paginate > .pagination').addClass('pagination-rounded');
            },
            "columnDefs": [
                { "orderable": false, "targets": [8] } // disable ordering on Actions column
            ]
        });

        // Auto-refresh page every 60 seconds to get latest data
        setInterval(function() {
            // Only refresh if user is not actively editing
            if (!$('input:focus, select:focus, textarea:focus').length) {
                location.reload();
            }
        }, 60000); // 60 seconds

        // Print function
        function printPage() {
            window.print();
        }

        // Quick actions dropdown
        $(document).on('click', '.quick-actions', function(e) {
            e.stopPropagation();
            var dropdown = $(this).next('.dropdown-menu');
            $('.dropdown-menu').not(dropdown).removeClass('show');
            dropdown.toggleClass('show');
        });

        // Close dropdowns when clicking outside
        $(document).click(function() {
            $('.dropdown-menu').removeClass('show');
        });
    });

    // Confirm before deleting
    function confirmDelete(action, id, type) {
        if (confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
            window.location.href = action;
        }
        return false;
    }

    // Update order status with AJAX (optional enhancement)
    function updateOrderStatus(orderId, status) {
        $.ajax({
            url: 'ajax/update_order_status.php',
            method: 'POST',
            data: {
                order_id: orderId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error updating status: ' + response.message);
                }
            },
            error: function() {
                alert('Error updating status. Please try again.');
            }
        });
    }

    // Search functionality
    function searchOrders() {
        var input = document.getElementById('searchOrders');
        var filter = input.value.toUpperCase();
        var table = document.getElementById("ordersTable");
        var tr = table.getElementsByTagName("tr");
        
        for (var i = 0; i < tr.length; i++) {
            var td = tr[i].getElementsByTagName("td");
            var showRow = false;
            
            for (var j = 0; j < td.length; j++) {
                if (td[j]) {
                    if (td[j].innerHTML.toUpperCase().indexOf(filter) > -1) {
                        showRow = true;
                        break;
                    }
                }
            }
            
            if (showRow) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    // Filter by status
    function filterByStatus(status) {
        $('.status-filter-btn').removeClass('active');
        $(`#filter-${status}`).addClass('active');
        
        if (status === 'all') {
            $('tbody tr').show();
        } else {
            $('tbody tr').each(function() {
                var rowStatus = $(this).find('select[name="order_status"]').val();
                if (rowStatus === status) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    }

    // Export to CSV
    function exportToCSV() {
        // This is a simplified version. You might want to implement server-side CSV export
        var table = document.getElementById("ordersTable");
        var rows = table.querySelectorAll("tr");
        var csv = [];
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll("td, th");
            
            for (var j = 0; j < cols.length - 1; j++) { // Exclude actions column
                row.push(cols[j].innerText.replace(/,/g, ""));
            }
            
            csv.push(row.join(","));        
        }
        
        // Download CSV file
        var csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
        var downloadLink = document.createElement("a");
        downloadLink.download = "orders_" + new Date().toISOString().split('T')[0] + ".csv";
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
    }
</script>

</body>

</html>
