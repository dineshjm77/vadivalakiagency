<?php
session_start();
include('config/config.php');

// Define formatCurrency function if not already defined
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₹' . number_format($amount, 2);
    }
}

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die("Invalid order ID.");
}

$order_id = intval($_GET['order_id']);

// Optional: Get referral page from URL parameter (e.g., ?ref=revenue-reports.php)
// If not provided, default to revenue-reports.php
$referral_page = isset($_GET['ref']) && !empty($_GET['ref']) 
    ? $_GET['ref'] 
    : 'revenue-reports.php';  // Change this to your default fallback page

// Fetch Business Settings
$business_query = mysqli_query($conn, "SELECT * FROM business_settings WHERE id = 1");
if (mysqli_num_rows($business_query) == 0) {
    die("Business settings not found.");
}
$business = mysqli_fetch_assoc($business_query);

// Fetch Order Details
$order_query = "
    SELECT 
        o.*, 
        c.customer_code, c.shop_name, c.customer_name, c.customer_contact, 
        c.shop_location, c.email
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.id = $order_id
";
$order_result = mysqli_query($conn, $order_query);
if (mysqli_num_rows($order_result) == 0) {
    die("Order not found.");
}
$order = mysqli_fetch_assoc($order_result);

// Fetch Order Items
$items_query = "
    SELECT 
        oi.*, 
        p.product_name, 
        b.brand_name,
        cat.category_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories cat ON p.category_id = cat.id
    WHERE oi.order_id = $order_id
";
$items_result = mysqli_query($conn, $items_query);

