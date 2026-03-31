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
if ($order_id <= 0) {
    die('Invalid invoice id');
}

$hasCustomerGst   = a5_column_exists($conn, 'customers', 'gst_no');
$hasBusinessFssai = a5_column_exists($conn, 'business_settings', 'fssai_no');

$extendedItemCols = [
    'hsn_code','case_pack','cases_qty','pieces_qty','free_qty','mrp',
    'base_rate','discount_amount','gst_rate','net_rate','taxable_value',
    'cgst_amount','sgst_amount'
];
$hasExtended = true;
foreach ($extendedItemCols as $c) {
    if (!a5_column_exists($conn, 'order_items', $c)) {
        $hasExtended = false;
        break;
    }
}

$business = [
    'business_name' => 'Business Name',
    'address'       => '',
    'city'          => '',
    'state'         => '',
    'pincode'       => '',
    'mobile'        => '',
    'phone'         => '',
    'gstin'         => '',
    'fssai_no'      => '',
];
$bsFields = 'business_name,address,city,state,pincode,mobile,phone,gstin' . ($hasBusinessFssai ? ',fssai_no' : '');
$bsRes = mysqli_query($conn, "SELECT $bsFields FROM business_settings ORDER BY id ASC LIMIT 1");
if ($bsRes && $bsRow = mysqli_fetch_assoc($bsRes)) {
    $business = array_merge($business, $bsRow);
}

$orderSql = "SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, c.shop_location, u.name AS staff_name";
$orderSql .= $hasCustomerGst ? ", COALESCE(c.gst_no,'') AS gst_no" : ", '' AS gst_no";
$orderSql .= " FROM orders o
               LEFT JOIN customers c ON o.customer_id = c.id
               LEFT JOIN admin_users u ON o.created_by = u.id
               WHERE o.id = $order_id
               LIMIT 1";
$orderRes = mysqli_query($conn, $orderSql);
$order = $orderRes ? mysqli_fetch_assoc($orderRes) : null;
if (!$order) {
    die('Invoice not found');
}

$itemSql = $hasExtended
    ? "SELECT oi.*, p.product_name
       FROM order_items oi
       LEFT JOIN products p ON oi.product_id = p.id
       WHERE oi.order_id = $order_id
       ORDER BY oi.id ASC"
    : "SELECT oi.*, p.product_name,
              '' AS hsn_code, 0 AS cases_qty, oi.quantity AS pieces_qty, 0 AS free_qty,
              oi.price AS mrp, oi.price AS base_rate, 0 AS discount_amount, 0 AS gst_rate,
              oi.price AS net_rate, oi.total AS taxable_value, 0 AS cgst_amount, 0 AS sgst_amount
       FROM order_items oi
       LEFT JOIN products p ON oi.product_id = p.id
       WHERE oi.order_id = $order_id
       ORDER BY oi.id ASC";

$itemRes = mysqli_query($conn, $itemSql);
$items = [];
$taxable = 0.00;
$cgst = 0.00;
$sgst = 0.00;
$grand = 0.00;

if ($itemRes) {
    while ($row = mysqli_fetch_assoc($itemRes)) {
        $items[] = $row;
        $taxable += (float)($row['taxable_value'] ?? $row['total'] ?? 0);
        $cgst    += (float)($row['cgst_amount'] ?? 0);
        $sgst    += (float)($row['sgst_amount'] ?? 0);
        $grand   += (float)($row['total'] ?? 0);
    }
}

