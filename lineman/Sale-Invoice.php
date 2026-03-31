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
if (!function_exists('fmtDate')) {
    function fmtDate($date) { return $date ? date('d-m-Y', strtotime($date)) : '-'; }
}
if (!function_exists('column_exists')) {
    function column_exists(mysqli $conn, string $table, string $column): bool {
        $table = mysqli_real_escape_string($conn, $table);
        $column = mysqli_real_escape_string($conn, $column);
        $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $rs && mysqli_num_rows($rs) > 0;
    }
}

$hasExtendedOrderItems = true;
foreach (['hsn_code','case_pack','cases_qty','pieces_qty','free_qty','mrp','base_rate','discount_amount','gst_rate','net_rate','taxable_value','cgst_amount','sgst_amount'] as $col) {
    if (!column_exists($conn, 'order_items', $col)) {
        $hasExtendedOrderItems = false;
        break;
    }
}

$linemanId = isset($_SESSION['lineman_id']) ? (int)$_SESSION['lineman_id'] : 0;
if ($linemanId <= 0 && isset($_SESSION['user_id'])) {
    $linemanId = (int)$_SESSION['user_id'];
}

if ($linemanId <= 0) {
    echo 'Lineman not found in session.';
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_payment'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $amount = round((float)($_POST['amount_paid'] ?? 0), 2);
    $paymentMethod = mysqli_real_escape_string($conn, trim((string)($_POST['payment_method'] ?? 'cash')));
    $referenceNo = mysqli_real_escape_string($conn, trim((string)($_POST['reference_no'] ?? '')));
    $notes = mysqli_real_escape_string($conn, trim((string)($_POST['notes'] ?? '')));

    $checkOrderSql = "
        SELECT o.id, o.order_number, o.total_amount, o.paid_amount, o.pending_amount, o.payment_status,
               c.id AS customer_id
        FROM orders o
        INNER JOIN customers c ON c.id = o.customer_id
        WHERE o.id = {$orderId} AND c.id = {$customerId} AND c.assigned_lineman_id = {$linemanId}
        LIMIT 1
    ";
    $orderRes = mysqli_query($conn, $checkOrderSql);
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

            if (column_exists($conn, 'payment_history', 'order_id')) {
                $sqlHist = "INSERT INTO payment_history (order_id, transaction_id, amount_paid, payment_method, reference_no, notes, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmtHist = mysqli_prepare($conn, $sqlHist);
                mysqli_stmt_bind_param($stmtHist, 'iidsssi', $orderId, $transactionId, $amount, $paymentMethod, $referenceNo, $noteText, $createdBy);
                if (!mysqli_stmt_execute($stmtHist)) {
                    throw new Exception('Failed to insert payment history: ' . mysqli_error($conn));
                }
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
            $success = 'Amount collected successfully.';
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $error = $ex->getMessage();
        }
    }
}

$linemanSql = "SELECT l.*, z.zone_name FROM linemen l LEFT JOIN zones z ON z.id = l.zone_id WHERE l.id = {$linemanId} LIMIT 1";
$linemanRes = mysqli_query($conn, $linemanSql);
$lineman = $linemanRes ? mysqli_fetch_assoc($linemanRes) : null;
if (!$lineman) {
    echo 'Lineman record not found.';
    exit;
}

$stats = ['first'=>0,'performance'=>0,'completed'=>0,'pending_collection'=>0.00,'today_collection'=>0.00];
$statsSql = "
    SELECT o.*, c.assigned_lineman_id
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    WHERE c.assigned_lineman_id = {$linemanId}
";
$statsRes = mysqli_query($conn, $statsSql);
while ($statsRes && $row = mysqli_fetch_assoc($statsRes)) {
    $status = strtolower((string)$row['status']);
    $payment = strtolower((string)$row['payment_status']);
    if (in_array($status, ['pending','processing'], true) && $payment === 'pending') $stats['first']++;
    if ($status === 'delivered' && in_array($payment, ['pending','partial'], true)) $stats['performance']++;
    if ($status === 'delivered' && $payment === 'paid') $stats['completed']++;
    $stats['pending_collection'] += (float)$row['pending_amount'];
}
$todayCollectionSql = "
    SELECT COALESCE(SUM(ph.amount_paid), 0) AS total_today
    FROM payment_history ph
    INNER JOIN orders o ON o.id = ph.order_id
    INNER JOIN customers c ON c.id = o.customer_id
    WHERE c.assigned_lineman_id = {$linemanId} AND DATE(ph.created_at) = CURDATE()
