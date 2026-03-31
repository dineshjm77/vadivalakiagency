<?php
session_start();
include('config/config.php');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

function a5_column_exists(mysqli $conn, string $table, string $column): bool {
    $table  = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $rs = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $rs && mysqli_num_rows($rs) > 0;
}

function a5_words($number): string {
    $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);
    $integer = floor((float)$number);
    if ($integer <= 0) return 'ZERO ONLY';
    return strtoupper($formatter->format($integer) . ' only');
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) die('Invalid invoice id');

$hasCustomerGst = a5_column_exists($conn, 'customers', 'gst_no');
$hasBusinessFssai = a5_column_exists($conn, 'business_settings', 'fssai_no');
$extendedItemCols = ['hsn_code','case_pack','cases_qty','pieces_qty','free_qty','mrp','base_rate','discount_amount','gst_rate','net_rate','taxable_value','cgst_amount','sgst_amount'];
$hasExtended = true;
foreach ($extendedItemCols as $c) { if (!a5_column_exists($conn,'order_items',$c)) { $hasExtended=false; break; } }

$business = ['business_name'=>'Business Name','address'=>'','city'=>'','state'=>'','pincode'=>'','mobile'=>'','phone'=>'','gstin'=>'','fssai_no'=>''];
$bsFields = 'business_name,address,city,state,pincode,mobile,phone,gstin' . ($hasBusinessFssai ? ',fssai_no' : '');
$bsRes = mysqli_query($conn, "SELECT $bsFields FROM business_settings ORDER BY id ASC LIMIT 1");
if ($bsRes && $bsRow = mysqli_fetch_assoc($bsRes)) $business = array_merge($business, $bsRow);

$orderSql = "SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, c.shop_location, u.name AS staff_name";
$orderSql .= $hasCustomerGst ? ", COALESCE(c.gst_no,'') AS gst_no" : ", '' AS gst_no";
$orderSql .= " FROM orders o LEFT JOIN customers c ON o.customer_id=c.id LEFT JOIN admin_users u ON o.created_by=u.id WHERE o.id=$order_id LIMIT 1";
$orderRes = mysqli_query($conn, $orderSql);
$order = $orderRes ? mysqli_fetch_assoc($orderRes) : null;
if (!$order) die('Invoice not found');

