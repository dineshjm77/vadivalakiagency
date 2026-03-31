<?php
session_start();
include('config/config.php');

if (!function_exists('ov_money')) {
    function ov_money($amount) { return '₹' . number_format((float)$amount, 2); }
}

function fetchOrderDetails(mysqli $conn, int $id): ?array {
    $sql = "SELECT o.*, 
                   c.*,
                   l.full_name as lineman_name,
                   l.employee_id as lineman_id,
                   l.phone as lineman_phone,
                   l.email as lineman_email,
                   COUNT(oi.id) as total_items_calc,
                   COALESCE(SUM(oi.total), 0) as order_total_calc
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
    $order = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    if ($result) mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $order ?: null;
}

function fetchOrderItems(mysqli $conn, int $id): array {
    $items = [];
    $sql = "SELECT oi.*, 
                   p.product_name, 
                   p.product_code,
                   p.stock_price,
                   p.customer_price
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    if ($result) mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $items;
}

function fetchTransactions(mysqli $conn, int $id): array {
    $rows = [];
    $sql = "SELECT * FROM transactions WHERE order_id = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && $row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    if ($result) mysqli_free_result($result);
    mysqli_stmt_close($stmt);
    return $rows;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('Invalid Order ID');
}

/* -------------------------
   HANDLE ACTIONS BEFORE HTML
------------------------- */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = trim((string)($_POST['status'] ?? ''));
    $allowed = ['pending','processing','delivered','cancelled'];
    if (!in_array($new_status, $allowed, true)) {
        header("Location: order-view.php?id=$id&msg=invalid_status");
        exit;
    }

    $currentOrder = fetchOrderDetails($conn, $id);
    if (!$currentOrder) {
        header("Location: order-view.php?id=$id&msg=not_found");
        exit;
    }

    $update_sql = "UPDATE orders SET status = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $id);
    $ok = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($ok) {
        if ($new_status === 'cancelled' && $currentOrder['status'] !== 'cancelled') {
            $orderItemsForRestore = fetchOrderItems($conn, $id);
            foreach ($orderItemsForRestore as $item) {
                $restore_stock_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                $restore_stmt = mysqli_prepare($conn, $restore_stock_sql);
                mysqli_stmt_bind_param($restore_stmt, "ii", $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($restore_stmt);
                mysqli_stmt_close($restore_stmt);
            }
        }
        header("Location: order-view.php?id=$id&msg=status_updated");
        exit;
    } else {
        header("Location: order-view.php?id=$id&msg=status_error");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $currentOrder = fetchOrderDetails($conn, $id);
    if (!$currentOrder) {
        header("Location: order-view.php?id=$id&msg=not_found");
        exit;
    }

    $amount = round((float)($_POST['payment_amount'] ?? 0), 2);
    $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));
    $reference_no = trim((string)($_POST['reference_no'] ?? ''));
    $notes = trim((string)($_POST['payment_notes'] ?? ''));

    if ($amount <= 0) {
        header("Location: order-view.php?id=$id&msg=invalid_payment");
        exit;
    }
    if ($amount > (float)$currentOrder['pending_amount']) {
        header("Location: order-view.php?id=$id&msg=payment_exceeds");
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $payment_id = 'PAY' . date('Ymd') . rand(100, 999);

        $transaction_sql = "INSERT INTO transactions 
                          (customer_id, order_id, payment_id, type, amount, 
                           payment_method, reference_no, notes, created_by, created_at) 
                          VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
        $trans_stmt = mysqli_prepare($conn, $transaction_sql);
        $created_by = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 1;
        mysqli_stmt_bind_param($trans_stmt, "iisdsssi",
            $currentOrder['customer_id'], $id, $payment_id, $amount,
            $payment_method, $reference_no, $notes, $created_by
        );
        if (!mysqli_stmt_execute($trans_stmt)) {
            throw new Exception('Failed to insert transaction');
        }
        mysqli_stmt_close($trans_stmt);

        $paid_amount = round((float)$currentOrder['paid_amount'] + $amount, 2);
        $pending_amount = round((float)$currentOrder['total_amount'] - $paid_amount, 2);
        if ($pending_amount < 0) $pending_amount = 0;

        if ($pending_amount <= 0) {
            $payment_status = 'paid';
        } elseif ($paid_amount > 0) {
            $payment_status = 'partial';
        } else {
            $payment_status = 'pending';
        }

        $update_order_sql = "UPDATE orders SET paid_amount = ?, pending_amount = ?, payment_status = ? WHERE id = ?";
        $update_order_stmt = mysqli_prepare($conn, $update_order_sql);
        mysqli_stmt_bind_param($update_order_stmt, "ddsi", $paid_amount, $pending_amount, $payment_status, $id);
        if (!mysqli_stmt_execute($update_order_stmt)) {
            throw new Exception('Failed to update order payment');
        }
        mysqli_stmt_close($update_order_stmt);

        $update_customer_sql = "UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?";
        $update_customer_stmt = mysqli_prepare($conn, $update_customer_sql);
        mysqli_stmt_bind_param($update_customer_stmt, "di", $amount, $currentOrder['customer_id']);
        if (!mysqli_stmt_execute($update_customer_stmt)) {
            throw new Exception('Failed to update customer balance');
        }
        mysqli_stmt_close($update_customer_stmt);

        mysqli_commit($conn);
        header("Location: order-view.php?id=$id&msg=payment_added");
        exit;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        header("Location: order-view.php?id=$id&msg=payment_error");
        exit;
    }
}