";
$todayCollectionRes = mysqli_query($conn, $todayCollectionSql);
if ($todayCollectionRes && $tc = mysqli_fetch_assoc($todayCollectionRes)) {
    $stats['today_collection'] = (float)$tc['total_today'];
}
?>
<?php
$search = trim((string)($_GET['search'] ?? ''));
$where = " WHERE c.assigned_lineman_id = {$linemanId}  AND o.status IN ('pending','processing') AND IFNULL(o.payment_status,'pending') = 'pending'  ";
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_number LIKE '%{$safeSearch}%' OR c.shop_name LIKE '%{$safeSearch}%' OR c.customer_name LIKE '%{$safeSearch}%') ";
}

$orderSql = "
    SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, c.shop_location,
           c.customer_code, c.assigned_area
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    {$where}
    ORDER BY o.order_date DESC, o.id DESC
";
$orderRes = mysqli_query($conn, $orderSql);
$orders = [];
while ($orderRes && $row = mysqli_fetch_assoc($orderRes)) {
    $orders[] = $row;
}

$itemMap = [];
if (!empty($orders)) {
    $orderIds = array_map(static fn($o) => (int)$o['id'], $orders);
    $idsCsv = implode(',', $orderIds);
    if ($hasExtendedOrderItems) {
        $itemsSql = "
            SELECT oi.order_id, p.product_name, oi.quantity, oi.price, oi.total,
                   COALESCE(oi.hsn_code, '') AS hsn_code, COALESCE(oi.case_pack, 1) AS case_pack,
                   COALESCE(oi.cases_qty, 0) AS cases_qty, COALESCE(oi.pieces_qty, oi.quantity) AS pieces_qty,
                   COALESCE(oi.free_qty, 0) AS free_qty
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id IN ({$idsCsv})
            ORDER BY oi.order_id ASC, oi.id ASC
        ";
    } else {
        $itemsSql = "
            SELECT oi.order_id, p.product_name, oi.quantity, oi.price, oi.total,
                   '' AS hsn_code, 1 AS case_pack, 0 AS cases_qty, oi.quantity AS pieces_qty, 0 AS free_qty
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id IN ({$idsCsv})
            ORDER BY oi.order_id ASC, oi.id ASC
        ";
    }
    $itemsRes = mysqli_query($conn, $itemsSql);
    while ($itemsRes && $item = mysqli_fetch_assoc($itemsRes)) {
        $itemMap[(int)$item['order_id']][] = $item;
    }
}
$pageTitle = 'First Sale Invoice';
$currentPage = 'first_sale';
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
                <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

                <div class="row mb-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <h4 class="mb-1">First Sale Invoice</h4>
                                    <div class="text-muted">Pending and processing first sale invoices</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold"><?php echo e($lineman['full_name']); ?> | <?php echo e($lineman['employee_id']); ?></div>
                                    <div class="text-muted"><?php echo e($lineman['assigned_area']); ?><?php if (!empty($lineman['zone_name'])): ?> | Zone: <?php echo e($lineman['zone_name']); ?><?php endif; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">First Sale Invoice</p><h3><?php echo (int)$stats['first']; ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Performance Invoice</p><h3><?php echo (int)$stats['performance']; ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Completed Invoice</p><h3><?php echo (int)$stats['completed']; ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Pending Collection</p><h3><?php echo money($stats['pending_collection']); ?></h3><small class="text-muted">Today collected: <?php echo money($stats['today_collection']); ?></small></div></div></div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-10">
                                <label class="form-label">Search Order / Shop</label>
                                <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Invoice number or shop name">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">First Sale Invoice List</h5>
                            <span class="text-muted">Show order, product, status and collection</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Needed Product</th>
                                        <th>Total</th>
                                        <th>Collected</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): $orderId = (int)$order['id']; ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($order['order_number']); ?></div>
                                            <small class="text-muted"><?php echo fmtDate($order['order_date']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($order['shop_name']); ?></div>
                                            <small><?php echo e($order['customer_name']); ?> | <?php echo e($order['customer_contact']); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($itemMap[$orderId])): ?>
                                                <?php foreach (array_slice($itemMap[$orderId], 0, 3) as $it): ?>
                                                    <div><?php echo e($it['product_name']); ?> <small class="text-muted">(Qty: <?php echo (int)$it['quantity']; ?>)</small></div>
                                                <?php endforeach; ?>
                                                <?php if (count($itemMap[$orderId]) > 3): ?><small class="text-muted">+<?php echo count($itemMap[$orderId]) - 3; ?> more</small><?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No items</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo money($order['total_amount']); ?></td>
                                        <td><?php echo money($order['paid_amount']); ?></td>
                                        <td><?php echo money($order['pending_amount']); ?></td>
                                        <td>
                                            <div><span class="badge bg-secondary-subtle text-secondary"><?php echo e(ucwords((string)$order['status'])); ?></span></div>
                                            <div class="mt-1"><span class="badge bg-warning-subtle text-warning"><?php echo e(ucwords((string)$order['payment_status'])); ?></span></div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-2">
                                                <a href="../admin/view-invoice.php?id=<?php echo $orderId; ?>" class="btn btn-sm btn-primary" target="_blank">View Invoice</a>
                                                <?php if ((float)$order['pending_amount'] > 0): ?>
                                                    <button type="button" class="btn btn-sm btn-success"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#collectModal"
                                                            data-order-id="<?php echo $orderId; ?>"
                                                            data-customer-id="<?php echo (int)$order['customer_id']; ?>"
                                                            data-order-number="<?php echo e($order['order_number']); ?>"
                                                            data-customer-name="<?php echo e($order['shop_name']); ?>"
                                                            data-pending="<?php echo e($order['pending_amount']); ?>">
                                                        Collect Amount
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-success small fw-semibold">Collection completed</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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

