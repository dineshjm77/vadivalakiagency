<?php
session_start();
include('config/config.php');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

function vi_column_exists(mysqli $conn, string $table, string $column): bool {
    $table  = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $rs && mysqli_num_rows($rs) > 0;
}

function amount_to_words($number): string {
    $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
    $integer = floor((float)$number);
    if ($integer <= 0) return 'ZERO ONLY';
    return strtoupper($formatter->format($integer) . ' only');
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    die('Invalid invoice id');
}

$hasCustomerGst = vi_column_exists($conn, 'customers', 'gst_no');
$hasProductHsn = vi_column_exists($conn, 'products', 'hsn_code');
$hasBusinessFssai = vi_column_exists($conn, 'business_settings', 'fssai_no');
$extendedItemCols = ['hsn_code','case_pack','cases_qty','pieces_qty','free_qty','mrp','base_rate','discount_amount','gst_rate','net_rate','taxable_value','cgst_amount','sgst_amount'];
$hasExtended = true;
foreach ($extendedItemCols as $c) { if (!vi_column_exists($conn,'order_items',$c)) { $hasExtended=false; break; } }

$business = [
    'business_name'=>'Business Name','address'=>'','city'=>'','state'=>'','pincode'=>'','mobile'=>'','phone'=>'','gstin'=>'','fssai_no'=>''
];
$bsFields = 'business_name,address,city,state,pincode,mobile,phone,gstin' . ($hasBusinessFssai ? ',fssai_no' : '');
$bsRes = mysqli_query($conn, "SELECT $bsFields FROM business_settings ORDER BY id ASC LIMIT 1");
if ($bsRes && $bsRow = mysqli_fetch_assoc($bsRes)) $business = array_merge($business, $bsRow);

$orderSql = "SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, c.shop_location, COALESCE(c.assigned_area,'') AS assigned_area, u.name AS staff_name";
$orderSql .= $hasCustomerGst ? ", COALESCE(c.gst_no,'') AS gst_no" : ", '' AS gst_no";
$orderSql .= " FROM orders o LEFT JOIN customers c ON o.customer_id=c.id LEFT JOIN admin_users u ON o.created_by=u.id WHERE o.id=$order_id LIMIT 1";
$orderRes = mysqli_query($conn, $orderSql);
$order = $orderRes ? mysqli_fetch_assoc($orderRes) : null;
if (!$order) die('Invoice not found');

