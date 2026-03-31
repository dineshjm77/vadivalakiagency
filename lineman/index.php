<?php
session_start();
include('../config/config.php');
include('includes/auth-check.php');

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('money')) {
    function money($amount) { return '₹' . number_format((float)$amount, 2); }
}
if (!function_exists('column_exists')) {
    function column_exists(mysqli $conn, string $table, string $column): bool {
        $table = mysqli_real_escape_string($conn, $table);
        $column = mysqli_real_escape_string($conn, $column);
        $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $rs && mysqli_num_rows($rs) > 0;
    }
}

$pageTitle = 'Lineman Work Dashboard';
$currentPage = 'dashboard';
$section = trim((string)($_GET['section'] ?? ''));
$invoiceTypeFilter = trim((string)($_GET['invoice_type'] ?? ''));
if ($section === 'orders') $currentPage = 'orders';
if ($section === 'collections') $currentPage = 'collections';
if ($invoiceTypeFilter === 'first_sale') $currentPage = 'first_sale';
if ($invoiceTypeFilter === 'performance') $currentPage = 'performance';
if ($invoiceTypeFilter === 'completed') $currentPage = 'completed';

$sessionRole = (string)($_SESSION['user_role'] ?? '');
$previewAllowed = in_array($sessionRole, ['admin', 'super_admin'], true);
$linemanId = isset($_SESSION['lineman_id']) ? (int)$_SESSION['lineman_id'] : (int)($_SESSION['user_id'] ?? 0);
if ($previewAllowed && isset($_GET['lineman_id']) && (int)$_GET['lineman_id'] > 0) {
    $linemanId = (int)$_GET['lineman_id'];
}

$linemanSql = "SELECT l.*, z.zone_name FROM linemen l LEFT JOIN zones z ON z.id = l.zone_id WHERE l.id = ? LIMIT 1";
$linemanStmt = mysqli_prepare($conn, $linemanSql);
mysqli_stmt_bind_param($linemanStmt, 'i', $linemanId);
mysqli_stmt_execute($linemanStmt);
$linemanRes = mysqli_stmt_get_result($linemanStmt);
$lineman = $linemanRes ? mysqli_fetch_assoc($linemanRes) : null;
if (!$lineman) {
    die('Lineman record not found.');
}

