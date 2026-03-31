 <?php
session_start();
include('../config/config.php');
include('includes/auth-check.php');

$pageTitle = 'Create Order';
$currentPage = 'create-order';

function lm_column_exists(mysqli $conn, string $table, string $column): bool {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $rs && mysqli_num_rows($rs) > 0;
}

function lm_generate_order_number(mysqli $conn): string {
    $month = (int)date('n');
    $startYear = (int)date($month >= 4 ? 'y' : 'y', $month >= 4 ? time() : strtotime('-1 year'));
    $endYear = (int)date($month >= 4 ? 'y' : 'y', $month >= 4 ? strtotime('+1 year') : time());
    $prefix = sprintf('%02d-%02d/', $startYear, $endYear);
    $safePrefix = mysqli_real_escape_string($conn, $prefix);
    $lastNo = 19000;
    $rs = mysqli_query($conn, "SELECT order_number FROM orders WHERE order_number LIKE '{$safePrefix}%' ORDER BY id DESC LIMIT 1");
    if ($rs && $row = mysqli_fetch_assoc($rs)) {
        if (preg_match('~/([0-9]+)$~', (string)$row['order_number'], $m)) {
            $lastNo = (int)$m[1];
        }
    }
    return $prefix . ($lastNo + 1);
}

$linemanId = isset($_SESSION['lineman_id']) ? (int)$_SESSION['lineman_id'] : (int)($_SESSION['user_id'] ?? 0);
$linemanSql = "SELECT l.*, z.zone_name
               FROM linemen l
               LEFT JOIN zones z ON z.id = l.zone_id
               WHERE l.id = ? LIMIT 1";
$linemanStmt = mysqli_prepare($conn, $linemanSql);
mysqli_stmt_bind_param($linemanStmt, "i", $linemanId);
mysqli_stmt_execute($linemanStmt);
$linemanRes = mysqli_stmt_get_result($linemanStmt);
$lineman = $linemanRes ? mysqli_fetch_assoc($linemanRes) : null;

if (!$lineman) {
    die('Invalid lineman session.');
}

$hasCustomerGst = lm_column_exists($conn, 'customers', 'gst_no');
$hasCustomerBeat = lm_column_exists($conn, 'customers', 'assigned_area');
$hasProductHsn = lm_column_exists($conn, 'products', 'hsn_code');
$hasProductCasePack = lm_column_exists($conn, 'products', 'case_pack');
$hasProductMrp = lm_column_exists($conn, 'products', 'mrp');
$hasProductGst = lm_column_exists($conn, 'products', 'gst_rate');

$extendedOrderItemColumns = [
    'hsn_code', 'case_pack', 'cases_qty', 'pieces_qty', 'free_qty', 'mrp',
    'base_rate', 'discount_amount', 'gst_rate', 'net_rate', 'taxable_value',
    'cgst_amount', 'sgst_amount'
];
$hasExtendedOrderItems = true;
foreach ($extendedOrderItemColumns as $col) {
    if (!lm_column_exists($conn, 'order_items', $col)) {
        $hasExtendedOrderItems = false;
        break;
    }
}

$customerSelect = "c.id, c.customer_code, c.shop_name, c.customer_name, c.customer_contact,
                   c.shop_location, c.current_balance, c.payment_terms, c.zone_id";
$customerSelect .= $hasCustomerGst ? ", COALESCE(c.gst_no, '') AS gst_no" : ", '' AS gst_no";
$customerSelect .= $hasCustomerBeat ? ", COALESCE(c.assigned_area, '') AS assigned_area" : ", '' AS assigned_area";

$customersSql = "SELECT $customerSelect
                 FROM customers c
                 WHERE c.status = 'active' AND c.assigned_lineman_id = ?
                 ORDER BY c.shop_name ASC";
$customersStmt = mysqli_prepare($conn, $customersSql);
mysqli_stmt_bind_param($customersStmt, "i", $linemanId);
mysqli_stmt_execute($customersStmt);
$customersRes = mysqli_stmt_get_result($customersStmt);
$customers = [];
$customerIds = [];
while ($customersRes && $row = mysqli_fetch_assoc($customersRes)) {
    $customers[] = $row;
    $customerIds[(int)$row['id']] = true;
}