<div class="modal fade" id="collectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Amount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="collect_payment" value="1">
                <input type="hidden" name="order_id" id="modal_order_id">
                <input type="hidden" name="customer_id" id="modal_customer_id">
                <div class="mb-3">
                    <label class="form-label">Invoice</label>
                    <input type="text" class="form-control" id="modal_order_number" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Customer</label>
                    <input type="text" class="form-control" id="modal_customer_name" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pending Amount</label>
                    <input type="text" class="form-control" id="modal_pending" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount Paid *</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="amount_paid" id="modal_amount_paid" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method *</label>
                    <select class="form-select" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reference No</label>
                    <input type="text" class="form-control" name="reference_no">
                </div>
                <div class="mb-0">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Save Collection</button>
            </div>
        </form>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
<script>
document.getElementById('collectModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('modal_order_id').value = button.getAttribute('data-order-id') || '';
    document.getElementById('modal_customer_id').value = button.getAttribute('data-customer-id') || '';
    document.getElementById('modal_order_number').value = button.getAttribute('data-order-number') || '';
    document.getElementById('modal_customer_name').value = button.getAttribute('data-customer-name') || '';
    const pending = parseFloat(button.getAttribute('data-pending') || '0');
    document.getElementById('modal_pending').value = '₹' + pending.toFixed(2);
    document.getElementById('modal_amount_paid').value = pending > 0 ? pending.toFixed(2) : '';
    document.getElementById('modal_amount_paid').max = pending.toFixed(2);
});
</script>
</body>
</html>
