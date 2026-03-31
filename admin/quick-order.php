<?php
session_start();
include('config/config.php');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$staff_name = trim((string)($_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Admin'));
$error_message = '';
$success_message = '';

function qo_column_exists(mysqli $conn, string $table, string $column): bool {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function qo_generate_invoice_number(mysqli $conn): string {
    $month = (int)date('n');
    $startYear = (int)date($month >= 4 ? 'y' : 'y', $month >= 4 ? time() : strtotime('-1 year'));
    $endYear = (int)date($month >= 4 ? 'y' : 'y', $month >= 4 ? strtotime('+1 year') : time());
    $fyPrefix = sprintf('%02d-%02d/', $startYear, $endYear);
    $safePrefix = mysqli_real_escape_string($conn, $fyPrefix);

    $lastNo = 19000;
    $rs = mysqli_query($conn, "SELECT order_number FROM orders WHERE order_number LIKE '{$safePrefix}%' ORDER BY id DESC LIMIT 1");
    if ($rs && $row = mysqli_fetch_assoc($rs)) {
        if (preg_match('~/([0-9]+)$~', (string)$row['order_number'], $m)) {
            $lastNo = (int)$m[1];
        }
    }

    return $fyPrefix . ($lastNo + 1);
}

$hasCustomerGst = qo_column_exists($conn, 'customers', 'gst_no');
$hasCustomerBeat = qo_column_exists($conn, 'customers', 'assigned_area');
$hasProductHsn = qo_column_exists($conn, 'products', 'hsn_code');
$hasProductCasePack = qo_column_exists($conn, 'products', 'case_pack');
$hasProductMrp = qo_column_exists($conn, 'products', 'mrp');
$hasProductGst = qo_column_exists($conn, 'products', 'gst_rate');
$hasBusinessFssai = qo_column_exists($conn, 'business_settings', 'fssai_no');

$extendedOrderItemColumns = [
    'hsn_code', 'case_pack', 'cases_qty', 'pieces_qty', 'free_qty', 'mrp',
    'base_rate', 'discount_amount', 'gst_rate', 'net_rate', 'taxable_value',
    'cgst_amount', 'sgst_amount'
];
$hasExtendedOrderItems = true;
foreach ($extendedOrderItemColumns as $col) {
    if (!qo_column_exists($conn, 'order_items', $col)) {
        $hasExtendedOrderItems = false;
        break;
    }
}

$business = [
    'business_name' => 'Business Name',
    'address' => '',
    'city' => '',
    'state' => '',
    'pincode' => '',
    'mobile' => '',
    'phone' => '',
    'gstin' => '',
    'fssai_no' => '',
];
$bsFields = "business_name, address, city, state, pincode, mobile, phone, gstin";
if ($hasBusinessFssai) {
    $bsFields .= ", fssai_no";
}
$bsRes = mysqli_query($conn, "SELECT $bsFields FROM business_settings ORDER BY id ASC LIMIT 1");
if ($bsRes && $bsRow = mysqli_fetch_assoc($bsRes)) {
    $business = array_merge($business, $bsRow);
}

$preselected_customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$preselected_product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$preselected_product_quantity = isset($_GET['quantity']) ? max(1, (int)$_GET['quantity']) : 1;

$customerSelect = "c.id, c.customer_code, c.shop_name, c.customer_name, c.customer_contact,
                   c.shop_location, c.current_balance, c.payment_terms, c.zone_id,
                   l.full_name AS lineman_name";
$customerSelect .= $hasCustomerGst ? ", COALESCE(c.gst_no, '') AS gst_no" : ", '' AS gst_no";
$customerSelect .= $hasCustomerBeat ? ", COALESCE(c.assigned_area, '') AS assigned_area" : ", '' AS assigned_area";

$customers_sql = "SELECT $customerSelect
                  FROM customers c
                  LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
                  WHERE c.status = 'active'
                  ORDER BY c.shop_name ASC";
$customers_result = mysqli_query($conn, $customers_sql);

$preselected_customer = null;
if ($preselected_customer_id > 0) {
    $check_sql = "SELECT $customerSelect
                  FROM customers c
                  LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
                  WHERE c.id = $preselected_customer_id AND c.status = 'active'";
    $check_result = mysqli_query($conn, $check_sql);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $preselected_customer = mysqli_fetch_assoc($check_result);
    } else {
        $error_message = 'Invalid customer';
        $preselected_customer_id = 0;
    }
}

$productSelect = "id, product_code, product_name, customer_price, quantity";
$productSelect .= $hasProductHsn ? ", COALESCE(hsn_code, '') AS hsn_code" : ", '' AS hsn_code";
$productSelect .= $hasProductCasePack ? ", COALESCE(case_pack, 1) AS case_pack" : ", 1 AS case_pack";
$productSelect .= $hasProductMrp ? ", COALESCE(mrp, customer_price) AS mrp" : ", customer_price AS mrp";
$productSelect .= $hasProductGst ? ", COALESCE(gst_rate, 0) AS gst_rate" : ", 0 AS gst_rate";

$products_sql = "SELECT $productSelect FROM products WHERE status = 'active' AND quantity > 0 ORDER BY product_name ASC";
$products_result = mysqli_query($conn, $products_sql);

$products_array = [];
$products_lookup = [];
if ($products_result) {
    while ($product = mysqli_fetch_assoc($products_result)) {
        $product['quantity'] = (int)$product['quantity'];
        $product['case_pack'] = max(1, (int)$product['case_pack']);
        $product['customer_price'] = (float)$product['customer_price'];
        $product['mrp'] = (float)$product['mrp'];
        $product['gst_rate'] = (float)$product['gst_rate'];
        $products_array[] = $product;
        $products_lookup[(int)$product['id']] = $product;
    }
}

$preselected_product = null;
if ($preselected_product_id > 0) {
    if (isset($products_lookup[$preselected_product_id])) {
        $preselected_product = $products_lookup[$preselected_product_id];
        if ($preselected_product_quantity > (int)$preselected_product['quantity']) {
            $preselected_product_quantity = (int)$preselected_product['quantity'];
            $error_message .= ($error_message ? ' | ' : '') . 'Only ' . $preselected_product['quantity'] . ' units available for ' . $preselected_product['product_name'];
        }
    } else {
        $error_message .= ($error_message ? ' | ' : '') . 'Invalid product or product out of stock';
        $preselected_product_id = 0;
    }
}

$order_number = qo_generate_invoice_number($conn);
$order_date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));
    $payment_status = trim((string)($_POST['payment_status'] ?? 'pending'));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $postedOrderNumber = trim((string)($_POST['order_number'] ?? ''));
    $postedOrderDate = trim((string)($_POST['order_date'] ?? date('Y-m-d')));
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);

    if ($customer_id <= 0) {
        $error_message = 'Please select a customer';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedOrderDate)) {
        $error_message = 'Invalid invoice date';
    } else {
        $customerRowSql = "SELECT id, current_balance, total_purchases, zone_id FROM customers WHERE id = $customer_id AND status = 'active' LIMIT 1";
        $customerRowRes = mysqli_query($conn, $customerRowSql);
        $customerRow = $customerRowRes ? mysqli_fetch_assoc($customerRowRes) : null;

        if (!$customerRow) {
            $error_message = 'Invalid customer selection';
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
            $total_stock_out = 0;

            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = (int)$product_ids[$i];
                if ($product_id <= 0 || !isset($products_lookup[$product_id])) {
                    continue;
                }

                $product = $products_lookup[$product_id];
                $case_pack = max(1, (int)($case_packs[$i] ?? $product['case_pack'] ?? 1));
                $cases = max(0, (int)($cases_qty[$i] ?? 0));
                $pieces = max(0, (int)($pieces_qty[$i] ?? 0));
                $free = max(0, (int)($free_qty[$i] ?? 0));
                $mrp = max(0, (float)($mrps[$i] ?? $product['mrp'] ?? $product['customer_price']));
                $rate = max(0, (float)($base_rates[$i] ?? $product['customer_price']));
                $disc = max(0, (float)($discount_amounts[$i] ?? 0));
                $gst = max(0, (float)($gst_rates[$i] ?? $product['gst_rate'] ?? 0));
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
                $net_rate = $taxable_rate * (1 + ($gst / 100));
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
                    'net_rate' => round($net_rate, 2),
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
                $total_stock_out += $stock_qty;
            }

            $grand_total = round($grand_total, 2);
            $taxable_total = round($taxable_total, 2);
            $cgst_total = round($cgst_total, 2);
            $sgst_total = round($sgst_total, 2);
            $round_off = round($grand_total - ($taxable_total + $cgst_total + $sgst_total), 2);

            if (!$error_message && empty($lineItems)) {
                $error_message = 'Please add at least one product to the invoice';
            }

            if (!$error_message) {
                if ($payment_status === 'paid') {
                    $paid_amount = $grand_total;
                } elseif ($payment_status === 'pending') {
                    $paid_amount = 0;
                }

                if ($paid_amount < 0 || $paid_amount > $grand_total) {
                    $error_message = 'Invalid paid amount';
                } elseif ($payment_status === 'partial' && ($paid_amount <= 0 || $paid_amount >= $grand_total)) {
                    $error_message = 'For partial payment, paid amount must be greater than 0 and less than total';
                }
            }

            if (!$error_message) {
                $pending_amount = round($grand_total - $paid_amount, 2);
                $invoice_notes = $notes;
                $zone_id = (isset($customerRow['zone_id']) && is_numeric($customerRow['zone_id']) && (int)$customerRow['zone_id'] > 0)
                    ? (int)$customerRow['zone_id']
                    : null;

                mysqli_begin_transaction($conn);
                try {
                    $paymentDate = $paid_amount > 0 ? date('Y-m-d H:i:s') : null;
                    $deliveryDate = $postedOrderDate;

                    if ($zone_id === null) {
                        $order_sql = "INSERT INTO orders (
                                        customer_id, order_number, order_date, total_items, total_amount,
                                        status, delivery_date, payment_date, notes, created_by,
                                        payment_method, payment_status, paid_amount, pending_amount, zone_id
                                      ) VALUES (?, ?, ?, ?, ?, 'delivered', ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
                        $stmt = mysqli_prepare($conn, $order_sql);
                        mysqli_stmt_bind_param(
                            $stmt,
                            'issidsssissdd',
                            $customer_id,
                            $postedOrderNumber,
                            $postedOrderDate,
                            $total_bill_qty,
                            $grand_total,
                            $deliveryDate,
                            $paymentDate,
                            $invoice_notes,
                            $admin_id,
                            $payment_method,
                            $payment_status,
                            $paid_amount,
                            $pending_amount
                        );
                    } else {
                        $order_sql = "INSERT INTO orders (
                                        customer_id, order_number, order_date, total_items, total_amount,
                                        status, delivery_date, payment_date, notes, created_by,
                                        payment_method, payment_status, paid_amount, pending_amount, zone_id
                                      ) VALUES (?, ?, ?, ?, ?, 'delivered', ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $order_sql);
                        mysqli_stmt_bind_param(
                            $stmt,
                            'issidsssissddi',
                            $customer_id,
                            $postedOrderNumber,
                            $postedOrderDate,
                            $total_bill_qty,
                            $grand_total,
                            $deliveryDate,
                            $paymentDate,
                            $invoice_notes,
                            $admin_id,
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
                            $item_sql = "INSERT INTO order_items (
                                            order_id, product_id, quantity, price, total, hsn_code, case_pack,
                                            cases_qty, pieces_qty, free_qty, mrp, base_rate, discount_amount,
                                            gst_rate, net_rate, taxable_value, cgst_amount, sgst_amount
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $item_stmt = mysqli_prepare($conn, $item_sql);
                            mysqli_stmt_bind_param(
                                $item_stmt,
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
                            $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price, total)
                                         VALUES (?, ?, ?, ?, ?)";
                            $item_stmt = mysqli_prepare($conn, $item_sql);
                            mysqli_stmt_bind_param(
                                $item_stmt,
                                'iiidd',
                                $order_id,
                                $item['product_id'],
                                $item['bill_qty'],
                                $item['net_rate'],
                                $item['line_total']
                            );
                        }

                        if (!mysqli_stmt_execute($item_stmt)) {
                            throw new Exception('Failed to add order item: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_close($item_stmt);

                        $update_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, 'iii', $item['stock_qty'], $item['product_id'], $item['stock_qty']);
                        if (!mysqli_stmt_execute($update_stmt) || mysqli_stmt_affected_rows($update_stmt) === 0) {
                            throw new Exception('Failed to update stock for ' . $item['product_name']);
                        }
                        mysqli_stmt_close($update_stmt);
                    }

                    if ($paid_amount > 0) {
                        $payment_id = 'PAY' . date('YmdHis') . rand(100, 999);
                        $payment_notes = 'Payment for invoice #' . $postedOrderNumber;
                        $payment_sql = "INSERT INTO transactions (
                                            customer_id, order_id, payment_id, type, amount,
                                            payment_method, reference_no, notes, created_by, created_at
                                        ) VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
                        $payment_stmt = mysqli_prepare($conn, $payment_sql);
                        mysqli_stmt_bind_param(
                            $payment_stmt,
                            'iisdsssi',
                            $customer_id,
                            $order_id,
                            $payment_id,
                            $paid_amount,
                            $payment_method,
                            $postedOrderNumber,
                            $payment_notes,
                            $admin_id
                        );
                        if (!mysqli_stmt_execute($payment_stmt)) {
                            throw new Exception('Failed to record payment transaction: ' . mysqli_error($conn));
                        }
                        $transaction_id = (int)mysqli_insert_id($conn);
                        mysqli_stmt_close($payment_stmt);

                        $history_sql = "INSERT INTO payment_history (
                                            order_id, transaction_id, amount_paid, payment_method,
                                            reference_no, notes, created_by, created_at
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        $history_stmt = mysqli_prepare($conn, $history_sql);
                        mysqli_stmt_bind_param(
                            $history_stmt,
                            'iidsssi',
                            $order_id,
                            $transaction_id,
                            $paid_amount,
                            $payment_method,
                            $postedOrderNumber,
                            $payment_notes,
                            $admin_id
                        );
                        if (!mysqli_stmt_execute($history_stmt)) {
                            throw new Exception('Failed to record payment history: ' . mysqli_error($conn));
                        }
                        mysqli_stmt_close($history_stmt);
                    }

                    $balance_sql = "UPDATE customers
                                    SET current_balance = current_balance + ?,
                                        total_purchases = total_purchases + ?,
                                        last_purchase_date = ?
                                    WHERE id = ?";
                    $balance_stmt = mysqli_prepare($conn, $balance_sql);
                    mysqli_stmt_bind_param($balance_stmt, 'ddsi', $pending_amount, $grand_total, $postedOrderDate, $customer_id);
                    if (!mysqli_stmt_execute($balance_stmt)) {
                        throw new Exception('Failed to update customer balance: ' . mysqli_error($conn));
                    }
                    mysqli_stmt_close($balance_stmt);

                    mysqli_commit($conn);
                    header('Location: quick-order.php?success=1&order_id=' . $order_id);
                    exit;
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_message = $e->getMessage();
                }
            }
        }
    }
}