$productSelect = "id, product_code, product_name, customer_price, quantity";
$productSelect .= $hasProductHsn ? ", COALESCE(hsn_code, '') AS hsn_code" : ", '' AS hsn_code";
$productSelect .= $hasProductCasePack ? ", COALESCE(case_pack, 1) AS case_pack" : ", 1 AS case_pack";
$productSelect .= $hasProductMrp ? ", COALESCE(mrp, customer_price) AS mrp" : ", customer_price AS mrp";
$productSelect .= $hasProductGst ? ", COALESCE(gst_rate, 0) AS gst_rate" : ", 0 AS gst_rate";

$productsSql = "SELECT $productSelect
                FROM products
                WHERE status = 'active' AND quantity > 0
                ORDER BY product_name ASC";
$productsRes = mysqli_query($conn, $productsSql);
$products = [];
$productsLookup = [];
while ($productsRes && $row = mysqli_fetch_assoc($productsRes)) {
    $row['quantity'] = (int)$row['quantity'];
    $row['case_pack'] = max(1, (int)$row['case_pack']);
    $row['customer_price'] = (float)$row['customer_price'];
    $row['mrp'] = (float)$row['mrp'];
    $row['gst_rate'] = (float)$row['gst_rate'];
    $products[] = $row;
    $productsLookup[(int)$row['id']] = $row;
}

