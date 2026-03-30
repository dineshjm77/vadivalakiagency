<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = 'add-purchase-order';
$pageTitle = 'Purchase Invoice Entry';

include('config/config.php');

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatCurrencyINR($amount)
{
    return '₹' . number_format((float)$amount, 2);
}

function hasColumn(mysqli $conn, $table, $column)
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'";
    $res = mysqli_query($conn, $sql);
    return $res && mysqli_num_rows($res) > 0;
}

function generatePONumber()
{
    return 'PO' . date('ymd') . rand(1000, 9999);
}

function normalizePaymentStatus($paid, $total)
{
    $paid = (float)$paid;
    $total = (float)$total;
    if ($paid <= 0) {
        return 'pending';
    }
    if ($paid >= $total) {
        return 'paid';
    }
    return 'partial';
}

$products_have_hsn = hasColumn($conn, 'products', 'hsn_code');
$business = [
    'business_name' => 'Business',
    'gstin' => '',
    'address' => '',
    'phone' => '',
    'mobile' => '',
    'email' => '',
    'tax_percentage' => '5.00'
];

$business_sql = "SELECT business_name, gstin, address, phone, mobile, email, tax_percentage FROM business_settings ORDER BY id ASC LIMIT 1";
$business_res = mysqli_query($conn, $business_sql);
if ($business_res && mysqli_num_rows($business_res) > 0) {
    $business = mysqli_fetch_assoc($business_res);
}

$creditors = [];
$creditor_sql = "SELECT id, vendor_name, company_name, address, phone, gstin, payment_terms FROM creditors WHERE status = 'active' ORDER BY vendor_name ASC";
$creditor_res = mysqli_query($conn, $creditor_sql);
if ($creditor_res) {
    while ($row = mysqli_fetch_assoc($creditor_res)) {
        $creditors[] = $row;
    }
}

$products = [];
$product_select = "SELECT id, product_code, product_name, stock_price, customer_price, quantity" . ($products_have_hsn ? ", hsn_code" : "") . " FROM products WHERE status != 'inactive' ORDER BY product_name ASC";
$product_res = mysqli_query($conn, $product_select);
if ($product_res) {
    while ($row = mysqli_fetch_assoc($product_res)) {
        if (!$products_have_hsn) {
            $row['hsn_code'] = '';
        }
        $products[] = $row;
    }
}

$message = '';
$error = '';

$po_number = generatePONumber();
$order_date = date('Y-m-d');
$invoice_date = date('Y-m-d');
$delivery_date = date('Y-m-d');
$payment_method = 'cash';
$order_status = 'delivered';
$creditor_id = '';
$invoice_no = '';
$dd_no = '';
$lr_no = '';
$eway_bill_no = '';
$reference_no = '';
$paid_amount = '0.00';
$notes = '';

