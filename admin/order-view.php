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
                $order = null;
                $customer = null;
                $order_items = [];
                $lineman = null;
                $message = '';
                $message_type = '';
                
                // Get the ID from URL
                $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                
                if ($id <= 0) {
                    echo '<div class="alert alert-danger">Invalid Order ID</div>';
                    exit;
                }
                
                // Fetch order data with customer and lineman information
                $sql = "SELECT o.*, 
                       c.*,
                       l.full_name as lineman_name,
                       l.employee_id as lineman_id,
                       l.phone as lineman_phone,
                       l.email as lineman_email,
                       COUNT(oi.id) as total_items,
                       SUM(oi.total) as order_total
                       FROM orders o
                       LEFT JOIN customers c ON o.customer_id = c.id
                       LEFT JOIN linemen l ON o.created_by = l.id
                       LEFT JOIN order_items oi ON o.id = oi.order_id
                       WHERE o.id = ?
                       GROUP BY o.id";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $order = mysqli_fetch_assoc($result);
                    
                    // Fetch order items
                    $items_sql = "SELECT oi.*, 
                                p.product_name, 
                                p.product_code,
                                p.stock_price,
                                p.customer_price
                                FROM order_items oi
                                LEFT JOIN products p ON oi.product_id = p.id
                                WHERE oi.order_id = ?";
                    $items_stmt = mysqli_prepare($conn, $items_sql);
                    mysqli_stmt_bind_param($items_stmt, "i", $id);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    
                    // Fetch transaction history for this order
                    $transactions_sql = "SELECT * FROM transactions 
                                        WHERE order_id = ? 
                                        ORDER BY created_at DESC";
                    $trans_stmt = mysqli_prepare($conn, $transactions_sql);
                    mysqli_stmt_bind_param($trans_stmt, "i", $id);
                    mysqli_stmt_execute($trans_stmt);
                    $transactions_result = mysqli_stmt_get_result($trans_stmt);
                    
                } else {
                    echo '<div class="alert alert-danger">Order not found</div>';
                    exit;
                }
                
                // Handle status update
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
                    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
                    $notes = mysqli_real_escape_string($conn, $_POST['status_notes']);
                    
                    $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $id);
                    
                    if (mysqli_stmt_execute($update_stmt)) {
                        // Update stock if order is cancelled
                        if ($new_status == 'cancelled' && $order['status'] != 'cancelled') {
                            foreach ($order_items as $item) {
                                $restore_stock_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                                $restore_stmt = mysqli_prepare($conn, $restore_stock_sql);
                                mysqli_stmt_bind_param($restore_stmt, "ii", $item['quantity'], $item['product_id']);
                                mysqli_stmt_execute($restore_stmt);
                            }
                        }
                        
                        $message = 'Order status updated successfully!';
                        $message_type = 'success';
                        
                        // Refresh order data
                        $result = mysqli_stmt_get_result($stmt);
                        $order = mysqli_fetch_assoc($result);
                        
                    } else {
                        $message = 'Error updating order status: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
                
                // Handle payment
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
                    $amount = floatval($_POST['payment_amount']);
                    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
                    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
                    $notes = mysqli_real_escape_string($conn, $_POST['payment_notes']);
                    
                    if ($amount <= 0) {
                        $message = 'Please enter a valid payment amount';
                        $message_type = 'danger';
                    } else {
                        // Generate payment ID
                        $payment_id = 'PAY' . date('Ymd') . rand(100, 999);
                        
                        // Insert transaction
                        $transaction_sql = "INSERT INTO transactions 
                                          (customer_id, order_id, payment_id, type, amount, 
                                           payment_method, reference_no, notes, created_by) 
                                          VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?)";
                        $trans_stmt = mysqli_prepare($conn, $transaction_sql);
                        $created_by = $_SESSION['admin_id'] ?? 1;
                        mysqli_stmt_bind_param($trans_stmt, "iisssssi", 
                            $order['customer_id'], $id, $payment_id, $amount, 
                            $payment_method, $reference_no, $notes, $created_by);
                        
                        if (mysqli_stmt_execute($trans_stmt)) {
                            // Update order payment status
                            $paid_amount = $order['paid_amount'] + $amount;
                            $pending_amount = $order['total_amount'] - $paid_amount;
                            
                            if ($pending_amount <= 0) {
                                $payment_status = 'paid';
                            } elseif ($paid_amount > 0) {
                                $payment_status = 'partial';
                            } else {
                                $payment_status = 'pending';
                            }
                            
                            $update_order_sql = "UPDATE orders SET 
                                                paid_amount = ?,
                                                pending_amount = ?,
                                                payment_status = ?
                                                WHERE id = ?";
                            $update_order_stmt = mysqli_prepare($conn, $update_order_sql);
                            mysqli_stmt_bind_param($update_order_stmt, "ddsi", 
                                $paid_amount, $pending_amount, $payment_status, $id);
                            mysqli_stmt_execute($update_order_stmt);
                            
                            // Update customer balance
                            $update_customer_sql = "UPDATE customers SET 
                                                   current_balance = current_balance - ? 
                                                   WHERE id = ?";
                            $update_customer_stmt = mysqli_prepare($conn, $update_customer_sql);
                            mysqli_stmt_bind_param($update_customer_stmt, "di", $amount, $order['customer_id']);
                            mysqli_stmt_execute($update_customer_stmt);
                            
                            $message = 'Payment recorded successfully!';
                            $message_type = 'success';
                            
                            // Refresh order data
                            $result = mysqli_stmt_get_result($stmt);
                            $order = mysqli_fetch_assoc($result);
                            
                        } else {
                            $message = 'Error recording payment: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                }
                
                // Handle delete
                if (isset($_GET['delete_item'])) {
                    $item_id = intval($_GET['delete_item']);
                    
                    // Get item details
                    $item_sql = "SELECT * FROM order_items WHERE id = ?";
                    $item_stmt = mysqli_prepare($conn, $item_sql);
                    mysqli_stmt_bind_param($item_stmt, "i", $item_id);
                    mysqli_stmt_execute($item_stmt);
                    $item_result = mysqli_stmt_get_result($item_stmt);
                    $item = mysqli_fetch_assoc($item_result);
                    
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Restore stock
                        $restore_stock_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                        $restore_stmt = mysqli_prepare($conn, $restore_stock_sql);
                        mysqli_stmt_bind_param($restore_stmt, "ii", $item['quantity'], $item['product_id']);
                        mysqli_stmt_execute($restore_stmt);
                        
                        // Delete order item
                        $delete_sql = "DELETE FROM order_items WHERE id = ?";
                        $delete_stmt = mysqli_prepare($conn, $delete_sql);
                        mysqli_stmt_bind_param($delete_stmt, "i", $item_id);
                        mysqli_stmt_execute($delete_stmt);
                        
                        // Update order total
                        $update_order_sql = "UPDATE orders SET 
                                            total_amount = total_amount - ?,
                                            total_items = total_items - 1
                                            WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_order_sql);
                        mysqli_stmt_bind_param($update_stmt, "di", $item['total'], $id);
                        mysqli_stmt_execute($update_stmt);
                        
                        // Commit transaction
                        mysqli_commit($conn);
                        
                        $message = 'Item removed from order successfully!';
                        $message_type = 'success';
                        
                        // Refresh data
                        header("Location: order-view.php?id=$id");
                        exit;
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $message = 'Error removing item: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }
                
                // Close connection
                mysqli_close($conn);
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h4 class="card-title mb-0">Order #<?php echo $order['order_number']; ?></h4>
                                    <div class="d-flex gap-2">
                                        <a href="order-invoice.php?id=<?php echo $id; ?>" 
                                           class="btn btn-success btn-sm" target="_blank">
                                            <i class="mdi mdi-receipt me-1"></i> View Invoice
                                        </a>
                                        <a href="order-edit.php?id=<?php echo $id; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="mdi mdi-pencil me-1"></i> Edit Order
                                        </a>
                                        <a href="orders-list.php" 
                                           class="btn btn-light btn-sm">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Order Summary -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Order Information</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <tr>
                                                        <th width="40%">Order Number:</th>
                                                        <td><span class="fw-bold"><?php echo $order['order_number']; ?></span></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Order Date:</th>
                                                        <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Order Status:</th>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            switch ($order['status']) {
                                                                case 'pending':
                                                                    $status_class = 'badge-soft-warning';
                                                                    break;
                                                                case 'processing':
                                                                    $status_class = 'badge-soft-primary';
                                                                    break;
                                                                case 'delivered':
                                                                    $status_class = 'badge-soft-success';
                                                                    break;
                                                                case 'cancelled':
                                                                    $status_class = 'badge-soft-danger';
                                                                    break;
                                                                default:
                                                                    $status_class = 'badge-soft-secondary';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($order['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Total Items:</th>
                                                        <td><span class="fw-bold"><?php echo $order['total_items']; ?> items</span></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Delivery Date:</th>
                                                        <td>
                                                            <?php 
                                                            echo $order['delivery_date'] 
                                                                ? date('d M, Y', strtotime($order['delivery_date'])) 
                                                                : '<span class="text-muted">Not scheduled</span>';
                                                            ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Customer Information</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <tr>
                                                        <th width="40%">Customer:</th>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                                <small class="text-muted"><?php echo htmlspecialchars($order['shop_name']); ?></small>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Contact:</th>
                                                        <td><?php echo $order['customer_contact']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Type:</th>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo ucfirst($order['customer_type']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Location:</th>
                                                        <td>
                                                            <small class="text-muted"><?php echo htmlspecialchars($order['shop_location']); ?></small>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Payment Terms:</th>
                                                        <td>
                                                            <span class="badge bg-secondary">
                                                                <?php echo str_replace('_', ' ', ucfirst($order['payment_terms'])); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Order Created By</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <?php if ($order['lineman_name']): ?>
                                                    <tr>
                                                        <th width="40%">Lineman:</th>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-bold"><?php echo htmlspecialchars($order['lineman_name']); ?></span>
                                                                <small class="text-muted">ID: <?php echo $order['lineman_id']; ?></small>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Contact:</th>
                                                        <td><?php echo $order['lineman_phone']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Email:</th>
                                                        <td>
                                                            <small class="text-muted"><?php echo $order['lineman_email']; ?></small>
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted">
                                                            <i class="mdi mdi-account-off h2"></i>
                                                            <p class="mt-2">Created by Admin</p>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr>
                                                        <th>Payment Status:</th>
                                                        <td>
                                                            <?php
                                                            $payment_status_class = '';
                                                            if ($order['payment_status'] == 'paid') {
                                                                $payment_status_class = 'badge-soft-success';
                                                            } elseif ($order['payment_status'] == 'partial') {
                                                                $payment_status_class = 'badge-soft-warning';
                                                            } else {
                                                                $payment_status_class = 'badge-soft-danger';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $payment_status_class; ?>">
                                                                <?php echo ucfirst($order['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order Items -->
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <h5 class="card-title mb-0">Order Items</h5>
                                                    <span class="badge bg-primary">
                                                        <?php echo count($order_items); ?> Items
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-centered mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Product</th>
                                                                <th>Product Code</th>
                                                                <th>Stock Price</th>
                                                                <th>Customer Price</th>
                                                                <th>Quantity</th>
                                                                <th>Total</th>
                                                                <th>Profit</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $subtotal = 0;
                                                            $total_profit = 0;
                                                            
                                                            if (count($order_items) > 0) {
                                                                $counter = 1;
                                                                foreach ($order_items as $item) {
                                                                    $profit = ($item['customer_price'] - $item['stock_price']) * $item['quantity'];
                                                                    $subtotal += $item['total'];
                                                                    $total_profit += $profit;
                                                                    ?>
                                                                    <tr>
                                                                        <td><?php echo $counter++; ?></td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <span class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                                                <small class="text-muted">ID: <?php echo $item['product_id']; ?></small>
                                                                            </div>
                                                                        </td>
                                                                        <td><?php echo $item['product_code']; ?></td>
                                                                        <td>₹<?php echo number_format($item['stock_price'], 2); ?></td>
                                                                        <td>₹<?php echo number_format($item['customer_price'], 2); ?></td>
                                                                        <td>
                                                                            <span class="badge bg-primary rounded-pill">
                                                                                <?php echo $item['quantity']; ?> units
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <span class="fw-bold">₹<?php echo number_format($item['total'], 2); ?></span>
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge bg-success">
                                                                                ₹<?php echo number_format($profit, 2); ?>
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex gap-1">
                                                                                <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                                                                <a href="?id=<?php echo $id; ?>&delete_item=<?php echo $item['id']; ?>" 
                                                                                   class="btn btn-danger btn-sm"
                                                                                   onclick="return confirm('Are you sure you want to remove this item from the order?');">
                                                                                    <i class="mdi mdi-delete"></i>
                                                                                </a>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                    <?php
                                                                }
                                                            } else {
                                                                ?>
                                                                <tr>
                                                                    <td colspan="9" class="text-center py-4">
                                                                        <i class="mdi mdi-cart-off display-4 text-muted"></i>
                                                                        <h5 class="mt-2">No items in this order</h5>
                                                                    </td>
                                                                </tr>
                                                                <?php
                                                            }
                                                            ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr>
                                                                <td colspan="5" class="text-end fw-bold">Subtotal:</td>
                                                                <td colspan="4" class="fw-bold">₹<?php echo number_format($subtotal, 2); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td colspan="5" class="text-end fw-bold">Total Profit:</td>
                                                                <td colspan="4" class="fw-bold text-success">
                                                                    ₹<?php echo number_format($total_profit, 2); ?>
                                                                </td>
                                                            </tr>
                                                            <?php if ($order['total_amount'] != $subtotal): ?>
                                                            <tr>
                                                                <td colspan="5" class="text-end fw-bold">Order Total (Database):</td>
                                                                <td colspan="4" class="fw-bold text-primary">
                                                                    ₹<?php echo number_format($order['total_amount'], 2); ?>
                                                                </td>
                                                            </tr>
                                                            <?php endif; ?>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Information -->
                                <div class="row mt-4">
                                    <div class="col-lg-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Payment Summary</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Order Total</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="text" class="form-control fw-bold" 
                                                                       value="<?php echo number_format($order['total_amount'], 2); ?>" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Paid Amount</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="text" class="form-control fw-bold text-success" 
                                                                       value="<?php echo number_format($order['paid_amount'], 2); ?>" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Pending Amount</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="text" class="form-control fw-bold text-danger" 
                                                                       value="<?php echo number_format($order['pending_amount'], 2); ?>" readonly>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment Status</label>
                                                            <div>
                                                                <?php
                                                                $payment_status_class = '';
                                                                if ($order['payment_status'] == 'paid') {
                                                                    $payment_status_class = 'badge-soft-success';
                                                                } elseif ($order['payment_status'] == 'partial') {
                                                                    $payment_status_class = 'badge-soft-warning';
                                                                } else {
                                                                    $payment_status_class = 'badge-soft-danger';
                                                                }
                                                                ?>
                                                                <span class="badge <?php echo $payment_status_class; ?> fs-6">
                                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment Method</label>
                                                            <div>
                                                                <span class="badge bg-secondary">
                                                                    <?php echo ucfirst($order['payment_method']); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Add Payment</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="order-view.php?id=<?php echo $id; ?>">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text">₹</span>
                                                                    <input type="number" class="form-control" name="payment_amount" 
                                                                           step="0.01" min="0.01" max="<?php echo $order['pending_amount']; ?>"
                                                                           value="<?php echo min($order['pending_amount'], 0); ?>"
                                                                           required>
                                                                </div>
                                                                <small class="text-muted">
                                                                    Max: ₹<?php echo number_format($order['pending_amount'], 2); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                                                <select class="form-select" name="payment_method" required>
                                                                    <option value="cash" <?php echo ($order['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                                                    <option value="bank_transfer">Bank Transfer</option>
                                                                    <option value="cheque">Cheque</option>
                                                                    <option value="upi">UPI</option>
                                                                    <option value="credit_card">Credit Card</option>
                                                                    <option value="debit_card">Debit Card</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Reference No.</label>
                                                                <input type="text" class="form-control" name="reference_no" 
                                                                       placeholder="e.g., cheque no, transaction id">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Notes</label>
                                                                <textarea class="form-control" name="payment_notes" rows="1"></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3">
                                                        <button type="submit" name="add_payment" value="1" 
                                                                class="btn btn-success w-100">
                                                            <i class="mdi mdi-cash-plus me-1"></i> Record Payment
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order Notes and Actions -->
                                <div class="row mt-4">
                                    <div class="col-lg-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Order Notes</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($order['notes']): ?>
                                                <div class="border rounded p-3 bg-light">
                                                    <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                                                </div>
                                                <?php else: ?>
                                                <p class="text-muted mb-0">No notes added for this order.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Update Order Status</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="order-view.php?id=<?php echo $id; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select" name="status" required>
                                                            <option value="pending" <?php echo ($order['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="processing" <?php echo ($order['status'] == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                                            <option value="delivered" <?php echo ($order['status'] == 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                                            <option value="cancelled" <?php echo ($order['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Status Notes</label>
                                                        <textarea class="form-control" name="status_notes" rows="2" 
                                                                  placeholder="Add notes about status change..."></textarea>
                                                    </div>
                                                    <div class="mt-3">
                                                        <button type="submit" name="update_status" value="1" 
                                                                class="btn btn-primary w-100">
                                                            <i class="mdi mdi-update me-1"></i> Update Status
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="row mt-4">
                                    <div class="col-lg-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                                                    <i class="mdi mdi-printer me-1"></i> Print Order
                                                </button>
                                                <button type="button" class="btn btn-outline-info ms-2" 
                                                        data-bs-toggle="modal" data-bs-target="#sendEmailModal">
                                                    <i class="mdi mdi-email me-1"></i> Email Invoice
                                                </button>
                                            </div>
                                            <div>
                                                <?php if ($order['status'] == 'pending' || $order['status'] == 'processing'): ?>
                                                <a href="order-edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
                                                    <i class="mdi mdi-pencil me-1"></i> Edit Order
                                                </a>
                                                <?php endif; ?>
                                                <a href="orders-list.php" class="btn btn-light ms-2">
                                                    <i class="mdi mdi-arrow-left me-1"></i> Back to Orders
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Send Email Modal -->
                <div class="modal fade" id="sendEmailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Send Invoice via Email</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="emailForm">
                                    <div class="mb-3">
                                        <label class="form-label">Customer Email</label>
                                        <input type="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($order['email'] ?? ''); ?>" 
                                               required>
                                        <small class="text-muted">The invoice will be sent to this email address</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Additional Message (Optional)</label>
                                        <textarea class="form-control" rows="3" 
                                                  placeholder="Add a personal message to the email..."></textarea>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="sendInvoiceEmail()">
                                    <i class="mdi mdi-send me-1"></i> Send Invoice
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

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

<script>
// Function to send invoice email
function sendInvoiceEmail() {
    var email = document.querySelector('#emailForm input[type="email"]').value;
    var message = document.querySelector('#emailForm textarea').value;
    
    if (!email) {
        alert('Please enter customer email address');
        return;
    }
    
    // Show loading
    var sendBtn = document.querySelector('#sendEmailModal .btn-primary');
    var originalText = sendBtn.innerHTML;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
    sendBtn.disabled = true;
    
    // AJAX request to send email
    $.ajax({
        url: 'ajax/send_invoice_email.php',
        method: 'POST',
        data: {
            order_id: <?php echo $id; ?>,
            email: email,
            message: message
        },
        success: function(response) {
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
            
            if (response.success) {
                alert('Invoice sent successfully!');
                $('#sendEmailModal').modal('hide');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
            alert('Error sending email. Please try again.');
        }
    });
}

// Print function
function printOrder() {
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Order #<?php echo $order['order_number']; ?></title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; }');
    printWindow.document.write('.print-header { text-align: center; margin-bottom: 20px; }');
    printWindow.document.write('.print-section { margin-bottom: 20px; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
    printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    printWindow.document.write('th { background-color: #f5f5f5; }');
    printWindow.document.write('@media print { .no-print { display: none; } }');
    printWindow.document.write('</style></head><body>');
    
    // Add print content
    printWindow.document.write('<div class="print-header">');
    printWindow.document.write('<h2>Order Details</h2>');
    printWindow.document.write('<p>Order #<?php echo $order['order_number']; ?></p>');
    printWindow.document.write('</div>');
    
    // Add order information
    printWindow.document.write('<div class="print-section">');
    printWindow.document.write('<h3>Order Information</h3>');
    printWindow.document.write('<p><strong>Order Date:</strong> <?php echo date('d M, Y', strtotime($order['order_date'])); ?></p>');
    printWindow.document.write('<p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>');
    printWindow.document.write('<p><strong>Payment Status:</strong> <?php echo ucfirst($order['payment_status']); ?></p>');
    printWindow.document.write('</div>');
    
    // Add customer information
    printWindow.document.write('<div class="print-section">');
    printWindow.document.write('<h3>Customer Information</h3>');
    printWindow.document.write('<p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>');
    printWindow.document.write('<p><strong>Shop:</strong> <?php echo htmlspecialchars($order['shop_name']); ?></p>');
    printWindow.document.write('<p><strong>Contact:</strong> <?php echo $order['customer_contact']; ?></p>');
    printWindow.document.write('</div>');
    
    // Add order items
    printWindow.document.write('<div class="print-section">');
    printWindow.document.write('<h3>Order Items</h3>');
    printWindow.document.write('<table>');
    printWindow.document.write('<thead><tr>');
    printWindow.document.write('<th>Product</th>');
    printWindow.document.write('<th>Quantity</th>');
    printWindow.document.write('<th>Price</th>');
    printWindow.document.write('<th>Total</th>');
    printWindow.document.write('</tr></thead><tbody>');
    
    <?php foreach ($order_items as $item): ?>
    printWindow.document.write('<tr>');
    printWindow.document.write('<td><?php echo htmlspecialchars($item['product_name']); ?></td>');
    printWindow.document.write('<td><?php echo $item['quantity']; ?></td>');
    printWindow.document.write('<td>₹<?php echo number_format($item['price'], 2); ?></td>');
    printWindow.document.write('<td>₹<?php echo number_format($item['total'], 2); ?></td>');
    printWindow.document.write('</tr>');
    <?php endforeach; ?>
    
    printWindow.document.write('</tbody><tfoot>');
    printWindow.document.write('<tr><td colspan="3" style="text-align: right;"><strong>Total:</strong></td>');
    printWindow.document.write('<td><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></td></tr>');
    printWindow.document.write('<tr><td colspan="3" style="text-align: right;"><strong>Paid:</strong></td>');
    printWindow.document.write('<td><strong>₹<?php echo number_format($order['paid_amount'], 2); ?></strong></td></tr>');
    printWindow.document.write('<tr><td colspan="3" style="text-align: right;"><strong>Pending:</strong></td>');
    printWindow.document.write('<td><strong>₹<?php echo number_format($order['pending_amount'], 2); ?></strong></td></tr>');
    printWindow.document.write('</tfoot></table>');
    printWindow.document.write('</div>');
    
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Confirm before removing item
function confirmRemoveItem(itemId, productName) {
    if (confirm('Are you sure you want to remove "' + productName + '" from this order?')) {
        window.location.href = '?id=<?php echo $id; ?>&delete_item=' + itemId;
    }
}

// Auto-calculate payment amount
document.querySelector('input[name="payment_amount"]').addEventListener('input', function(e) {
    var maxAmount = parseFloat('<?php echo $order['pending_amount']; ?>');
    var enteredAmount = parseFloat(e.target.value);
    
    if (enteredAmount > maxAmount) {
        e.target.value = maxAmount;
        alert('Payment amount cannot exceed pending amount of ₹' + maxAmount.toFixed(2));
    }
});

// Status update confirmation
document.querySelector('form[name="update_status"]').addEventListener('submit', function(e) {
    var newStatus = document.querySelector('select[name="status"]').value;
    var oldStatus = '<?php echo $order['status']; ?>';
    
    if (newStatus == 'cancelled' && oldStatus != 'cancelled') {
        if (!confirm('Are you sure you want to cancel this order? Stock will be restored.')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + P to print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
    
    // Esc to go back
    if (e.key === 'Escape') {
        window.location.href = 'orders-list.php';
    }
});

// Update page title with order number
document.title = 'Order #<?php echo $order['order_number']; ?> - APR Water Agencies';
</script>

</body>

</html>