$error_message = '';
$success_message = '';
$order_number = lm_generate_order_number($conn);
$order_date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));
    $payment_status = trim((string)($_POST['payment_status'] ?? 'pending'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $postedOrderNumber = trim((string)($_POST['order_number'] ?? $order_number));
    $postedOrderDate = trim((string)($_POST['order_date'] ?? $order_date));
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);

    if ($customer_id <= 0 || !isset($customerIds[$customer_id])) {
        $error_message = 'Please select a valid assigned customer.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedOrderDate)) {
        $error_message = 'Invalid order date.';
    } else {
        $customerRowSql = "SELECT id, current_balance, total_purchases, zone_id
                           FROM customers
                           WHERE id = $customer_id AND assigned_lineman_id = $linemanId AND status = 'active'
                           LIMIT 1";
        $customerRowRes = mysqli_query($conn, $customerRowSql);
        $customerRow = $customerRowRes ? mysqli_fetch_assoc($customerRowRes) : null;

        if (!$customerRow) {
            $error_message = 'Invalid customer selection.';
        } else {
            $product_ids = $_POST['product_id'] ?? [];
            $case_packs = $_POST['case_pack'] ?? [];
            $cases_qty = $_POST['cases_qty'] ?? [];
            $pieces_qty = $_POST['pieces_qty'] ?? [];
            $free_qty = $_POST['free_qty'] ?? [];
            $mrps = $_POST['mrp'] ?? [];
            $base_rates = $_POST['base_rate'] ?? [];
            $discount_amounts = $_POST['discount_amount'] ?? [];
            $gst_rates = $_POST['gst_rate'] ?? [];
            $hsn_codes = $_POST['hsn_code'] ?? [];

            $lineItems = [];
            $grand_total = 0.0;
            $taxable_total = 0.0;
            $cgst_total = 0.0;
            $sgst_total = 0.0;
            $total_bill_qty = 0;

            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = (int)$product_ids[$i];
                if ($product_id <= 0 || !isset($productsLookup[$product_id])) {
                    continue;
                }

                $product = $productsLookup[$product_id];
                $case_pack = max(1, (int)($case_packs[$i] ?? $product['case_pack']));
                $cases = max(0, (int)($cases_qty[$i] ?? 0));
                $pieces = max(0, (int)($pieces_qty[$i] ?? 0));
                $free = max(0, (int)($free_qty[$i] ?? 0));
                $mrp = max(0, (float)($mrps[$i] ?? $product['mrp']));
                $rate = max(0, (float)($base_rates[$i] ?? $product['customer_price']));
                $disc = max(0, (float)($discount_amounts[$i] ?? 0));
                $gst = max(0, (float)($gst_rates[$i] ?? $product['gst_rate']));
                $hsn = trim((string)($hsn_codes[$i] ?? $product['hsn_code'] ?? ''));

                $bill_qty = ($cases * $case_pack) + $pieces;
                $stock_qty = $bill_qty + $free;
                if ($bill_qty <= 0) {
                    continue;
                }
                if ($stock_qty > (int)$product['quantity']) {
                    $error_message = 'Insufficient stock for ' . $product['product_name'] . '. Available: ' . $product['quantity'];
                    break;
                }

                $taxable_rate = max(0, $rate - $disc);
                $net_rate = round($taxable_rate * (1 + ($gst / 100)), 2);
                $line_total = round($bill_qty * $net_rate, 2);
                $line_taxable = $gst > 0 ? round($line_total / (1 + ($gst / 100)), 2) : $line_total;
                $line_tax = round($line_total - $line_taxable, 2);
                $line_cgst = round($line_tax / 2, 2);
                $line_sgst = round($line_tax / 2, 2);

                $lineItems[] = [
                    'product_id' => $product_id,
                    'product_name' => $product['product_name'],
                    'hsn_code' => $hsn,
                    'case_pack' => $case_pack,
                    'cases_qty' => $cases,
                    'pieces_qty' => $pieces,
                    'free_qty' => $free,
                    'bill_qty' => $bill_qty,
                    'stock_qty' => $stock_qty,
                    'mrp' => $mrp,
                    'base_rate' => $rate,
                    'discount_amount' => $disc,
                    'gst_rate' => $gst,
                    'net_rate' => $net_rate,
                    'line_total' => $line_total,
                    'taxable_value' => $line_taxable,
                    'cgst_amount' => $line_cgst,
                    'sgst_amount' => $line_sgst,
                ];

                $grand_total += $line_total;
                $taxable_total += $line_taxable;
                $cgst_total += $line_cgst;
                $sgst_total += $line_sgst;
                $total_bill_qty += $bill_qty;
            }

            if (!$error_message && empty($lineItems)) {
                $error_message = 'Please add at least one product.';
            }

            $grand_total = round($grand_total, 2);
            if (!$error_message) {
                if ($payment_status === 'paid') {
                    $paid_amount = $grand_total;
                } elseif ($payment_status === 'pending') {
                    $paid_amount = 0;
                }
                if ($paid_amount < 0 || $paid_amount > $grand_total) {
                    $error_message = 'Invalid paid amount.';
                } elseif ($payment_status === 'partial' && ($paid_amount <= 0 || $paid_amount >= $grand_total)) {
                    $error_message = 'For partial payment, paid amount must be greater than 0 and less than total.';
                }
            }

            if (!$error_message) {
                $pending_amount = round($grand_total - $paid_amount, 2);
                $zone_id = (isset($customerRow['zone_id']) && is_numeric($customerRow['zone_id']) && (int)$customerRow['zone_id'] > 0)
                    ? (int)$customerRow['zone_id']
                    : null;

                mysqli_begin_transaction($conn);
                try {
                    $paymentDate = $paid_amount > 0 ? date('Y-m-d H:i:s') : null;

                    if ($zone_id === null) {
                        $orderSql = "INSERT INTO orders (
                                        customer_id, order_number, order_date, total_items, total_amount,
                                        status, delivery_date, payment_date, notes, created_by,
                                        payment_method, payment_status, paid_amount, pending_amount, zone_id
                                     ) VALUES (?, ?, ?, ?, ?, 'pending', NULL, ?, ?, ?, ?, ?, ?, ?, NULL)";
                        $stmt = mysqli_prepare($conn, $orderSql);
                        mysqli_stmt_bind_param(
                            $stmt,
                            'issidssissdd',
                            $customer_id,
                            $postedOrderNumber,
                            $postedOrderDate,
                            $total_bill_qty,
                            $grand_total,
                            $paymentDate,
                            $notes,
                            $linemanId,
                            $payment_method,
                            $payment_status,
                            $paid_amount,
                            $pending_amount
                        );
                    } else {
                        $orderSql = "INSERT INTO orders (
                                        customer_id, order_number, order_date, total_items, total_amount,
                                        status, delivery_date, payment_date, notes, created_by,
                                        payment_method, payment_status, paid_amount, pending_amount, zone_id
                                     ) VALUES (?, ?, ?, ?, ?, 'pending', NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $orderSql);
                        mysqli_stmt_bind_param(
                            $stmt,
                            'issidssissddi',
                            $customer_id,
                            $postedOrderNumber,
                            $postedOrderDate,
                            $total_bill_qty,
                            $grand_total,
                            $paymentDate,
                            $notes,
                            $linemanId,
                            $payment_method,
                            $payment_status,
                            $paid_amount,
                            $pending_amount,
                            $zone_id
                        );
                    }

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception('Failed to create order: ' . mysqli_error($conn));
                    }
                    $order_id = (int)mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);

                    foreach ($lineItems as $item) {
                        if ($hasExtendedOrderItems) {
                            $itemSql = "INSERT INTO order_items (
                                            order_id, product_id, quantity, price, total, hsn_code, case_pack,
                                            cases_qty, pieces_qty, free_qty, mrp, base_rate, discount_amount,
                                            gst_rate, net_rate, taxable_value, cgst_amount, sgst_amount
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $itemStmt = mysqli_prepare($conn, $itemSql);
                            mysqli_stmt_bind_param(
                                $itemStmt,
                                'iiiddsiiiidddddddd',
                                $order_id,
                                $item['product_id'],
                                $item['bill_qty'],
                                $item['net_rate'],
                                $item['line_total'],
                                $item['hsn_code'],
                                $item['case_pack'],
                                $item['cases_qty'],
                                $item['pieces_qty'],
                                $item['free_qty'],
                                $item['mrp'],
                                $item['base_rate'],
                                $item['discount_amount'],
                                $item['gst_rate'],
                                $item['net_rate'],
                                $item['taxable_value'],
                                $item['cgst_amount'],
                                $item['sgst_amount']
                            );
                        } else {
                            $itemSql = "INSERT INTO order_items (order_id, product_id, quantity, price, total)
                                        VALUES (?, ?, ?, ?, ?)";
                            $itemStmt = mysqli_prepare($conn, $itemSql);
                            mysqli_stmt_bind_param(
                                $itemStmt,
                                'iiidd',
                                $order_id,
                                $item['product_id'],
                                $item['bill_qty'],
                                $item['net_rate'],
                                $item['line_total']
                            );
                        }

                        if (!mysqli_stmt_execute($itemStmt)) {
                            throw new Exception('Failed to add order item: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_close($itemStmt);

                        $updateSql = "UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?";
                        $updateStmt = mysqli_prepare($conn, $updateSql);
                        mysqli_stmt_bind_param($updateStmt, 'iii', $item['stock_qty'], $item['product_id'], $item['stock_qty']);
                        if (!mysqli_stmt_execute($updateStmt) || mysqli_stmt_affected_rows($updateStmt) === 0) {
                            throw new Exception('Failed to update stock for ' . $item['product_name']);
                        }
                        mysqli_stmt_close($updateStmt);
                    }

                    if ($paid_amount > 0) {
                        $payment_id = 'PAY' . date('YmdHis') . rand(100, 999);
                        $payment_notes = 'Payment for order #' . $postedOrderNumber;

                        $paymentSql = "INSERT INTO transactions (
                                            customer_id, order_id, payment_id, type, amount,
                                            payment_method, reference_no, notes, created_by, created_at
                                       ) VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
                        $paymentStmt = mysqli_prepare($conn, $paymentSql);
                        mysqli_stmt_bind_param(
                            $paymentStmt,
                            'iisdsssi',
                            $customer_id,
                            $order_id,
                            $payment_id,
                            $paid_amount,
                            $payment_method,
                            $postedOrderNumber,
                            $payment_notes,
                            $linemanId
                        );
                        if (!mysqli_stmt_execute($paymentStmt)) {
                            throw new Exception('Failed to record payment transaction: ' . mysqli_error($conn));
                        }
                        $transaction_id = (int)mysqli_insert_id($conn);
                        mysqli_stmt_close($paymentStmt);

                        if (lm_column_exists($conn, 'payment_history', 'order_id')) {
                            $historySql = "INSERT INTO payment_history (
                                                order_id, transaction_id, amount_paid, payment_method,
                                                reference_no, notes, created_by, created_at
                                           ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                            $historyStmt = mysqli_prepare($conn, $historySql);
                            mysqli_stmt_bind_param(
                                $historyStmt,
                                'iidsssi',
                                $order_id,
                                $transaction_id,
                                $paid_amount,
                                $payment_method,
                                $postedOrderNumber,
                                $payment_notes,
                                $linemanId
                            );
                            mysqli_stmt_execute($historyStmt);
                            mysqli_stmt_close($historyStmt);
                        }
                    }

                    $balanceSql = "UPDATE customers
                                   SET current_balance = current_balance + ?,
                                       total_purchases = total_purchases + ?,
                                       last_purchase_date = ?
                                   WHERE id = ?";
                    $balanceStmt = mysqli_prepare($conn, $balanceSql);
                    mysqli_stmt_bind_param($balanceStmt, 'ddsi', $pending_amount, $grand_total, $postedOrderDate, $customer_id);
                    if (!mysqli_stmt_execute($balanceStmt)) {
                        throw new Exception('Failed to update customer balance: ' . mysqli_error($conn));
                    }
                    mysqli_stmt_close($balanceStmt);

                    mysqli_commit($conn);
                    header('Location: create-order.php?success=1&order_id=' . $order_id);
                    exit;
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_message = $e->getMessage();
                }
            }
        }
    }
}