$customers_for_js = [];
if ($customers_result) {
    mysqli_data_seek($customers_result, 0);
    while ($c = mysqli_fetch_assoc($customers_result)) {
        $customers_for_js[] = $c;
    }
    mysqli_data_seek($customers_result, 0);
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
            <?php $current_page = 'quick-order'; include('includes/sidebar.php'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle-outline me-2"></i>
                        Invoice created successfully.
                        <?php if (isset($_GET['order_id'])): ?>
                            <a href="view-invoice.php?id=<?php echo (int)$_GET['order_id']; ?>" class="alert-link">View Invoice</a>
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

                <?php if ($preselected_product): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-information-outline me-2"></i>
                        Pre-added product: <strong><?php echo htmlspecialchars($preselected_product['product_name']); ?></strong>
                        (Qty: <?php echo (int)$preselected_product_quantity; ?>)
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="orderForm" onsubmit="return validateInvoiceForm();">
                    <input type="hidden" name="order_number" id="order_number" value="<?php echo htmlspecialchars($order_number); ?>">
                    <input type="hidden" name="order_date" id="order_date" value="<?php echo htmlspecialchars($order_date); ?>">

                    <div class="card invoice-entry-card mb-4">
                        <div class="card-body p-3 p-md-4">
                            <div class="invoice-sheet">
                                <div class="invoice-top-row">
                                    <div class="invoice-company-block">
                                        <h3 class="invoice-company-name mb-1"><?php echo htmlspecialchars($business['business_name']); ?></h3>
                                        <div class="small text-muted mb-1">
                                            <?php
                                            $sellerAddress = trim(implode(', ', array_filter([
                                                $business['address'] ?? '',
                                                $business['city'] ?? '',
                                                $business['state'] ?? '',
                                                $business['pincode'] ?? ''
                                            ])));
                                            echo htmlspecialchars($sellerAddress ?: 'Business address');
                                            ?>
                                        </div>
                                        <div><strong>GSTIN :</strong> <span id="sellerGstinText"><?php echo htmlspecialchars((string)($business['gstin'] ?? '')); ?></span></div>
                                        <div><strong>Cell :</strong> <?php echo htmlspecialchars((string)($business['mobile'] ?? '')); ?></div>
                                        <?php if (!empty($business['phone'])): ?>
                                            <div><strong>Phone :</strong> <?php echo htmlspecialchars((string)$business['phone']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($business['fssai_no'])): ?>
                                            <div><strong>FSSAI :</strong> <?php echo htmlspecialchars((string)$business['fssai_no']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="invoice-title-block text-center">
                                        <div class="invoice-title">TAX INVOICE</div>
                                    </div>
                                    <div class="invoice-customer-block">
                                        <div class="fw-bold mb-1">To: <span id="invoiceCustomerName"><?php echo htmlspecialchars($preselected_customer['shop_name'] ?? 'Select customer'); ?></span></div>
                                        <div id="invoiceCustomerAddress" class="small text-muted mb-1"><?php echo htmlspecialchars($preselected_customer['shop_location'] ?? ''); ?></div>
                                        <div><strong>GSTIN :</strong> <span id="invoiceCustomerGstin"><?php echo htmlspecialchars($preselected_customer['gst_no'] ?? ''); ?></span></div>
                                        <div><strong>PH No :</strong> <span id="invoiceCustomerPhone"><?php echo htmlspecialchars($preselected_customer['customer_contact'] ?? ''); ?></span></div>
                                        <div><strong>Beat :</strong> <span id="invoiceCustomerBeat"><?php echo htmlspecialchars($preselected_customer['assigned_area'] ?? ''); ?></span></div>
                                    </div>
                                </div>

                                <div class="invoice-meta-row">
                                    <div><strong>Invoice No :</strong> <span id="invoiceNumberText"><?php echo htmlspecialchars($order_number); ?></span></div>
                                    <div><strong>Date :</strong> <span id="invoiceDateText"><?php echo htmlspecialchars(date('d-m-y', strtotime($order_date))); ?></span></div>
                                    <div><strong>Staff :</strong> <?php echo htmlspecialchars($staff_name); ?></div>
                                    <div><strong>Payment :</strong> <span id="invoicePaymentStatusText">Pending</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-xl-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><i class="mdi mdi-store me-2"></i>Customer & Invoice Details</h5>

                                    <div class="mb-3">
                                        <label class="form-label">Search Customer</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customerSearch" placeholder="Search by shop / customer / phone">
                                            <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Select Customer *</label>
                                        <select class="form-select" id="customer_id" name="customer_id" required>
                                            <option value="">-- Select customer --</option>
                                            <?php foreach ($customers_for_js as $customer): ?>
                                                <option
                                                    value="<?php echo (int)$customer['id']; ?>"
                                                    data-contact="<?php echo htmlspecialchars((string)$customer['customer_contact']); ?>"
                                                    data-code="<?php echo htmlspecialchars((string)$customer['customer_code']); ?>"
                                                    data-address="<?php echo htmlspecialchars((string)$customer['shop_location']); ?>"
                                                    data-balance="<?php echo htmlspecialchars((string)$customer['current_balance']); ?>"
                                                    data-terms="<?php echo htmlspecialchars((string)$customer['payment_terms']); ?>"
                                                    data-lineman="<?php echo htmlspecialchars((string)($customer['lineman_name'] ?? '')); ?>"
                                                    data-gst="<?php echo htmlspecialchars((string)($customer['gst_no'] ?? '')); ?>"
                                                    data-beat="<?php echo htmlspecialchars((string)($customer['assigned_area'] ?? '')); ?>"
                                                    <?php echo ($preselected_customer_id === (int)$customer['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($customer['shop_name']); ?> - <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Customer Code</label>
                                            <input type="text" class="form-control" id="customer_code" readonly value="<?php echo htmlspecialchars($preselected_customer['customer_code'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Contact</label>
                                            <input type="text" class="form-control" id="customer_contact" readonly value="<?php echo htmlspecialchars($preselected_customer['customer_contact'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">GST No</label>
                                            <input type="text" class="form-control" id="customer_gst" readonly value="<?php echo htmlspecialchars($preselected_customer['gst_no'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Beat</label>
                                            <input type="text" class="form-control" id="customer_beat" readonly value="<?php echo htmlspecialchars($preselected_customer['assigned_area'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Current Balance</label>
                                            <input type="text" class="form-control" id="customer_balance" readonly value="<?php echo htmlspecialchars($preselected_customer ? '₹' . number_format((float)$preselected_customer['current_balance'], 2) : '₹0.00'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Payment Terms</label>
                                            <input type="text" class="form-control" id="payment_terms" readonly value="<?php echo htmlspecialchars($preselected_customer ? ucfirst(str_replace('_', ' ', (string)$preselected_customer['payment_terms'])) : ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" id="shop_location" rows="3" readonly><?php echo htmlspecialchars($preselected_customer['shop_location'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label">Assigned Lineman</label>
                                        <input type="text" class="form-control" id="assigned_lineman" readonly value="<?php echo htmlspecialchars($preselected_customer['lineman_name'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <h5 class="card-title mb-0"><i class="mdi mdi-cube-outline me-2"></i>Add Products</h5>
                                        <div class="search-box quick-product-search">
                                            <input type="text" class="form-control" id="productSearch" placeholder="Search product / code / HSN">
                                            <i class="ri-search-line search-icon"></i>
                                        </div>
                                    </div>

                                    <div class="table-responsive product-picker-table-wrap">
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
                                                <?php foreach ($products_array as $product): ?>
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
                                                                <i class="mdi mdi-plus"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <h5 class="card-title mb-0"><i class="mdi mdi-file-document-outline me-2"></i>Invoice Items</h5>
                                        <small class="text-muted">Layout aligned to your sample invoice format</small>
                                    </div>

                                    <div class="table-responsive invoice-items-wrap">
                                        <table class="table table-bordered align-middle mb-0 invoice-items-table" id="invoiceItemsTable">
                                            <thead>
                                                <tr>
                                                    <th>No</th>
                                                    <th>HSN</th>
                                                    <th>Product Name</th>
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
                                            <tbody id="invoiceItemsBody">
                                                <tr id="emptyInvoiceRow">
                                                    <td colspan="14" class="text-center text-muted py-4">No products added yet</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-lg-5">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3"><i class="mdi mdi-cash-multiple me-2"></i>Payment Information</h5>

                                            <div class="mb-3">
                                                <label class="form-label">Payment Method *</label>
                                                <select class="form-select" name="payment_method" id="payment_method" required>
                                                    <option value="cash">Cash</option>
                                                    <option value="upi">UPI</option>
                                                    <option value="card">Card</option>
                                                    <option value="bank_transfer">Bank Transfer</option>
                                                    <option value="wallet">Wallet</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Payment Status *</label>
                                                <select class="form-select" name="payment_status" id="payment_status" required>
                                                    <option value="pending">Pending</option>
                                                    <option value="paid">Paid</option>
                                                    <option value="partial">Partial</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Amount Paid (₹) *</label>
                                                <input type="number" class="form-control" name="paid_amount" id="paid_amount" min="0" step="0.01" value="0">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Pending Amount (₹)</label>
                                                <input type="text" class="form-control" id="pending_amount" value="₹0.00" readonly>
                                            </div>

                                            <div class="mb-0">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="notes" id="notes" rows="4" placeholder="Special instructions, delivery details, remarks..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    <div class="card h-100 totals-card">
                                        <div class="card-body">
                                            <h5 class="card-title mb-3"><i class="mdi mdi-calculator-variant-outline me-2"></i>Invoice Totals</h5>

                                            <div class="totals-grid">
                                                <div class="total-label">Taxable Value</div>
                                                <div class="total-value" id="taxable_value_text">₹0.00</div>

                                                <div class="total-label">CGST</div>
                                                <div class="total-value" id="cgst_total_text">₹0.00</div>

                                                <div class="total-label">SGST</div>
                                                <div class="total-value" id="sgst_total_text">₹0.00</div>

                                                <div class="total-label">Round Off</div>
                                                <div class="total-value" id="round_off_text">₹0.00</div>

                                                <div class="total-label fw-bold">Grand Total</div>
                                                <div class="total-value grand-total" id="grand_total_text">₹0.00</div>
                                            </div>

                                            <input type="hidden" name="total_amount" id="total_amount" value="0.00">

                                            <div class="invoice-footer-preview mt-4">
                                                <div class="small text-muted mb-2">Amount in words</div>
                                                <div class="fw-semibold" id="amount_words_text">Zero only</div>
                                            </div>

                                            <div class="d-grid gap-2 mt-4">
                                                <button type="submit" name="create_order" class="btn btn-primary btn-lg">
                                                    <i class="mdi mdi-check-circle-outline me-2"></i>Create Invoice
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" id="resetInvoiceBtn">
                                                    <i class="mdi mdi-refresh me-2"></i>Reset Form
                                                </button>
                                            </div>
                                        </div>
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

<style>
.invoice-entry-card .invoice-sheet {
    border: 1px solid #cfd6e4;
    background: #fff;
}
.invoice-top-row {
    display: grid;
    grid-template-columns: 1.2fr .5fr 1.2fr;
    border-bottom: 1px solid #cfd6e4;
}
.invoice-company-block,
.invoice-title-block,
.invoice-customer-block {
    padding: 16px;
    min-height: 150px;
}
.invoice-title-block {
    display: flex;
    align-items: center;
    justify-content: center;
    border-left: 1px solid #cfd6e4;
    border-right: 1px solid #cfd6e4;
}
.invoice-title {
    font-weight: 700;
    letter-spacing: 1px;
    font-size: 1.2rem;
}
.invoice-company-name {
    font-size: 1.35rem;
    font-weight: 700;
}
.invoice-meta-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0;
}
.invoice-meta-row > div {
    padding: 10px 16px;
    border-right: 1px solid #cfd6e4;
}
.invoice-meta-row > div:last-child {
    border-right: 0;
}
.quick-product-search {
    min-width: 280px;
}
.product-picker-table-wrap,
.invoice-items-wrap {
    max-height: 420px;
    overflow: auto;
}
.invoice-items-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fb;
    white-space: nowrap;
    font-size: 12px;
}
.invoice-items-table td,
.invoice-items-table th {
    padding: .55rem .45rem;
    vertical-align: middle;
}
.invoice-items-table input.form-control {
    min-width: 72px;
    padding: .35rem .4rem;
}
.invoice-items-table .product-cell {
    min-width: 220px;
}
.invoice-items-table .readonly-cell {
    min-width: 86px;
    text-align: right;
    font-weight: 600;
}
.totals-card .totals-grid {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: .6rem 1rem;
    align-items: center;
}
.totals-card .total-value {
    text-align: right;
    font-weight: 600;
}
.totals-card .grand-total {
    font-size: 1.35rem;
    color: #0d6efd;
}
.invoice-footer-preview {
    padding: 12px 14px;
    background: #f8f9fb;
    border-radius: 8px;
}
@media (max-width: 991.98px) {
    .invoice-top-row,
    .invoice-meta-row {
        grid-template-columns: 1fr;
    }
    .invoice-title-block,
    .invoice-meta-row > div {
        border-left: 0;
        border-right: 0;
        border-top: 1px solid #cfd6e4;
    }
    .quick-product-search {
        min-width: 100%;
    }
}
</style>

<script>
const productsData = <?php echo json_encode($products_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const preselectedProductId = <?php echo (int)$preselected_product_id; ?>;
const preselectedProductQuantity = <?php echo (int)$preselected_product_quantity; ?>;
const selectedLines = [];
const paymentStatusSelect = document.getElementById('payment_status');
const paidAmountInput = document.getElementById('paid_amount');
const customerSelect = document.getElementById('customer_id');

function formatMoney(value) {
    return '₹' + Number(value || 0).toFixed(2);
}

function toNumberWords(num) {
    num = Math.round(Number(num || 0));
    if (num === 0) return 'Zero only';
    const belowTwenty = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    function twoDigits(n) {
        if (n < 20) return belowTwenty[n];
        const t = Math.floor(n / 10);
        const r = n % 10;
        return tens[t] + (r ? ' ' + belowTwenty[r] : '');
    }
    function threeDigits(n) {
        const h = Math.floor(n / 100);
        const r = n % 100;
        let out = '';
        if (h) out += belowTwenty[h] + ' Hundred';
        if (r) out += (out ? ' ' : '') + twoDigits(r);
        return out;
    }
    let result = '';
    const crore = Math.floor(num / 10000000);
    num %= 10000000;
    const lakh = Math.floor(num / 100000);
    num %= 100000;
    const thousand = Math.floor(num / 1000);
    num %= 1000;
    const hundred = num;
    if (crore) result += threeDigits(crore) + ' Crore ';
    if (lakh) result += threeDigits(lakh) + ' Lakh ';
    if (thousand) result += threeDigits(thousand) + ' Thousand ';
    if (hundred) result += threeDigits(hundred);
    return result.trim() + ' only';
}

function getProductById(id) {
    return productsData.find(p => Number(p.id) === Number(id)) || null;
}

function updateCustomerDetails() {
    const option = customerSelect.options[customerSelect.selectedIndex];
    const code = option ? (option.dataset.code || '') : '';
    const contact = option ? (option.dataset.contact || '') : '';
    const address = option ? (option.dataset.address || '') : '';
    const balance = option ? Number(option.dataset.balance || 0) : 0;
    const terms = option ? (option.dataset.terms || '') : '';
    const lineman = option ? (option.dataset.lineman || '') : '';
    const gst = option ? (option.dataset.gst || '') : '';
    const beat = option ? (option.dataset.beat || '') : '';
    const name = option && option.value ? option.text.split(' - ')[0] : 'Select customer';

    document.getElementById('customer_code').value = code;
    document.getElementById('customer_contact').value = contact;
    document.getElementById('shop_location').value = address;
    document.getElementById('customer_balance').value = formatMoney(balance);
    document.getElementById('payment_terms').value = terms.replaceAll('_', ' ');
    document.getElementById('assigned_lineman').value = lineman;
    document.getElementById('customer_gst').value = gst;
    document.getElementById('customer_beat').value = beat;

    document.getElementById('invoiceCustomerName').textContent = name;
    document.getElementById('invoiceCustomerAddress').textContent = address;
    document.getElementById('invoiceCustomerGstin').textContent = gst;
    document.getElementById('invoiceCustomerPhone').textContent = contact;
    document.getElementById('invoiceCustomerBeat').textContent = beat;
}

function addProductLine(productId, initialPieces = 1) {
    const product = getProductById(productId);
    if (!product) return;

    const existing = selectedLines.find(line => Number(line.product_id) === Number(productId));
    if (existing) {
        existing.pieces_qty = Math.min(Number(product.quantity), Number(existing.pieces_qty) + Number(initialPieces || 1));
        renderInvoiceLines();
        return;
    }

    selectedLines.push({
        product_id: Number(product.id),
        product_name: product.product_name,
        product_code: product.product_code,
        hsn_code: product.hsn_code || '',
        case_pack: Math.max(1, Number(product.case_pack || 1)),
        cases_qty: 0,
        pieces_qty: Math.max(1, Number(initialPieces || 1)),
        free_qty: 0,
        mrp: Number(product.mrp || product.customer_price || 0),
        base_rate: Number(product.customer_price || 0),
        discount_amount: 0,
        gst_rate: Number(product.gst_rate || 0),
        available_stock: Number(product.quantity || 0)
    });

    renderInvoiceLines();
}

function removeProductLine(index) {
    selectedLines.splice(index, 1);
    renderInvoiceLines();
}

function updateLineValue(index, key, value) {
    if (!selectedLines[index]) return;
    if (['case_pack', 'cases_qty', 'pieces_qty', 'free_qty'].includes(key)) {
        selectedLines[index][key] = Math.max(0, parseInt(value || 0, 10));
        if (key === 'case_pack') {
            selectedLines[index][key] = Math.max(1, parseInt(value || 1, 10));
        }
    } else {
        selectedLines[index][key] = Math.max(0, parseFloat(value || 0));
    }
    renderInvoiceLines();
}

function calculateLine(line) {
    const casePack = Math.max(1, Number(line.case_pack || 1));
    const cases = Math.max(0, Number(line.cases_qty || 0));
    const pcs = Math.max(0, Number(line.pieces_qty || 0));
    const free = Math.max(0, Number(line.free_qty || 0));
    const billQty = (cases * casePack) + pcs;
    const stockQty = billQty + free;
    const baseRate = Math.max(0, Number(line.base_rate || 0));
    const disc = Math.max(0, Number(line.discount_amount || 0));
    const gst = Math.max(0, Number(line.gst_rate || 0));
    const taxableRate = Math.max(0, baseRate - disc);
    const netRate = taxableRate * (1 + (gst / 100));
    const total = billQty * netRate;
    const taxableValue = gst > 0 ? total / (1 + (gst / 100)) : total;
    const tax = total - taxableValue;
    return {
        billQty,
        stockQty,
        netRate,
        total,
        taxableValue,
        cgst: tax / 2,
        sgst: tax / 2
    };
}

function renderInvoiceLines() {
    const tbody = document.getElementById('invoiceItemsBody');
    tbody.innerHTML = '';

    if (selectedLines.length === 0) {
        tbody.innerHTML = '<tr id="emptyInvoiceRow"><td colspan="14" class="text-center text-muted py-4">No products added yet</td></tr>';
        updateTotals();
        return;
    }

    selectedLines.forEach((line, index) => {
        const calc = calculateLine(line);
        const stockWarning = calc.stockQty > Number(line.available_stock || 0);

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}
                <input type="hidden" name="product_id[]" value="${line.product_id}">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm" name="hsn_code[]" value="${(line.hsn_code || '').replace(/"/g, '&quot;')}">
            </td>
            <td class="product-cell">
                <div class="fw-semibold">${line.product_name}</div>
                <div class="small text-muted">${line.product_code} | Stock: ${line.available_stock}${stockWarning ? ' <span class="text-danger">(Exceeds)</span>' : ''}</div>
            </td>
            <td><input type="number" min="1" class="form-control form-control-sm" name="case_pack[]" value="${Math.max(1, Number(line.case_pack || 1))}" oninput="updateLineValue(${index}, 'case_pack', this.value)"></td>
            <td><input type="number" min="0" class="form-control form-control-sm" name="cases_qty[]" value="${Math.max(0, Number(line.cases_qty || 0))}" oninput="updateLineValue(${index}, 'cases_qty', this.value)"></td>
            <td><input type="number" min="0" class="form-control form-control-sm" name="pieces_qty[]" value="${Math.max(0, Number(line.pieces_qty || 0))}" oninput="updateLineValue(${index}, 'pieces_qty', this.value)"></td>
            <td><input type="number" min="0" class="form-control form-control-sm" name="free_qty[]" value="${Math.max(0, Number(line.free_qty || 0))}" oninput="updateLineValue(${index}, 'free_qty', this.value)"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="mrp[]" value="${Number(line.mrp || 0).toFixed(2)}" oninput="updateLineValue(${index}, 'mrp', this.value)"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="base_rate[]" value="${Number(line.base_rate || 0).toFixed(2)}" oninput="updateLineValue(${index}, 'base_rate', this.value)"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="discount_amount[]" value="${Number(line.discount_amount || 0).toFixed(2)}" oninput="updateLineValue(${index}, 'discount_amount', this.value)"></td>
            <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" name="gst_rate[]" value="${Number(line.gst_rate || 0).toFixed(2)}" oninput="updateLineValue(${index}, 'gst_rate', this.value)"></td>
            <td class="readonly-cell">${Number(calc.netRate || 0).toFixed(2)}</td>
            <td class="readonly-cell">${Number(calc.total || 0).toFixed(2)}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProductLine(${index})"><i class="mdi mdi-close"></i></button></td>
        `;
        tbody.appendChild(tr);
    });

    updateTotals();
}

function updateTotals() {
    let taxable = 0;
    let cgst = 0;
    let sgst = 0;
    let grand = 0;

    selectedLines.forEach(line => {
        const calc = calculateLine(line);
        taxable += calc.taxableValue;
        cgst += calc.cgst;
        sgst += calc.sgst;
        grand += calc.total;
    });

    taxable = Number(taxable.toFixed(2));
    cgst = Number(cgst.toFixed(2));
    sgst = Number(sgst.toFixed(2));
    grand = Number(grand.toFixed(2));
    const roundOff = Number((grand - (taxable + cgst + sgst)).toFixed(2));

    document.getElementById('taxable_value_text').textContent = formatMoney(taxable);
    document.getElementById('cgst_total_text').textContent = formatMoney(cgst);
    document.getElementById('sgst_total_text').textContent = formatMoney(sgst);
    document.getElementById('round_off_text').textContent = formatMoney(roundOff);
    document.getElementById('grand_total_text').textContent = formatMoney(grand);
    document.getElementById('total_amount').value = grand.toFixed(2);
    document.getElementById('amount_words_text').textContent = toNumberWords(grand);

    updatePaymentFields();
}

function updatePaymentFields() {
    const status = paymentStatusSelect.value;
    const total = Number(document.getElementById('total_amount').value || 0);
    document.getElementById('invoicePaymentStatusText').textContent = status.charAt(0).toUpperCase() + status.slice(1);

    if (status === 'paid') {
        paidAmountInput.value = total.toFixed(2);
        paidAmountInput.readOnly = true;
    } else if (status === 'pending') {
        paidAmountInput.value = '0.00';
        paidAmountInput.readOnly = true;
    } else {
        if (Number(paidAmountInput.value || 0) >= total) {
            paidAmountInput.value = '0.00';
        }
        paidAmountInput.readOnly = false;
    }

    const paid = Math.min(total, Math.max(0, Number(paidAmountInput.value || 0)));
    document.getElementById('pending_amount').value = formatMoney(total - paid);
}

function validateInvoiceForm() {
    if (!customerSelect.value) {
        alert('Please select a customer');
        return false;
    }
    if (selectedLines.length === 0) {
        alert('Please add at least one product');
        return false;
    }

    for (const line of selectedLines) {
        const calc = calculateLine(line);
        if (calc.billQty <= 0) {
            alert('Each line must have at least one billed quantity');
            return false;
        }
        if (calc.stockQty > Number(line.available_stock || 0)) {
            alert('Stock exceeded for ' + line.product_name);
            return false;
        }
    }

    const total = Number(document.getElementById('total_amount').value || 0);
    const paid = Number(paidAmountInput.value || 0);
    const status = paymentStatusSelect.value;

    if (total <= 0) {
        alert('Total amount must be greater than zero');
        return false;
    }
    if (paid < 0 || paid > total) {
        alert('Invalid paid amount');
        return false;
    }
    if (status === 'partial' && (paid <= 0 || paid >= total)) {
        alert('Partial payment must be greater than 0 and less than total');
        return false;
    }
    return true;
}

function resetInvoiceForm() {
    if (!confirm('Reset the current invoice?')) return;
    selectedLines.length = 0;
    document.getElementById('orderForm').reset();
    document.getElementById('order_number').value = <?php echo json_encode($order_number); ?>;
    document.getElementById('invoiceNumberText').textContent = <?php echo json_encode($order_number); ?>;
    document.getElementById('order_date').value = <?php echo json_encode($order_date); ?>;
    document.getElementById('invoiceDateText').textContent = <?php echo json_encode(date('d-m-y', strtotime($order_date))); ?>;
    updateCustomerDetails();
    renderInvoiceLines();
}

document.getElementById('customerSearch').addEventListener('input', function () {
    const term = this.value.toLowerCase();
    Array.from(customerSelect.options).forEach((option, index) => {
        if (index === 0) return;
        const text = option.text.toLowerCase();
        option.hidden = term && !text.includes(term);
    });
});

customerSelect.addEventListener('change', updateCustomerDetails);
paymentStatusSelect.addEventListener('change', updatePaymentFields);
paidAmountInput.addEventListener('input', updatePaymentFields);
document.getElementById('resetInvoiceBtn').addEventListener('click', resetInvoiceForm);

document.getElementById('productSearch').addEventListener('input', function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#productPickerTable tbody tr').forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
});

document.querySelectorAll('.add-product-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        addProductLine(this.dataset.id, 1);
    });
});

document.getElementById('invoiceDateText').textContent = new Date(document.getElementById('order_date').value).toLocaleDateString('en-GB').replaceAll('/', '-');
updateCustomerDetails();
renderInvoiceLines();

if (preselectedProductId > 0) {
    addProductLine(preselectedProductId, preselectedProductQuantity > 0 ? preselectedProductQuantity : 1);
}
</script>
</body>
</html>
