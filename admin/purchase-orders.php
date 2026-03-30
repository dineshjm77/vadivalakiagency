<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = 'purchase-orders';
$pageTitle = 'Purchase Orders';

include('config/config.php');

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatCurrencyINR($amount)
{
    return '₹' . number_format((float)$amount, 2);
}

function formatDateValue($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('d M, Y', $time) : $date;
}

function hasColumn(mysqli $conn, $table, $column)
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $res = mysqli_query($conn, $sql);
    return $res && mysqli_num_rows($res) > 0;
}

function noteField($notes, $label)
{
    if (empty($notes)) {
        return '';
    }
    $pattern = '/^' . preg_quote($label, '/') . '\s*:\s*(.+)$/mi';
    if (preg_match($pattern, $notes, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function parseItemNote($notes, $key)
{
    if (empty($notes)) {
        return '';
    }
    $pattern = '/(?:^|\|)\s*' . preg_quote($key, '/') . '\s*:\s*([^|]+)/i';
    if (preg_match($pattern, $notes, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function normalizePurchasePaymentStatus($paidAmount, $totalAmount)
{
    $paidAmount = round((float)$paidAmount, 2);
    $totalAmount = round((float)$totalAmount, 2);

    if ($paidAmount <= 0) {
        return 'pending';
    }
    if ($paidAmount >= $totalAmount) {
        return 'paid';
    }
    return 'partial';
}

function getPoInvoiceNo($row)
{
    if (!empty($row['invoice_no'])) {
        return trim((string)$row['invoice_no']);
    }
    return noteField($row['notes'] ?? '', 'Supplier Invoice No');
}

function findCreditorPurchaseId(mysqli $conn, array $poRow, $invoiceNo)
{
    $creditorId = (int)($poRow['creditor_id'] ?? 0);
    if ($creditorId <= 0) {
        return 0;
    }

    if ($invoiceNo !== '') {
        $safeInvoice = mysqli_real_escape_string($conn, $invoiceNo);
        $sql = "SELECT id
                FROM creditor_purchases
                WHERE creditor_id = {$creditorId}
                  AND invoice_no = '{$safeInvoice}'
                ORDER BY id DESC
                LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            return (int)$row['id'];
        }
    }

    $purchaseDate = mysqli_real_escape_string($conn, (string)($poRow['order_date'] ?? ''));
    $totalAmount = (float)($poRow['total_amount'] ?? 0);
    $sql = "SELECT id
            FROM creditor_purchases
            WHERE creditor_id = {$creditorId}
              AND purchase_date = '{$purchaseDate}'
              AND ABS(total_amount - {$totalAmount}) < 0.01
            ORDER BY id DESC
            LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return (int)$row['id'];
    }

    return 0;
}

$po_has_invoice_no = hasColumn($conn, 'purchase_orders', 'invoice_no');
$po_has_dd_no = hasColumn($conn, 'purchase_orders', 'dd_no');
$po_has_lr_no = hasColumn($conn, 'purchase_orders', 'lr_no');
$po_has_eway_bill_no = hasColumn($conn, 'purchase_orders', 'eway_bill_no');
$po_has_reference_no = hasColumn($conn, 'purchase_orders', 'reference_no');

if (isset($_GET['action']) && $_GET['action'] === 'items' && isset($_GET['po_id'])) {
    $po_id = (int)$_GET['po_id'];
    $header = null;
    $items = [];

    $header_sql = "SELECT po.*, c.vendor_name, c.company_name, c.phone, c.gstin"
        . ($po_has_invoice_no ? ", po.invoice_no" : "")
        . ($po_has_dd_no ? ", po.dd_no" : "")
        . ($po_has_lr_no ? ", po.lr_no" : "")
        . ($po_has_eway_bill_no ? ", po.eway_bill_no" : "")
        . ($po_has_reference_no ? ", po.reference_no" : "")
        . " FROM purchase_orders po
            LEFT JOIN creditors c ON po.creditor_id = c.id
            WHERE po.id = {$po_id}
            LIMIT 1";

    $header_res = mysqli_query($conn, $header_sql);
    if ($header_res && mysqli_num_rows($header_res) > 0) {
        $header = mysqli_fetch_assoc($header_res);
    }

    if ($header) {
        if (!$po_has_invoice_no) $header['invoice_no'] = getPoInvoiceNo($header);
        if (!$po_has_dd_no) $header['dd_no'] = noteField($header['notes'] ?? '', 'DD No');
        if (!$po_has_lr_no) $header['lr_no'] = noteField($header['notes'] ?? '', 'LR / Lorry Receipt No');
        if (!$po_has_eway_bill_no) $header['eway_bill_no'] = noteField($header['notes'] ?? '', 'E-Way Bill No');
        if (!$po_has_reference_no) $header['reference_no'] = noteField($header['notes'] ?? '', 'Reference No');
    }

    $items_sql = "SELECT * FROM purchase_order_items WHERE po_id = {$po_id} ORDER BY id ASC";
    $items_res = mysqli_query($conn, $items_sql);
    if ($items_res) {
        while ($row = mysqli_fetch_assoc($items_res)) {
            $row['hsn_code'] = parseItemNote($row['notes'] ?? '', 'HSN');
            $row['case_pack'] = parseItemNote($row['notes'] ?? '', 'Case Pack');
            $row['mrp'] = parseItemNote($row['notes'] ?? '', 'MRP');
            $row['tax_percent'] = parseItemNote($row['notes'] ?? '', 'Tax %');
            $row['tax_amount'] = parseItemNote($row['notes'] ?? '', 'Tax Amount');
            $items[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $header ? true : false,
        'header' => $header,
        'items' => $items,
    ]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'payments' && isset($_GET['po_id'])) {
    $po_id = (int)$_GET['po_id'];
    $header = null;
    $payments = [];

    $header_sql = "SELECT po.*, c.vendor_name, c.company_name, c.phone, c.gstin"
        . ($po_has_invoice_no ? ", po.invoice_no" : "")
        . " FROM purchase_orders po
            LEFT JOIN creditors c ON po.creditor_id = c.id
            WHERE po.id = {$po_id}
            LIMIT 1";
    $header_res = mysqli_query($conn, $header_sql);
    if ($header_res && mysqli_num_rows($header_res) > 0) {
        $header = mysqli_fetch_assoc($header_res);
        if (!$po_has_invoice_no) {
            $header['invoice_no'] = getPoInvoiceNo($header);
        }
    }

    $payment_sql = "SELECT * FROM purchase_payments WHERE po_id = {$po_id} ORDER BY payment_date DESC, id DESC";
    $payment_res = mysqli_query($conn, $payment_sql);
    $payment_total = 0.00;
    if ($payment_res) {
        while ($row = mysqli_fetch_assoc($payment_res)) {
            $payment_total += (float)$row['amount'];
            $payments[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $header ? true : false,
        'header' => $header,
        'payments' => $payments,
        'payment_total' => $payment_total,
        'payment_count' => count($payments),
    ]);
    exit;
}

$error = '';
$success = '';

if (isset($_SESSION['purchase_order_error'])) {
    $error = (string)$_SESSION['purchase_order_error'];
    unset($_SESSION['purchase_order_error']);
}
if (isset($_SESSION['purchase_order_success'])) {
    $success = (string)$_SESSION['purchase_order_success'];
    unset($_SESSION['purchase_order_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action_type'] ?? '') === 'add_payment') {
    $po_id = (int)($_POST['po_id'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);

    $allowed_methods = ['cash', 'bank_transfer', 'cheque', 'upi'];

    if ($po_id <= 0) {
        $error = 'Invalid purchase invoice selected.';
    } elseif ($payment_date === '') {
        $error = 'Payment date is required.';
    } elseif ($amount <= 0) {
        $error = 'Payment amount must be greater than zero.';
    } elseif (!in_array($payment_method, $allowed_methods, true)) {
        $error = 'Invalid payment method selected.';
    }

    $poRow = null;
    if ($error === '') {
        $po_sql = "SELECT po.*, c.vendor_name, c.company_name"
            . ($po_has_invoice_no ? ", po.invoice_no" : "")
            . " FROM purchase_orders po
                LEFT JOIN creditors c ON po.creditor_id = c.id
                WHERE po.id = {$po_id}
                LIMIT 1";
        $po_res = mysqli_query($conn, $po_sql);
        if ($po_res && mysqli_num_rows($po_res) > 0) {
            $poRow = mysqli_fetch_assoc($po_res);
            if (!$po_has_invoice_no) {
                $poRow['invoice_no'] = getPoInvoiceNo($poRow);
            }
        } else {
            $error = 'Purchase invoice not found.';
        }
    }

    if ($error === '' && $poRow) {
        $currentPaid = (float)$poRow['paid_amount'];
        $totalAmount = (float)$poRow['total_amount'];
        $dueAmount = max(0, round($totalAmount - $currentPaid, 2));

        if ($dueAmount <= 0) {
            $error = 'This purchase invoice is already fully paid.';
        } elseif ($amount > $dueAmount) {
            $error = 'Payment amount cannot be greater than due amount (' . formatCurrencyINR($dueAmount) . ').';
        }
    }

    if ($error === '' && $poRow) {
        mysqli_begin_transaction($conn);
        try {
            $created_by = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            $poNumber = (string)$poRow['po_number'];
            $creditorId = (int)$poRow['creditor_id'];
            $newPaidAmount = round((float)$poRow['paid_amount'] + $amount, 2);
            $newStatus = normalizePurchasePaymentStatus($newPaidAmount, (float)$poRow['total_amount']);
            $invoiceNo = trim((string)($poRow['invoice_no'] ?? ''));

            $purchasePaymentStmt = mysqli_prepare(
                $conn,
                "INSERT INTO purchase_payments (
                    po_id, payment_date, amount, payment_method, reference_no, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$purchasePaymentStmt) {
                throw new Exception('Unable to prepare purchase payment insert query.');
            }
            $paymentNotes = $notes !== '' ? $notes : ('Repayment for ' . $poNumber);
            mysqli_stmt_bind_param(
                $purchasePaymentStmt,
                'isdsssi',
                $po_id,
                $payment_date,
                $amount,
                $payment_method,
                $reference_no,
                $paymentNotes,
                $created_by
            );
            if (!mysqli_stmt_execute($purchasePaymentStmt)) {
                throw new Exception('Failed to save purchase repayment: ' . mysqli_stmt_error($purchasePaymentStmt));
            }
            mysqli_stmt_close($purchasePaymentStmt);

            $creditorPaymentStmt = mysqli_prepare(
                $conn,
                "INSERT INTO creditor_payments (
                    creditor_id, payment_date, amount, payment_method, reference_no, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$creditorPaymentStmt) {
                throw new Exception('Unable to prepare creditor payment insert query.');
            }
            $creditorNotes = 'Purchase repayment for ' . $poNumber . ($invoiceNo !== '' ? (' / Invoice ' . $invoiceNo) : '');
            if ($notes !== '') {
                $creditorNotes .= ' | ' . $notes;
            }
            mysqli_stmt_bind_param(
                $creditorPaymentStmt,
                'isdsssi',
                $creditorId,
                $payment_date,
                $amount,
                $payment_method,
                $reference_no,
                $creditorNotes,
                $created_by
            );
            if (!mysqli_stmt_execute($creditorPaymentStmt)) {
                throw new Exception('Failed to save supplier payment entry: ' . mysqli_stmt_error($creditorPaymentStmt));
            }
            mysqli_stmt_close($creditorPaymentStmt);

            $purchaseOrderStmt = mysqli_prepare(
                $conn,
                "UPDATE purchase_orders
                 SET paid_amount = ?, payment_status = ?
                 WHERE id = ?"
            );
            if (!$purchaseOrderStmt) {
                throw new Exception('Unable to prepare purchase order update query.');
            }
            mysqli_stmt_bind_param($purchaseOrderStmt, 'dsi', $newPaidAmount, $newStatus, $po_id);
            if (!mysqli_stmt_execute($purchaseOrderStmt)) {
                throw new Exception('Failed to update purchase invoice payment status: ' . mysqli_stmt_error($purchaseOrderStmt));
            }
            mysqli_stmt_close($purchaseOrderStmt);

            $creditorUpdateStmt = mysqli_prepare(
                $conn,
                "UPDATE creditors
                 SET total_paid = total_paid + ?,
                     current_balance = current_balance - ?
                 WHERE id = ?"
            );
            if (!$creditorUpdateStmt) {
                throw new Exception('Unable to prepare creditor balance update query.');
            }
            mysqli_stmt_bind_param($creditorUpdateStmt, 'ddi', $amount, $amount, $creditorId);
            if (!mysqli_stmt_execute($creditorUpdateStmt)) {
                throw new Exception('Failed to update supplier balance: ' . mysqli_stmt_error($creditorUpdateStmt));
            }
            mysqli_stmt_close($creditorUpdateStmt);

            $creditorPurchaseId = findCreditorPurchaseId($conn, $poRow, $invoiceNo);
            if ($creditorPurchaseId > 0) {
                $creditorPurchaseStmt = mysqli_prepare(
                    $conn,
                    "UPDATE creditor_purchases
                     SET paid_amount = LEAST(total_amount, paid_amount + ?),
                         payment_status = ?
                     WHERE id = ?"
                );
                if ($creditorPurchaseStmt) {
                    mysqli_stmt_bind_param($creditorPurchaseStmt, 'dsi', $amount, $newStatus, $creditorPurchaseId);
                    if (!mysqli_stmt_execute($creditorPurchaseStmt)) {
                        throw new Exception('Failed to update creditor purchase payment status: ' . mysqli_stmt_error($creditorPurchaseStmt));
                    }
                    mysqli_stmt_close($creditorPurchaseStmt);
                }
            }

            mysqli_commit($conn);
            $_SESSION['purchase_order_success'] = 'Repayment saved successfully for ' . $poNumber . '.';
            header('Location: purchase-orders.php');
            exit;
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $error = $ex->getMessage();
        }
    }
}

$creditors = [];
$creditor_res = mysqli_query($conn, "SELECT id, vendor_name, company_name FROM creditors WHERE status = 'active' ORDER BY vendor_name ASC");
if ($creditor_res) {
    while ($row = mysqli_fetch_assoc($creditor_res)) {
        $creditors[] = $row;
    }
}

$q = trim($_GET['q'] ?? '');
$creditor_id = (int)($_GET['creditor_id'] ?? 0);
$payment_status = trim($_GET['payment_status'] ?? '');
$order_status = trim($_GET['order_status'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where = [];
if ($creditor_id > 0) {
    $where[] = 'po.creditor_id = ' . $creditor_id;
}
if (in_array($payment_status, ['pending', 'partial', 'paid'], true)) {
    $where[] = "po.payment_status = '" . mysqli_real_escape_string($conn, $payment_status) . "'";
}
if (in_array($order_status, ['draft', 'confirmed', 'shipped', 'delivered', 'cancelled'], true)) {
    $where[] = "po.order_status = '" . mysqli_real_escape_string($conn, $order_status) . "'";
}
if ($from_date !== '') {
    $where[] = "po.order_date >= '" . mysqli_real_escape_string($conn, $from_date) . "'";
}
if ($to_date !== '') {
    $where[] = "po.order_date <= '" . mysqli_real_escape_string($conn, $to_date) . "'";
}
if ($q !== '') {
    $safe_q = mysqli_real_escape_string($conn, $q);
    $where[] = "(
        po.po_number LIKE '%{$safe_q}%'
        OR c.vendor_name LIKE '%{$safe_q}%'
        OR c.company_name LIKE '%{$safe_q}%'
        OR po.notes LIKE '%{$safe_q}%'
        OR EXISTS (
            SELECT 1
            FROM purchase_order_items poi2
            WHERE poi2.po_id = po.id
              AND (poi2.product_name LIKE '%{$safe_q}%' OR poi2.notes LIKE '%{$safe_q}%')
        )
    )";
}

$where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT
            po.*,
            c.vendor_name,
            c.company_name,
            c.phone,
            c.gstin,
            COALESCE(item_summary.item_count, 0) AS item_count,
            COALESCE(item_summary.total_qty, 0) AS total_qty,
            COALESCE(payment_summary.payment_count, 0) AS payment_count,
            payment_summary.last_payment_date"
        . ($po_has_invoice_no ? ", po.invoice_no" : "")
        . ($po_has_dd_no ? ", po.dd_no" : "")
        . ($po_has_lr_no ? ", po.lr_no" : "")
        . ($po_has_eway_bill_no ? ", po.eway_bill_no" : "")
        . ($po_has_reference_no ? ", po.reference_no" : "")
        . "
        FROM purchase_orders po
        LEFT JOIN creditors c ON po.creditor_id = c.id
        LEFT JOIN (
            SELECT po_id, COUNT(*) AS item_count, SUM(quantity) AS total_qty
            FROM purchase_order_items
            GROUP BY po_id
        ) item_summary ON item_summary.po_id = po.id
        LEFT JOIN (
            SELECT po_id, COUNT(*) AS payment_count, MAX(payment_date) AS last_payment_date
            FROM purchase_payments
            GROUP BY po_id
        ) payment_summary ON payment_summary.po_id = po.id
        {$where_sql}
        ORDER BY po.created_at DESC, po.id DESC";

$result = mysqli_query($conn, $sql);
$rows = [];

$total_invoices = 0;
$total_amount = 0.00;
$total_paid = 0.00;
$total_due = 0.00;
$pending_count = 0;
$partial_count = 0;
$paid_count = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!$po_has_invoice_no) $row['invoice_no'] = getPoInvoiceNo($row);
        if (!$po_has_dd_no) $row['dd_no'] = noteField($row['notes'] ?? '', 'DD No');
        if (!$po_has_lr_no) $row['lr_no'] = noteField($row['notes'] ?? '', 'LR / Lorry Receipt No');
        if (!$po_has_eway_bill_no) $row['eway_bill_no'] = noteField($row['notes'] ?? '', 'E-Way Bill No');
        if (!$po_has_reference_no) $row['reference_no'] = noteField($row['notes'] ?? '', 'Reference No');

        $row['paid_amount'] = (float)$row['paid_amount'];
        $row['total_amount'] = (float)$row['total_amount'];
        $row['due_amount'] = max(0, round($row['total_amount'] - $row['paid_amount'], 2));
        $rows[] = $row;

        $total_invoices++;
        $total_amount += $row['total_amount'];
        $total_paid += $row['paid_amount'];
        $total_due += $row['due_amount'];

        if ($row['payment_status'] === 'pending') $pending_count++;
        if ($row['payment_status'] === 'partial') $partial_count++;
        if ($row['payment_status'] === 'paid') $paid_count++;
    }
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

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-1"></i><?php echo e($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-1"></i><?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-file-document-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Invoices</p>
                                        <h4 class="mb-0" id="stat-total-invoices"><?php echo (int)$total_invoices; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle text-success rounded-2 fs-2">
                                            <i class="mdi mdi-cash-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Amount</p>
                                        <h4 class="mb-0"><?php echo formatCurrencyINR($total_amount); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-info-subtle text-info rounded-2 fs-2">
                                            <i class="mdi mdi-wallet-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Paid Amount</p>
                                        <h4 class="mb-0"><?php echo formatCurrencyINR($total_paid); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2">
                                            <i class="mdi mdi-alert-circle-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Pending Due</p>
                                        <h4 class="mb-0"><?php echo formatCurrencyINR($total_due); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="get" class="row g-3 align-items-end mb-4">
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="q" id="searchInput" class="form-control" placeholder="PO no, supplier, product, invoice..." value="<?php echo e($q); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Supplier</label>
                                        <select name="creditor_id" class="form-select">
                                            <option value="">All Suppliers</option>
                                            <?php foreach ($creditors as $creditor): ?>
                                                <option value="<?php echo (int)$creditor['id']; ?>" <?php echo $creditor_id === (int)$creditor['id'] ? 'selected' : ''; ?>>
                                                    <?php echo e($creditor['vendor_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Payment Status</label>
                                        <select name="payment_status" class="form-select">
                                            <option value="">All</option>
                                            <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="partial" <?php echo $payment_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                            <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Order Status</label>
                                        <select name="order_status" class="form-select">
                                            <option value="">All</option>
                                            <option value="draft" <?php echo $order_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="confirmed" <?php echo $order_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="shipped" <?php echo $order_status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $order_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $order_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">From</label>
                                        <input type="date" name="from_date" class="form-control" value="<?php echo e($from_date); ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">To</label>
                                        <input type="date" name="to_date" class="form-control" value="<?php echo e($to_date); ?>">
                                    </div>
                                    <div class="col-md-1 d-grid">
                                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter me-1"></i>Filter</button>
                                    </div>
                                    <div class="col-md-12 d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                                        <div>
                                            <span class="badge badge-soft-warning me-2">Pending: <?php echo (int)$pending_count; ?></span>
                                            <span class="badge badge-soft-info me-2">Partial: <?php echo (int)$partial_count; ?></span>
                                            <span class="badge badge-soft-success">Paid: <?php echo (int)$paid_count; ?></span>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="purchase-orders.php" class="btn btn-light">Clear</a>
                                            <a href="add-purchase-order.php" class="btn btn-success"><i class="mdi mdi-plus-circle-outline me-1"></i> Create Purchase Invoice</a>
                                        </div>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="purchaseOrdersTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>PO No</th>
                                                <th>Invoice No</th>
                                                <th>Supplier</th>
                                                <th>Order Date</th>
                                                <th>Items</th>
                                                <th>Total</th>
                                                <th>Paid</th>
                                                <th>Due</th>
                                                <th>Payment</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="purchaseOrdersBody">
                                            <?php if (!empty($rows)): ?>
                                                <?php foreach ($rows as $index => $row): ?>
                                                    <?php
                                                        $payment_badge = 'badge-soft-warning';
                                                        if ($row['payment_status'] === 'partial') $payment_badge = 'badge-soft-info';
                                                        if ($row['payment_status'] === 'paid') $payment_badge = 'badge-soft-success';

                                                        $status_badge = 'badge-soft-secondary';
                                                        if ($row['order_status'] === 'confirmed') $status_badge = 'badge-soft-primary';
                                                        if ($row['order_status'] === 'shipped') $status_badge = 'badge-soft-info';
                                                        if ($row['order_status'] === 'delivered') $status_badge = 'badge-soft-success';
                                                        if ($row['order_status'] === 'cancelled') $status_badge = 'badge-soft-danger';
                                                    ?>
                                                    <tr data-search="<?php echo e(strtolower(($row['po_number'] ?? '') . ' ' . ($row['invoice_no'] ?? '') . ' ' . ($row['vendor_name'] ?? '') . ' ' . ($row['company_name'] ?? ''))); ?>">
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td>
                                                            <span class="fw-semibold d-block"><?php echo e($row['po_number']); ?></span>
                                                            <?php if (!empty($row['reference_no'])): ?>
                                                                <small class="text-muted">Ref: <?php echo e($row['reference_no']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo e($row['invoice_no'] ?: '-'); ?></span>
                                                            <?php if (!empty($row['eway_bill_no'])): ?>
                                                                <small class="d-block text-muted">EWB: <?php echo e($row['eway_bill_no']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium d-block"><?php echo e($row['vendor_name'] ?: ($row['company_name'] ?: 'N/A')); ?></span>
                                                            <small class="text-muted d-block"><?php echo e($row['phone'] ?: ''); ?></small>
                                                            <?php if (!empty($row['gstin'])): ?>
                                                                <small class="text-muted d-block">GSTIN: <?php echo e($row['gstin']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="d-block"><?php echo e(formatDateValue($row['order_date'])); ?></span>
                                                            <small class="text-muted">Delivery: <?php echo e(formatDateValue($row['delivery_date'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium d-block"><?php echo (int)$row['item_count']; ?> item(s)</span>
                                                            <small class="text-muted"><?php echo number_format((float)$row['total_qty']); ?> pcs</small>
                                                        </td>
                                                        <td class="fw-medium"><?php echo formatCurrencyINR($row['total_amount']); ?></td>
                                                        <td class="text-success fw-medium">
                                                            <?php echo formatCurrencyINR($row['paid_amount']); ?>
                                                            <small class="d-block text-muted"><?php echo (int)$row['payment_count']; ?> payment(s)</small>
                                                        </td>
                                                        <td class="text-warning fw-medium"><?php echo formatCurrencyINR($row['due_amount']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $payment_badge; ?> font-size-12"><?php echo e(ucfirst($row['payment_status'])); ?></span>
                                                            <?php if (!empty($row['last_payment_date'])): ?>
                                                                <small class="d-block text-muted mt-1">Last: <?php echo e(formatDateValue($row['last_payment_date'])); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $status_badge; ?> font-size-12"><?php echo e(ucfirst($row['order_status'])); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item view-items" href="#" data-id="<?php echo (int)$row['id']; ?>">
                                                                            <i class="mdi mdi-eye-outline me-1"></i> View Items
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item add-payment" href="#"
                                                                           data-id="<?php echo (int)$row['id']; ?>"
                                                                           data-po-number="<?php echo e($row['po_number']); ?>"
                                                                           data-supplier="<?php echo e($row['vendor_name'] ?: ($row['company_name'] ?: 'N/A')); ?>"
                                                                           data-total="<?php echo e(number_format($row['total_amount'], 2, '.', '')); ?>"
                                                                           data-paid="<?php echo e(number_format($row['paid_amount'], 2, '.', '')); ?>"
                                                                           data-due="<?php echo e(number_format($row['due_amount'], 2, '.', '')); ?>"
                                                                           data-status="<?php echo e($row['payment_status']); ?>">
                                                                            <i class="mdi mdi-cash-plus me-1"></i> Add Repayment
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item view-payments" href="#" data-id="<?php echo (int)$row['id']; ?>">
                                                                            <i class="mdi mdi-history me-1"></i> Payment History
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="add-purchase-order.php">
                                                                            <i class="mdi mdi-plus-circle-outline me-1"></i> New Invoice
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="12" class="text-center py-5">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-file-document-outline display-4"></i>
                                                            <h5 class="mt-2">No Purchase Orders Found</h5>
                                                            <p class="mb-3">Create your first purchase invoice entry from the purchase module.</p>
                                                            <a href="add-purchase-order.php" class="btn btn-success">
                                                                <i class="mdi mdi-plus-circle-outline me-1"></i> Create Purchase Invoice
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3 text-muted" id="tableCountText">
                                    Showing <?php echo (int)$total_invoices; ?> purchase order(s)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include('includes/footer.php')?>
    </div>
</div>

<?php include('includes/rightbar.php')?>

<div class="modal fade" id="itemsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="poHeaderDetails" class="mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>HSN</th>
                                <th>Case Pack</th>
                                <th>Qty</th>
                                <th>Rate/Pcs</th>
                                <th>MRP</th>
                                <th>Tax %</th>
                                <th>Tax Amt</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="itemsModalBody">
                            <tr><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add Purchase Repayment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action_type" value="add_payment">
                    <input type="hidden" name="po_id" id="payment_po_id">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light">
                                <small class="text-muted d-block">PO Number</small>
                                <strong id="payment_po_number">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light">
                                <small class="text-muted d-block">Supplier</small>
                                <strong id="payment_supplier">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <small class="text-muted d-block">Invoice Total</small>
                                <strong id="payment_total">₹0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <small class="text-muted d-block">Already Paid</small>
                                <strong id="payment_paid">₹0.00</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <small class="text-muted d-block">Balance Due</small>
                                <strong class="text-warning" id="payment_due">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?php echo e(date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="payment_amount" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" id="payment_reference_no" class="form-control" placeholder="Cheque no / UTR / UPI ref">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" id="payment_notes" class="form-control" placeholder="Optional notes">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save-outline me-1"></i> Save Repayment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Payment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="paymentHistoryHeader" class="mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Notes</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody id="paymentHistoryBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/scripts.php')?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const rows = () => Array.from(document.querySelectorAll('#purchaseOrdersBody tr'));
    const tableCountText = document.getElementById('tableCountText');
    const paymentModalEl = document.getElementById('paymentModal');
    const paymentModal = new bootstrap.Modal(paymentModalEl);
    const itemsModal = new bootstrap.Modal(document.getElementById('itemsModal'));
    const paymentHistoryModal = new bootstrap.Modal(document.getElementById('paymentHistoryModal'));

    function updateVisibleCount() {
        const visible = rows().filter(row => row.style.display !== 'none').length;
        if (tableCountText) {
            tableCountText.textContent = `Showing ${visible} purchase order(s)`;
        }
    }

    function formatMoney(value) {
        const num = Number(value || 0);
        return `₹${num.toFixed(2)}`;
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const q = this.value.trim().toLowerCase();
            rows().forEach(function (row) {
                const hay = (row.getAttribute('data-search') || row.textContent).toLowerCase();
                row.style.display = hay.includes(q) ? '' : 'none';
            });
            updateVisibleCount();
        });
    }

    document.addEventListener('click', function (e) {
        const itemsTrigger = e.target.closest('.view-items');
        if (itemsTrigger) {
            e.preventDefault();
            const poId = itemsTrigger.getAttribute('data-id');
            const body = document.getElementById('itemsModalBody');
            const header = document.getElementById('poHeaderDetails');
            body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading...</td></tr>';
            header.innerHTML = '';
            itemsModal.show();

            fetch('purchase-orders.php?action=items&po_id=' + encodeURIComponent(poId))
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Unable to load purchase order details.</td></tr>';
                        return;
                    }

                    const h = data.header || {};
                    header.innerHTML = `
                        <div class="row g-3">
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">PO No</small><strong>${h.po_number || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Invoice No</small><strong>${h.invoice_no || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Supplier</small><strong>${h.vendor_name || h.company_name || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Order Date</small><strong>${h.order_date || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">DD No</small><strong>${h.dd_no || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">LR No</small><strong>${h.lr_no || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">E-Way Bill</small><strong>${h.eway_bill_no || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Total / Paid / Due</small><strong>${formatMoney(h.total_amount || 0)} / ${formatMoney(h.paid_amount || 0)} / ${formatMoney((Number(h.total_amount || 0) - Number(h.paid_amount || 0)) || 0)}</strong></div></div>
                        </div>`;

                    if (!data.items || !data.items.length) {
                        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No item rows found.</td></tr>';
                        return;
                    }

                    body.innerHTML = data.items.map((item, index) => `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.product_name || '-'}</td>
                            <td>${item.hsn_code || '-'}</td>
                            <td>${item.case_pack || '-'}</td>
                            <td>${item.quantity || 0}</td>
                            <td>${formatMoney(item.unit_price || 0)}</td>
                            <td>${item.mrp || '-'}</td>
                            <td>${item.tax_percent || '-'}</td>
                            <td>${item.tax_amount || '-'}</td>
                            <td>${formatMoney(item.total || 0)}</td>
                        </tr>
                    `).join('');
                })
                .catch(() => {
                    body.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Network error while loading items.</td></tr>';
                });
            return;
        }

        const addPaymentTrigger = e.target.closest('.add-payment');
        if (addPaymentTrigger) {
            e.preventDefault();
            const status = addPaymentTrigger.getAttribute('data-status') || '';
            const due = Number(addPaymentTrigger.getAttribute('data-due') || 0);

            document.getElementById('payment_po_id').value = addPaymentTrigger.getAttribute('data-id') || '';
            document.getElementById('payment_po_number').textContent = addPaymentTrigger.getAttribute('data-po-number') || '-';
            document.getElementById('payment_supplier').textContent = addPaymentTrigger.getAttribute('data-supplier') || '-';
            document.getElementById('payment_total').textContent = formatMoney(addPaymentTrigger.getAttribute('data-total') || 0);
            document.getElementById('payment_paid').textContent = formatMoney(addPaymentTrigger.getAttribute('data-paid') || 0);
            document.getElementById('payment_due').textContent = formatMoney(due);
            document.getElementById('payment_amount').value = due > 0 ? due.toFixed(2) : '';
            document.getElementById('payment_amount').max = due > 0 ? due.toFixed(2) : '';
            document.getElementById('payment_reference_no').value = '';
            document.getElementById('payment_notes').value = '';
            document.getElementById('payment_method').value = 'cash';
            document.getElementById('payment_date').value = '<?php echo e(date('Y-m-d')); ?>';

            if (status === 'paid' || due <= 0) {
                alert('This purchase invoice is already fully paid.');
                return;
            }

            paymentModal.show();
            return;
        }

        const paymentsTrigger = e.target.closest('.view-payments');
        if (paymentsTrigger) {
            e.preventDefault();
            const poId = paymentsTrigger.getAttribute('data-id');
            const header = document.getElementById('paymentHistoryHeader');
            const body = document.getElementById('paymentHistoryBody');
            header.innerHTML = '';
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>';
            paymentHistoryModal.show();

            fetch('purchase-orders.php?action=payments&po_id=' + encodeURIComponent(poId))
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Unable to load payment history.</td></tr>';
                        return;
                    }

                    const h = data.header || {};
                    const total = Number(h.total_amount || 0);
                    const paid = Number(h.paid_amount || 0);
                    const due = Math.max(0, total - paid);
                    header.innerHTML = `
                        <div class="row g-3">
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">PO No</small><strong>${h.po_number || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Invoice No</small><strong>${h.invoice_no || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Supplier</small><strong>${h.vendor_name || h.company_name || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Total / Paid / Due</small><strong>${formatMoney(total)} / ${formatMoney(paid)} / ${formatMoney(due)}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Payment Status</small><strong>${h.payment_status || '-'}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Payments Count</small><strong>${data.payment_count || 0}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Recorded Payments</small><strong>${formatMoney(data.payment_total || 0)}</strong></div></div>
                            <div class="col-md-3"><div class="border rounded p-2"><small class="text-muted d-block">Order Date</small><strong>${h.order_date || '-'}</strong></div></div>
                        </div>`;

                    if (!data.payments || !data.payments.length) {
                        body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No repayment entries found.</td></tr>';
                        return;
                    }

                    body.innerHTML = data.payments.map((payment, index) => `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${payment.payment_date || '-'}</td>
                            <td>${formatMoney(payment.amount || 0)}</td>
                            <td>${(payment.payment_method || '-').replace('_', ' ')}</td>
                            <td>${payment.reference_no || '-'}</td>
                            <td>${payment.notes || '-'}</td>
                            <td>${payment.created_at || '-'}</td>
                        </tr>
                    `).join('');
                })
                .catch(() => {
                    body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Network error while loading payment history.</td></tr>';
                });
        }
    });

    updateVisibleCount();
});
</script>
</body>
</html>