$customersForJs = json_encode($customers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$productsForJs = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<style>
@media (max-width: 991.98px){
    .page-content{padding:14px !important;}
    .container-fluid{padding-left:6px !important; padding-right:6px !important;}
    .card{border-radius:12px !important;}
    .card-body{padding:14px !important;}
    .text-end{text-align:left !important;}
}
@media (max-width: 767.98px){
    .page-content{padding:10px !important;}
    .container-fluid{padding-left:4px !important; padding-right:4px !important;}
    .card-body{padding:12px !important;}
    .row.g-4{row-gap:12px !important;}
    .form-control,.form-select,.btn{font-size:14px;}
    .form-control,.form-select{min-height:42px;}
    textarea.form-control{min-height:84px;}
    #productPickerTable{min-width:720px;}
    #invoiceItemsTable{min-width:1280px;}
    .table-responsive{
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        border-radius:10px;
    }
    .mobile-scroll-note{
        display:block !important;
        font-size:12px;
        color:#6c757d;
        margin-top:8px;
    }
    .order-header-stack > div{
        width:100%;
    }
    .order-header-stack .text-end{
        margin-top:8px;
    }
}
@media (min-width: 768px){
    .mobile-scroll-note{display:none !important;}
}
</style>
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

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-2"></i>
                        Order created successfully.
                        <?php if (isset($_GET['order_id'])): ?>
                            <a href="../view-invoice.php?id=<?php echo (int)$_GET['order_id']; ?>" class="alert-link" target="_blank">View Invoice</a>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="orderForm" onsubmit="return validateInvoiceForm();">
                    <input type="hidden" name="order_number" id="order_number" value="<?php echo htmlspecialchars($order_number); ?>">
                    <input type="hidden" name="order_date" id="order_date" value="<?php echo htmlspecialchars($order_date); ?>">

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between gap-3 order-header-stack">
                                <div>
                                    <h4 class="card-title mb-1">Create Customer Place Order</h4>
                                    <div class="text-muted">
                                        <?php echo htmlspecialchars($lineman['full_name']); ?> |
                                        <?php echo htmlspecialchars($lineman['employee_id']); ?> |
                                        <?php echo htmlspecialchars($lineman['zone_name'] ?: ($lineman['assigned_area'] ?: 'No Zone')); ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div><strong>Order No:</strong> <span id="invoiceNumberText"><?php echo htmlspecialchars($order_number); ?></span></div>
                                    <div><strong>Date:</strong> <span id="invoiceDateText"><?php echo date('d-m-Y', strtotime($order_date)); ?></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><i class="fas fa-store me-2"></i>Customer Details</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Search Customer</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customerSearch" placeholder="Search by shop / customer / phone">
                                            <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Select Customer *</label>
                                        <select class="form-select" id="customer_id" name="customer_id" required>
                                            <option value="">-- Select assigned customer --</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option
                                                    value="<?php echo (int)$customer['id']; ?>"
                                                    data-contact="<?php echo htmlspecialchars((string)$customer['customer_contact']); ?>"
                                                    data-code="<?php echo htmlspecialchars((string)$customer['customer_code']); ?>"
                                                    data-address="<?php echo htmlspecialchars((string)$customer['shop_location']); ?>"
                                                    data-balance="<?php echo htmlspecialchars((string)$customer['current_balance']); ?>"
                                                    data-terms="<?php echo htmlspecialchars((string)$customer['payment_terms']); ?>"
                                                    data-gst="<?php echo htmlspecialchars((string)$customer['gst_no']); ?>"
                                                    data-beat="<?php echo htmlspecialchars((string)$customer['assigned_area']); ?>">
                                                    <?php echo htmlspecialchars($customer['shop_name']); ?> - <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Customer Code</label>
                                            <input type="text" class="form-control" id="customer_code" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Contact</label>
                                            <input type="text" class="form-control" id="customer_contact" readonly>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">GST No</label>
                                            <input type="text" class="form-control" id="customer_gst" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Beat</label>
                                            <input type="text" class="form-control" id="customer_beat" readonly>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Current Balance</label>
                                            <input type="text" class="form-control" id="customer_balance" readonly value="₹0.00">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Payment Terms</label>
                                            <input type="text" class="form-control" id="payment_terms" readonly>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" id="shop_location" rows="3" readonly></textarea>
                                    </div>

                                    <hr>
                                    <h6 class="mb-3">Payment Information</h6>

                                    <div class="mb-3">
                                        <label class="form-label">Payment Method *</label>
                                        <select class="form-select" name="payment_method" id="payment_method" required>
                                            <option value="cash">Cash</option>
                                            <option value="upi">UPI</option>
                                            <option value="card">Card</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Payment Status *</label>
                                        <select class="form-select" name="payment_status" id="payment_status" required>
                                            <option value="pending">Pending Payment</option>
                                            <option value="paid">Fully Paid</option>
                                            <option value="partial">Partial Payment</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Amount Paid (₹)</label>
                                        <input type="number" class="form-control" name="paid_amount" id="paid_amount" min="0" step="0.01" value="0">
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="notes" rows="3" placeholder="Any order notes..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <h5 class="card-title mb-0"><i class="fas fa-cube me-2"></i>Product Selection</h5>
                                        <div class="search-box" style="max-width: 320px; width:100%;">
                                            <input type="text" class="form-control" id="productSearch" placeholder="Search product / code / HSN">
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle table-hover mb-0" id="productPickerTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width: 220px;">Product</th>
                                                    <th>HSN</th>
                                                    <th>Case Pk</th>
                                                    <th>Rate</th>
                                                    <th>Stock</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($products as $product): ?>
                                                    <tr data-product-id="<?php echo (int)$product['id']; ?>">
                                                        <td>
                                                            <div class="fw-semibold product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                                            <div class="small text-muted product-code"><?php echo htmlspecialchars($product['product_code']); ?></div>
                                                        </td>
                                                        <td class="product-hsn"><?php echo htmlspecialchars((string)$product['hsn_code']); ?></td>
                                                        <td><?php echo (int)$product['case_pack']; ?></td>
                                                        <td>₹<?php echo number_format((float)$product['customer_price'], 2); ?></td>
                                                        <td><span class="badge bg-success-subtle text-success"><?php echo (int)$product['quantity']; ?></span></td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-sm btn-primary add-product-btn" data-id="<?php echo (int)$product['id']; ?>">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mobile-scroll-note">Swipe left and right to view all product columns on mobile.</div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <h5 class="card-title mb-0"><i class="fas fa-file-invoice me-2"></i>Selected Items</h5>
                                        <small class="text-muted">Quick-order style item selection for lineman orders</small>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle mb-0" id="invoiceItemsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width:180px;">Product</th>
                                                    <th>HSN</th>
                                                    <th>Case Pk</th>
                                                    <th>CS</th>
                                                    <th>PC</th>
                                                    <th>FR</th>
                                                    <th>MRP</th>
                                                    <th>Rate</th>
                                                    <th>Disc</th>
                                                    <th>GST%</th>
                                                    <th>Net Rate</th>
                                                    <th>Total</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="invoiceItemsBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="mobile-scroll-note">Swipe left and right to view all selected item columns on mobile.</div>

                                    <div id="noItemsMessage" class="text-center text-muted py-4">No items added yet.</div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Taxable Value</label>
                                            <input type="text" class="form-control" id="taxable_total_text" readonly value="₹0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">CGST</label>
                                            <input type="text" class="form-control" id="cgst_total_text" readonly value="₹0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">SGST</label>
                                            <input type="text" class="form-control" id="sgst_total_text" readonly value="₹0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Grand Total</label>
                                            <input type="text" class="form-control fw-bold text-primary" id="grand_total_text" readonly value="₹0.00">
                                            <input type="hidden" id="grand_total" value="0">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" name="create_order" class="btn btn-primary w-100">
                                                <i class="fas fa-check-circle me-2"></i>Create Order
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-muted small">
                                        This order is saved as <strong>Pending</strong> and will be visible on the admin side immediately.
                                    </div>
                                    <div class="mobile-scroll-note">On mobile, fill customer details first, then scroll sideways in the product tables.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <?php include('includes/footer.php'); ?>
            </div>
        </div>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
<script>
const customersData = <?php echo $customersForJs; ?>;
const productsData = <?php echo $productsForJs; ?>;
const productsMap = {};
productsData.forEach(p => productsMap[String(p.id)] = p);

const selectedItems = [];

function currency(v){ return '₹' + Number(v || 0).toFixed(2); }

function updateCustomerDetails() {
    const select = document.getElementById('customer_id');
    const option = select.options[select.selectedIndex];
    document.getElementById('customer_code').value = option?.dataset.code || '';
    document.getElementById('customer_contact').value = option?.dataset.contact || '';
    document.getElementById('customer_gst').value = option?.dataset.gst || '';
    document.getElementById('customer_beat').value = option?.dataset.beat || '';
    document.getElementById('shop_location').value = option?.dataset.address || '';
    document.getElementById('customer_balance').value = currency(option?.dataset.balance || 0);
    document.getElementById('payment_terms').value = (option?.dataset.terms || '').replaceAll('_', ' ');
}

function customerSearchFilter() {
    const term = document.getElementById('customerSearch').value.toLowerCase();
    const select = document.getElementById('customer_id');
    Array.from(select.options).forEach((opt, idx) => {
        if (idx === 0) return;
        const text = opt.textContent.toLowerCase();
        opt.hidden = term !== '' && !text.includes(term);
    });
}

function productSearchFilter() {
    const term = document.getElementById('productSearch').value.toLowerCase();
    document.querySelectorAll('#productPickerTable tbody tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(term) ? '' : 'none';
    });
}

