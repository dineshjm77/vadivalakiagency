<?php
// Database connection
include('config/config.php');

// Get the ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die('Invalid Order ID');
}

// Fetch business settings
$business_sql = "SELECT * FROM business_settings WHERE id = 1 LIMIT 1";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

// Fetch order data with customer information
$sql = "SELECT o.*, 
       c.*,
       l.full_name as lineman_name,
       l.employee_id as lineman_id
       FROM orders o
       LEFT JOIN customers c ON o.customer_id = c.id
       LEFT JOIN linemen l ON o.created_by = l.id
       WHERE o.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $order = mysqli_fetch_assoc($result);
    
    // Fetch order items
    $items_sql = "SELECT oi.*, 
                p.product_name, 
                p.product_code,
                p.stock_price,
                p.customer_price
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?";
    $items_stmt = mysqli_prepare($conn, $items_sql);
    mysqli_stmt_bind_param($items_stmt, "i", $id);
    mysqli_stmt_execute($items_stmt);
    $items_result = mysqli_stmt_get_result($items_stmt);
    
    $order_items = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $order_items[] = $item;
    }
    
    mysqli_stmt_close($items_stmt);
    
} else {
    die('Order not found');
}

// Close connection
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Calculate totals
$subtotal = 0;
$total_profit = 0;
foreach ($order_items as $item) {
    $subtotal += $item['total'];
    $total_profit += ($item['customer_price'] - $item['stock_price']) * $item['quantity'];
}

// Use order total from database if it exists, otherwise calculate
if ($order['total_amount'] > 0) {
    $subtotal = $order['total_amount'];
}

// Tax calculation
$tax_percentage = $business['tax_percentage'] ?? 0;
$tax_amount = ($subtotal * $tax_percentage) / 100;
$grand_total = $subtotal + $tax_amount;

// Function to convert number to words
function numberToWords($num) {
    $ones = array(
        0 => "Zero", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four",
        5 => "Five", 6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine",
        10 => "Ten", 11 => "Eleven", 12 => "Twelve", 13 => "Thirteen",
        14 => "Fourteen", 15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen",
        18 => "Eighteen", 19 => "Nineteen"
    );
    
    $tens = array(
        2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );
    
    $rupees = floor($num);
    $paise = round(($num - $rupees) * 100);
    
    $words = "";
    
    if ($rupees > 0) {
        if ($rupees >= 10000000) {
            $crore = floor($rupees / 10000000);
            $words .= numberToWords($crore) . " Crore ";
            $rupees %= 10000000;
        }
        
        if ($rupees >= 100000) {
            $lakh = floor($rupees / 100000);
            $words .= numberToWords($lakh) . " Lakh ";
            $rupees %= 100000;
        }
        
        if ($rupees >= 1000) {
            $thousand = floor($rupees / 1000);
            $words .= numberToWords($thousand) . " Thousand ";
            $rupees %= 1000;
        }
        
        if ($rupees >= 100) {
            $hundred = floor($rupees / 100);
            $words .= numberToWords($hundred) . " Hundred ";
            $rupees %= 100;
        }
        
        if ($rupees > 0) {
            if ($rupees < 20) {
                $words .= $ones[$rupees] . " ";
            } else {
                $words .= $tens[floor($rupees / 10)] . " ";
                if ($rupees % 10 > 0) {
                    $words .= $ones[$rupees % 10] . " ";
                }
            }
        }
        
        $words .= "Rupees ";
    }
    
    if ($paise > 0) {
        if ($paise < 20) {
            $words .= "and " . $ones[$paise] . " Paise";
        } else {
            $words .= "and " . $tens[floor($paise / 10)] . " ";
            if ($paise % 10 > 0) {
                $words .= $ones[$paise % 10] . " ";
            }
            $words .= "Paise";
        }
    }
    
    return $words ? $words . " Only" : "Zero Rupees Only";
}

// Calculate amount in words
if ($order['payment_status'] == 'partial' && $order['pending_amount'] > 0) {
    $amount_for_words = $order['pending_amount'];
} else {
    $amount_for_words = $grand_total;
}

