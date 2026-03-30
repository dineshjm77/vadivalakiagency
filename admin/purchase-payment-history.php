<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = 'purchase-orders';
$pageTitle = 'Purchase Payment History';

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

function getPoInvoiceNo($row)
{
    if (!empty($row['invoice_no'])) {
        return trim((string)$row['invoice_no']);
    }
    return noteField($row['notes'] ?? '', 'Supplier Invoice No');
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
            header('Location: purchase-payment-history.php');
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
$payment_method = trim($_GET['payment_method'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');
$po_payment_status = trim($_GET['po_payment_status'] ?? '');

$where = [];
if ($creditor_id > 0) {
    $where[] = 'po.creditor_id = ' . $creditor_id;
}
if (in_array($payment_method, ['cash', 'bank_transfer', 'cheque', 'upi'], true)) {
    $where[] = "pp.payment_method = '" . mysqli_real_escape_string($conn, $payment_method) . "'";
}
if (in_array($po_payment_status, ['pending', 'partial', 'paid'], true)) {
    $where[] = "po.payment_status = '" . mysqli_real_escape_string($conn, $po_payment_status) . "'";
}
if ($from_date !== '') {
    $where[] = "pp.payment_date >= '" . mysqli_real_escape_string($conn, $from_date) . "'";
}
if ($to_date !== '') {
    $where[] = "pp.payment_date <= '" . mysqli_real_escape_string($conn, $to_date) . "'";
}
if ($q !== '') {
    $safe_q = mysqli_real_escape_string($conn, $q);
    $where[] = "(
        po.po_number LIKE '%{$safe_q}%'
        OR c.vendor_name LIKE '%{$safe_q}%'
        OR c.company_name LIKE '%{$safe_q}%'
        OR pp.reference_no LIKE '%{$safe_q}%'
        OR pp.notes LIKE '%{$safe_q}%'
        OR po.notes LIKE '%{$safe_q}%'
    )";
}

$where_sql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT
            pp.*,
            po.po_number,
            po.creditor_id,
            po.order_date,
            po.total_amount,
            po.paid_amount AS invoice_paid_amount,
            po.payment_status AS invoice_payment_status,
            po.order_status,
            po.notes AS po_notes,
            c.vendor_name,
            c.company_name,
            c.phone,
            c.gstin,
            au.name AS created_by_name"
        . ($po_has_invoice_no ? ", po.invoice_no" : "")
        . "
        FROM purchase_payments pp
        INNER JOIN purchase_orders po ON pp.po_id = po.id
        LEFT JOIN creditors c ON po.creditor_id = c.id
        LEFT JOIN admin_users au ON pp.created_by = au.id
        {$where_sql}
        ORDER BY pp.payment_date DESC, pp.id DESC";

$result = mysqli_query($conn, $sql);
$rows = [];
$total_payments = 0;
$total_amount = 0.00;
$today_amount = 0.00;
$unique_invoices = [];
$method_totals = [
    'cash' => 0.00,
    'bank_transfer' => 0.00,
    'cheque' => 0.00,
    'upi' => 0.00,
];
$todayDate = date('Y-m-d');

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if (!$po_has_invoice_no) {
            $row['invoice_no'] = getPoInvoiceNo(['invoice_no' => '', 'notes' => $row['po_notes']]);
        }
        $row['amount'] = (float)$row['amount'];
        $row['total_amount'] = (float)$row['total_amount'];
        $row['invoice_paid_amount'] = (float)$row['invoice_paid_amount'];
        $row['due_amount'] = max(0, round($row['total_amount'] - $row['invoice_paid_amount'], 2));
        $rows[] = $row;

        $total_payments++;
        $total_amount += $row['amount'];
        if ($row['payment_date'] === $todayDate) {
            $today_amount += $row['amount'];
        }
        $unique_invoices[$row['po_id']] = true;
        if (isset($method_totals[$row['payment_method']])) {
            $method_totals[$row['payment_method']] += $row['amount'];
        }
    }
}