$success = '';
$error = '';
$hasPaymentHistoryOrderId = column_exists($conn, 'payment_history', 'order_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_payment'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $amount = round((float)($_POST['amount_paid'] ?? 0), 2);
    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'cash'));
    $referenceNo = trim((string)($_POST['reference_no'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    $checkOrderSql = "
        SELECT o.id, o.order_number, o.total_amount, o.paid_amount, o.pending_amount, o.payment_status,
               c.id AS customer_id
        FROM orders o
        INNER JOIN customers c ON c.id = o.customer_id
        WHERE o.id = ? AND c.id = ? AND c.assigned_lineman_id = ?
        LIMIT 1
    ";
    $orderStmt = mysqli_prepare($conn, $checkOrderSql);
    mysqli_stmt_bind_param($orderStmt, 'iii', $orderId, $customerId, $linemanId);
    mysqli_stmt_execute($orderStmt);
    $orderRes = mysqli_stmt_get_result($orderStmt);
    $orderRow = $orderRes ? mysqli_fetch_assoc($orderRes) : null;

    if (!$orderRow) {
        $error = 'Invalid order for this lineman.';
    } elseif ($amount <= 0) {
        $error = 'Collection amount must be greater than zero.';
    } elseif ($amount > (float)$orderRow['pending_amount']) {
        $error = 'Collection amount cannot exceed pending amount.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $paymentId = 'PAY' . date('YmdHis') . rand(100, 999);
            $noteText = $notes !== '' ? $notes : ('Collection for invoice #' . $orderRow['order_number']);
            $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : $linemanId;

            $sqlTxn = "INSERT INTO transactions (customer_id, order_id, payment_id, type, amount, payment_method, reference_no, notes, created_by, created_at)
                       VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
            $stmtTxn = mysqli_prepare($conn, $sqlTxn);
            mysqli_stmt_bind_param($stmtTxn, 'iisdsssi', $customerId, $orderId, $paymentId, $amount, $paymentMethod, $referenceNo, $noteText, $createdBy);
            if (!mysqli_stmt_execute($stmtTxn)) {
                throw new Exception('Failed to insert transaction: ' . mysqli_error($conn));
            }
            $transactionId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmtTxn);

            if ($hasPaymentHistoryOrderId) {
                $sqlHist = "INSERT INTO payment_history (order_id, transaction_id, amount_paid, payment_method, reference_no, notes, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmtHist = mysqli_prepare($conn, $sqlHist);
                mysqli_stmt_bind_param($stmtHist, 'iidsssi', $orderId, $transactionId, $amount, $paymentMethod, $referenceNo, $noteText, $createdBy);
                mysqli_stmt_execute($stmtHist);
                mysqli_stmt_close($stmtHist);
            }

            $newPaid = round((float)$orderRow['paid_amount'] + $amount, 2);
            $newPending = round((float)$orderRow['total_amount'] - $newPaid, 2);
            $newStatus = $newPending <= 0 ? 'paid' : 'partial';

            $sqlOrder = "UPDATE orders SET paid_amount = ?, pending_amount = ?, payment_status = ?, payment_date = NOW() WHERE id = ?";
            $stmtOrder = mysqli_prepare($conn, $sqlOrder);
            mysqli_stmt_bind_param($stmtOrder, 'ddsi', $newPaid, $newPending, $newStatus, $orderId);
            if (!mysqli_stmt_execute($stmtOrder)) {
                throw new Exception('Failed to update order payment: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($stmtOrder);

            $sqlCustomer = "UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?";
            $stmtCustomer = mysqli_prepare($conn, $sqlCustomer);
            mysqli_stmt_bind_param($stmtCustomer, 'di', $amount, $customerId);
            if (!mysqli_stmt_execute($stmtCustomer)) {
                throw new Exception('Failed to update customer balance: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($stmtCustomer);

            mysqli_commit($conn);
            header('Location: index.php?msg=collection_saved' . ($previewAllowed ? '&lineman_id=' . $linemanId : '') . ($invoiceTypeFilter !== '' ? '&invoice_type=' . urlencode($invoiceTypeFilter) : ''));
            exit;
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $error = $ex->getMessage();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'collection_saved') {
    $success = 'Amount collected successfully.';
}

$search = trim((string)($_GET['search'] ?? ''));
$where = " WHERE c.assigned_lineman_id = ? ";
$params = [$linemanId];
$types = 'i';
if ($search !== '') {
    $where .= " AND (o.order_number LIKE ? OR c.shop_name LIKE ? OR c.customer_name LIKE ?) ";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$orderSqlCore = "
    SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, c.shop_location, c.current_balance,
           c.customer_code, c.assigned_area,
           CASE
               WHEN o.status IN ('pending','processing') AND IFNULL(o.payment_status,'pending') = 'pending' THEN 'First Sale Invoice'
               WHEN o.status = 'delivered' AND IFNULL(o.payment_status,'pending') IN ('pending','partial') THEN 'Performance Invoice'
               WHEN o.status = 'delivered' AND IFNULL(o.payment_status,'pending') = 'paid' THEN 'Completed Invoice'
               ELSE CONCAT(UCASE(LEFT(o.status,1)), SUBSTRING(o.status,2), ' Invoice')
           END AS invoice_type,
           GROUP_CONCAT(CONCAT(p.product_name, ' (Qty: ', oi.quantity, ')') SEPARATOR ', ') AS needed_products
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    {$where}
    GROUP BY o.id
";

if ($invoiceTypeFilter === 'first_sale') {
    $orderSql = "SELECT * FROM ({$orderSqlCore}) x WHERE x.invoice_type = 'First Sale Invoice' ORDER BY x.order_date DESC, x.id DESC";
} elseif ($invoiceTypeFilter === 'performance') {
    $orderSql = "SELECT * FROM ({$orderSqlCore}) x WHERE x.invoice_type = 'Performance Invoice' ORDER BY x.order_date DESC, x.id DESC";
} elseif ($invoiceTypeFilter === 'completed') {
    $orderSql = "SELECT * FROM ({$orderSqlCore}) x WHERE x.invoice_type = 'Completed Invoice' ORDER BY x.order_date DESC, x.id DESC";
} else {
    $orderSql = $orderSqlCore . " ORDER BY o.order_date DESC, o.id DESC";
}

$orderStmt = mysqli_prepare($conn, $orderSql);
mysqli_stmt_bind_param($orderStmt, $types, ...$params);
mysqli_stmt_execute($orderStmt);
$orderRes = mysqli_stmt_get_result($orderStmt);
$orders = [];
$stats = ['first' => 0, 'performance' => 0, 'completed' => 0, 'pending_collection' => 0.00, 'today_collection' => 0.00];
while ($orderRes && $row = mysqli_fetch_assoc($orderRes)) {
    $orders[] = $row;
    if ($row['invoice_type'] === 'First Sale Invoice') $stats['first']++;
    if ($row['invoice_type'] === 'Performance Invoice') $stats['performance']++;
    if ($row['invoice_type'] === 'Completed Invoice') $stats['completed']++;
    $stats['pending_collection'] += (float)$row['pending_amount'];
}

$todayCollectionSql = "
    SELECT COALESCE(SUM(t.amount), 0) AS total_today
    FROM transactions t
    INNER JOIN orders o ON o.id = t.order_id
    INNER JOIN customers c ON c.id = o.customer_id
    WHERE c.assigned_lineman_id = ? AND t.type = 'payment' AND DATE(t.created_at) = CURDATE()
";
$todayStmt = mysqli_prepare($conn, $todayCollectionSql);
mysqli_stmt_bind_param($todayStmt, 'i', $linemanId);
mysqli_stmt_execute($todayStmt);
$todayRes = mysqli_stmt_get_result($todayStmt);
if ($todayRes && $tc = mysqli_fetch_assoc($todayRes)) {
    $stats['today_collection'] = (float)$tc['total_today'];
}

$collectionHistorySql = "
    SELECT t.created_at, t.amount, t.payment_method, t.reference_no, t.notes, o.order_number, c.shop_name, c.customer_name
    FROM transactions t
    INNER JOIN orders o ON o.id = t.order_id
    INNER JOIN customers c ON c.id = o.customer_id
    WHERE c.assigned_lineman_id = ? AND t.type = 'payment'
    ORDER BY t.created_at DESC
    LIMIT 25
";
$collectionHistoryStmt = mysqli_prepare($conn, $collectionHistorySql);
mysqli_stmt_bind_param($collectionHistoryStmt, 'i', $linemanId);
mysqli_stmt_execute($collectionHistoryStmt);
$collectionHistoryRes = mysqli_stmt_get_result($collectionHistoryStmt);
$collectionHistory = [];
while ($collectionHistoryRes && $row = mysqli_fetch_assoc($collectionHistoryRes)) {
    $collectionHistory[] = $row;
}
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php'); ?>
        </div>
    </div>

    <div class="main-content">
        <?php include('includes/topbar.php'); ?>

        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box">
                    <div>
                        <h4 class="mb-0">Lineman Work Dashboard</h4>
                        <div class="text-muted mt-1"><?php echo e($lineman['full_name']); ?> | <?php echo e($lineman['employee_id']); ?> | <?php echo e($lineman['zone_name'] ?: ($lineman['assigned_area'] ?: 'No Zone')); ?></div>
                    </div>
                    <div class="page-title-right text-end">
                        <div class="small text-muted">Phone: <?php echo e($lineman['phone'] ?: '-'); ?></div>
                        <div class="small">Status:
                            <span class="badge <?php echo $lineman['status'] === 'active' ? 'badge-soft-success' : 'badge-soft-danger'; ?>">
                                <?php echo ucfirst((string)$lineman['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small">First Sale Invoice</div><h3 class="mb-0"><?php echo $stats['first']; ?></h3></div><div class="icon badge-soft-primary"><i class="fas fa-file-invoice"></i></div></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small">Performance Invoice</div><h3 class="mb-0"><?php echo $stats['performance']; ?></h3></div><div class="icon badge-soft-warning"><i class="fas fa-chart-line"></i></div></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small">Completed Invoice</div><h3 class="mb-0"><?php echo $stats['completed']; ?></h3></div><div class="icon badge-soft-success"><i class="fas fa-circle-check"></i></div></div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small">Pending Collection</div><h4 class="mb-1"><?php echo money($stats['pending_collection']); ?></h4><small class="text-muted">Today collected: <?php echo money($stats['today_collection']); ?></small></div><div class="icon badge-soft-danger"><i class="fas fa-money-bill-wave"></i></div></div></div>
                    </div>
                </div>

                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form class="row g-3" method="GET">
                            <?php if ($previewAllowed): ?>
                                <div class="col-md-2">
                                    <label class="form-label">Preview Lineman ID</label>
                                    <input type="number" class="form-control" name="lineman_id" value="<?php echo (int)$linemanId; ?>">
                                </div>
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label class="form-label">Invoice Type</label>
                                <select class="form-select" name="invoice_type">
                                    <option value="" <?php echo $invoiceTypeFilter === '' ? 'selected' : ''; ?>>All</option>
                                    <option value="first_sale" <?php echo $invoiceTypeFilter === 'first_sale' ? 'selected' : ''; ?>>First Sale Invoice</option>
                                    <option value="performance" <?php echo $invoiceTypeFilter === 'performance' ? 'selected' : ''; ?>>Performance Invoice</option>
                                    <option value="completed" <?php echo $invoiceTypeFilter === 'completed' ? 'selected' : ''; ?>>Completed Invoice</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Search Order / Shop</label>
                                <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Enter invoice no or customer">
                            </div>
                            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Apply</button></div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4" id="assigned-orders">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Assigned Orders</h5>
                            <small class="text-muted">Show order, needed product, invoice type, collection</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Needed Product</th>
                                        <th>Total</th>
                                        <th>Collected</th>
                                        <th>Due</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th class="no-print">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orders)): ?>
                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                            $typeClass = 'badge-soft-primary';
                                            if ($order['invoice_type'] === 'Performance Invoice') $typeClass = 'badge-soft-warning';
                                            if ($order['invoice_type'] === 'Completed Invoice') $typeClass = 'badge-soft-success';
                                            ?>
                                            <tr>
                                                <td><div class="fw-semibold"><?php echo e($order['order_number']); ?></div><small class="text-muted"><?php echo date('d-m-Y', strtotime((string)$order['order_date'])); ?></small></td>
                                                <td><div class="fw-semibold"><?php echo e($order['shop_name']); ?></div><small class="text-muted"><?php echo e($order['customer_name']); ?> | <?php echo e($order['customer_contact']); ?></small></td>
                                                <td style="max-width:280px;"><small><?php echo e($order['needed_products'] ?: '-'); ?></small></td>
                                                <td><?php echo money($order['total_amount']); ?></td>
                                                <td><?php echo money($order['paid_amount']); ?></td>
                                                <td><?php echo money($order['pending_amount']); ?></td>
                                                <td><span class="badge <?php echo $typeClass; ?>"><?php echo e($order['invoice_type']); ?></span></td>
                                                <td>
                                                    <div><span class="badge <?php echo strtolower((string)$order['status']) === 'delivered' ? 'badge-soft-success' : 'badge-soft-primary'; ?>"><?php echo ucfirst((string)$order['status']); ?></span></div>
                                                    <div class="mt-1"><span class="badge <?php echo strtolower((string)$order['payment_status']) === 'paid' ? 'badge-soft-success' : (strtolower((string)$order['payment_status']) === 'partial' ? 'badge-soft-warning' : 'badge-soft-danger'); ?>"><?php echo ucfirst((string)$order['payment_status']); ?></span></div>
                                                </td>
                                                <td class="no-print">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a href="../view-invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">View Invoice</a>
                                                        <?php if ((float)$order['pending_amount'] > 0): ?>
                                                            <button type="button" class="btn btn-sm btn-success collect-btn"
                                                                data-bs-toggle="modal" data-bs-target="#collectAmountModal"
                                                                data-order-id="<?php echo (int)$order['id']; ?>"
                                                                data-customer-id="<?php echo (int)$order['customer_id']; ?>"
                                                                data-order-number="<?php echo e($order['order_number']); ?>"
                                                                data-customer="<?php echo e($order['shop_name']); ?>"
                                                                data-pending="<?php echo number_format((float)$order['pending_amount'], 2, '.', ''); ?>">
                                                                Collect Amount
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-success small fw-semibold">Collection completed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="9" class="text-center text-muted py-4">No assigned orders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card" id="collection-history">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Amount Collection History</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Method</th><th>Reference</th><th>Amount</th><th>Notes</th></tr></thead>
                                <tbody>
                                    <?php if (!empty($collectionHistory)): ?>
                                        <?php foreach ($collectionHistory as $history): ?>
                                            <tr>
                                                <td><?php echo date('d-m-Y h:i A', strtotime((string)$history['created_at'])); ?></td>
                                                <td><?php echo e($history['order_number']); ?></td>
                                                <td><?php echo e($history['shop_name']); ?></td>
                                                <td><?php echo e(ucwords(str_replace('_', ' ', (string)$history['payment_method']))); ?></td>
                                                <td><?php echo e($history['reference_no'] ?: '-'); ?></td>
                                                <td class="fw-semibold text-success"><?php echo money($history['amount']); ?></td>
                                                <td><?php echo e($history['notes'] ?: '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No collection history found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php include('includes/footer.php'); ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="collectAmountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Collect Amount</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="collect_payment" value="1">
                    <input type="hidden" name="order_id" id="modal_order_id">
                    <input type="hidden" name="customer_id" id="modal_customer_id">

                    <div class="mb-3"><label class="form-label">Invoice</label><input type="text" class="form-control" id="modal_invoice" readonly></div>
                    <div class="mb-3"><label class="form-label">Customer</label><input type="text" class="form-control" id="modal_customer" readonly></div>
                    <div class="mb-3"><label class="form-label">Pending Amount</label><input type="text" class="form-control" id="modal_pending" readonly></div>
                    <div class="mb-3"><label class="form-label">Amount Paid *</label><input type="number" class="form-control" name="amount_paid" id="modal_amount_paid" step="0.01" min="0.01" required></div>
                    <div class="mb-3"><label class="form-label">Payment Method *</label><select class="form-select" name="payment_method" required><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option></select></div>
                    <div class="mb-3"><label class="form-label">Reference No</label><input type="text" class="form-control" name="reference_no"></div>
                    <div class="mb-0"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit">Save Collection</button></div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
</body>
</html>