if ($hasExtended) {
    $itemSql = "SELECT oi.*, p.product_name, p.product_code FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$order_id ORDER BY oi.id ASC";
} else {
    $itemSql = "SELECT oi.*, p.product_name, p.product_code" . ($hasProductHsn ? ", COALESCE(p.hsn_code,'') AS hsn_code" : ", '' AS hsn_code") . ", 1 AS case_pack, 0 AS cases_qty, oi.quantity AS pieces_qty, 0 AS free_qty, oi.price AS mrp, oi.price AS base_rate, 0 AS discount_amount, 0 AS gst_rate, oi.price AS net_rate, oi.total AS taxable_value, 0 AS cgst_amount, 0 AS sgst_amount FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$order_id ORDER BY oi.id ASC";
}
$itemRes = mysqli_query($conn, $itemSql);
$items = [];
$taxable = 0; $cgst = 0; $sgst = 0; $grand = 0;
if ($itemRes) {
    while ($row = mysqli_fetch_assoc($itemRes)) {
        $items[] = $row;
        $taxable += (float)($row['taxable_value'] ?? $row['total'] ?? 0);
        $cgst += (float)($row['cgst_amount'] ?? 0);
        $sgst += (float)($row['sgst_amount'] ?? 0);
        $grand += (float)($row['total'] ?? 0);
    }
}
$roundOff = round($grand - ($taxable + $cgst + $sgst), 2);
$amountWords = amount_to_words($grand);
$sellerAddress = trim(implode(', ', array_filter([$business['address'],$business['city'],$business['state'],$business['pincode']])));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?php echo htmlspecialchars($order['order_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f2f4f7;font-family:Arial,Helvetica,sans-serif}
.invoice-wrap{max-width:1100px;margin:20px auto}
.invoice-paper{background:#fff;border:1px solid #222;padding:18px}
.invoice-head{display:grid;grid-template-columns:1.2fr .5fr 1.2fr;border-bottom:1px solid #222}
.invoice-head>div{padding:10px;min-height:120px}
.invoice-head>div:nth-child(2){border-left:1px solid #222;border-right:1px solid #222;text-align:center}
.title{font-size:28px;font-weight:700;letter-spacing:1px;margin-top:28px}
.meta-row{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #222}
.meta-row div{padding:8px 10px;border-right:1px solid #222}.meta-row div:last-child{border-right:none}
.table td,.table th{border:1px solid #222 !important;padding:4px 6px;font-size:13px;vertical-align:top}
.summary-grid{display:grid;grid-template-columns:2fr 1fr;gap:0}
.summary-left,.summary-right{border:1px solid #222;border-top:none;padding:10px}
.summary-right .big-total{font-size:38px;font-weight:700;text-align:right}
.sign-row{display:grid;grid-template-columns:1fr 1fr 1fr;border:1px solid #222;border-top:none}
.sign-row div{padding:18px 12px;min-height:72px;display:flex;align-items:flex-end;justify-content:center;font-weight:700}
.toolbar{display:flex;gap:10px;justify-content:end;margin-bottom:14px}
@media print{body{background:#fff}.toolbar{display:none}.invoice-wrap{max-width:none;margin:0}.invoice-paper{border:none;padding:0}}
</style>
</head>
<body>
<div class="invoice-wrap">
    <div class="toolbar">
        <a href="quick-order.php" class="btn btn-secondary">Back</a>
        <a href="view-invoice-a5.php?id=<?php echo (int)$order_id; ?>" class="btn btn-outline-primary">Open A5 View</a>
        <button class="btn btn-primary" onclick="window.print()">Print A4</button>
    </div>

    <div class="invoice-paper">
        <div class="invoice-head">
            <div>
                <div style="font-size:18px;font-weight:700"><?php echo htmlspecialchars($business['business_name']); ?></div>
                <div><?php echo htmlspecialchars($sellerAddress); ?></div>
                <div><strong>CELL :</strong> <?php echo htmlspecialchars((string)$business['mobile']); ?></div>
                <div><strong>GSTIN :</strong> <?php echo htmlspecialchars((string)$business['gstin']); ?></div>
            </div>
            <div><div class="title">TAX<br>INVOICE</div></div>
            <div>
                <div style="font-size:18px;font-weight:700">To: <?php echo htmlspecialchars($order['shop_name']); ?></div>
                <div><?php echo nl2br(htmlspecialchars((string)$order['shop_location'])); ?></div>
                <div><strong>GSTIN :</strong> <?php echo htmlspecialchars((string)$order['gst_no']); ?></div>
                <div><strong>PH No :</strong> <?php echo htmlspecialchars((string)$order['customer_contact']); ?></div>
            </div>
        </div>

        <div class="meta-row">
            <div><strong>Invoice No :</strong> <?php echo htmlspecialchars($order['order_number']); ?></div>
            <div><strong>Date :</strong> <?php echo date('d-m-y', strtotime($order['order_date'])); ?></div>
            <div><strong>FSSAI :</strong> <?php echo htmlspecialchars((string)$business['fssai_no']); ?></div>
            <div><strong>Staff :</strong> <?php echo htmlspecialchars((string)($order['staff_name'] ?: ($_SESSION['name'] ?? 'Admin'))); ?></div>
        </div>

        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width:40px">No</th>
                    <th style="width:90px">HSN</th>
                    <th>Product Name</th>
                    <th style="width:55px">CS</th>
                    <th style="width:55px">PC</th>
                    <th style="width:55px">FR</th>
                    <th style="width:70px">MRP</th>
                    <th style="width:70px">Rate</th>
                    <th style="width:70px">Disc</th>
                    <th style="width:70px">GST%</th>
                    <th style="width:80px">Net Rate</th>
                    <th style="width:90px">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars((string)($item['hsn_code'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)($item['product_name'] ?? '')); ?></td>
                    <td><?php echo (int)($item['cases_qty'] ?? 0); ?></td>
                    <td><?php echo (int)($item['pieces_qty'] ?? ($item['quantity'] ?? 0)); ?></td>
                    <td><?php echo (int)($item['free_qty'] ?? 0); ?></td>
                    <td><?php echo number_format((float)($item['mrp'] ?? 0), 2); ?></td>
                    <td><?php echo number_format((float)($item['base_rate'] ?? $item['price'] ?? 0), 2); ?></td>
                    <td><?php echo number_format((float)($item['discount_amount'] ?? 0), 2); ?></td>
                    <td><?php echo rtrim(rtrim(number_format((float)($item['gst_rate'] ?? 0), 2), '0'), '.'); ?>%</td>
                    <td><?php echo number_format((float)($item['net_rate'] ?? $item['price'] ?? 0), 2); ?></td>
                    <td><?php echo number_format((float)($item['total'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="12" class="text-center">No invoice items found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-grid">
            <div class="summary-left">
                <div style="font-weight:700;margin-bottom:8px"><?php echo htmlspecialchars($amountWords); ?></div>
                <div class="row">
                    <div class="col-6">
                        <div><strong>Sales Value</strong> <?php echo number_format($taxable, 2); ?></div>
                        <div><strong>CGST %</strong> 2.5%</div>
                        <div><strong>Amount</strong> <?php echo number_format($cgst, 2); ?></div>
                    </div>
                    <div class="col-6">
                        <div><strong>Sales Value</strong> <?php echo number_format($taxable, 2); ?></div>
                        <div><strong>SGST %</strong> 2.5%</div>
                        <div><strong>Amount</strong> <?php echo number_format($sgst, 2); ?></div>
                    </div>
                </div>
            </div>
            <div class="summary-right">
                <div><strong>Taxable Value :</strong> <?php echo number_format($taxable, 2); ?></div>
                <div><strong>CGST :</strong> <?php echo number_format($cgst, 2); ?></div>
                <div><strong>SGST :</strong> <?php echo number_format($sgst, 2); ?></div>
                <div><strong>Round Off :</strong> <?php echo number_format($roundOff, 2); ?></div>
                <div class="big-total"><?php echo number_format($grand, 2); ?></div>
            </div>
        </div>

        <div class="sign-row">
            <div>BUYER'S SIGNATURE</div>
            <div>E.&amp;.O.E</div>
            <div>For <?php echo strtoupper(htmlspecialchars($business['business_name'])); ?></div>
        </div>
    </div>
</div>
</body>
</html>