$amount_in_words = numberToWords($amount_for_words);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_number']; ?> - <?php echo htmlspecialchars($business['business_name'] ?? 'APR Water Agencies'); ?></title>
    <style>
        /* Invoice Styles */
        @page {
            margin: 0;
            size: A4;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .company-info {
            flex: 1;
        }
        
        .invoice-info {
            text-align: right;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin: 0 0 5px 0;
        }
        
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin: 0 0 10px 0;
        }
        
        .invoice-number {
            font-size: 18px;
            color: #666;
            margin: 0;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .col {
            flex: 1;
            padding: 0 10px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .col-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
        }
        
        .col-8 {
            flex: 0 0 66.666%;
            max-width: 66.666%;
        }
        
        .label {
            font-weight: bold;
            color: #666;
            margin-bottom: 3px;
        }
        
        .value {
            color: #333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .table th {
            background: #007bff;
            color: white;
            font-weight: bold;
            padding: 10px;
            text-align: left;
            border: none;
        }
        
        .table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-bold {
            font-weight: bold;
        }
        
        .total-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #007bff;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-pending {
            background: #ffc107;
            color: #856404;
        }
        
        .status-processing {
            background: #17a2b8;
            color: white;
        }
        
        .status-delivered {
            background: #28a745;
            color: white;
        }
        
        .status-cancelled {
            background: #dc3545;
            color: white;
        }
        
        .payment-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .payment-paid {
            background: #28a745;
            color: white;
        }
        
        .payment-partial {
            background: #ffc107;
            color: #856404;
        }
        
        .payment-pending {
            background: #dc3545;
            color: white;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #666;
            text-align: center;
        }
        
        .terms-conditions {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 11px;
            color: #666;
        }
        
        .print-only {
            display: block;
        }
        
        .screen-only {
            display: none;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
                font-size: 11px;
            }
            
            .invoice-container {
                max-width: 100%;
                padding: 15px;
                box-shadow: none;
                border-radius: 0;
            }
            
            .print-only {
                display: block;
            }
            
            .screen-only {
                display: none;
            }
            
            .no-print {
                display: none !important;
            }
            
            .table th {
                background: #007bff !important;
                -webkit-print-color-adjust: exact;
                color: white !important;
            }
        }
        
        @media screen {
            .print-only {
                display: none;
            }
            
            .screen-only {
                display: block;
            }
        }
        
        .print-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            margin-left: 10px;
        }
        
        .print-btn:hover {
            background: #0056b3;
        }
        
        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .download-btn:hover {
            background: #218838;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            margin-right: 10px;
        }
        
        .back-btn:hover {
            background: #545b62;
        }
        
        .amount-box {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-top: 5px;
            font-style: italic;
            min-height: 40px;
        }
        
        .customer-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Print Actions - Only visible on screen -->
    <div class="print-actions screen-only no-print">
        <button class="back-btn" onclick="window.history.back()">
            ← Back
        </button>
        <button class="print-btn" onclick="window.print()">
            🖨️ Print Invoice
        </button>
        <button class="download-btn" onclick="downloadPDF()">
            📥 Download PDF
        </button>
    </div>
    
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="company-info">
                <h1 class="company-name">
                    <?php echo htmlspecialchars($business['business_name'] ?? 'APR Water Agencies'); ?>
                </h1>
                
                <?php if ($business['business_type']): ?>
                <p style="margin: 5px 0; color: #666;"><?php echo htmlspecialchars($business['business_type']); ?></p>
                <?php endif; ?>
                
                <?php if ($business['address']): ?>
                <p style="margin: 3px 0; color: #666;">
                    <?php echo htmlspecialchars($business['address']); ?>
                    <?php if ($business['city']): ?>, <?php echo htmlspecialchars($business['city']); ?><?php endif; ?>
                    <?php if ($business['state']): ?>, <?php echo htmlspecialchars($business['state']); ?><?php endif; ?>
                    <?php if ($business['pincode']): ?> - <?php echo htmlspecialchars($business['pincode']); ?><?php endif; ?>
                </p>
                <?php endif; ?>
                
                <div style="margin-top: 10px;">
                    <?php if ($business['contact_person']): ?>
                    <p style="margin: 3px 0; color: #666;">
                        <strong>Contact:</strong> <?php echo htmlspecialchars($business['contact_person']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($business['mobile']): ?>
                    <p style="margin: 3px 0; color: #666;">
                        <strong>Mobile:</strong> <?php echo htmlspecialchars($business['mobile']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($business['phone']): ?>
                    <p style="margin: 3px 0; color: #666;">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($business['phone']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($business['email']): ?>
                    <p style="margin: 3px 0; color: #666;">
                        <strong>Email:</strong> <?php echo htmlspecialchars($business['email']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($business['gstin']): ?>
                    <p style="margin: 3px 0; color: #666;">
                        <strong>GSTIN:</strong> <?php echo htmlspecialchars($business['gstin']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="invoice-info">
                <h1 class="invoice-title">INVOICE</h1>
                <p class="invoice-number">
                    Invoice #: <?php echo htmlspecialchars($order['order_number']); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Date:</strong> <?php echo date('d M, Y', strtotime($order['order_date'])); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Due Date:</strong> 
                    <?php 
                    $due_date = date('d M, Y', strtotime($order['order_date']));
                    if ($order['payment_terms'] == 'credit_7') {
                        $due_date = date('d M, Y', strtotime($order['order_date'] . ' +7 days'));
                    } elseif ($order['payment_terms'] == 'credit_15') {
                        $due_date = date('d M, Y', strtotime($order['order_date'] . ' +15 days'));
                    } elseif ($order['payment_terms'] == 'credit_30') {
                        $due_date = date('d M, Y', strtotime($order['order_date'] . ' +30 days'));
                    }
                    echo $due_date;
                    ?>
                </p>
                <div style="margin-top: 10px;">
                    <span class="customer-status">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    <span class="payment-status payment-<?php echo $order['payment_status']; ?>" style="margin-left: 5px;">
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Billing and Shipping Information -->
        <div class="row section">
            <div class="col-6">
                <div class="section-title">Bill To:</div>
                <p style="margin: 5px 0; font-weight: bold; font-size: 14px;">
                    <?php echo htmlspecialchars($order['customer_name']); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Shop:</strong> <?php echo htmlspecialchars($order['shop_name']); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Contact:</strong> <?php echo htmlspecialchars($order['customer_contact']); ?>
                    <?php if ($order['alternate_contact']): ?>
                    / <?php echo htmlspecialchars($order['alternate_contact']); ?>
                    <?php endif; ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Location:</strong> <?php echo htmlspecialchars($order['shop_location']); ?>
                </p>
                <?php if ($order['email']): ?>
                <p style="margin: 5px 0;">
                    <strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?>
                </p>
                <?php endif; ?>
                <p style="margin: 5px 0;">
                    <strong>Customer Type:</strong> 
                    <span style="text-transform: capitalize;">
                        <?php echo str_replace('_', ' ', $order['customer_type']); ?>
                    </span>
                </p>
            </div>
            
            <div class="col-6">
                <div class="section-title">Order Information:</div>
                <p style="margin: 5px 0;">
                    <strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Order Date:</strong> <?php echo date('d M, Y', strtotime($order['order_date'])); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Payment Terms:</strong> 
                    <?php echo str_replace('_', ' ', ucfirst($order['payment_terms'])); ?>
                </p>
                <p style="margin: 5px 0;">
                    <strong>Payment Method:</strong> 
                    <?php echo str_replace('_', ' ', ucfirst($order['payment_method'])); ?>
                </p>
                <?php if ($order['lineman_name']): ?>
                <p style="margin: 5px 0;">
                    <strong>Sales Person:</strong> <?php echo htmlspecialchars($order['lineman_name']); ?>
                    (ID: <?php echo htmlspecialchars($order['lineman_id']); ?>)
                </p>
                <?php endif; ?>
                <?php if ($order['delivery_date']): ?>
                <p style="margin: 5px 0;">
                    <strong>Delivery Date:</strong> <?php echo date('d M, Y', strtotime($order['delivery_date'])); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Order Items Table -->
        <div class="section">
            <div class="section-title">Order Items</div>
            <table class="table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="30%">Description</th>
                        <th width="10%">Code</th>
                        <th width="10%" class="text-right">Stock Price</th>
                        <th width="10%" class="text-right">Selling Price</th>
                        <th width="10%" class="text-center">Qty</th>
                        <th width="10%" class="text-right">Profit</th>
                        <th width="15%" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($order_items as $item):
                        $profit = ($item['customer_price'] - $item['stock_price']) * $item['quantity'];
                    ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                        <td class="text-right"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($item['stock_price'], 2); ?></td>
                        <td class="text-right"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($item['customer_price'], 2); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-right text-bold" style="color: #28a745;">
                            <?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($profit, 2); ?>
                        </td>
                        <td class="text-right text-bold">
                            <?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($item['total'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Payment Summary -->
        <div class="row">
            <div class="col-8">
                <!-- Notes Section -->
                <?php if ($order['notes'] || $business['terms_conditions']): ?>
                <div class="section">
                    <div class="section-title">Notes & Terms</div>
                    <?php if ($order['notes']): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Order Notes:</strong>
                        <p style="margin: 5px 0; color: #666;">
                            <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($business['terms_conditions']): ?>
                    <div>
                        <strong>Terms & Conditions:</strong>
                        <div class="terms-conditions">
                            <?php echo nl2br(htmlspecialchars($business['terms_conditions'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-4">
                <div class="total-section">
                    <div class="section-title">Payment Summary</div>
                    
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span class="text-bold"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if ($business['show_tax_invoice'] == 1 && $tax_percentage > 0): ?>
                    <div class="total-row">
                        <span>Tax (<?php echo $tax_percentage; ?>%):</span>
                        <span class="text-bold"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['payment_status'] == 'partial' || $order['payment_status'] == 'paid'): ?>
                    <div class="total-row" style="color: #28a745;">
                        <span>Amount Paid:</span>
                        <span class="text-bold"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($order['paid_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['payment_status'] == 'partial' && $order['pending_amount'] > 0): ?>
                    <div class="total-row" style="color: #dc3545;">
                        <span>Balance Due:</span>
                        <span class="text-bold"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($order['pending_amount'], 2); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="total-row grand-total">
                        <span>Total Amount:</span>
                        <span><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; color: #28a745;">
                        <span>Total Profit:</span>
                        <span class="text-bold"><?php echo $business['currency'] ?? '₹'; ?><?php echo number_format($total_profit, 2); ?></span>
                    </div>
                    
                    <div class="total-row" style="margin-top: 20px;">
                        <span>Amount in Words:</span>
                    </div>
                    <div class="amount-box">
                        <strong><?php echo $amount_in_words; ?></strong>
                    </div>
                </div>
            </div>
        </div>

    </div>
    
    <script>
    // Function to download as PDF (using browser's print to PDF)
    function downloadPDF() {
        window.print();
    }
    
    // Auto-print option
    <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
    window.onload = function() {
        window.print();
    };
    <?php endif; ?>
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
        
        // Esc to go back
        if (e.key === 'Escape') {
            window.history.back();
        }
        
        // Ctrl + S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            downloadPDF();
        }
    });
    
    // Add print-specific styling
    var style = document.createElement('style');
    style.innerHTML = `
        @media print {
            @page {
                margin: 15mm;
            }
            
            body * {
                visibility: hidden;
            }
            
            .invoice-container, .invoice-container * {
                visibility: visible;
            }
            
            .invoice-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            
            .table th {
                background-color: #007bff !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
            }
            
            .payment-status, .customer-status {
                border: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
            }
            
            .terms-conditions, .amount-box {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>