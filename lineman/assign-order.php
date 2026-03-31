<?php
session_start();
include('../config/config.php');
include('includes/auth-check.php');

$pageTitle = 'Assigned Orders';
$currentPage = 'orders';

$linemanId = isset($_SESSION['lineman_id']) ? (int)$_SESSION['lineman_id'] : (int)($_SESSION['user_id'] ?? 0);
$previewLinemanId = isset($_GET['lineman_id']) ? (int)$_GET['lineman_id'] : 0;

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && $previewLinemanId > 0) {
    $linemanId = $previewLinemanId;
}

$lineman = null;
$linemanSql = "SELECT l.*, z.zone_name
               FROM linemen l
               LEFT JOIN zones z ON z.id = l.zone_id
               WHERE l.id = ? LIMIT 1";
$linemanStmt = mysqli_prepare($conn, $linemanSql);
mysqli_stmt_bind_param($linemanStmt, "i", $linemanId);
mysqli_stmt_execute($linemanStmt);
$linemanRes = mysqli_stmt_get_result($linemanStmt);
if ($linemanRes && mysqli_num_rows($linemanRes) > 0) {
    $lineman = mysqli_fetch_assoc($linemanRes);
}
if (!$lineman) {
    die('Invalid lineman session.');
}

$success_message = '';
$error_message = '';
$hasPaymentHistoryOrderId = false;
$phColRs = mysqli_query($conn, "SHOW COLUMNS FROM payment_history LIKE 'order_id'");
$hasPaymentHistoryOrderId = $phColRs && mysqli_num_rows($phColRs) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_amount'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));
    $reference_no = trim((string)($_POST['reference_no'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($order_id <= 0 || $amount_paid <= 0) {
        $error_message = 'Please enter valid collection details.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $orderSql = "
                SELECT o.*, c.id AS customer_id, c.assigned_lineman_id
                FROM orders o
                INNER JOIN customers c ON c.id = o.customer_id
                WHERE o.id = ? LIMIT 1";
            $orderStmt = mysqli_prepare($conn, $orderSql);
            mysqli_stmt_bind_param($orderStmt, "i", $order_id);
            mysqli_stmt_execute($orderStmt);
            $orderRes = mysqli_stmt_get_result($orderStmt);
            $order = $orderRes ? mysqli_fetch_assoc($orderRes) : null;

            if (!$order || (int)$order['assigned_lineman_id'] !== $linemanId) {
                throw new Exception('Invalid order selected.');
            }

            $pending = (float)$order['pending_amount'];
            if ($amount_paid > $pending) {
                throw new Exception('Amount paid cannot be more than pending amount.');
            }

            $paymentId = 'PAY' . date('YmdHis') . rand(100, 999);
            $createdBy = $linemanId;

            $txnSql = "INSERT INTO transactions
                        (customer_id, order_id, payment_id, type, amount, payment_method, reference_no, notes, created_by, created_at)
                       VALUES
                        (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
            $txnStmt = mysqli_prepare($conn, $txnSql);
            mysqli_stmt_bind_param(
                $txnStmt,
                "iisdsssi",
                $order['customer_id'],
                $order_id,
                $paymentId,
                $amount_paid,
                $payment_method,
                $reference_no,
                $notes,
                $createdBy
            );
            if (!mysqli_stmt_execute($txnStmt)) {
                throw new Exception('Failed to save transaction: ' . mysqli_error($conn));
            }
            $transactionId = mysqli_insert_id($conn);

            if ($hasPaymentHistoryOrderId) {
                $phSql = "INSERT INTO payment_history
                            (order_id, transaction_id, amount_paid, payment_method, reference_no, notes, created_by, created_at)
                          VALUES
                            (?, ?, ?, ?, ?, ?, ?, NOW())";
                $phStmt = mysqli_prepare($conn, $phSql);
                mysqli_stmt_bind_param(
                    $phStmt,
                    "iidsssi",
                    $order_id,
                    $transactionId,
                    $amount_paid,
                    $payment_method,
                    $reference_no,
                    $notes,
                    $createdBy
                );
                mysqli_stmt_execute($phStmt);
            }

            $newPaid = (float)$order['paid_amount'] + $amount_paid;
            $newPending = max(0, (float)$order['pending_amount'] - $amount_paid);
            $paymentStatus = $newPending <= 0 ? 'paid' : 'partial';

            $updOrderSql = "UPDATE orders
                            SET paid_amount = ?, pending_amount = ?, payment_status = ?, payment_date = NOW()
                            WHERE id = ?";
            $updOrderStmt = mysqli_prepare($conn, $updOrderSql);
            mysqli_stmt_bind_param($updOrderStmt, "ddsi", $newPaid, $newPending, $paymentStatus, $order_id);
            if (!mysqli_stmt_execute($updOrderStmt)) {
                throw new Exception('Failed to update order.');
            }

            $updCustSql = "UPDATE customers
                           SET current_balance = GREATEST(0, current_balance - ?)
                           WHERE id = ?";
            $updCustStmt = mysqli_prepare($conn, $updCustSql);
            mysqli_stmt_bind_param($updCustStmt, "di", $amount_paid, $order['customer_id']);
            if (!mysqli_stmt_execute($updCustStmt)) {
                throw new Exception('Failed to update customer balance.');
            }

            mysqli_commit($conn);
            header('Location: assign-order.php?msg=collection_saved' . ($previewLinemanId > 0 ? '&lineman_id=' . $previewLinemanId : ''));
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'collection_saved') {
    $success_message = 'Amount collected successfully.';
}

$filterType = isset($_GET['invoice_type']) ? trim((string)$_GET['invoice_type']) : 'all';
$paymentFilter = isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : 'all';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$orderWhere = " WHERE c.assigned_lineman_id = ? ";
$params = [$linemanId];
$types = "i";

if ($search !== '') {
    $orderWhere .= " AND (o.order_number LIKE ? OR c.shop_name LIKE ? OR c.customer_name LIKE ? OR c.customer_contact LIKE ?) ";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "ssss";
}

if ($filterType === 'first_sale') {
    $orderWhere .= " AND o.status IN ('pending','processing') AND IFNULL(o.payment_status,'pending') = 'pending' ";
} elseif ($filterType === 'performance') {
    $orderWhere .= " AND o.status = 'delivered' AND IFNULL(o.payment_status,'pending') IN ('pending','partial') ";
} elseif ($filterType === 'completed') {
    $orderWhere .= " AND o.status = 'delivered' AND IFNULL(o.payment_status,'pending') = 'paid' ";
}

if ($paymentFilter !== 'all') {
    $orderWhere .= " AND IFNULL(o.payment_status,'pending') = ? ";
    $params[] = $paymentFilter;
    $types .= "s";
}

$orderSql = "
SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, c.shop_location,
       GROUP_CONCAT(CONCAT(p.product_name, ' (Qty: ', oi.quantity, ')') SEPARATOR ', ') AS needed_products,
       COUNT(oi.id) AS item_count
FROM orders o
INNER JOIN customers c ON c.id = o.customer_id
LEFT JOIN order_items oi ON oi.order_id = o.id
LEFT JOIN products p ON p.id = oi.product_id
{$orderWhere}
GROUP BY o.id
ORDER BY o.id DESC";
$orderStmt = mysqli_prepare($conn, $orderSql);
mysqli_stmt_bind_param($orderStmt, $types, ...$params);
mysqli_stmt_execute($orderStmt);
$orderRes = mysqli_stmt_get_result($orderStmt);

$orders = [];
$totalAmount = 0.0;
$totalCollected = 0.0;
$totalDue = 0.0;
while ($orderRes && $row = mysqli_fetch_assoc($orderRes)) {
    $orders[] = $row;
    $totalAmount += (float)$row['total_amount'];
    $totalCollected += (float)$row['paid_amount'];
    $totalDue += (float)$row['pending_amount'];
}

function invoiceTypeLabel(array $order): array {
    $status = strtolower((string)$order['status']);
    $payment = strtolower((string)$order['payment_status']);
    if (in_array($status, ['pending', 'processing']) && $payment === 'pending') {
        return ['First Sale Invoice', 'badge-soft-primary'];
    }
    if ($status === 'delivered' && in_array($payment, ['pending', 'partial'])) {
        return ['Performance Invoice', 'badge-soft-warning'];
    }
    return ['Completed Invoice', 'badge-soft-success'];
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
                        <h4 class="mb-0">Assigned Orders</h4>
                        <div class="text-muted mt-1">
                            <?php echo htmlspecialchars($lineman['full_name']); ?> |
                            <?php echo htmlspecialchars($lineman['employee_id']); ?> |
                            <?php echo htmlspecialchars($lineman['zone_name'] ?: ($lineman['assigned_area'] ?: 'No Zone')); ?>
                        </div>
                    </div>
                    <div class="page-title-right text-end">
                        <div class="small text-muted">Phone: <?php echo htmlspecialchars($lineman['phone'] ?: '-'); ?></div>
                        <div class="small">Status:
                            <span class="badge <?php echo $lineman['status'] === 'active' ? 'badge-soft-success' : 'badge-soft-danger'; ?>">
                                <?php echo ucfirst($lineman['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Total Orders</div>
                                    <h3 class="mb-0"><?php echo count($orders); ?></h3>
                                </div>
                                <div class="icon badge-soft-primary"><i class="fas fa-file-invoice"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Order Amount</div>
                                    <h4 class="mb-0">₹<?php echo number_format($totalAmount, 2); ?></h4>
                                </div>
                                <div class="icon badge-soft-primary"><i class="fas fa-indian-rupee-sign"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Collected</div>
                                    <h4 class="mb-0 text-success">₹<?php echo number_format($totalCollected, 2); ?></h4>
                                </div>
                                <div class="icon badge-soft-success"><i class="fas fa-money-bill-wave"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Pending Due</div>
                                    <h4 class="mb-0 text-danger">₹<?php echo number_format($totalDue, 2); ?></h4>
                                </div>
                                <div class="icon badge-soft-danger"><i class="fas fa-wallet"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form class="row g-3" method="GET">
                            <?php if ($previewLinemanId > 0): ?>
                                <input type="hidden" name="lineman_id" value="<?php echo (int)$previewLinemanId; ?>">
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label class="form-label">Invoice Type</label>
                                <select class="form-select" name="invoice_type">
                                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="first_sale" <?php echo $filterType === 'first_sale' ? 'selected' : ''; ?>>First Sale Invoice</option>
                                    <option value="performance" <?php echo $filterType === 'performance' ? 'selected' : ''; ?>>Performance Invoice</option>
                                    <option value="completed" <?php echo $filterType === 'completed' ? 'selected' : ''; ?>>Completed Invoice</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Payment Status</label>
                                <select class="form-select" name="payment_status">
                                    <option value="all" <?php echo $paymentFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $paymentFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?php echo $paymentFilter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?php echo $paymentFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search Order / Shop / Phone</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter invoice no, shop, customer or phone">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card" id="assigned-orders">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Assigned Orders List</h5>
                            <small class="text-muted">Separate page for sidebar Assigned Orders</small>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Needed Product</th>
                                        <th>Items</th>
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
                                            <?php [$typeLabel, $typeClass] = invoiceTypeLabel($order); ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                                    <small class="text-muted"><?php echo date('d-m-Y', strtotime($order['order_date'])); ?></small>
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
                                                <td><span class="badge <?php echo $typeClass; ?>"><?php echo $typeLabel; ?></span></td>
                                                <td>
                                                    <div>
                                                        <span class="badge <?php echo strtolower((string)$order['status']) === 'delivered' ? 'badge-soft-success' : 'badge-soft-primary'; ?>">
                                                            <?php echo ucfirst((string)$order['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="mt-1">
                                                        <span class="badge <?php
                                                            $payment = strtolower((string)$order['payment_status']);
                                                            echo $payment === 'paid' ? 'badge-soft-success' : ($payment === 'partial' ? 'badge-soft-warning' : 'badge-soft-danger');
                                                        ?>">
                                                            <?php echo ucfirst((string)$order['payment_status']); ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="no-print">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a href="../view-invoice.php?id=<?php echo (int)$order['id']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            View Invoice
                                                        </a>
                                                        <?php if ((float)$order['pending_amount'] > 0): ?>
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-success collect-btn"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#collectAmountModal"
                                                                data-order-id="<?php echo (int)$order['id']; ?>"
                                                                data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                                data-customer="<?php echo htmlspecialchars($order['shop_name']); ?>"
                                                                data-pending="<?php echo number_format((float)$order['pending_amount'], 2, '.', ''); ?>">
                                                                Collect
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-success small fw-semibold">Completed</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">No assigned orders found.</td>
                                        </tr>
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
                    <input type="hidden" name="collect_amount" value="1">
                    <input type="hidden" name="order_id" id="modal_order_id">

                    <div class="mb-3">
                        <label class="form-label">Invoice</label>
                        <input type="text" class="form-control" id="modal_invoice" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" id="modal_customer" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pending Amount</label>
                        <input type="text" class="form-control" id="modal_pending" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount Paid *</label>
                        <input type="number" class="form-control" name="amount_paid" id="modal_amount_paid" step="0.01" min="0.01" required>
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
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" type="submit">Save Collection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
</body>
</html>
