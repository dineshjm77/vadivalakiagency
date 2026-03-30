<?php
session_start();
include('config/config.php');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

function vi_column_exists(mysqli $conn, string $table, string $column): bool {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

function vi_money($value): string {
    return number_format((float)$value, 2, '.', '');
}

function vi_words($num): string {
    $num = round((float)$num);
    if ($num == 0) return 'ZERO ONLY';

    $ones = ['', 'ONE','TWO','THREE','FOUR','FIVE','SIX','SEVEN','EIGHT','NINE','TEN',
             'ELEVEN','TWELVE','THIRTEEN','FOURTEEN','FIFTEEN','SIXTEEN','SEVENTEEN','EIGHTEEN','NINETEEN'];
    $tens = ['', '', 'TWENTY','THIRTY','FORTY','FIFTY','SIXTY','SEVENTY','EIGHTY','NINETY'];

    $convert = function($n) use (&$convert, $ones, $tens) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[intdiv($n,10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        if ($n < 1000) return $ones[intdiv($n,100)] . ' HUNDRED' . ($n % 100 ? ' AND ' . $convert($n % 100) : '');
        if ($n < 100000) return $convert(intdiv($n,1000)) . ' THOUSAND' . ($n % 1000 ? ' ' . $convert($n % 1000) : '');
        if ($n < 10000000) return $convert(intdiv($n,100000)) . ' LAKH' . ($n % 100000 ? ' ' . $convert($n % 100000) : '');
        return $convert(intdiv($n,10000000)) . ' CRORE' . ($n % 10000000 ? ' ' . $convert($n % 10000000) : '');
    };

    return trim($convert((int)$num)) . ' ONLY';
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    die('Invalid invoice id');
}

$hasCustomerGst = vi_column_exists($conn, 'customers', 'gst_no');
$hasCustomerBeat = vi_column_exists($conn, 'customers', 'assigned_area');
$hasBusinessFssai = vi_column_exists($conn, 'business_settings', 'fssai_no');
$extendedCols = ['hsn_code','case_pack','cases_qty','pieces_qty','free_qty','mrp','base_rate','discount_amount','gst_rate','net_rate','taxable_value','cgst_amount','sgst_amount'];
$hasExtendedItems = true;
foreach ($extendedCols as $col) {
    if (!vi_column_exists($conn, 'order_items', $col)) {
        $hasExtendedItems = false;
        break;
    }
}

$businessFields = "business_name, address, city, state, pincode, mobile, phone, gstin";
if ($hasBusinessFssai) $businessFields .= ", fssai_no";
$bs = mysqli_query($conn, "SELECT $businessFields FROM business_settings ORDER BY id ASC LIMIT 1");
$business = $bs && mysqli_num_rows($bs) ? mysqli_fetch_assoc($bs) : [];

$customerFields = "c.id, c.shop_name, c.customer_name, c.customer_contact, c.shop_location, o.order_number, o.order_date,
                   o.total_amount, o.paid_amount, o.pending_amount, o.payment_method, o.payment_status, o.notes,
                   u.name AS staff_name";
$customerFields .= $hasCustomerGst ? ", COALESCE(c.gst_no,'') AS gst_no" : ", '' AS gst_no";
$customerFields .= $hasCustomerBeat ? ", COALESCE(c.assigned_area,'') AS assigned_area" : ", '' AS assigned_area";

$orderSql = "SELECT $customerFields
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             LEFT JOIN admin_users u ON o.created_by = u.id
             WHERE o.id = $orderId
             LIMIT 1";
$orderRes = mysqli_query($conn, $orderSql);
if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
    die('Invoice not found');
}
$order = mysqli_fetch_assoc($orderRes);

if ($hasExtendedItems) {
    $itemSql = "SELECT oi.*, p.product_name
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = $orderId
                ORDER BY oi.id ASC";
} else {
    $itemSql = "SELECT oi.*, p.product_name,
                       '' AS hsn_code, 1 AS case_pack, 0 AS cases_qty,
                       oi.quantity AS pieces_qty, 0 AS free_qty,
                       oi.price AS mrp, oi.price AS base_rate, 0 AS discount_amount,
                       0 AS gst_rate, oi.price AS net_rate,
                       oi.total AS taxable_value, 0 AS cgst_amount, 0 AS sgst_amount
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = $orderId
                ORDER BY oi.id ASC";
}
$itemRes = mysqli_query($conn, $itemSql);
$items = [];
$taxableTotal = 0;
$cgstTotal = 0;
$sgstTotal = 0;
$grandTotal = 0;
while ($itemRes && $row = mysqli_fetch_assoc($itemRes)) {
    $row['taxable_value'] = (float)$row['taxable_value'];
    $row['cgst_amount'] = (float)$row['cgst_amount'];
    $row['sgst_amount'] = (float)$row['sgst_amount'];
    $row['total'] = (float)$row['total'];
    $items[] = $row;
    $taxableTotal += $row['taxable_value'];
    $cgstTotal += $row['cgst_amount'];
    $sgstTotal += $row['sgst_amount'];
    $grandTotal += $row['total'];
}
$roundOff = round($grandTotal - ($taxableTotal + $cgstTotal + $sgstTotal), 2);
$sellerAddress = trim(implode(', ', array_filter([
    $business['address'] ?? '',
    $business['city'] ?? '',
    $business['state'] ?? '',
    $business['pincode'] ?? ''
])));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice <?php echo htmlspecialchars($order['order_number']); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:20px;color:#000}
.invoice-wrap{max-width:1120px;margin:0 auto}
.toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.toolbar a,.toolbar button{padding:10px 14px;border:1px solid #222;background:#fff;color:#000;text-decoration:none;cursor:pointer;font-size:14px}
.sheet{background:#fff;padding:18px;border:1px solid #111}
.top-grid{display:grid;grid-template-columns:1fr 90px 1fr;border:1px solid #111;border-bottom:none}
.top-grid>div{padding:10px}
.title-box{border-left:1px solid #111;border-right:1px solid #111;text-align:center;display:flex;align-items:center;justify-content:center;font-weight:700}
.tax-title{text-decoration:underline;font-size:14px}
.header-row{display:grid;grid-template-columns:1fr 1fr;border:1px solid #111;border-top:none}
.header-row>div{padding:8px 10px}
.header-row>div:last-child{border-left:1px solid #111}
.meta-table{width:100%;border-collapse:collapse;border:1px solid #111;border-top:none}
.meta-table td{padding:6px 8px;border-right:1px solid #111}
.meta-table td:last-child{border-right:none}
.items{width:100%;border-collapse:collapse;border:1px solid #111;border-top:none}
.items th,.items td{border:1px solid #111;padding:5px 6px;vertical-align:top}
.items th{text-align:center;font-size:13px}
.items td{text-align:center;font-size:12px}
.items td.left{text-align:left}
.items .blank-row td{height:190px}
.bottom-area{display:grid;grid-template-columns:1fr 240px;border-left:1px solid #111;border-right:1px solid #111;border-bottom:1px solid #111}
.bottom-left{padding:8px 10px;border-right:1px solid #111}
.total-box{padding:8px 12px;font-size:20px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.tax-summary{width:100%;border-collapse:collapse;margin-top:6px}
.tax-summary td{padding:2px 8px;font-size:12px}
.sign-row{display:grid;grid-template-columns:1fr 1fr 1fr;border:1px solid #111;border-top:none;padding:18px 10px 8px 10px;align-items:end;min-height:70px}
.sign-row div{text-align:center;font-weight:700}
.small{font-size:12px}.bold{font-weight:700}.right{text-align:right}.u{text-decoration:underline}
@media print{body{background:#fff;padding:0}.toolbar{display:none}.invoice-wrap{max-width:none}.sheet{border:none;padding:0}}
</style>
</head>
<body>
<div class="invoice-wrap">
    <div class="toolbar">
        <a href="quick-order.php">Back</a>
        <div>
            <button type="button" onclick="window.print()">Print Invoice</button>
        </div>
    </div>

    <div class="sheet">
        <div class="top-grid">
            <div>
                <div style="font-size:18px;font-weight:700;">SREE VADIVALAKI AMMAN AGENCIES</div>
                <div><?php echo htmlspecialchars($sellerAddress); ?></div>
                <div><span class="bold">CELL :</span> <?php echo htmlspecialchars((string)($business['mobile'] ?? '')); ?></div>
                <div><span class="bold">GSTIN :</span> <?php echo htmlspecialchars((string)($business['gstin'] ?? '')); ?></div>
            </div>
            <div class="title-box"><span class="tax-title">TAX INVOICE</span></div>
            <div>
                <div><span class="bold">To:</span> <?php echo htmlspecialchars($order['shop_name']); ?></div>
                <div><?php echo nl2br(htmlspecialchars((string)$order['shop_location'])); ?></div>
                <div><span class="bold">GSTIN :</span> <?php echo htmlspecialchars((string)$order['gst_no']); ?> &nbsp;&nbsp; <span class="bold">PH No:</span> <?php echo htmlspecialchars((string)$order['customer_contact']); ?></div>
            </div>
        </div>

        <table class="meta-table">
            <tr>
                <td><span class="bold">Invoice No :</span> <?php echo htmlspecialchars($order['order_number']); ?></td>
                <td><span class="bold">Date :</span> <?php echo htmlspecialchars(date('d-m-y', strtotime($order['order_date']))); ?></td>
                <td><span class="bold">FSSAI:</span> <?php echo htmlspecialchars((string)($business['fssai_no'] ?? '')); ?></td>
                <td><span class="bold">Staff :</span> <?php echo htmlspecialchars((string)($order['staff_name'] ?: 'Admin')); ?></td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th style="width:110px;">HSN</th>
                    <th>Product Name</th>
                    <th colspan="3" style="width:120px;">CS - PC - FR</th>
                    <th style="width:80px;">MRP</th>
                    <th style="width:80px;">Rate</th>
                    <th style="width:70px;">Disc</th>
                    <th style="width:70px;">GST%</th>
                    <th style="width:90px;">Net Rate</th>
                    <th style="width:110px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars((string)$item['hsn_code']); ?></td>
                    <td class="left"><?php echo htmlspecialchars((string)$item['product_name']); ?></td>
                    <td><?php echo (int)$item['cases_qty']; ?></td>
                    <td><?php echo (int)$item['pieces_qty']; ?></td>
                    <td><?php echo (int)$item['free_qty']; ?></td>
                    <td class="right"><?php echo vi_money($item['mrp']); ?></td>
                    <td class="right"><?php echo vi_money($item['base_rate']); ?></td>
                    <td class="right"><?php echo vi_money($item['discount_amount']); ?></td>
                    <td><?php echo rtrim(rtrim(number_format((float)$item['gst_rate'],2,'.',''),'0'),'.'); ?>%</td>
                    <td class="right"><?php echo vi_money($item['net_rate']); ?></td>
                    <td class="right"><?php echo vi_money($item['total']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php for ($r = count($items); $r < 8; $r++): ?>
                <tr>
                    <td>&nbsp;</td><td></td><td class="left"></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="bottom-area">
            <div class="bottom-left">
                <div class="bold"><?php echo vi_words($grandTotal); ?></div>
                <table class="tax-summary">
                    <tr>
                        <td class="u">SalesValue</td>
                        <td class="u">CGST %</td>
                        <td class="u">Amount</td>
                        <td class="u">SalesValue</td>
                        <td class="u">SGST %</td>
                        <td class="u">Amount(Rs)</td>
                    </tr>
                    <tr>
                        <td><?php echo vi_money($taxableTotal); ?></td>
                        <td>2.5 %</td>
                        <td><?php echo vi_money($cgstTotal); ?></td>
                        <td><?php echo vi_money($taxableTotal); ?></td>
                        <td>2.5 %</td>
                        <td><?php echo vi_money($sgstTotal); ?></td>
                    </tr>
                </table>
            </div>
            <div class="total-box">
                <span>Total :</span>
                <span><?php echo vi_money($grandTotal); ?></span>
            </div>
        </div>

        <div class="sign-row">
            <div>BUYER'S SIGNATURE</div>
            <div>E.&amp;.O.E</div>
            <div>For SREE VADIVALAKI AMMAN AGENCIES</div>
        </div>
    </div>
</div>
</body>
</html>