$subtotal = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_number']; ?> - <?php echo $business['business_name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; background: #f8f9fa; }
        .invoice-box {
            max-width: 900px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .logo { max-height: 80px; }
        table { width: 100%; line-height: inherit; text-align: left; }
        .table th, .table td { padding: 8px; vertical-align: top; }
        .table-bordered th, .table-bordered td { border: 1px solid #ddd; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .mt-4 { margin-top: 1.5rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .border-top { border-top: 2px solid #000; }
        @media print {
            body { background: white; }
            .invoice-box { box-shadow: none; margin: 0; padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="invoice-box">
    <div class="no-print text-end mb-3">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="mdi mdi-printer"></i> Print Invoice
        </button>
        <a href="order-view.php?id=<?php echo $order_id; ?>" class="btn btn-secondary ms-2">
            Back to Order
        </a>
    </div>

    <!-- Invoice Content (same as before) -->
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="4">
                <table>
                    <tr>
                        <td class="title">
                            <?php if ($business['business_logo'] && $business['show_logo_invoice']): ?>
                                <img src="<?php echo $business['business_logo']; ?>" class="logo" alt="Logo">
                            <?php else: ?>
                                <h3><?php echo $business['business_name']; ?></h3>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <h4>Invoice</h4>
                            <br>
                            Invoice #: <strong><?php echo $order['order_number']; ?></strong><br>
                            Order Date: <strong><?php echo date('d M, Y', strtotime($order['order_date'])); ?></strong><br>
                            <?php if ($order['delivery_date']): ?>
                                Delivery Date: <strong><?php echo date('d M, Y', strtotime($order['delivery_date'])); ?></strong><br>
                            <?php endif; ?>
                            Payment Status: 
                            <span class="badge <?php echo $order['payment_status'] == 'paid' ? 'bg-success' : ($order['payment_status'] == 'partial' ? 'bg-warning' : 'bg-danger'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['payment_status'])); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Rest of invoice content (unchanged) -->
        <tr class="information mt-4">
            <td colspan="4">
                <table>
                    <tr>
                        <td width="50%">
                            <strong>From:</strong><br>
                            <?php echo $business['business_name']; ?><br>
                            <?php echo nl2br($business['address']); ?><br>
                            <?php echo $business['city'] . ', ' . $business['state'] . ' - ' . $business['pincode']; ?><br>
                            Mobile: <?php echo $business['mobile']; ?><br>
                            <?php if ($business['gstin']): ?>
                                GSTIN: <?php echo $business['gstin']; ?><br>
                            <?php endif; ?>
                            Email: <?php echo $business['email']; ?>
                        </td>
                        <td width="50%" class="text-end">
                            <strong>Bill To:</strong><br>
                            <?php echo $order['customer_name']; ?><br>
                            <?php echo $order['shop_name']; ?><br>
                            <?php echo nl2br($order['shop_location']); ?><br>
                            Mobile: <?php echo $order['customer_contact']; ?><br>
                            <?php if ($order['email']): ?>
                                Email: <?php echo $order['email']; ?><br>
                            <?php endif; ?>
                            Customer Code: <?php echo $order['customer_code']; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Items Table -->
        <tr class="heading mt-4">
            <td colspan="4">
                <h5 class="border-top pt-3">Order Items</h5>
            </td>
        </tr>
        <tr>
            <td colspan="4">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th width="8%">#</th>
                            <th>Product</th>
                            <th width="15%">Quantity</th>
                            <th width="15%">Unit Price</th>
                            <th width="15%" class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($item = mysqli_fetch_assoc($items_result)): ?>
                            <?php 
                            $item_total = $item['quantity'] * $item['price'];
                            $subtotal += $item_total;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    <?php if ($item['brand_name']): ?>
                                        <br><small><?php echo htmlspecialchars($item['brand_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $item['quantity']; ?> nos</td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td class="text-end"><?php echo formatCurrency($item_total); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </td>
        </tr>

        <!-- Total Section -->
        <tr class="total mt-4">
            <td colspan="4">
                <table style="width: 100%;">
                    <tr>
                        <td width="65%"></td>
                        <td width="35%">
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Subtotal</strong></td>
                                    <td class="text-end"><?php echo formatCurrency($subtotal); ?></td>
                                </tr>
                                <tr class="fw-bold border-top">
                                    <td>Total Amount</td>
                                    <td class="text-end"><?php echo formatCurrency($subtotal); ?></td>
                                </tr>
                                <tr>
                                    <td>Paid Amount</td>
                                    <td class="text-end text-success"><?php echo formatCurrency($order['paid_amount']); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Balance Due</td>
                                    <td class="text-end text-danger"><?php echo formatCurrency($order['pending_amount']); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- Terms & Footer -->
        <?php if (!empty($business['terms_conditions']) || !empty($business['payment_instructions'])): ?>
        <tr class="mt-4">
            <td colspan="4">
                <?php if (!empty($business['terms_conditions'])): ?>
                    <h6>Terms & Conditions:</h6>
                    <p style="font-size: 12px;"><?php echo nl2br(htmlspecialchars($business['terms_conditions'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($business['payment_instructions'])): ?>
                    <h6>Payment Instructions:</h6>
                    <p style="font-size: 12px;"><?php echo nl2br(htmlspecialchars($business['payment_instructions'])); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>

        <?php if (!empty($business['invoice_footer'])): ?>
        <tr class="mt-4">
            <td colspan="4" class="text-center">
                <p style="font-size: 12px; color: #666;">
                    <?php echo nl2br(htmlspecialchars($business['invoice_footer'])); ?>
                </p>
            </td>
        </tr>
        <?php endif; ?>

        <tr>
            <td colspan="4" class="text-center mt-4" style="color: #888; font-size: 11px;">
                Thank you for your business! | Generated on <?php echo date('d M, Y h:i A'); ?>
            </td>
        </tr>
    </table>

    <?php if ($business['show_qr_code']): ?>
    <div class="text-center mt-4">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('Order: ' . $order['order_number'] . ' | Amount: ₹' . number_format($order['total_amount'], 2)); ?>" alt="QR Code">
        <br><small>Scan for order details</small>
    </div>
    <?php endif; ?>
</div>

<!-- AUTO PRINT + SMART FALLBACK REDIRECT -->
<script>
    // Referral page (passed via ?ref= or default)
    const referralPage = '<?php echo addslashes($referral_page); ?>';

    // Trigger print on load
    window.onload = function() {
        window.print();
    };

    // Redirect after successful print
    window.onafterprint = function() {
        window.location.href = referralPage;
    };

    // Fallback: If user cancels print or onafterprint not supported, redirect after 3 seconds
    setTimeout(function() {
        window.location.href = referralPage;
    }, 3000);
</script>

</body>
</html>
<?php 
mysqli_close($conn); 
?>