$outstanding_sql = "SELECT
                        po.id,
                        po.po_number,
                        po.order_date,
                        po.total_amount,
                        po.paid_amount,
                        po.payment_status,
                        po.notes,
                        c.vendor_name,
                        c.company_name"
                    . ($po_has_invoice_no ? ", po.invoice_no" : "")
                    . "
                    FROM purchase_orders po
                    LEFT JOIN creditors c ON po.creditor_id = c.id
                    WHERE po.payment_status IN ('pending','partial')
                    ORDER BY po.order_date DESC, po.id DESC
                    LIMIT 12";
$outstanding_res = mysqli_query($conn, $outstanding_sql);
$outstanding_rows = [];
$outstanding_total_due = 0.00;
if ($outstanding_res) {
    while ($row = mysqli_fetch_assoc($outstanding_res)) {
        if (!$po_has_invoice_no) {
            $row['invoice_no'] = getPoInvoiceNo($row);
        }
        $row['total_amount'] = (float)$row['total_amount'];
        $row['paid_amount'] = (float)$row['paid_amount'];
        $row['due_amount'] = max(0, round($row['total_amount'] - $row['paid_amount'], 2));
        $outstanding_total_due += $row['due_amount'];
        $outstanding_rows[] = $row;
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

                <div class="row mb-3">
                    <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h4 class="mb-1">Purchase Payment History</h4>
                            <p class="text-muted mb-0">Track supplier repayments and outstanding invoice dues.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="add-purchase-order.php" class="btn btn-success">
                                <i class="mdi mdi-plus-circle-outline me-1"></i> New Purchase Invoice
                            </a>
                            <a href="purchase-orders.php" class="btn btn-primary">
                                <i class="mdi mdi-file-document-outline me-1"></i> Purchase Invoices
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-cash-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Payments</p>
                                        <h4 class="mb-0"><?php echo (int)$total_payments; ?></h4>
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
                                            <i class="mdi mdi-currency-inr"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Paid</p>
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
                                            <i class="mdi mdi-calendar-today"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Today Paid</p>
                                        <h4 class="mb-0"><?php echo formatCurrencyINR($today_amount); ?></h4>
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
                                            <i class="mdi mdi-file-document-multiple-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Invoices Covered</p>
                                        <h4 class="mb-0"><?php echo count($unique_invoices); ?></h4>
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
                                <form method="get" class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" name="q" class="form-control" placeholder="PO No / Supplier / Ref No" value="<?php echo e($q); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Supplier</label>
                                        <select name="creditor_id" class="form-select">
                                            <option value="0">All Suppliers</option>
                                            <?php foreach ($creditors as $creditor): ?>
                                                <option value="<?php echo (int)$creditor['id']; ?>" <?php echo $creditor_id === (int)$creditor['id'] ? 'selected' : ''; ?>>
                                                    <?php echo e($creditor['vendor_name'] ?: $creditor['company_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Payment Method</label>
                                        <select name="payment_method" class="form-select">
                                            <option value="">All Methods</option>
                                            <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="cheque" <?php echo $payment_method === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                            <option value="upi" <?php echo $payment_method === 'upi' ? 'selected' : ''; ?>>UPI</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Invoice Status</label>
                                        <select name="po_payment_status" class="form-select">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo $po_payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="partial" <?php echo $po_payment_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                            <option value="paid" <?php echo $po_payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
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
                                        <button type="submit" class="btn btn-primary">
                                            <i class="mdi mdi-magnify"></i>
                                        </button>
                                    </div>
                                    <div class="col-md-12">
                                        <a href="purchase-payment-history.php" class="btn btn-light btn-sm">Clear Filters</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div>
                                        <h5 class="card-title mb-1">Repayment Entries</h5>
                                        <p class="text-muted mb-0">Every payment saved in <code>purchase_payments</code> is shown here.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge badge-soft-secondary font-size-12">Cash: <?php echo formatCurrencyINR($method_totals['cash']); ?></span>
                                        <span class="badge badge-soft-info font-size-12">Bank: <?php echo formatCurrencyINR($method_totals['bank_transfer']); ?></span>
                                        <span class="badge badge-soft-warning font-size-12">Cheque: <?php echo formatCurrencyINR($method_totals['cheque']); ?></span>
                                        <span class="badge badge-soft-success font-size-12">UPI: <?php echo formatCurrencyINR($method_totals['upi']); ?></span>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Payment Date</th>
                                                <th>Supplier</th>
                                                <th>PO / Invoice</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                                <th>Reference No</th>
                                                <th>Invoice Status</th>
                                                <th>Created By</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($rows)): ?>
                                                <?php foreach ($rows as $index => $row): ?>
                                                    <?php
                                                        $payment_badge = 'badge-soft-warning';
                                                        if ($row['invoice_payment_status'] === 'partial') $payment_badge = 'badge-soft-info';
                                                        if ($row['invoice_payment_status'] === 'paid') $payment_badge = 'badge-soft-success';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td>
                                                            <span class="fw-medium d-block"><?php echo e(formatDateValue($row['payment_date'])); ?></span>
                                                            <small class="text-muted">Entry #<?php echo (int)$row['id']; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium d-block"><?php echo e($row['vendor_name'] ?: ($row['company_name'] ?: 'N/A')); ?></span>
                                                            <?php if (!empty($row['phone'])): ?><small class="text-muted d-block"><?php echo e($row['phone']); ?></small><?php endif; ?>
                                                            <?php if (!empty($row['gstin'])): ?><small class="text-muted">GSTIN: <?php echo e($row['gstin']); ?></small><?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium d-block"><?php echo e($row['po_number']); ?></span>
                                                            <small class="text-muted d-block">Invoice: <?php echo e($row['invoice_no'] !== '' ? $row['invoice_no'] : '-'); ?></small>
                                                            <small class="text-muted">PO Date: <?php echo e(formatDateValue($row['order_date'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium text-success d-block"><?php echo formatCurrencyINR($row['amount']); ?></span>
                                                            <small class="text-muted">Due Now: <?php echo formatCurrencyINR($row['due_amount']); ?></small>
                                                        </td>
                                                        <td><span class="badge badge-soft-primary font-size-12"><?php echo e(ucwords(str_replace('_', ' ', $row['payment_method']))); ?></span></td>
                                                        <td>
                                                            <?php if (!empty($row['reference_no'])): ?>
                                                                <span class="fw-medium d-block"><?php echo e($row['reference_no']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($row['notes'])): ?>
                                                                <small class="text-muted d-block text-truncate" style="max-width: 220px;"><?php echo e($row['notes']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $payment_badge; ?> font-size-12"><?php echo e(ucfirst($row['invoice_payment_status'])); ?></span>
                                                            <small class="text-muted d-block">Total: <?php echo formatCurrencyINR($row['total_amount']); ?></small>
                                                            <small class="text-muted">Paid: <?php echo formatCurrencyINR($row['invoice_paid_amount']); ?></small>
                                                        </td>
                                                        <td><?php echo e($row['created_by_name'] ?: 'System'); ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="purchase-orders.php?q=<?php echo urlencode($row['po_number']); ?>">
                                                                            <i class="mdi mdi-file-document-outline me-1"></i> Open Invoice Page
                                                                        </a>
                                                                    </li>
                                                                    <?php if ($row['due_amount'] > 0): ?>
                                                                        <li>
                                                                            <a class="dropdown-item add-payment" href="#"
                                                                               data-id="<?php echo (int)$row['po_id']; ?>"
                                                                               data-po-number="<?php echo e($row['po_number']); ?>"
                                                                               data-supplier="<?php echo e($row['vendor_name'] ?: ($row['company_name'] ?: 'N/A')); ?>"
                                                                               data-total="<?php echo e(number_format($row['total_amount'], 2, '.', '')); ?>"
                                                                               data-paid="<?php echo e(number_format($row['invoice_paid_amount'], 2, '.', '')); ?>"
                                                                               data-due="<?php echo e(number_format($row['due_amount'], 2, '.', '')); ?>">
                                                                                <i class="mdi mdi-cash-plus me-1"></i> Add Repayment
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-5">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-history display-4"></i>
                                                            <h5 class="mt-2">No Payment History Found</h5>
                                                            <p class="mb-0">Repayment entries will appear here after saving payments.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-alert-circle-outline me-1"></i> Outstanding Purchase Invoices
                                </h5>
                                <span class="badge badge-soft-warning font-size-12">Total Due: <?php echo formatCurrencyINR($outstanding_total_due); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Supplier</th>
                                                <th>PO / Invoice</th>
                                                <th>PO Date</th>
                                                <th>Total</th>
                                                <th>Paid</th>
                                                <th>Due</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($outstanding_rows)): ?>
                                                <?php foreach ($outstanding_rows as $index => $row): ?>
                                                    <?php $status_badge = $row['payment_status'] === 'partial' ? 'badge-soft-info' : 'badge-soft-warning'; ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><?php echo e($row['vendor_name'] ?: ($row['company_name'] ?: 'N/A')); ?></td>
                                                        <td>
                                                            <span class="fw-medium d-block"><?php echo e($row['po_number']); ?></span>
                                                            <small class="text-muted">Invoice: <?php echo e($row['invoice_no'] !== '' ? $row['invoice_no'] : '-'); ?></small>
                                                        </td>
                                                        <td><?php echo e(formatDateValue($row['order_date'])); ?></td>
                                                        <td><?php echo formatCurrencyINR($row['total_amount']); ?></td>
                                                        <td><?php echo formatCurrencyINR($row['paid_amount']); ?></td>
                                                        <td class="text-warning fw-medium"><?php echo formatCurrencyINR($row['due_amount']); ?></td>
                                                        <td><span class="badge <?php echo $status_badge; ?> font-size-12"><?php echo e(ucfirst($row['payment_status'])); ?></span></td>
                                                        <td>
                                                            <a class="btn btn-sm btn-primary add-payment" href="#"
                                                               data-id="<?php echo (int)$row['id']; ?>"
                                                               data-po-number="<?php echo e($row['po_number']); ?>"
                                                               data-supplier="<?php echo e($row['vendor_name'] ?: ($row['company_name'] ?: 'N/A')); ?>"
                                                               data-total="<?php echo e(number_format($row['total_amount'], 2, '.', '')); ?>"
                                                               data-paid="<?php echo e(number_format($row['paid_amount'], 2, '.', '')); ?>"
                                                               data-due="<?php echo e(number_format($row['due_amount'], 2, '.', '')); ?>">
                                                                <i class="mdi mdi-cash-plus me-1"></i> Add Repayment
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted py-4">No outstanding purchase invoices.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light h-100">
                                <small class="text-muted d-block">PO Number</small>
                                <strong id="payment_po_number">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light h-100">
                                <small class="text-muted d-block">Supplier</small>
                                <strong id="payment_supplier">-</strong>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light h-100">
                                <small class="text-muted d-block">Due Amount</small>
                                <strong id="payment_due_text">₹0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="payment_amount" class="form-control" min="0.01" step="0.01" required>
                            <small class="text-muted">Max due amount only.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="upi">UPI</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference No</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Cheque / UTR / Ref No">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" class="form-control" placeholder="Payment notes">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save-outline me-1"></i> Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php')?>
<script>
document.addEventListener('click', function (event) {
    const paymentBtn = event.target.closest('.add-payment');
    if (!paymentBtn) return;
    event.preventDefault();

    const poId = paymentBtn.getAttribute('data-id') || '';
    const poNumber = paymentBtn.getAttribute('data-po-number') || '-';
    const supplier = paymentBtn.getAttribute('data-supplier') || '-';
    const due = parseFloat(paymentBtn.getAttribute('data-due') || '0');

    document.getElementById('payment_po_id').value = poId;
    document.getElementById('payment_po_number').textContent = poNumber;
    document.getElementById('payment_supplier').textContent = supplier;
    document.getElementById('payment_due_text').textContent = '₹' + due.toFixed(2);

    const amountInput = document.getElementById('payment_amount');
    amountInput.value = due > 0 ? due.toFixed(2) : '';
    amountInput.max = due.toFixed(2);

    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    paymentModal.show();
});
</script>
</body>
</html>
 