function addProduct(productId) {
    const key = String(productId);
    const product = productsMap[key];
    if (!product) return;
    const exists = selectedItems.find(x => String(x.product_id) === key);
    if (exists) return;

    selectedItems.push({
        product_id: product.id,
        product_name: product.product_name,
        hsn_code: product.hsn_code || '',
        case_pack: Number(product.case_pack || 1),
        cases_qty: 0,
        pieces_qty: 1,
        free_qty: 0,
        mrp: Number(product.mrp || product.customer_price || 0),
        base_rate: Number(product.customer_price || 0),
        discount_amount: 0,
        gst_rate: Number(product.gst_rate || 0),
        available_qty: Number(product.quantity || 0)
    });
    renderItems();
}

function removeItem(index) {
    selectedItems.splice(index, 1);
    renderItems();
}

function recalcItem(item) {
    item.case_pack = Math.max(1, Number(item.case_pack || 1));
    item.cases_qty = Math.max(0, Number(item.cases_qty || 0));
    item.pieces_qty = Math.max(0, Number(item.pieces_qty || 0));
    item.free_qty = Math.max(0, Number(item.free_qty || 0));
    item.mrp = Math.max(0, Number(item.mrp || 0));
    item.base_rate = Math.max(0, Number(item.base_rate || 0));
    item.discount_amount = Math.max(0, Number(item.discount_amount || 0));
    item.gst_rate = Math.max(0, Number(item.gst_rate || 0));

    item.bill_qty = (item.cases_qty * item.case_pack) + item.pieces_qty;
    item.stock_qty = item.bill_qty + item.free_qty;
    item.taxable_rate = Math.max(0, item.base_rate - item.discount_amount);
    item.net_rate = Number((item.taxable_rate * (1 + (item.gst_rate / 100))).toFixed(2));
    item.line_total = Number((item.bill_qty * item.net_rate).toFixed(2));
    item.taxable_value = item.gst_rate > 0 ? Number((item.line_total / (1 + (item.gst_rate / 100))).toFixed(2)) : item.line_total;
    item.tax_amount = Number((item.line_total - item.taxable_value).toFixed(2));
    item.cgst_amount = Number((item.tax_amount / 2).toFixed(2));
    item.sgst_amount = Number((item.tax_amount / 2).toFixed(2));
}