$amountWords = a5_words($grand);
$sellerAddress = trim(implode(', ', array_filter([
    $business['address'],
    $business['city'],
    $business['state'],
    $business['pincode']
])));
$staffName = (string)($order['staff_name'] ?: ($_SESSION['name'] ?? 'Admin'));
$totalInvoice = isset($order['total_amount']) ? (float)$order['total_amount'] : (float)$grand;
$paidInvoice = isset($order['paid_amount']) ? (float)$order['paid_amount'] : 0.00;
$balanceInvoice = isset($order['pending_amount']) ? (float)$order['pending_amount'] : max(0, $totalInvoice - $paidInvoice);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>A5 Invoice <?php echo htmlspecialchars((string)$order['order_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body{
        margin:0;
        background:#eef1f4;
        font-family:Arial, Helvetica, sans-serif;
        color:#111;
    }
    .toolbar{
        max-width:220mm;
        margin:14px auto 10px;
        display:flex;
        justify-content:flex-end;
        gap:10px;
    }
    .page{
        width:210mm;
        min-height:148mm;
        margin:0 auto 14px;
        background:#fff;
        box-shadow:0 0 10px rgba(0,0,0,.14);
        padding:4mm;
    }
    .invoice{
        border:1px solid #111;
    }
    .header-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        border-bottom:1px solid #111;
    }
    .header-cell{
        padding:5px 7px;
        min-height:84px;
    }
    .header-cell + .header-cell{
        border-left:1px solid #111;
    }
    .company-name{
        font-size:16px;
        font-weight:700;
        letter-spacing:.3px;
        text-transform:uppercase;
        margin-bottom:2px;
    }
    .small-line{
        font-size:11px;
        line-height:1.35;
    }
    .invoice-title{
        text-align:center;
        font-size:14px;
        font-weight:700;
        letter-spacing:.5px;
        text-decoration:underline;
        margin-bottom:4px;
    }
    .buyer-name{
        font-size:15px;
        font-weight:700;
        text-transform:uppercase;
        margin-bottom:2px;
    }
    .meta-grid{
        display:grid;
        grid-template-columns:1.3fr .8fr 1fr 1fr;
        border-bottom:1px solid #111;
    }
    .meta-item{
        padding:4px 6px;
        font-size:11px;
        min-height:34px;
        display:flex;
        align-items:center;
        border-right:1px solid #111;
    }
    .meta-item:last-child{
        border-right:none;
    }
    .label{
        font-weight:700;
        margin-right:4px;
        white-space:nowrap;
    }
    .items{
        width:100%;
        border-collapse:collapse;
    }
    .items th,
    .items td{
        border:1px solid #111;
        padding:3px 4px;
        font-size:10px;
        line-height:1.25;
        vertical-align:top;
    }
    .items thead th{
        text-align:center;
        font-weight:700;
        background:#f9f9f9;
    }
    .items td.num,
    .items th.num{
        text-align:right;
        white-space:nowrap;
    }
    .items td.center,
    .items th.center{
        text-align:center;
    }
    .product-cell{
        min-width:42mm;
    }
    .footer-grid{
        display:grid;
        grid-template-columns:1.5fr .9fr;
        border-top:1px solid #111;
    }
    .footer-left,
    .footer-right{
        min-height:58px;
        padding:6px 8px;
    }
    .footer-left{
        border-right:1px solid #111;
    }
    .words{
        font-size:11px;
        font-weight:700;
        text-transform:uppercase;
        margin-bottom:5px;
    }
    .tax-row{
        font-size:11px;
        line-height:1.5;
    }
    .grand-total{
        font-size:22px;
        font-weight:700;
        text-align:right;
        margin-top:10px;
    }
    .sign-row{
        display:grid;
        grid-template-columns:1fr .8fr 1.2fr;
        border-top:1px solid #111;
    }
    .sign-box{
        min-height:52px;
        padding:6px;
        display:flex;
        align-items:flex-end;
        justify-content:center;
        font-size:11px;
        font-weight:700;
        text-align:center;
    }
    .sign-box + .sign-box{
        border-left:1px solid #111;
    }
    .empty-row td{
        height:18px;
    }
    @page{
        size:A5 landscape;
        margin:4mm;
    }
    @media print{
        body{background:#fff;}
        .toolbar{display:none !important;}
        .page{
            width:auto;
            min-height:auto;
            margin:0;
            box-shadow:none;
            padding:0;
        }
    }
</style>
</head>
<body>
<div class="toolbar">
    <a href="view-invoice.php?id=<?php echo (int)$order_id; ?>" class="btn btn-outline-secondary">Open A4 View</a>
    <button class="btn btn-primary" onclick="window.print()">Print A5</button>
</div>

<div class="page">
    <div class="invoice">
        <div class="header-grid">
            <div class="header-cell">
                <div class="company-name"><?php echo strtoupper(htmlspecialchars((string)$business['business_name'])); ?></div>
                <div class="small-line"><?php echo htmlspecialchars($sellerAddress); ?></div>
                <div class="small-line"><span class="label">CELL :</span><?php echo htmlspecialchars((string)$business['mobile']); ?></div>
                <div class="small-line"><span class="label">GSTIN :</span><?php echo htmlspecialchars((string)$business['gstin']); ?></div>
                <?php if (!empty($business['fssai_no'])): ?>
                    <div class="small-line"><span class="label">FSSAI :</span><?php echo htmlspecialchars((string)$business['fssai_no']); ?></div>
                <?php endif; ?>
            </div>
            <div class="header-cell">
                <div class="invoice-title">TAX INVOICE</div>
                <div class="buyer-name"><?php echo htmlspecialchars((string)$order['shop_name']); ?></div>
                <div class="small-line"><?php echo htmlspecialchars((string)$order['shop_location']); ?></div>
                <div class="small-line"><span class="label">GSTIN :</span><?php echo htmlspecialchars((string)$order['gst_no']); ?></div>
                <div class="small-line"><span class="label">PH NO :</span><?php echo htmlspecialchars((string)$order['customer_contact']); ?></div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item"><span class="label">Invoice No :</span><?php echo htmlspecialchars((string)$order['order_number']); ?></div>
            <div class="meta-item"><span class="label">Date :</span><?php echo date('d-m-y', strtotime((string)$order['order_date'])); ?></div>
            <div class="meta-item"><span class="label">Staff :</span><?php echo htmlspecialchars($staffName); ?></div>
            <div class="meta-item"><span class="label">Status :</span><?php echo htmlspecialchars(ucfirst((string)$order['payment_status'])); ?></div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th class="center" style="width:6mm;">No</th>
                    <th class="center" style="width:16mm;">HSN</th>
                    <th class="product-cell">Product Name</th>
                    <th class="center" style="width:9mm;">CS</th>
                    <th class="center" style="width:9mm;">PC</th>
                    <th class="center" style="width:9mm;">FR</th>
                    <th class="num" style="width:14mm;">MRP</th>
                    <th class="num" style="width:14mm;">Rate</th>
                    <th class="num" style="width:12mm;">Disc</th>
                    <th class="center" style="width:11mm;">GST%</th>
                    <th class="num" style="width:16mm;">Net Rate</th>
                    <th class="num" style="width:18mm;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="center"><?php echo $i + 1; ?></td>
                            <td class="center"><?php echo htmlspecialchars((string)($item['hsn_code'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($item['product_name'] ?? '')); ?></td>
                            <td class="center"><?php echo (int)($item['cases_qty'] ?? 0); ?></td>
                            <td class="center"><?php echo (int)($item['pieces_qty'] ?? ($item['quantity'] ?? 0)); ?></td>
                            <td class="center"><?php echo (int)($item['free_qty'] ?? 0); ?></td>
                            <td class="num"><?php echo number_format((float)($item['mrp'] ?? 0), 2); ?></td>
                            <td class="num"><?php echo number_format((float)($item['base_rate'] ?? $item['price'] ?? 0), 2); ?></td>
                            <td class="num"><?php echo number_format((float)($item['discount_amount'] ?? 0), 2); ?></td>
                            <td class="center"><?php echo rtrim(rtrim(number_format((float)($item['gst_rate'] ?? 0), 2), '0'), '.'); ?>%</td>
                            <td class="num"><?php echo number_format((float)($item['net_rate'] ?? $item['price'] ?? 0), 2); ?></td>
                            <td class="num"><?php echo number_format((float)($item['total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="12" class="center">No items found</td></tr>
                <?php endif; ?>

                <?php for ($r = count($items); $r < 6; $r++): ?>
                    <tr class="empty-row">
                        <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="footer-grid">
            <div class="footer-left">
                <div class="words"><?php echo htmlspecialchars($amountWords); ?></div>
                <div class="tax-row"><span class="label">Sales Value :</span><?php echo number_format($taxable, 2); ?></div>
                <div class="tax-row"><span class="label">CGST :</span><?php echo number_format($cgst, 2); ?> &nbsp;&nbsp; <span class="label">SGST :</span><?php echo number_format($sgst, 2); ?></div>
            </div>
            <div class="footer-right">
                <div style="font-size:12px; line-height:1.7; text-align:right; margin-top:2px;">
                    <div><strong>Total Amount :</strong> <?php echo number_format($totalInvoice, 2); ?></div>
                    <div><strong>Paid Amount :</strong> <?php echo number_format($paidInvoice, 2); ?></div>
                    <div><strong>Balance :</strong> <?php echo number_format($balanceInvoice, 2); ?></div>
                </div>
                <div class="grand-total">Total : <?php echo number_format($totalInvoice, 2); ?></div>
            </div>
        </div>

        <div class="sign-row">
            <div class="sign-box">BUYER'S SIGNATURE</div>
            <div class="sign-box">E.&amp;.O.E</div>
            <div class="sign-box">For <?php echo strtoupper(htmlspecialchars((string)$business['business_name'])); ?></div>
        </div>
    </div>
</div>
</body>
</html>
