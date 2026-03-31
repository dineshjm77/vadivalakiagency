<?php
session_start();
include('../config/config.php');

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');
$currentPage = 'assigned-orders';
$selfPage = basename($_SERVER['PHP_SELF'] ?? 'assigned-orders.php');

function ao_build_invoice_url(int $orderId): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/assigned-orders.php')), '/');
    return $scheme . '://' . $host . $basePath . '/view-invoice.php?id=' . $orderId;
}

function ao_clean_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 10) {
        return '91' . $digits;
    }
    return $digits;
}

if (isset($_GET['action']) && $_GET['action'] === 'items' && isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    $sql = "SELECT oi.*, p.product_name
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

if (isset($_GET['action']) && isset($_GET['order_id']) && in_array($_GET['action'], ['performance', 'convert'], true)) {
    $order_id = (int)$_GET['order_id'];
    $action = (string)$_GET['action'];

    $checkSql = "SELECT id, status, payment_status FROM orders WHERE id = ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "i", $order_id);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    $orderRow = $checkRes ? mysqli_fetch_assoc($checkRes) : null;

    if ($orderRow) {
        if ($action === 'performance') {
            $newStatus = 'delivered';
            $updateSql = "UPDATE orders SET status = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($conn, $updateSql);
            mysqli_stmt_bind_param($updateStmt, "si", $newStatus, $order_id);
            mysqli_stmt_execute($updateStmt);
            header('Location: ' . $selfPage . '?msg=performance_updated');
            exit;
        }

        if ($action === 'convert') {
            header('Location: quick-order.php?edit_order_id=' . $order_id);
            exit;
        }
    }
}

$linemen = [];
$lmRes = mysqli_query($conn, "SELECT id, full_name, employee_id FROM linemen WHERE status = 'active' ORDER BY full_name ASC");
while ($lmRes && $row = mysqli_fetch_assoc($lmRes)) {
    $linemen[] = $row;
}

$linemanId = isset($_GET['lineman_id']) ? (int)$_GET['lineman_id'] : 0;
$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'all';
$paymentFilter = isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : 'all';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$where = " WHERE c.assigned_lineman_id IS NOT NULL ";
$params = [];
$types = '';

if ($linemanId > 0) {
    $where .= " AND c.assigned_lineman_id = ? ";
    $params[] = $linemanId;
    $types .= 'i';
}
if ($statusFilter !== 'all') {
    $where .= " AND o.status = ? ";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($paymentFilter !== 'all') {
    $where .= " AND IFNULL(o.payment_status, 'pending') = ? ";
    $params[] = $paymentFilter;
    $types .= 's';
}
if ($search !== '') {
    $where .= " AND (o.order_number LIKE ? OR c.shop_name LIKE ? OR c.customer_name LIKE ? OR l.full_name LIKE ?) ";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

$sql = "SELECT
            o.id, o.order_number, o.order_date, o.total_amount, o.paid_amount, o.pending_amount,
            o.status, o.payment_status, o.created_by,
            c.shop_name, c.customer_name, c.customer_contact,
            l.full_name AS lineman_name, l.employee_id AS lineman_code,
            GROUP_CONCAT(CONCAT(p.product_name, ' (Qty: ', oi.quantity, ')') SEPARATOR ', ') AS needed_products,
            COUNT(oi.id) AS item_count
        FROM orders o
        INNER JOIN customers c ON c.id = o.customer_id
        LEFT JOIN linemen l ON l.id = c.assigned_lineman_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        LEFT JOIN products p ON p.id = oi.product_id
        {$where}
        GROUP BY o.id
        ORDER BY o.id DESC";
$stmt = mysqli_prepare($conn, $sql);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
$totalOrders = 0;
$totalAmount = 0.0;
$totalCollected = 0.0;
$totalDue = 0.0;
while ($res && $row = mysqli_fetch_assoc($res)) {
    $orders[] = $row;
    $totalOrders++;
    $totalAmount += (float)$row['total_amount'];
    $totalCollected += (float)$row['paid_amount'];
    $totalDue += (float)$row['pending_amount'];
}
?>
<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Assigned Customer Orders</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Assigned Orders</li>
                        </ol>
                    </div>
                </div>

                <?php if (isset($_GET['msg']) && $_GET['msg'] === 'performance_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> Performance invoice updated successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div><p class="text-muted mb-1">Total Orders</p><h3 class="mb-0"><?php echo $totalOrders; ?></h3></div>
                                <div class="avatar-sm"><div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-4"><i class="fas fa-file-invoice"></i></div></div>
                            </div>
                        </div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div><p class="text-muted mb-1">Order Amount</p><h4 class="mb-0">₹<?php echo number_format($totalAmount, 2); ?></h4></div>
                                <div class="avatar-sm"><div class="avatar-title bg-info-subtle text-info rounded-circle fs-4"><i class="fas fa-indian-rupee-sign"></i></div></div>
                            </div>
                        </div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div><p class="text-muted mb-1">Collected</p><h4 class="mb-0 text-success">₹<?php echo number_format($totalCollected, 2); ?></h4></div>
                                <div class="avatar-sm"><div class="avatar-title bg-success-subtle text-success rounded-circle fs-4"><i class="fas fa-money-bill-wave"></i></div></div>
                            </div>
                        </div></div>
                    </div>
                    <div class="col-md-3">
                        <div class="card"><div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div><p class="text-muted mb-1">Pending Due</p><h4 class="mb-0 text-danger">₹<?php echo number_format($totalDue, 2); ?></h4></div>
                                <div class="avatar-sm"><div class="avatar-title bg-danger-subtle text-danger rounded-circle fs-4"><i class="fas fa-wallet"></i></div></div>
                            </div>
                        </div></div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3" method="GET">
                            <div class="col-md-3">
                                <label class="form-label">Line Man</label>
                                <select class="form-select" name="lineman_id">
                                    <option value="0">All</option>
                                    <?php foreach ($linemen as $lm): ?>
                                        <option value="<?php echo (int)$lm['id']; ?>" <?php echo $linemanId === (int)$lm['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lm['full_name']); ?> (<?php echo htmlspecialchars($lm['employee_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order Status</label>
                                <select class="form-select" name="status">
                                    <option value="all" <?php echo $statusFilter==='all'?'selected':''; ?>>All</option>
                                    <option value="pending" <?php echo $statusFilter==='pending'?'selected':''; ?>>Pending</option>
                                    <option value="processing" <?php echo $statusFilter==='processing'?'selected':''; ?>>Processing</option>
                                    <option value="delivered" <?php echo $statusFilter==='delivered'?'selected':''; ?>>Delivered</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment</label>
                                <select class="form-select" name="payment_status">
                                    <option value="all" <?php echo $paymentFilter==='all'?'selected':''; ?>>All</option>
                                    <option value="pending" <?php echo $paymentFilter==='pending'?'selected':''; ?>>Pending</option>
                                    <option value="partial" <?php echo $paymentFilter==='partial'?'selected':''; ?>>Partial</option>
                                    <option value="paid" <?php echo $paymentFilter==='paid'?'selected':''; ?>>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Invoice / customer / line man">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Assigned Orders List</h5>
                            <small class="text-muted">Orders created for customers assigned to line men</small>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-centered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Line Man</th>
                                        <th>Customer</th>
                                        <th>Needed Product</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Collected</th>
                                        <th>Due</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orders)): ?>
                                        <?php foreach ($orders as $order): ?>
                                            <?php
                                                $customerPhone = ao_clean_phone((string)($order['customer_contact'] ?? ''));
                                                $invoiceUrl = ao_build_invoice_url((int)$order['id']);
                                                $waMessage = "Invoice No: " . $order['order_number'] .
                                                    "\nCustomer: " . $order['shop_name'] .
                                                    "\nTotal: ₹" . number_format((float)$order['total_amount'], 2) .
                                                    "\nPaid: ₹" . number_format((float)$order['paid_amount'], 2) .
                                                    "\nDue: ₹" . number_format((float)$order['pending_amount'], 2) .
                                                    "\nInvoice Link: " . $invoiceUrl;
                                                $waLink = $customerPhone !== '' ? ('https://wa.me/' . $customerPhone . '?text=' . rawurlencode($waMessage)) : '';
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                                    <small class="text-muted"><?php echo date('d-m-Y', strtotime($order['order_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($order['lineman_name'] ?: '-'); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['lineman_code'] ?: ''); ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($order['shop_name']); ?></div>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($order['customer_name']); ?></small>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($order['customer_contact']); ?></small>
                                                </td>
                                                <td style="max-width:280px;">
                                                    <small><?php echo htmlspecialchars($order['needed_products'] ?: '-'); ?></small>
                                                </td>
                                                <td><?php echo (int)$order['item_count']; ?></td>
                                                <td>₹<?php echo number_format((float)$order['total_amount'], 2); ?></td>
                                                <td class="text-success">₹<?php echo number_format((float)$order['paid_amount'], 2); ?></td>
                                                <td class="<?php echo (float)$order['pending_amount'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                    ₹<?php echo number_format((float)$order['pending_amount'], 2); ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <span class="badge <?php echo strtolower((string)$order['status']) === 'delivered' ? 'bg-success' : 'bg-warning'; ?>">
                                                            <?php echo ucfirst((string)$order['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="mt-1">
                                                        <span class="badge <?php
                                                            $payment = strtolower((string)$order['payment_status']);
                                                            echo $payment === 'paid' ? 'bg-success' : ($payment === 'partial' ? 'bg-info' : 'bg-danger');
                                                        ?>">
                                                            <?php echo ucfirst((string)$order['payment_status']); ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a href="view-invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">View Invoice</a>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary view-items-btn" data-id="<?php echo (int)$order['id']; ?>" data-endpoint="<?php echo htmlspecialchars($selfPage); ?>">
                                                            View Items
                                                        </button>
                                                        <?php if (strtolower((string)$order['status']) !== 'delivered' || in_array(strtolower((string)$order['payment_status']), ['pending','partial'], true)): ?>
                                                            <a href="<?php echo htmlspecialchars($selfPage); ?>?action=performance&order_id=<?php echo (int)$order['id']; ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Convert this order to performance invoice?');">
                                                                Performance Invoice
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="quick-order.php?edit_order_id=<?php echo (int)$order['id']; ?>" class="btn btn-sm btn-outline-success">
                                                            Convert To Invoice
                                                        </a>
                                                        <?php if ($waLink !== ''): ?>
                                                            <a href="<?php echo htmlspecialchars($waLink); ?>" class="btn btn-sm btn-outline-success" target="_blank">
                                                                WhatsApp
                                                            </a>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>WhatsApp</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="10" class="text-center text-muted py-4">No assigned orders found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="itemsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="modal-title">Order Items</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th>Qty</th>
                                                <th>Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsModalBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-items-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.id;
            const endpoint = this.dataset.endpoint || 'assigned-orders.php';
            fetch(endpoint + '?action=items&order_id=' + orderId)
                .then(res => res.json())
                .then(items => {
                    const body = document.getElementById('itemsModalBody');
                    body.innerHTML = '';
                    if (!items.length) {
                        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No items found.</td></tr>';
                    } else {
                        items.forEach(item => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${item.product_name || '-'}</td>
                                <td>${item.quantity || 0}</td>
                                <td>₹${parseFloat(item.price || 0).toFixed(2)}</td>
                                <td>₹${parseFloat(item.total || 0).toFixed(2)}</td>
                            `;
                            body.appendChild(tr);
                        });
                    }
                    new bootstrap.Modal(document.getElementById('itemsModal')).show();
                })
                .catch(() => {
                    const body = document.getElementById('itemsModalBody');
                    body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load items.</td></tr>';
                    new bootstrap.Modal(document.getElementById('itemsModal')).show();
                });
        });
    });
});
</script>
</body>
</html>