$itemSql = $hasExtended
    ? "SELECT oi.*, p.product_name FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$order_id ORDER BY oi.id ASC"
    : "SELECT oi.*, p.product_name, '' AS hsn_code, 0 AS cases_qty, oi.quantity AS pieces_qty, 0 AS free_qty, oi.price AS mrp, oi.price AS base_rate, 0 AS discount_amount, 0 AS gst_rate, oi.price AS net_rate, oi.total AS taxable_value, 0 AS cgst_amount, 0 AS sgst_amount FROM order_items oi LEFT JOIN products p ON oi.product_id=p.id WHERE oi.order_id=$order_id ORDER BY oi.id ASC";
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
$amountWords = a5_words($grand);
$sellerAddress = trim(implode(', ', array_filter([$business['address'],$business['city'],$business['state'],$business['pincode']])));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>A5 Invoice <?php echo htmlspecialchars($order['order_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#eef1f4;font-family:Arial,Helvetica,sans-serif}
.toolbar{max-width:840px;margin:18px auto 10px;display:flex;justify-content:end;gap:10px}
.a5-wrap{width:210mm;margin:0 auto;background:#fff;padding:8mm;box-shadow:0 0 8px rgba(0,0,0,.15)}
.bill{border:1px solid #222}
.top{display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #222}
.top>div{padding:8px 10px;min-height:92px}
.top>div:first-child{border-right:1px solid #222}
.bill-title{text-align:center;font-weight:700;text-decoration:underline}
.meta{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-bottom:1px solid #222}
.meta div{padding:6px 8px;border-right:1px solid #222;font-size:13px}.meta div:last-child{border-right:none}
.tbl{width:100%;border-collapse:collapse}
.tbl th,.tbl td{border:1px solid #222;padding:3px 4px;font-size:12px;vertical-align:top}
.tbl th{font-weight:700;text-align:center}
.bottom{display:grid;grid-template-columns:1.5fr .9fr;border-top:1px solid #222}
.bottom .left,.bottom .right{padding:8px 10px;min-height:92px}.bottom .left{border-right:1px solid #222}
.sign{display:grid;grid-template-columns:1fr 1fr 1fr;border-top:1px solid #222}
.sign div{min-height:60px;display:flex;align-items:flex-end;justify-content:center;padding:8px 6px;font-weight:700}
.total-box{font-size:22px;font-weight:700;text-align:right}
@page { size: A5 portrait; margin: 6mm; }
@media print{body{background:#fff}.toolbar{display:none}.a5-wrap{width:auto;margin:0;padding:0;box-shadow:none}}
</style>
</head>
<body>
<div class="toolbar">
    <a href="view-invoice.php?id=<?php echo (int)$order_id; ?>" class="btn btn-outline-secondary">Open A4 View</a>
    <button class="btn btn-primary" onclick="window.print()">Print A5</button>
</div>
<div class="a5-wrap">
    <div class="bill">
        <div class="top">
            <div>
                <div style="font-size:16px;font-weight:700"><?php echo strtoupper(htmlspecialchars($business['business_name'])); ?></div>
                <div><?php echo htmlspecialchars($sellerAddress); ?></div>
                <div><strong>CELL :</strong> <?php echo htmlspecialchars((string)$business['mobile']); ?></div>
                <div><strong>GSTIN :</strong> <?php echo htmlspecialchars((string)$business['gstin']); ?></div>
            </div>
            <div>
                <div class="bill-title">TAX INVOICE</div>
                <div style="font-size:16px;font-weight:700;margin-top:4px">To: <?php echo htmlspecialchars($order['shop_name']); ?></div>
                <div><?php echo htmlspecialchars((string)$order['shop_location']); ?></div>
                <div><strong>GSTIN :</strong> <?php echo htmlspecialchars((string)$order['gst_no']); ?></div>
                <div><strong>PH No :</strong> <?php echo htmlspecialchars((string)$order['customer_contact']); ?></div>
            </div>
        </div>
        <div class="meta">
            <div><strong>Invoice No :</strong> <?php echo htmlspecialchars($order['order_number']); ?></div>
            <div><strong>Date :</strong> <?php echo date('d-m-y', strtotime($order['order_date'])); ?></div>
            <div><strong>FSSAI :</strong> <?php echo htmlspecialchars((string)$business['fssai_no']); ?></div>
            <div><strong>Staff :</strong> <?php echo htmlspecialchars((string)($order['staff_name'] ?: ($_SESSION['name'] ?? 'Admin'))); ?></div>
        </div>
        <table class="tbl">
            <thead>
                <tr>
                    <th>No</th><th>HSN</th><th>Product Name</th><th>CS</th><th>PC</th><th>FR</th><th>MRP</th><th>Rate</th><th>Disc</th><th>GST%</th><th>Net Rate</th><th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
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
            </tbody>
        </table>
        <div class="bottom">
            <div class="left">
                <div style="font-weight:700"><?php echo htmlspecialchars($amountWords); ?></div>
                <div class="mt-2"><strong>Sales Value</strong> <?php echo number_format($taxable,2); ?> &nbsp; <strong>CGST</strong> <?php echo number_format($cgst,2); ?></div>
                <div><strong>Sales Value</strong> <?php echo number_format($taxable,2); ?> &nbsp; <strong>SGST</strong> <?php echo number_format($sgst,2); ?></div>
            </div>
            <div class="right">
                <div class="total-box">Total : <?php echo number_format($grand,2); ?></div>
            </div>
        </div>
        <div class="sign">
            <div>BUYER'S SIGNATURE</div>
            <div>E.&amp;.O.E</div>
            <div>For <?php echo strtoupper(htmlspecialchars($business['business_name'])); ?></div>
        </div>
    </div>
</div>
</body>
</html>