$item_rows = [
    [
        'product_id' => '',
        'product_name' => '',
        'hsn_code' => '',
        'case_pack' => '',
        'qty' => '',
        'mrp' => '',
        'tax_percent' => '5',
        'rate_per_pcs' => '',
        'taxable_value' => '',
        'tax_amount' => '',
        'line_total' => ''
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $po_number = trim($_POST['po_number'] ?? generatePONumber());
    $order_date = trim($_POST['order_date'] ?? date('Y-m-d'));
    $invoice_date = trim($_POST['invoice_date'] ?? $order_date);
    $delivery_date = trim($_POST['delivery_date'] ?? $order_date);
    $creditor_id = (int)($_POST['creditor_id'] ?? 0);
    $invoice_no = trim($_POST['invoice_no'] ?? '');
    $dd_no = trim($_POST['dd_no'] ?? '');
    $lr_no = trim($_POST['lr_no'] ?? '');
    $eway_bill_no = trim($_POST['eway_bill_no'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'cash');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $paid_amount = trim($_POST['paid_amount'] ?? '0.00');
    $order_status = trim($_POST['order_status'] ?? 'delivered');
    $notes = trim($_POST['notes'] ?? '');

    $product_ids = $_POST['item_product_id'] ?? [];
    $product_names = $_POST['item_product_name'] ?? [];
    $hsn_codes = $_POST['item_hsn_code'] ?? [];
    $case_packs = $_POST['item_case_pack'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $mrps = $_POST['item_mrp'] ?? [];
    $tax_percents = $_POST['item_tax_percent'] ?? [];
    $rates = $_POST['item_rate_per_pcs'] ?? [];

    $item_rows = [];
    $subtotal = 0.00;
    $tax_total = 0.00;
    $grand_total = 0.00;
    $valid_items = [];

    $row_count = max(
        count($product_ids),
        count($product_names),
        count($hsn_codes),
        count($case_packs),
        count($qtys),
        count($mrps),
        count($tax_percents),
        count($rates)
    );

    for ($i = 0; $i < $row_count; $i++) {
        $product_id = (int)($product_ids[$i] ?? 0);
        $product_name = trim($product_names[$i] ?? '');
        $hsn_code = trim($hsn_codes[$i] ?? '');
        $case_pack = (float)($case_packs[$i] ?? 0);
        $qty = (float)($qtys[$i] ?? 0);
        $mrp = (float)($mrps[$i] ?? 0);
        $tax_percent = (float)($tax_percents[$i] ?? 0);
        $rate_per_pcs = (float)($rates[$i] ?? 0);
        $taxable_value = $qty * $rate_per_pcs;
        $tax_amount = $taxable_value * $tax_percent / 100;
        $line_total = $taxable_value + $tax_amount;

        $item_rows[] = [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'hsn_code' => $hsn_code,
            'case_pack' => $case_pack > 0 ? $case_pack : '',
            'qty' => $qty > 0 ? $qty : '',
            'mrp' => $mrp > 0 ? $mrp : '',
            'tax_percent' => $tax_percent > 0 ? $tax_percent : '0',
            'rate_per_pcs' => $rate_per_pcs > 0 ? $rate_per_pcs : '',
            'taxable_value' => $taxable_value > 0 ? number_format($taxable_value, 2, '.', '') : '',
            'tax_amount' => $tax_amount > 0 ? number_format($tax_amount, 2, '.', '') : '',
            'line_total' => $line_total > 0 ? number_format($line_total, 2, '.', '') : ''
        ];

        if ($product_name !== '' && $qty > 0 && $rate_per_pcs >= 0) {
            $valid_items[] = [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'hsn_code' => $hsn_code,
                'case_pack' => $case_pack,
                'qty' => $qty,
                'mrp' => $mrp,
                'tax_percent' => $tax_percent,
                'rate_per_pcs' => $rate_per_pcs,
                'taxable_value' => $taxable_value,
                'tax_amount' => $tax_amount,
                'line_total' => $line_total
            ];
            $subtotal += $taxable_value;
            $tax_total += $tax_amount;
            $grand_total += $line_total;
        }
    }

    $paid_amount_float = (float)$paid_amount;

    if ($creditor_id <= 0) {
        $error = 'Please select a supplier.';
    } elseif (empty($valid_items)) {
        $error = 'Please add at least one valid item row.';
    } elseif ($paid_amount_float < 0) {
        $error = 'Paid amount cannot be negative.';
    } elseif ($paid_amount_float > $grand_total) {
        $error = 'Paid amount cannot be greater than total amount.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            $created_by = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            $payment_status = normalizePaymentStatus($paid_amount_float, $grand_total);
            $round_off = round($grand_total) - $grand_total;
            $cgst_amount = $tax_total / 2;
            $sgst_amount = $tax_total / 2;

            $extra_header_notes = [];
            if ($invoice_no !== '') $extra_header_notes[] = 'Supplier Invoice No: ' . $invoice_no;
            if ($invoice_date !== '') $extra_header_notes[] = 'Invoice Date: ' . $invoice_date;
            if ($dd_no !== '') $extra_header_notes[] = 'DD No: ' . $dd_no;
            if ($lr_no !== '') $extra_header_notes[] = 'LR / Lorry Receipt No: ' . $lr_no;
            if ($eway_bill_no !== '') $extra_header_notes[] = 'E-Way Bill No: ' . $eway_bill_no;
            if ($payment_method !== '') $extra_header_notes[] = 'Payment Method: ' . $payment_method;
            if ($reference_no !== '') $extra_header_notes[] = 'Reference No: ' . $reference_no;
            $extra_header_notes[] = 'CGST: ' . number_format($cgst_amount, 2, '.', '');
            $extra_header_notes[] = 'SGST: ' . number_format($sgst_amount, 2, '.', '');
            $extra_header_notes[] = 'Round Off: ' . number_format($round_off, 2, '.', '');
            $combined_notes = trim(implode("\n", $extra_header_notes) . "\n" . $notes);

            $po_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO purchase_orders (
                    po_number, creditor_id, order_date, expected_delivery_date, delivery_date,
                    subtotal, tax_amount, total_amount, paid_amount, payment_status,
                    order_status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$po_stmt) {
                throw new Exception('Unable to prepare purchase order insert query.');
            }

            mysqli_stmt_bind_param(
                $po_stmt,
                'sisssddddsssi',
                $po_number,
                $creditor_id,
                $order_date,
                $invoice_date,
                $delivery_date,
                $subtotal,
                $tax_total,
                $grand_total,
                $paid_amount_float,
                $payment_status,
                $order_status,
                $combined_notes,
                $created_by
            );

            if (!mysqli_stmt_execute($po_stmt)) {
                throw new Exception('Failed to create purchase order: ' . mysqli_stmt_error($po_stmt));
            }

            $po_id = mysqli_insert_id($conn);
            mysqli_stmt_close($po_stmt);

            $item_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO purchase_order_items (
                    po_id, product_id, product_name, quantity, unit_price, total, received_quantity, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$item_stmt) {
                throw new Exception('Unable to prepare purchase item insert query.');
            }

            $stock_update_stmt = mysqli_prepare(
                $conn,
                "UPDATE products SET quantity = ?, stock_price = ?, profit = ?, profit_percentage = ? WHERE id = ?"
            );

            $stock_txn_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO stock_transactions (
                    product_id, transaction_type, quantity, stock_price, previous_quantity, new_quantity, notes, created_by
                ) VALUES (?, 'purchase', ?, ?, ?, ?, ?, ?)"
            );

            $product_fetch_stmt = mysqli_prepare(
                $conn,
                "SELECT quantity, customer_price FROM products WHERE id = ? LIMIT 1"
            );

            foreach ($valid_items as $item) {
                $product_id = $item['product_id'] > 0 ? $item['product_id'] : null;
                $product_name = $item['product_name'];
                $qty = (int)$item['qty'];
                $unit_price = (float)$item['rate_per_pcs'];
                $row_total = (float)$item['line_total'];
                $received_quantity = $qty;
                $item_notes = 'HSN: ' . $item['hsn_code'] . ' | Case Pack: ' . $item['case_pack'] . ' | MRP: ' . $item['mrp'] . ' | Tax %: ' . $item['tax_percent'] . ' | Tax Amount: ' . number_format((float)$item['tax_amount'], 2, '.', '');

                mysqli_stmt_bind_param(
                    $item_stmt,
                    'iisdidds',
                    $po_id,
                    $product_id,
                    $product_name,
                    $qty,
                    $unit_price,
                    $row_total,
                    $received_quantity,
                    $item_notes
                );

                if (!mysqli_stmt_execute($item_stmt)) {
                    throw new Exception('Failed to save purchase item: ' . mysqli_stmt_error($item_stmt));
                }

                if (!empty($product_id)) {
                    mysqli_stmt_bind_param($product_fetch_stmt, 'i', $product_id);
                    if (!mysqli_stmt_execute($product_fetch_stmt)) {
                        throw new Exception('Failed to fetch product stock before update.');
                    }
                    $product_result = mysqli_stmt_get_result($product_fetch_stmt);
                    $product_row = $product_result ? mysqli_fetch_assoc($product_result) : null;

                    if ($product_row) {
                        $previous_quantity = (int)$product_row['quantity'];
                        $new_quantity = $previous_quantity + $qty;
                        $customer_price = (float)$product_row['customer_price'];
                        $profit = $customer_price - $unit_price;
                        $profit_percentage = $unit_price > 0 ? ($profit / $unit_price) * 100 : 0;

                        mysqli_stmt_bind_param(
                            $stock_update_stmt,
                            'idddi',
                            $new_quantity,
                            $unit_price,
                            $profit,
                            $profit_percentage,
                            $product_id
                        );

                        if (!mysqli_stmt_execute($stock_update_stmt)) {
                            throw new Exception('Failed to update product stock: ' . mysqli_stmt_error($stock_update_stmt));
                        }

                        $stock_note = 'Purchase Invoice Entry - ' . $po_number;
                        mysqli_stmt_bind_param(
                            $stock_txn_stmt,
                            'iidiisi',
                            $product_id,
                            $qty,
                            $unit_price,
                            $previous_quantity,
                            $new_quantity,
                            $stock_note,
                            $created_by
                        );

                        if (!mysqli_stmt_execute($stock_txn_stmt)) {
                            throw new Exception('Failed to create stock transaction: ' . mysqli_stmt_error($stock_txn_stmt));
                        }
                    }
                }
            }

            mysqli_stmt_close($item_stmt);
            if ($stock_update_stmt) mysqli_stmt_close($stock_update_stmt);
            if ($stock_txn_stmt) mysqli_stmt_close($stock_txn_stmt);
            if ($product_fetch_stmt) mysqli_stmt_close($product_fetch_stmt);

            $creditor_purchase_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO creditor_purchases (
                    creditor_id, purchase_date, invoice_no, total_amount, paid_amount, payment_status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if (!$creditor_purchase_stmt) {
                throw new Exception('Unable to prepare creditor purchase insert query.');
            }

            mysqli_stmt_bind_param(
                $creditor_purchase_stmt,
                'issddssi',
                $creditor_id,
                $order_date,
                $invoice_no,
                $grand_total,
                $paid_amount_float,
                $payment_status,
                $combined_notes,
                $created_by
            );

            if (!mysqli_stmt_execute($creditor_purchase_stmt)) {
                throw new Exception('Failed to create creditor purchase entry: ' . mysqli_stmt_error($creditor_purchase_stmt));
            }
            mysqli_stmt_close($creditor_purchase_stmt);

            if ($paid_amount_float > 0) {
                $purchase_payment_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO purchase_payments (
                        po_id, payment_date, amount, payment_method, reference_no, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                if ($purchase_payment_stmt) {
                    $payment_notes = 'Purchase payment for ' . $po_number;
                    mysqli_stmt_bind_param(
                        $purchase_payment_stmt,
                        'isdsssi',
                        $po_id,
                        $order_date,
                        $paid_amount_float,
                        $payment_method,
                        $reference_no,
                        $payment_notes,
                        $created_by
                    );

                    if (!mysqli_stmt_execute($purchase_payment_stmt)) {
                        throw new Exception('Failed to save purchase payment: ' . mysqli_stmt_error($purchase_payment_stmt));
                    }
                    mysqli_stmt_close($purchase_payment_stmt);
                }

                $creditor_payment_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO creditor_payments (
                        creditor_id, payment_date, amount, payment_method, reference_no, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                if ($creditor_payment_stmt) {
                    $creditor_payment_note = 'Purchase invoice payment for ' . $po_number;
                    mysqli_stmt_bind_param(
                        $creditor_payment_stmt,
                        'isdsssi',
                        $creditor_id,
                        $order_date,
                        $paid_amount_float,
                        $payment_method,
                        $reference_no,
                        $creditor_payment_note,
                        $created_by
                    );

                    if (!mysqli_stmt_execute($creditor_payment_stmt)) {
                        throw new Exception('Failed to save creditor payment: ' . mysqli_stmt_error($creditor_payment_stmt));
                    }
                    mysqli_stmt_close($creditor_payment_stmt);
                }
            }

            $creditor_update_stmt = mysqli_prepare(
                $conn,
                "UPDATE creditors
                 SET total_purchases = total_purchases + ?,
                     total_paid = total_paid + ?,
                     current_balance = current_balance + ?
                 WHERE id = ?"
            );

            if (!$creditor_update_stmt) {
                throw new Exception('Unable to prepare creditor update query.');
            }

            $balance_increase = $grand_total - $paid_amount_float;
            mysqli_stmt_bind_param(
                $creditor_update_stmt,
                'dddi',
                $grand_total,
                $paid_amount_float,
                $balance_increase,
                $creditor_id
            );

            if (!mysqli_stmt_execute($creditor_update_stmt)) {
                throw new Exception('Failed to update supplier balance: ' . mysqli_stmt_error($creditor_update_stmt));
            }
            mysqli_stmt_close($creditor_update_stmt);

            mysqli_commit($conn);
            header('Location: add-purchase-order.php?success=1&po_number=' . urlencode($po_number));
            exit;
        } catch (Exception $ex) {
            mysqli_rollback($conn);
            $error = $ex->getMessage();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = 'Purchase invoice entry saved successfully. PO Number: ' . ($_GET['po_number'] ?? '');
    $po_number = generatePONumber();
    $creditor_id = '';
    $invoice_no = '';
    $dd_no = '';
    $lr_no = '';
    $eway_bill_no = '';
    $reference_no = '';
    $paid_amount = '0.00';
    $notes = '';
    $item_rows = [[
        'product_id' => '', 'product_name' => '', 'hsn_code' => '', 'case_pack' => '', 'qty' => '',
        'mrp' => '', 'tax_percent' => '5', 'rate_per_pcs' => '', 'taxable_value' => '', 'tax_amount' => '', 'line_total' => ''
    ]];
}
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
<?php include('includes/pre-loader.php'); ?>
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

                <div class="row mb-3">
                    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h4 class="mb-1">Purchase Invoice Entry</h4>
                            <p class="text-muted mb-0">Create supplier purchase entry based on tax invoice format.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="purchase-orders.php" class="btn btn-outline-primary">
                                <i class="mdi mdi-format-list-bulleted me-1"></i> All Purchase Orders
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo e($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo e($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="post" id="purchaseInvoiceForm" autocomplete="off">
                    <div class="row">
                        <div class="col-xl-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="mdi mdi-store me-1"></i> Business Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2"><strong><?php echo e($business['business_name'] ?? ''); ?></strong></div>
                                    <div class="small text-muted mb-1">GSTIN: <?php echo e($business['gstin'] ?? ''); ?></div>
                                    <div class="small text-muted mb-1"><?php echo nl2br(e($business['address'] ?? '')); ?></div>
                                    <div class="small text-muted mb-1">Phone: <?php echo e(trim(($business['phone'] ?? '') . ' / ' . ($business['mobile'] ?? ''), ' /')); ?></div>
                                    <div class="small text-muted">Email: <?php echo e($business['email'] ?? ''); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="mdi mdi-file-document-edit-outline me-1"></i> Invoice Header</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">PO Number</label>
                                            <input type="text" name="po_number" class="form-control" value="<?php echo e($po_number); ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Entry Date</label>
                                            <input type="date" name="order_date" class="form-control" value="<?php echo e($order_date); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Supplier Invoice Date</label>
                                            <input type="date" name="invoice_date" class="form-control" value="<?php echo e($invoice_date); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Supplier</label>
                                            <select name="creditor_id" id="creditor_id" class="form-select" required>
                                                <option value="">Select Supplier</option>
                                                <?php foreach ($creditors as $creditor): ?>
                                                    <option
                                                        value="<?php echo (int)$creditor['id']; ?>"
                                                        data-address="<?php echo e($creditor['address']); ?>"
                                                        data-phone="<?php echo e($creditor['phone']); ?>"
                                                        data-gstin="<?php echo e($creditor['gstin']); ?>"
                                                        data-payment_terms="<?php echo e($creditor['payment_terms']); ?>"
                                                        <?php echo ((int)$creditor_id === (int)$creditor['id']) ? 'selected' : ''; ?>
                                                    >
                                                        <?php echo e($creditor['vendor_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Supplier Invoice No</label>
                                            <input type="text" name="invoice_no" class="form-control" value="<?php echo e($invoice_no); ?>" placeholder="Ex: L/25-26/3342">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">DD No</label>
                                            <input type="text" name="dd_no" class="form-control" value="<?php echo e($dd_no); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">LR / Lorry Receipt No</label>
                                            <input type="text" name="lr_no" class="form-control" value="<?php echo e($lr_no); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">E-Way Bill No</label>
                                            <input type="text" name="eway_bill_no" class="form-control" value="<?php echo e($eway_bill_no); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Delivery / Received Date</label>
                                            <input type="date" name="delivery_date" class="form-control" value="<?php echo e($delivery_date); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select">
                                                <?php foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'cheque' => 'Cheque', 'upi' => 'UPI'] as $key => $label): ?>
                                                    <option value="<?php echo e($key); ?>" <?php echo $payment_method === $key ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Reference No</label>
                                            <input type="text" name="reference_no" class="form-control" value="<?php echo e($reference_no); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="mdi mdi-account-box-outline me-1"></i> Supplier Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2"><strong id="supplier_name_display">-</strong></div>
                                    <div class="small text-muted mb-1">GSTIN: <span id="supplier_gstin_display">-</span></div>
                                    <div class="small text-muted mb-1">Phone: <span id="supplier_phone_display">-</span></div>
                                    <div class="small text-muted">Address: <span id="supplier_address_display">-</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <h5 class="card-title mb-0"><i class="mdi mdi-package-variant-closed me-1"></i> Purchase Items</h5>
                                        <button type="button" class="btn btn-primary btn-sm" id="addRowBtn">
                                            <i class="mdi mdi-plus me-1"></i> Add Item Row
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle mb-0" id="itemsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:50px;">#</th>
                                                    <th style="min-width:180px;">Product</th>
                                                    <th style="min-width:160px;">Item Name</th>
                                                    <th style="min-width:110px;">HSN Code</th>
                                                    <th style="min-width:90px;">Case Pkg</th>
                                                    <th style="min-width:90px;">Qty (Pcs)</th>
                                                    <th style="min-width:90px;">MRP</th>
                                                    <th style="min-width:90px;">Tax %</th>
                                                    <th style="min-width:110px;">Rate / Pcs</th>
                                                    <th style="min-width:110px;">Taxable</th>
                                                    <th style="min-width:100px;">Tax Amt</th>
                                                    <th style="min-width:110px;">Total</th>
                                                    <th style="width:60px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemsBody">
                                                <?php foreach ($item_rows as $index => $row): ?>
                                                    <tr class="item-row">
                                                        <td class="row-number text-center"><?php echo (int)$index + 1; ?></td>
                                                        <td>
                                                            <select name="item_product_id[]" class="form-select product-select">
                                                                <option value="">Select</option>
                                                                <?php foreach ($products as $product): ?>
                                                                    <option
                                                                        value="<?php echo (int)$product['id']; ?>"
                                                                        data-name="<?php echo e($product['product_name']); ?>"
                                                                        data-hsn="<?php echo e($product['hsn_code'] ?? ''); ?>"
                                                                        data-stock-price="<?php echo e($product['stock_price']); ?>"
                                                                        data-customer-price="<?php echo e($product['customer_price']); ?>"
                                                                        data-qty="<?php echo e($product['quantity']); ?>"
                                                                        <?php echo ((int)$row['product_id'] === (int)$product['id']) ? 'selected' : ''; ?>
                                                                    >
                                                                        <?php echo e($product['product_name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td><input type="text" name="item_product_name[]" class="form-control item-name" value="<?php echo e($row['product_name']); ?>" placeholder="Manual item name"></td>
                                                        <td><input type="text" name="item_hsn_code[]" class="form-control item-hsn" value="<?php echo e($row['hsn_code']); ?>"></td>
                                                        <td><input type="number" step="0.01" min="0" name="item_case_pack[]" class="form-control item-case-pack" value="<?php echo e($row['case_pack']); ?>"></td>
                                                        <td><input type="number" step="1" min="0" name="item_qty[]" class="form-control item-qty" value="<?php echo e($row['qty']); ?>"></td>
                                                        <td><input type="number" step="0.01" min="0" name="item_mrp[]" class="form-control item-mrp" value="<?php echo e($row['mrp']); ?>"></td>
                                                        <td><input type="number" step="0.01" min="0" name="item_tax_percent[]" class="form-control item-tax-percent" value="<?php echo e($row['tax_percent']); ?>"></td>
                                                        <td><input type="number" step="0.01" min="0" name="item_rate_per_pcs[]" class="form-control item-rate" value="<?php echo e($row['rate_per_pcs']); ?>"></td>
                                                        <td><input type="text" class="form-control item-taxable bg-light" value="<?php echo e($row['taxable_value']); ?>" readonly></td>
                                                        <td><input type="text" class="form-control item-tax-amount bg-light" value="<?php echo e($row['tax_amount']); ?>" readonly></td>
                                                        <td><input type="text" class="form-control item-total bg-light" value="<?php echo e($row['line_total']); ?>" readonly></td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-outline-danger btn-sm remove-row">
                                                                <i class="mdi mdi-delete-outline"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="mdi mdi-note-text-outline me-1"></i> Notes</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Paid Amount</label>
                                            <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" class="form-control" value="<?php echo e($paid_amount); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Order Status</label>
                                            <select name="order_status" class="form-select">
                                                <?php foreach (['draft', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $status): ?>
                                                    <option value="<?php echo e($status); ?>" <?php echo $order_status === $status ? 'selected' : ''; ?>><?php echo e(ucfirst($status)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Supplier Terms</label>
                                            <input type="text" id="supplier_terms_display" class="form-control bg-light" value="-" readonly>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Additional Notes</label>
                                            <textarea name="notes" class="form-control" rows="5" placeholder="Goods received notes, transport notes, remarks... "><?php echo e($notes); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="mdi mdi-calculator-variant-outline me-1"></i> Totals</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Taxable Value</span>
                                        <strong id="summary_subtotal">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>CGST</span>
                                        <strong id="summary_cgst">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>SGST</span>
                                        <strong id="summary_sgst">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Total Tax</span>
                                        <strong id="summary_tax">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Round Off</span>
                                        <strong id="summary_round_off">₹0.00</strong>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between py-1">
                                        <span class="fs-6">Grand Total</span>
                                        <strong class="fs-5 text-primary" id="summary_total">₹0.00</strong>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Balance Payable</span>
                                        <strong class="text-danger" id="summary_balance">₹0.00</strong>
                                    </div>
                                    <div class="mt-3 d-grid gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="mdi mdi-content-save-outline me-1"></i> Save Purchase Entry
                                        </button>
                                        <button type="reset" class="btn btn-light" id="resetFormBtn">
                                            <i class="mdi mdi-refresh me-1"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/rightbar.php'); ?>
<?php include('includes/scripts.php'); ?>

<script>
(function() {
    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const creditorSelect = document.getElementById('creditor_id');
    const paidAmountInput = document.getElementById('paid_amount');
    const resetFormBtn = document.getElementById('resetFormBtn');

    function inr(value) {
        const num = parseFloat(value || 0);
        return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateSupplierCard() {
        const selected = creditorSelect.options[creditorSelect.selectedIndex];
        document.getElementById('supplier_name_display').textContent = selected ? selected.text || '-' : '-';
        document.getElementById('supplier_gstin_display').textContent = selected?.dataset?.gstin || '-';
        document.getElementById('supplier_phone_display').textContent = selected?.dataset?.phone || '-';
        document.getElementById('supplier_address_display').textContent = selected?.dataset?.address || '-';
        document.getElementById('supplier_terms_display').value = selected?.dataset?.payment_terms || '-';
    }

    function bindRowEvents(row) {
        row.querySelectorAll('.item-qty, .item-tax-percent, .item-rate, .item-mrp, .item-case-pack').forEach(input => {
            input.addEventListener('input', () => {
                calculateRow(row);
                calculateSummary();
            });
        });

        const productSelect = row.querySelector('.product-select');
        productSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            row.querySelector('.item-name').value = opt?.dataset?.name || '';
            if (opt?.dataset?.hsn !== undefined) {
                row.querySelector('.item-hsn').value = opt.dataset.hsn || '';
            }
            if (opt?.dataset?.stockPrice) {
                row.querySelector('.item-rate').value = opt.dataset.stockPrice || '';
            }
            calculateRow(row);
            calculateSummary();
        });

        row.querySelector('.remove-row').addEventListener('click', function() {
            if (itemsBody.querySelectorAll('.item-row').length > 1) {
                row.remove();
                updateRowNumbers();
                calculateSummary();
            }
        });
    }

    function updateRowNumbers() {
        itemsBody.querySelectorAll('.item-row').forEach((row, index) => {
            row.querySelector('.row-number').textContent = index + 1;
        });
    }

    function calculateRow(row) {
        const qty = parseFloat(row.querySelector('.item-qty').value || 0);
        const taxPercent = parseFloat(row.querySelector('.item-tax-percent').value || 0);
        const rate = parseFloat(row.querySelector('.item-rate').value || 0);

        const taxable = qty * rate;
        const taxAmount = taxable * taxPercent / 100;
        const total = taxable + taxAmount;

        row.querySelector('.item-taxable').value = taxable > 0 ? taxable.toFixed(2) : '';
        row.querySelector('.item-tax-amount').value = taxAmount > 0 ? taxAmount.toFixed(2) : '';
        row.querySelector('.item-total').value = total > 0 ? total.toFixed(2) : '';
    }

    function calculateSummary() {
        let subtotal = 0;
        let totalTax = 0;
        let total = 0;

        itemsBody.querySelectorAll('.item-row').forEach(row => {
            subtotal += parseFloat(row.querySelector('.item-taxable').value || 0);
            totalTax += parseFloat(row.querySelector('.item-tax-amount').value || 0);
            total += parseFloat(row.querySelector('.item-total').value || 0);
        });

        const cgst = totalTax / 2;
        const sgst = totalTax / 2;
        const roundOff = Math.round(total) - total;
        const payableTotal = total;
        const paid = parseFloat(paidAmountInput.value || 0);
        const balance = payableTotal - paid;

        document.getElementById('summary_subtotal').textContent = inr(subtotal);
        document.getElementById('summary_cgst').textContent = inr(cgst);
        document.getElementById('summary_sgst').textContent = inr(sgst);
        document.getElementById('summary_tax').textContent = inr(totalTax);
        document.getElementById('summary_round_off').textContent = inr(roundOff);
        document.getElementById('summary_total').textContent = inr(payableTotal);
        document.getElementById('summary_balance').textContent = inr(balance);
    }

    function addRow(data = {}) {
        const row = document.createElement('tr');
        row.className = 'item-row';
        row.innerHTML = `
            <td class="row-number text-center"></td>
            <td>
                <select name="item_product_id[]" class="form-select product-select">
                    <option value="">Select</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo (int)$product['id']; ?>" data-name="<?php echo e($product['product_name']); ?>" data-hsn="<?php echo e($product['hsn_code'] ?? ''); ?>" data-stock-price="<?php echo e($product['stock_price']); ?>" data-customer-price="<?php echo e($product['customer_price']); ?>" data-qty="<?php echo e($product['quantity']); ?>"><?php echo e($product['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" name="item_product_name[]" class="form-control item-name" placeholder="Manual item name"></td>
            <td><input type="text" name="item_hsn_code[]" class="form-control item-hsn"></td>
            <td><input type="number" step="0.01" min="0" name="item_case_pack[]" class="form-control item-case-pack"></td>
            <td><input type="number" step="1" min="0" name="item_qty[]" class="form-control item-qty"></td>
            <td><input type="number" step="0.01" min="0" name="item_mrp[]" class="form-control item-mrp"></td>
            <td><input type="number" step="0.01" min="0" name="item_tax_percent[]" class="form-control item-tax-percent" value="5"></td>
            <td><input type="number" step="0.01" min="0" name="item_rate_per_pcs[]" class="form-control item-rate"></td>
            <td><input type="text" class="form-control item-taxable bg-light" readonly></td>
            <td><input type="text" class="form-control item-tax-amount bg-light" readonly></td>
            <td><input type="text" class="form-control item-total bg-light" readonly></td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="mdi mdi-delete-outline"></i></button>
            </td>
        `;
        itemsBody.appendChild(row);
        bindRowEvents(row);
        updateRowNumbers();
        calculateSummary();
    }

    addRowBtn.addEventListener('click', function() {
        addRow();
    });

    creditorSelect.addEventListener('change', updateSupplierCard);
    paidAmountInput.addEventListener('input', calculateSummary);
    resetFormBtn.addEventListener('click', function() {
        setTimeout(() => {
            updateSupplierCard();
            calculateSummary();
        }, 100);
    });

    itemsBody.querySelectorAll('.item-row').forEach(row => {
        bindRowEvents(row);
        calculateRow(row);
    });

    updateRowNumbers();
    updateSupplierCard();
    calculateSummary();
})();
</script>
</body>
</html>