if (isset($_GET['delete_item'])) {
    $item_id = intval($_GET['delete_item']);

    $item_sql = "SELECT * FROM order_items WHERE id = ? AND order_id = ?";
    $item_stmt = mysqli_prepare($conn, $item_sql);
    mysqli_stmt_bind_param($item_stmt, "ii", $item_id, $id);
    mysqli_stmt_execute($item_stmt);
    $item_result = mysqli_stmt_get_result($item_stmt);
    $item = $item_result ? mysqli_fetch_assoc($item_result) : null;
    if ($item_result) mysqli_free_result($item_result);
    mysqli_stmt_close($item_stmt);

    if ($item) {
        mysqli_begin_transaction($conn);
        try {
            $restore_stock_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
            $restore_stmt = mysqli_prepare($conn, $restore_stock_sql);
            mysqli_stmt_bind_param($restore_stmt, "ii", $item['quantity'], $item['product_id']);
            mysqli_stmt_execute($restore_stmt);
            mysqli_stmt_close($restore_stmt);

            $delete_sql = "DELETE FROM order_items WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $item_id);
            mysqli_stmt_execute($delete_stmt);
            mysqli_stmt_close($delete_stmt);

            $newTotalItems = 0;
            $newTotalAmount = 0.0;
            $calcSql = "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as amt FROM order_items WHERE order_id = ?";
            $calcStmt = mysqli_prepare($conn, $calcSql);
            mysqli_stmt_bind_param($calcStmt, "i", $id);
            mysqli_stmt_execute($calcStmt);
            $calcRes = mysqli_stmt_get_result($calcStmt);
            if ($calcRes && $calcRow = mysqli_fetch_assoc($calcRes)) {
                $newTotalItems = (int)$calcRow['cnt'];
                $newTotalAmount = (float)$calcRow['amt'];
            }
            if ($calcRes) mysqli_free_result($calcRes);
            mysqli_stmt_close($calcStmt);

            $currentOrder = fetchOrderDetails($conn, $id);
            $paidAmountCurrent = $currentOrder ? (float)$currentOrder['paid_amount'] : 0;
            if ($paidAmountCurrent > $newTotalAmount) $paidAmountCurrent = $newTotalAmount;
            $newPendingAmount = max(0, $newTotalAmount - $paidAmountCurrent);
            $newPaymentStatus = $paidAmountCurrent <= 0 ? 'pending' : ($newPendingAmount <= 0 ? 'paid' : 'partial');

            $update_order_sql = "UPDATE orders SET total_amount = ?, total_items = ?, paid_amount = ?, pending_amount = ?, payment_status = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_order_sql);
            mysqli_stmt_bind_param($update_stmt, "didssi", $newTotalAmount, $newTotalItems, $paidAmountCurrent, $newPendingAmount, $newPaymentStatus, $id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            mysqli_commit($conn);
            header("Location: order-view.php?id=$id&msg=item_deleted");
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            header("Location: order-view.php?id=$id&msg=item_delete_error");
            exit;
        }
    } else {
        header("Location: order-view.php?id=$id&msg=item_not_found");
        exit;
    }
}

/* -------------------------
   FETCH DATA FOR VIEW
------------------------- */

$order = fetchOrderDetails($conn, $id);
if (!$order) {
    die('Order not found');
}
$order_items = fetchOrderItems($conn, $id);
$transactions = fetchTransactions($conn, $id);

$message = '';
$message_type = '';
$messageMap = [
    'status_updated' => ['success', 'Order status updated successfully!'],
    'status_error' => ['danger', 'Error updating order status.'],
    'payment_added' => ['success', 'Payment recorded successfully!'],
    'payment_error' => ['danger', 'Error recording payment.'],
    'invalid_payment' => ['danger', 'Please enter a valid payment amount.'],
    'payment_exceeds' => ['danger', 'Payment amount cannot exceed pending amount.'],
    'item_deleted' => ['success', 'Item removed from order successfully!'],
    'item_delete_error' => ['danger', 'Error removing item from order.'],
    'item_not_found' => ['danger', 'Order item not found.'],
    'invalid_status' => ['danger', 'Invalid order status selected.'],
    'not_found' => ['danger', 'Order not found.'],
];
if (isset($_GET['msg']) && isset($messageMap[$_GET['msg']])) {
    [$message_type, $message] = $messageMap[$_GET['msg']];
}
?>
<!doctype html>
<html lang="en">

<?php include('includes/head.php')?>

<body data-sidebar="dark">

<?php include('includes/pre-loader.php')?>

<div id="layout-wrapper">

<?php include('includes/topbar.php')?>    

    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php')?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

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
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <h4 class="card-title mb-0">Order #<?php echo $order['order_number']; ?></h4>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="order-invoice.php?id=<?php echo $id; ?>" class="btn btn-success btn-sm" target="_blank">
                                            <i class="mdi mdi-receipt me-1"></i> View Invoice
                                        </a>
                                        <a href="order-edit.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
                                            <i class="mdi mdi-pencil me-1"></i> Edit Order
                                        </a>
                                        <a href="orders-list.php" class="btn btn-light btn-sm">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Order Information</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <tr><th width="40%">Order Number:</th><td><span class="fw-bold"><?php echo $order['order_number']; ?></span></td></tr>
                                                    <tr><th>Order Date:</th><td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td></tr>
                                                    <tr><th>Order Status:</th><td>
                                                        <?php
                                                        $status_class = 'badge-soft-secondary';
                                                        if ($order['status'] === 'pending') $status_class = 'badge-soft-warning';
                                                        elseif ($order['status'] === 'processing') $status_class = 'badge-soft-primary';
                                                        elseif ($order['status'] === 'delivered') $status_class = 'badge-soft-success';
                                                        elseif ($order['status'] === 'cancelled') $status_class = 'badge-soft-danger';
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($order['status']); ?></span>
                                                    </td></tr>
                                                    <tr><th>Total Items:</th><td><span class="fw-bold"><?php echo (int)$order['total_items']; ?> items</span></td></tr>
                                                    <tr><th>Total Amount:</th><td><span class="fw-bold"><?php echo ov_money($order['total_amount']); ?></span></td></tr>
                                                    <tr><th>Delivery Date:</th><td><?php echo $order['delivery_date'] ? date('d M, Y', strtotime($order['delivery_date'])) : '<span class="text-muted">Not scheduled</span>'; ?></td></tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Customer Information</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <tr><th width="40%">Customer:</th><td><div class="d-flex flex-column"><span class="fw-bold"><?php echo htmlspecialchars($order['customer_name']); ?></span><small class="text-muted"><?php echo htmlspecialchars($order['shop_name']); ?></small></div></td></tr>
                                                    <tr><th>Contact:</th><td><?php echo htmlspecialchars((string)$order['customer_contact']); ?></td></tr>
                                                    <tr><th>Type:</th><td><span class="badge bg-info"><?php echo ucfirst((string)$order['customer_type']); ?></span></td></tr>
                                                    <tr><th>Location:</th><td><small class="text-muted"><?php echo htmlspecialchars((string)$order['shop_location']); ?></small></td></tr>
                                                    <tr><th>Payment Terms:</th><td><span class="badge bg-secondary"><?php echo str_replace('_', ' ', ucfirst((string)$order['payment_terms'])); ?></span></td></tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="card border">
                                            <div class="card-body">
                                                <h6 class="card-title mb-3">Order Created By</h6>
                                                <table class="table table-sm table-borderless mb-0">
                                                    <?php if (!empty($order['lineman_name'])): ?>
                                                    <tr><th width="40%">Lineman:</th><td><div class="d-flex flex-column"><span class="fw-bold"><?php echo htmlspecialchars($order['lineman_name']); ?></span><small class="text-muted">ID: <?php echo htmlspecialchars((string)$order['lineman_id']); ?></small></div></td></tr>
                                                    <tr><th>Contact:</th><td><?php echo htmlspecialchars((string)$order['lineman_phone']); ?></td></tr>
                                                    <tr><th>Email:</th><td><small class="text-muted"><?php echo htmlspecialchars((string)$order['lineman_email']); ?></small></td></tr>
                                                    <?php else: ?>
                                                    <tr><td colspan="2" class="text-center text-muted"><i class="mdi mdi-account-off h2"></i><p class="mt-2">Created by Admin</p></td></tr>
                                                    <?php endif; ?>
                                                    <tr><th>Payment Status:</th><td>
                                                        <?php
                                                        $payment_status_class = 'badge-soft-danger';
                                                        if ($order['payment_status'] == 'paid') $payment_status_class = 'badge-soft-success';
                                                        elseif ($order['payment_status'] == 'partial') $payment_status_class = 'badge-soft-warning';
                                                        ?>
                                                        <span class="badge <?php echo $payment_status_class; ?>"><?php echo ucfirst((string)$order['payment_status']); ?></span>
                                                    </td></tr>
                                                    <tr><th>Paid:</th><td class="text-success fw-bold"><?php echo ov_money($order['paid_amount']); ?></td></tr>
                                                    <tr><th>Pending:</th><td class="text-danger fw-bold"><?php echo ov_money($order['pending_amount']); ?></td></tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-lg-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <h5 class="card-title mb-0">Order Items</h5>
                                                    <span class="badge bg-primary"><?php echo count($order_items); ?> Items</span>
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
                                                                    $profit = ((float)$item['customer_price'] - (float)$item['stock_price']) * (float)$item['quantity'];
                                                                    $subtotal += (float)$item['total'];
                                                                    $total_profit += $profit;
                                                            ?>
                                                                    <tr>
                                                                        <td><?php echo $counter++; ?></td>
                                                                        <td><div class="d-flex flex-column"><span class="fw-bold"><?php echo htmlspecialchars((string)$item['product_name']); ?></span><small class="text-muted">ID: <?php echo (int)$item['product_id']; ?></small></div></td>
                                                                        <td><?php echo htmlspecialchars((string)$item['product_code']); ?></td>
                                                                        <td><?php echo ov_money($item['stock_price']); ?></td>
                                                                        <td><?php echo ov_money($item['customer_price']); ?></td>
                                                                        <td><span class="badge bg-primary rounded-pill"><?php echo (int)$item['quantity']; ?> units</span></td>
                                                                        <td><span class="fw-bold"><?php echo ov_money($item['total']); ?></span></td>
                                                                        <td><span class="badge bg-success"><?php echo ov_money($profit); ?></span></td>
                                                                        <td>
                                                                            <?php if ($order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                                                                <a href="?id=<?php echo $id; ?>&delete_item=<?php echo (int)$item['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove this item from the order?');">
                                                                                    <i class="mdi mdi-delete"></i>
                                                                                </a>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                            <?php
                                                                }
                                                            } else {
                                                            ?>
                                                                <tr><td colspan="9" class="text-center py-4"><i class="mdi mdi-cart-off display-4 text-muted"></i><h5 class="mt-2">No items in this order</h5></td></tr>
                                                            <?php } ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr><td colspan="6" class="text-end fw-bold">Subtotal:</td><td colspan="3" class="fw-bold"><?php echo ov_money($subtotal); ?></td></tr>
                                                            <tr><td colspan="6" class="text-end fw-bold">Total Profit:</td><td colspan="3" class="fw-bold text-success"><?php echo ov_money($total_profit); ?></td></tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="card mb-3">
                                            <div class="card-header"><h5 class="card-title mb-0">Update Status</h5></div>
                                            <div class="card-body">
                                                <form method="POST" action="order-view.php?id=<?php echo $id; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Order Status</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                            <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" name="update_status" class="btn btn-primary w-100">Update Status</button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="card mb-3">
                                            <div class="card-header"><h5 class="card-title mb-0">Add Payment</h5></div>
                                            <div class="card-body">
                                                <form method="POST" action="order-view.php?id=<?php echo $id; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                                        <div class="input-group">
                                                            <span class="input-group-text">₹</span>
                                                            <input type="number" class="form-control" name="payment_amount" step="0.01" min="0.01" max="<?php echo (float)$order['pending_amount']; ?>" value="<?php echo $order['pending_amount'] > 0 ? number_format((float)$order['pending_amount'], 2, '.', '') : ''; ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Payment Method</label>
                                                        <select name="payment_method" class="form-select" required>
                                                            <option value="cash">Cash</option>
                                                            <option value="upi">UPI</option>
                                                            <option value="card">Card</option>
                                                            <option value="bank_transfer">Bank Transfer</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Reference No</label>
                                                        <input type="text" name="reference_no" class="form-control" placeholder="Transaction / Reference No">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Notes</label>
                                                        <textarea name="payment_notes" class="form-control" rows="3" placeholder="Optional notes"></textarea>
                                                    </div>
                                                    <button type="submit" name="add_payment" class="btn btn-success w-100" <?php echo ((float)$order['pending_amount'] <= 0) ? 'disabled' : ''; ?>>
                                                        Record Payment
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="card">
                                            <div class="card-header"><h5 class="card-title mb-0">Payment History</h5></div>
                                            <div class="card-body">
                                                <?php if (!empty($transactions)): ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm align-middle mb-0">
                                                            <thead>
                                                                <tr><th>Date</th><th>Amount</th><th>Method</th></tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($transactions as $tr): ?>
                                                                    <tr>
                                                                        <td><small><?php echo date('d M Y', strtotime($tr['created_at'])); ?></small></td>
                                                                        <td class="fw-bold text-success"><?php echo ov_money($tr['amount']); ?></td>
                                                                        <td><?php echo htmlspecialchars(ucwords(str_replace('_',' ',(string)$tr['payment_method']))); ?></td>
                                                                    </tr>
                                                                    <?php if (!empty($tr['reference_no']) || !empty($tr['notes'])): ?>
                                                                    <tr>
                                                                        <td colspan="3">
                                                                            <?php if (!empty($tr['reference_no'])): ?><small class="d-block text-muted">Ref: <?php echo htmlspecialchars((string)$tr['reference_no']); ?></small><?php endif; ?>
                                                                            <?php if (!empty($tr['notes'])): ?><small class="d-block text-muted"><?php echo htmlspecialchars((string)$tr['notes']); ?></small><?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center text-muted py-3">
                                                        <i class="mdi mdi-cash-remove fs-2 d-block"></i>
                                                        No payment history found.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <?php mysqli_close($conn); ?>

            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php')?>
</body>
</html>
