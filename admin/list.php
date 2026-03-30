<?php
session_start();
include('config/config.php');
if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('money')) { function money($v){ return '₹' . number_format((float)$v, 2); } }
if (!function_exists('fmtDate')) { function fmtDate($v){ return $v ? date('d-m-Y', strtotime($v)) : '-'; } }

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'], true)) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_invoice'])) {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $orderRes = mysqli_query($conn, "SELECT id, status, payment_status FROM orders WHERE id = {$orderId} LIMIT 1");
    $order = $orderRes ? mysqli_fetch_assoc($orderRes) : null;
    if (!$order) {
        $error = 'Order not found.';
    } else {
        $sql = "UPDATE orders SET status = 'delivered', delivery_date = COALESCE(delivery_date, CURDATE()) WHERE id = {$orderId}";
        if (mysqli_query($conn, $sql)) {
            $success = 'Order converted to invoice successfully.';
        } else {
            $error = 'Failed to convert invoice: ' . mysqli_error($conn);
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$type = trim((string)($_GET['invoice_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));

$where = ' WHERE 1=1 ';
if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_number LIKE '%{$safe}%' OR c.shop_name LIKE '%{$safe}%' OR c.customer_name LIKE '%{$safe}%' OR l.full_name LIKE '%{$safe}%') ";
}
if ($status !== '') {
    $safe = mysqli_real_escape_string($conn, $status);
    $where .= " AND o.status = '{$safe}' ";
}
if ($paymentStatus !== '') {
    $safe = mysqli_real_escape_string($conn, $paymentStatus);
    $where .= " AND o.payment_status = '{$safe}' ";
}

$sql = "
    SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, l.full_name AS lineman_name,
           CASE
               WHEN o.status IN ('pending','processing') AND o.payment_status = 'pending' THEN 'First Sale Invoice'
               WHEN o.status = 'delivered' AND o.payment_status IN ('pending','partial') THEN 'Performance Invoice'
               WHEN o.status = 'delivered' AND o.payment_status = 'paid' THEN 'Completed Invoice'
               ELSE CONCAT(UCASE(LEFT(o.status,1)), SUBSTRING(o.status,2), ' Invoice')
           END AS invoice_type
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN linemen l ON l.id = c.assigned_lineman_id
    {$where}
";
if ($type !== '') {
    $safe = mysqli_real_escape_string($conn, $type);
    $sql = "SELECT * FROM ({$sql}) x WHERE x.invoice_type = '{$safe}' ORDER BY x.order_date DESC, x.id DESC";
} else {
    $sql .= " ORDER BY o.order_date DESC, o.id DESC";
}
$res = mysqli_query($conn, $sql);
$rows = [];
$cards = ['first' => 0, 'performance' => 0, 'completed' => 0, 'collection' => 0.00];
while ($res && $row = mysqli_fetch_assoc($res)) {
    $rows[] = $row;
    if ($row['invoice_type'] === 'First Sale Invoice') $cards['first']++;
    if ($row['invoice_type'] === 'Performance Invoice') $cards['performance']++;
    if ($row['invoice_type'] === 'Completed Invoice') $cards['completed']++;
    $cards['collection'] += (float)$row['paid_amount'];
}

$historySql = "
    SELECT ph.*, o.order_number, c.shop_name, c.customer_name, l.full_name AS lineman_name
    FROM payment_history ph
    INNER JOIN orders o ON o.id = ph.order_id
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN linemen l ON l.id = c.assigned_lineman_id
    ORDER BY ph.created_at DESC
    LIMIT 30
";
$historyRes = mysqli_query($conn, $historySql);
$history = [];
while ($historyRes && $h = mysqli_fetch_assoc($historyRes)) $history[] = $h;
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
<?php include('includes/pre-loader.php'); ?>
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu"><div data-simplebar class="h-100"><?php $current_page='orders-list'; include('includes/sidebar.php'); ?></div></div>
    <div class="main-content"><div class="page-content"><div class="container-fluid">
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">First Sale Invoice</p><h3><?php echo (int)$cards['first']; ?></h3></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Performance Invoice</p><h3><?php echo (int)$cards['performance']; ?></h3></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Completed Invoice</p><h3><?php echo (int)$cards['completed']; ?></h3></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><p class="text-muted mb-1">Collection History Total</p><h3><?php echo money($cards['collection']); ?></h3></div></div></div>
        </div>

        <div class="card mb-4"><div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Search</label><input type="text" class="form-control" name="search" value="<?php echo e($search); ?>"></div>
                <div class="col-md-2"><label class="form-label">Invoice Type</label><select class="form-select" name="invoice_type"><option value="">All</option><option value="First Sale Invoice" <?php echo $type==='First Sale Invoice'?'selected':''; ?>>First Sale Invoice</option><option value="Performance Invoice" <?php echo $type==='Performance Invoice'?'selected':''; ?>>Performance Invoice</option><option value="Completed Invoice" <?php echo $type==='Completed Invoice'?'selected':''; ?>>Completed Invoice</option></select></div>
                <div class="col-md-2"><label class="form-label">Order Status</label><select class="form-select" name="status"><option value="">All</option><option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option><option value="processing" <?php echo $status==='processing'?'selected':''; ?>>Processing</option><option value="delivered" <?php echo $status==='delivered'?'selected':''; ?>>Delivered</option><option value="cancelled" <?php echo $status==='cancelled'?'selected':''; ?>>Cancelled</option></select></div>
                <div class="col-md-2"><label class="form-label">Payment</label><select class="form-select" name="payment_status"><option value="">All</option><option value="pending" <?php echo $paymentStatus==='pending'?'selected':''; ?>>Pending</option><option value="partial" <?php echo $paymentStatus==='partial'?'selected':''; ?>>Partial</option><option value="paid" <?php echo $paymentStatus==='paid'?'selected':''; ?>>Paid</option></select></div>
                <div class="col-md-3"><button class="btn btn-primary w-100">Apply Filters</button></div>
            </form>
        </div></div>

        <div class="card mb-4"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Admin Side Order List</h5><span class="text-muted">Action concept: convert invoice, amount collection history, invoice type</span></div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Invoice</th><th>Customer</th><th>Lineman</th><th>Total</th><th>Paid</th><th>Due</th><th>Invoice Type</th><th>Status</th><th>Action</th></tr></thead><tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No orders found.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><div class="fw-semibold"><?php echo e($row['order_number']); ?></div><small class="text-muted"><?php echo fmtDate($row['order_date']); ?></small></td>
                    <td><div class="fw-semibold"><?php echo e($row['shop_name']); ?></div><small><?php echo e($row['customer_name']); ?> | <?php echo e($row['customer_contact']); ?></small></td>
                    <td><?php echo e($row['lineman_name'] ?: '-'); ?></td>
                    <td><?php echo money($row['total_amount']); ?></td>
                    <td><?php echo money($row['paid_amount']); ?></td>
                    <td><?php echo money($row['pending_amount']); ?></td>
                    <td><span class="badge bg-info-subtle text-info"><?php echo e($row['invoice_type']); ?></span></td>
                    <td><div><span class="badge bg-secondary-subtle text-secondary"><?php echo e(ucwords($row['status'])); ?></span></div><div class="mt-1"><span class="badge bg-warning-subtle text-warning"><?php echo e(ucwords($row['payment_status'])); ?></span></div></td>
                    <td>
                        <div class="d-flex flex-column gap-2">
                            <a href="view-invoice.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            <?php if (in_array($row['status'], ['pending','processing'], true)): ?>
                                <form method="post">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$row['id']; ?>">
                                    <button class="btn btn-sm btn-success w-100" name="convert_invoice" value="1">Convert Invoice</button>
                                </form>
                            <?php else: ?>
                                <span class="small text-success fw-semibold">Already converted</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody></table></div>
        </div></div>

        <div class="card"><div class="card-body">
            <h5 class="mb-3">Amount Collection History</h5>
            <div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Lineman</th><th>Method</th><th>Reference</th><th>Amount</th><th>Notes</th></tr></thead><tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No collection history found.</td></tr>
                <?php else: foreach ($history as $h): ?>
                    <tr><td><?php echo fmtDate($h['created_at']); ?></td><td><?php echo e($h['order_number']); ?></td><td><?php echo e($h['shop_name']); ?></td><td><?php echo e($h['lineman_name'] ?: '-'); ?></td><td><?php echo e(ucwords(str_replace('_', ' ', $h['payment_method']))); ?></td><td><?php echo e($h['reference_no']); ?></td><td><?php echo money($h['amount_paid']); ?></td><td><?php echo e($h['notes']); ?></td></tr>
                <?php endforeach; endif; ?>
            </tbody></table></div>
        </div></div>
    </div></div><?php include('includes/footer.php'); ?></div>
</div>
<?php include('includes/rightbar.php'); ?>
<?php include('includes/scripts.php'); ?>
</body></html>
