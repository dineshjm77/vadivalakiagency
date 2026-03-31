<?php
session_start();
include('config/config.php');

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'], true)) {
    header('Location: index.php');
    exit;
}

function formatCurrency($amount) { return '₹' . number_format((float)$amount, 2); }
function formatDate($date) { return $date ? date('d M, Y', strtotime($date)) : '-'; }
function formatDateTime($date) { return $date ? date('d M, Y h:i A', strtotime($date)) : '-'; }
function table_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $cache[$key];
}

$a5InvoicePage = 'view-invoice-a5.php';
if (!file_exists(__DIR__ . '/' . $a5InvoicePage) && file_exists(__DIR__ . '/view-invoice-a5-payment-summary.php')) {
    $a5InvoicePage = 'view-invoice-a5-payment-summary.php';
}

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_payment'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));
    $reference_no = trim((string)($_POST['reference_no'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $orderSql = "SELECT o.id, o.customer_id, o.order_number, o.total_amount, o.paid_amount, o.pending_amount, o.payment_status,
                        c.customer_name, c.shop_name
                 FROM orders o
                 INNER JOIN customers c ON c.id = o.customer_id
                 WHERE o.id = ? AND o.status != 'cancelled'
                 LIMIT 1";
    $orderStmt = mysqli_prepare($conn, $orderSql);
    mysqli_stmt_bind_param($orderStmt, "i", $order_id);
    mysqli_stmt_execute($orderStmt);
    $orderRes = mysqli_stmt_get_result($orderStmt);
    $order = $orderRes ? mysqli_fetch_assoc($orderRes) : null;
    if ($orderRes) mysqli_free_result($orderRes);
    mysqli_stmt_close($orderStmt);

    if (!$order) {
        $message = 'Order not found.';
        $message_type = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Please enter a valid payment amount.';
        $message_type = 'danger';
    } elseif ($amount > (float)$order['pending_amount']) {
        $message = 'Payment amount cannot exceed pending amount.';
        $message_type = 'danger';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $payment_id = 'PAY' . date('YmdHis') . rand(100, 999);
            $created_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 1);
            $txnNotes = $notes !== '' ? $notes : ('Payment received for order #' . $order['order_number']);

            $txnSql = "INSERT INTO transactions
                       (customer_id, order_id, payment_id, type, amount, payment_method, reference_no, notes, created_by, created_at)
                       VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
            $txnStmt = mysqli_prepare($conn, $txnSql);
            mysqli_stmt_bind_param($txnStmt, "iisdsssi",
                $order['customer_id'], $order_id, $payment_id, $amount, $payment_method, $reference_no, $txnNotes, $created_by
            );
            if (!mysqli_stmt_execute($txnStmt)) {
                throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
            }
            $transaction_id = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($txnStmt);

            if (table_has_column($conn, 'payment_history', 'order_id')) {
                $historySql = "INSERT INTO payment_history
                              (order_id, transaction_id, amount_paid, payment_method, reference_no, notes, created_by, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $historyStmt = mysqli_prepare($conn, $historySql);
                mysqli_stmt_bind_param($historyStmt, "iidsssi",
                    $order_id, $transaction_id, $amount, $payment_method, $reference_no, $txnNotes, $created_by
                );
                if (!mysqli_stmt_execute($historyStmt)) {
                    throw new Exception('Failed to record payment history: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($historyStmt);
            }

            $newPaid = round((float)$order['paid_amount'] + $amount, 2);
            $newPending = round((float)$order['total_amount'] - $newPaid, 2);
            if ($newPending < 0) $newPending = 0;

            if ($newPending <= 0) {
                $newStatus = 'paid';
            } elseif ($newPaid > 0) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'pending';
            }

            $updateOrderSql = "UPDATE orders SET paid_amount = ?, pending_amount = ?, payment_status = ? WHERE id = ?";
            $updateOrderStmt = mysqli_prepare($conn, $updateOrderSql);
            mysqli_stmt_bind_param($updateOrderStmt, "ddsi", $newPaid, $newPending, $newStatus, $order_id);
            if (!mysqli_stmt_execute($updateOrderStmt)) {
                throw new Exception('Failed to update order payment: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($updateOrderStmt);

            $updateCustomerSql = "UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?";
            $updateCustomerStmt = mysqli_prepare($conn, $updateCustomerSql);
            mysqli_stmt_bind_param($updateCustomerStmt, "di", $amount, $order['customer_id']);
            if (!mysqli_stmt_execute($updateCustomerStmt)) {
                throw new Exception('Failed to update customer balance: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($updateCustomerStmt);

            mysqli_commit($conn);
            header('Location: pending-payments.php?msg=payment_success');
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
            $message_type = 'danger';
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'payment_success') {
    $message = 'Payment recorded successfully!';
    $message_type = 'success';
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$search_term = isset($_GET['search']) ? trim((string)($_GET['search'] ?? '')) : '';
$view_type = isset($_GET['view']) ? $_GET['view'] : 'orders';

$customers_summary = [];
$orders_summary = [];
$total_pending = 0;
$total_customers = 0;
$total_orders = 0;
$overdue_amount = 0;

$customers = [];
$customers_sql = "SELECT id, customer_name, shop_name, customer_contact FROM customers WHERE status = 'active' ORDER BY customer_name";
$customers_result = mysqli_query($conn, $customers_sql);
if ($customers_result) {
    while ($row = mysqli_fetch_assoc($customers_result)) {
        $customers[$row['id']] = $row['customer_name'] . ' - ' . $row['shop_name'] . ' (' . $row['customer_contact'] . ')';
    }
    mysqli_free_result($customers_result);
}

if ($view_type === 'customers') {
    $sql = "SELECT 
                c.id, c.customer_code, c.customer_name, c.shop_name, c.customer_contact, c.customer_type,
                c.current_balance, c.payment_terms,
                COUNT(DISTINCT o.id) as pending_orders_count,
                COALESCE(SUM(o.pending_amount),0) as total_pending,
                MAX(o.order_date) as last_order_date,
                DATEDIFF(CURDATE(), MAX(o.order_date)) as days_since_last_order
            FROM customers c
            LEFT JOIN orders o ON c.id = o.customer_id 
                AND o.pending_amount > 0 
                AND o.status != 'cancelled'
            WHERE c.status = 'active'";
    if ($customer_id > 0) $sql .= " AND c.id = " . $customer_id;
    if ($search_term !== '') {
        $safe = mysqli_real_escape_string($conn, $search_term);
        $sql .= " AND (c.customer_name LIKE '%$safe%' OR c.shop_name LIKE '%$safe%' OR c.customer_contact LIKE '%$safe%' OR c.customer_code LIKE '%$safe%')";
    }
    $sql .= " GROUP BY c.id
              HAVING (COUNT(DISTINCT o.id) > 0 OR c.current_balance > 0)
              ORDER BY total_pending DESC, c.customer_name";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $customers_summary[] = $row;
            $total_pending += (float)$row['total_pending'];
            $total_customers++;
            if ((int)$row['days_since_last_order'] > 30 && (float)$row['total_pending'] > 0) {
                $overdue_amount += (float)$row['total_pending'];
            }
        }
        mysqli_free_result($result);
    }
} else {
    $sql = "SELECT 
                o.*, c.customer_name, c.shop_name, c.customer_contact, c.customer_type, c.current_balance,
                DATEDIFF(CURDATE(), o.order_date) as days_passed,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE o.pending_amount > 0 AND o.status != 'cancelled'";
    $safeStart = mysqli_real_escape_string($conn, $start_date);
    $safeEnd = mysqli_real_escape_string($conn, $end_date);
    $sql .= " AND o.order_date BETWEEN '$safeStart' AND '$safeEnd'";
    if ($customer_id > 0) $sql .= " AND o.customer_id = " . $customer_id;
    if ($payment_status === 'overdue') $sql .= " AND DATEDIFF(CURDATE(), o.order_date) > 30";
    elseif ($payment_status === 'partial') $sql .= " AND o.paid_amount > 0 AND o.pending_amount > 0";
    elseif ($payment_status === 'pending') $sql .= " AND o.paid_amount = 0";

    if ($search_term !== '') {
        $safe = mysqli_real_escape_string($conn, $search_term);
        $sql .= " AND (o.order_number LIKE '%$safe%' OR c.customer_name LIKE '%$safe%' OR c.shop_name LIKE '%$safe%' OR c.customer_contact LIKE '%$safe%')";
    }
    $sql .= " ORDER BY o.order_date ASC, o.pending_amount DESC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $orders_summary[] = $row;
            $total_pending += (float)$row['pending_amount'];
            $total_orders++;
            if ((int)$row['days_passed'] > 30) $overdue_amount += (float)$row['pending_amount'];
        }
        mysqli_free_result($result);
    }
}

$today_stats = ['count' => 0, 'total' => 0];
$today_result = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(amount),0) as total FROM transactions WHERE type = 'payment' AND DATE(created_at) = CURDATE()");
if ($today_result) { $today_stats = mysqli_fetch_assoc($today_result); mysqli_free_result($today_result); }

$month_stats = ['count' => 0, 'total' => 0];
$month_result = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(amount),0) as total FROM transactions WHERE type = 'payment' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
if ($month_result) { $month_stats = mysqli_fetch_assoc($month_result); mysqli_free_result($month_result); }

$customers_pending_stats = ['count' => 0];
$customers_pending_result = mysqli_query($conn, "SELECT COUNT(DISTINCT customer_id) as count FROM orders WHERE pending_amount > 0 AND status != 'cancelled'");
if ($customers_pending_result) { $customers_pending_stats = mysqli_fetch_assoc($customers_pending_result); mysqli_free_result($customers_pending_result); }

?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php')?>
<style>
.table-scroll-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.table-scroll-wrap table{min-width:1180px}
.actions-top-bar{position:sticky;top:70px;z-index:10;background:#fff;padding-bottom:.5rem}
.receive-toolbar{display:flex;gap:.5rem;flex-wrap:wrap}
.receive-toolbar .btn{white-space:nowrap}
@media (max-width: 767.98px){
    .receive-toolbar .btn{width:100%}
    .table-scroll-wrap table{min-width:1080px}
}
</style>
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
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2"><i class="mdi mdi-cash-multiple"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Total Pending</p><h4 class="mb-0"><?php echo formatCurrency($total_pending); ?></h4><p class="text-muted mb-0"><?php echo $view_type === 'customers' ? $total_customers . ' customers' : $total_orders . ' orders'; ?></p></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-success-subtle text-success rounded-2 fs-2"><i class="mdi mdi-cash-check"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Today's Collections</p><h4 class="mb-0"><?php echo formatCurrency($today_stats['total'] ?? 0); ?></h4><p class="text-muted mb-0"><?php echo (int)($today_stats['count'] ?? 0); ?> payments</p></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2"><i class="mdi mdi-calendar-clock"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Overdue (30+ days)</p><h4 class="mb-0"><?php echo formatCurrency($overdue_amount); ?></h4><p class="text-muted mb-0"><?php echo (int)($customers_pending_stats['count'] ?? 0); ?> customers</p></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-info-subtle text-info rounded-2 fs-2"><i class="mdi mdi-calendar-month"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">This Month</p><h4 class="mb-0"><?php echo formatCurrency($month_stats['total'] ?? 0); ?></h4><p class="text-muted mb-0"><?php echo (int)($month_stats['count'] ?? 0); ?> payments</p></div></div></div></div></div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body actions-top-bar">
                                <h5 class="card-title mb-3">Filters & View Options</h5>
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">View Type</label>
                                        <select class="form-select" name="view" onchange="this.form.submit()">
                                            <option value="orders" <?php echo $view_type == 'orders' ? 'selected' : ''; ?>>Order-wise</option>
                                            <option value="customers" <?php echo $view_type == 'customers' ? 'selected' : ''; ?>>Customer-wise</option>
                                        </select>
                                    </div>

                                    <?php if ($view_type === 'orders'): ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <?php endif; ?>

                                    <div class="col-md-2">
                                        <label class="form-label">Customer</label>
                                        <select class="form-select" name="customer_id">
                                            <option value="0">All Customers</option>
                                            <?php foreach ($customers as $cid => $name): ?>
                                                <option value="<?php echo $cid; ?>" <?php echo $customer_id == $cid ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if ($view_type === 'orders'): ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="all" <?php echo $payment_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="overdue" <?php echo $payment_status == 'overdue' ? 'selected' : ''; ?>>Overdue Only</option>
                                            <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>Partial Paid</option>
                                            <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Fully Pending</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>

                                    <div class="col-md-2">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search...">
                                    </div>

                                    <div class="col-md-12">
                                        <div class="receive-toolbar mt-3">
                                            <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter me-1"></i> Apply Filters</button>
                                            <a href="pending-payments.php" class="btn btn-secondary"><i class="mdi mdi-refresh me-1"></i> Reset</a>
                                            <button type="button" class="btn btn-info" onclick="window.print()"><i class="mdi mdi-printer me-1"></i> Print</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($view_type === 'customers'): ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h4 class="card-title mb-0">Customer-wise Pending Payments</h4>
                                    <p class="card-title-desc mb-0">View pending amounts by customer</p>
                                </div>
                                <div class="text-muted">Showing: <strong><?php echo count($customers_summary); ?> customers</strong> | Total: <strong><?php echo formatCurrency($total_pending); ?></strong></div>
                            </div>
                            <div class="table-scroll-wrap">
                                <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th><th>Customer</th><th>Contact</th><th>Customer Type</th><th>Payment Terms</th><th>Current Balance</th><th>Pending Orders</th><th>Pending Amount</th><th>Last Order Date</th><th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($customers_summary)): $counter = 1; foreach ($customers_summary as $customer): 
                                        $is_overdue = ((int)$customer['days_since_last_order'] > 30 && (float)$customer['total_pending'] > 0);
                                        $status_class = $is_overdue ? 'badge-soft-danger' : ((float)$customer['total_pending'] > 0 ? 'badge-soft-warning' : 'badge-soft-success');
                                        $status_text = $is_overdue ? 'Overdue' : ((float)$customer['total_pending'] > 0 ? 'Pending' : 'Clear');
                                        $last_order = $customer['last_order_date'] ? date('d M, Y', strtotime($customer['last_order_date'])) : 'No orders';
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><div class="fw-semibold"><?php echo htmlspecialchars($customer['customer_name']); ?></div><small class="text-muted"><?php echo htmlspecialchars($customer['shop_name']); ?></small><br><small class="text-muted"><?php echo htmlspecialchars($customer['customer_code']); ?></small></td>
                                            <td><?php echo htmlspecialchars($customer['customer_contact']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($customer['customer_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($customer['payment_terms']); ?></td>
                                            <td><?php echo formatCurrency($customer['current_balance']); ?></td>
                                            <td><?php echo (int)$customer['pending_orders_count']; ?></td>
                                            <td class="fw-bold"><?php echo formatCurrency($customer['total_pending']); ?></td>
                                            <td><?php echo $last_order; ?></td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="10" class="text-center py-4 text-muted">No pending customers found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h4 class="card-title mb-0">Order-wise Pending Payments</h4>
                                    <p class="card-title-desc mb-0">View pending amounts by individual orders</p>
                                </div>
                                <div class="text-muted">Showing: <strong><?php echo count($orders_summary); ?> orders</strong> | Total: <strong><?php echo formatCurrency($total_pending); ?></strong></div>
                            </div>
                            <div class="small text-muted mb-2">Buttons are fixed at the top. Swipe the table left/right only if needed.</div>
                            <div class="table-scroll-wrap">
                                <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Order #</th><th>Date</th><th>Customer</th><th>Items</th><th>Total Amount</th><th>Paid</th><th>Pending</th><th>Payment Status</th><th>Days</th><th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($orders_summary)): foreach ($orders_summary as $order): ?>
                                        <tr>
                                            <td><div class="fw-semibold"><?php echo htmlspecialchars($order['order_number']); ?></div></td>
                                            <td><?php echo formatDate($order['order_date']); ?></td>
                                            <td><div class="fw-semibold"><?php echo htmlspecialchars($order['customer_name']); ?></div><small class="text-muted"><?php echo htmlspecialchars($order['shop_name']); ?> | <?php echo htmlspecialchars($order['customer_contact']); ?></small></td>
                                            <td><span class="badge bg-info rounded-pill"><?php echo (int)$order['item_count']; ?> items</span></td>
                                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                            <td class="text-success fw-semibold"><?php echo formatCurrency($order['paid_amount']); ?></td>
                                            <td class="text-danger fw-semibold"><?php echo formatCurrency($order['pending_amount']); ?></td>
                                            <td>
                                                <?php
                                                $paymentClass = 'badge-soft-danger';
                                                if ($order['payment_status'] === 'paid') $paymentClass = 'badge-soft-success';
                                                elseif ($order['payment_status'] === 'partial') $paymentClass = 'badge-soft-warning';
                                                ?>
                                                <span class="badge <?php echo $paymentClass; ?>"><?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></span>
                                            </td>
                                            <td><?php echo (int)$order['days_passed']; ?> days</td>
                                            <td>
                                                <div class="d-flex flex-column gap-2">
                                                    <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars($a5InvoicePage); ?>?id=<?php echo (int)$order['id']; ?>" target="_blank">
                                                        <i class="mdi mdi-receipt me-1"></i> A5 Invoice
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-success receive-payment-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#receivePaymentModal"
                                                            data-order-id="<?php echo (int)$order['id']; ?>"
                                                            data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                            data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>"
                                                            data-pending-amount="<?php echo (float)$order['pending_amount']; ?>"
                                                            data-total-amount="<?php echo (float)$order['total_amount']; ?>"
                                                            data-paid-amount="<?php echo (float)$order['paid_amount']; ?>">
                                                        <i class="mdi mdi-cash-check me-1"></i> Receive Payment
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="10" class="text-center py-4 text-muted">No pending orders found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<div class="modal fade" id="receivePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="pending-payments.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Receive Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="receive_payment" value="1">
                    <input type="hidden" name="order_id" id="rp_order_id">

                    <div class="mb-3">
                        <label class="form-label">Order #</label>
                        <input type="text" class="form-control" id="rp_order_number" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" id="rp_customer_name" readonly>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" id="rp_total_amount" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Already Paid</label>
                            <input type="text" class="form-control" id="rp_paid_amount" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pending Amount</label>
                        <input type="text" class="form-control" id="rp_pending_amount" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Receive Amount</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" id="rp_amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference No</label>
                        <input type="text" class="form-control" name="reference_no" placeholder="Optional reference number">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success" type="submit">Save Payment</button>
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php')?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('receivePaymentModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            const orderId = btn.getAttribute('data-order-id') || '';
            const orderNo = btn.getAttribute('data-order-number') || '';
            const customer = btn.getAttribute('data-customer-name') || '';
            const pending = parseFloat(btn.getAttribute('data-pending-amount') || '0');
            const total = parseFloat(btn.getAttribute('data-total-amount') || '0');
            const paid = parseFloat(btn.getAttribute('data-paid-amount') || '0');

            document.getElementById('rp_order_id').value = orderId;
            document.getElementById('rp_order_number').value = orderNo;
            document.getElementById('rp_customer_name').value = customer;
            document.getElementById('rp_pending_amount').value = '₹' + pending.toFixed(2);
            document.getElementById('rp_total_amount').value = '₹' + total.toFixed(2);
            document.getElementById('rp_paid_amount').value = '₹' + paid.toFixed(2);
            document.getElementById('rp_amount').value = pending > 0 ? pending.toFixed(2) : '';
            document.getElementById('rp_amount').setAttribute('max', pending.toFixed(2));
        });
    }
});
</script>
</body>
</html>