function renderItems() {
    const tbody = document.getElementById('invoiceItemsBody');
    const empty = document.getElementById('noItemsMessage');
    tbody.innerHTML = '';

    if (!selectedItems.length) {
        empty.style.display = 'block';
        updateTotals();
        return;
    }
    empty.style.display = 'none';

    selectedItems.forEach((item, index) => {
        recalcItem(item);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div class="fw-semibold">${item.product_name}</div>
                <small class="text-muted">Stock: ${item.available_qty}</small>
                <input type="hidden" name="product_id[]" value="${item.product_id}">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="hsn_code[]" value="${item.hsn_code}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="case_pack" name="case_pack[]" value="${item.case_pack}" min="1">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="cases_qty" name="cases_qty[]" value="${item.cases_qty}" min="0">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="pieces_qty" name="pieces_qty[]" value="${item.pieces_qty}" min="0">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="free_qty" name="free_qty[]" value="${item.free_qty}" min="0">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="mrp" name="mrp[]" value="${item.mrp}" step="0.01" min="0">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="base_rate" name="base_rate[]" value="${item.base_rate}" step="0.01" min="0">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="discount_amount" name="discount_amount[]" value="${item.discount_amount}" step="0.01" min="0">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm item-input" data-index="${index}" data-field="gst_rate" name="gst_rate[]" value="${item.gst_rate}" step="0.01" min="0">
            </td>
            <td><input type="text" class="form-control form-control-sm" value="${item.net_rate.toFixed(2)}" readonly></td>
            <td><input type="text" class="form-control form-control-sm" value="${item.line_total.toFixed(2)}" readonly></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${index})"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateTotals();
}

function updateTotals() {
    let taxable = 0, cgst = 0, sgst = 0, grand = 0;
    selectedItems.forEach(item => {
        recalcItem(item);
        taxable += item.taxable_value || 0;
        cgst += item.cgst_amount || 0;
        sgst += item.sgst_amount || 0;
        grand += item.line_total || 0;
    });
    document.getElementById('taxable_total_text').value = currency(taxable);
    document.getElementById('cgst_total_text').value = currency(cgst);
    document.getElementById('sgst_total_text').value = currency(sgst);
    document.getElementById('grand_total_text').value = currency(grand);
    document.getElementById('grand_total').value = grand.toFixed(2);

    const paymentStatus = document.getElementById('payment_status').value;
    const paid = document.getElementById('paid_amount');
    if (paymentStatus === 'paid') paid.value = grand.toFixed(2);
    if (paymentStatus === 'pending') paid.value = '0';
}

function validateInvoiceForm() {
    if (!document.getElementById('customer_id').value) {
        alert('Please select a customer');
        return false;
    }
    if (!selectedItems.length) {
        alert('Please add at least one item');
        return false;
    }
    for (const item of selectedItems) {
        recalcItem(item);
        if (item.bill_qty <= 0) {
            alert('Each selected item must have quantity greater than 0');
            return false;
        }
        if (item.stock_qty > item.available_qty) {
            alert('Stock exceeded for ' + item.product_name + '. Available: ' + item.available_qty);
            return false;
        }
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('customer_id').addEventListener('change', updateCustomerDetails);
    document.getElementById('customerSearch').addEventListener('input', customerSearchFilter);
    document.getElementById('productSearch').addEventListener('input', productSearchFilter);
    document.getElementById('payment_status').addEventListener('change', updateTotals);

    document.querySelectorAll('.add-product-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            addProduct(this.dataset.id);
        });
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-input')) {
            const idx = Number(e.target.dataset.index);
            const field = e.target.dataset.field;
            if (!Number.isNaN(idx) && selectedItems[idx]) {
                selectedItems[idx][field] = e.target.value;
                renderItems();
            }
        }
    });

    updateCustomerDetails();
    renderItems();
});
</script>
</body>
</html>
