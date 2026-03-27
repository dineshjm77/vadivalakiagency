<?php
include('config/config.php');
include('includes/auth-check.php');

if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];
$payment_id = isset($_GET['payment_id']) ? mysqli_real_escape_string($conn, $_GET['payment_id']) : '';

if (empty($payment_id)) {
    header('Location: orders.php');
    exit;
}

// Extract numeric ID from payment_id
$transaction_id = 0;
if (preg_match('/PAY\d+(\d{4})$/', $payment_id, $matches)) {
    $transaction_id = $matches[1];
}

// Fetch payment details
if ($transaction_id > 0) {
    $payment_sql = "SELECT t.*, c.shop_name, c.customer_name, c.customer_contact, 
                           c.shop_location, c.customer_code,
                           l.full_name as collected_by,
                           o.order_number, o.total_amount as order_total
                    FROM transactions t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    LEFT JOIN linemen l ON t.created_by = l.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    WHERE t.id = $transaction_id AND t.created_by = $lineman_id";
} else {
    $payment_sql = "SELECT t.*, c.shop_name, c.customer_name, c.customer_contact, 
                           c.shop_location, c.customer_code,
                           l.full_name as collected_by,
                           o.order_number, o.total_amount as order_total
                    FROM transactions t
                    LEFT JOIN customers c ON t.customer_id = c.id
                    LEFT JOIN linemen l ON t.created_by = l.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    WHERE t.created_by = $lineman_id 
                    ORDER BY t.created_at DESC LIMIT 1";
}

$payment_result = mysqli_query($conn, $payment_sql);

if (!$payment_result || mysqli_num_rows($payment_result) == 0) {
    header('Location: orders.php');
    exit;
}

$payment = mysqli_fetch_assoc($payment_result);

// Get business settings
$settings_sql = "SELECT * FROM business_settings LIMIT 1";
$settings_result = mysqli_query($conn, $settings_sql);
$settings = mysqli_fetch_assoc($settings_result);

// Format dates
$payment_date = date('d M, Y', strtotime($payment['created_at']));
$payment_time = date('h:i A', strtotime($payment['created_at']));
$payment_datetime = date('d M, Y h:i A', strtotime($payment['created_at']));

// Get payment method display name
$payment_methods = [
    'cash' => 'Cash',
    'upi' => 'UPI',
    'card' => 'Card',
    'bank_transfer' => 'Bank Transfer',
    'cheque' => 'Cheque',
    'wallet' => 'Digital Wallet',
    'other' => 'Other'
];
$payment_method_display = $payment_methods[$payment['payment_method']] ?? ucfirst($payment['payment_method']);

// Get order details if payment is for a specific order
$order_details = null;
if (!empty($payment['order_id'])) {
    $order_sql = "SELECT o.* FROM orders o WHERE o.id = {$payment['order_id']} AND o.created_by = $lineman_id";
    $order_result = mysqli_query($conn, $order_sql);
    if ($order_result && mysqli_num_rows($order_result) > 0) {
        $order_details = mysqli_fetch_assoc($order_result);
    }
}

// Close connection early since we don't need it anymore
if (isset($conn)) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $payment_id; ?></title>
    
    <style>
        /* Reset for printing */
        @media print {
            @page {
                size: A4;
                margin: 0;
                padding: 0;
            }
            
            html, body {
                width: 210mm;
                height: 297mm;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                font-size: 12pt;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            
            * {
                box-sizing: border-box;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Main container - exactly A4 size */
        .a4-container {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 15mm;
            background: white;
            font-family: 'Arial', 'Helvetica', sans-serif;
            line-height: 1.4;
            position: relative;
        }
        
        /* Header section */
        .receipt-header {
            text-align: center;
            padding-bottom: 10mm;
            border-bottom: 2px solid #333;
            margin-bottom: 10mm;
        }
        
        .receipt-header h1 {
            font-size: 24pt;
            font-weight: bold;
            margin: 0 0 5mm 0;
            color: #000;
        }
        
        .receipt-number {
            font-size: 14pt;
            margin: 2mm 0;
        }
        
        .receipt-date {
            font-size: 12pt;
            color: #666;
        }
        
        /* Company info */
        .company-info {
            float: left;
            width: 60%;
        }
        
        .company-info h3 {
            font-size: 14pt;
            margin: 0 0 2mm 0;
            font-weight: bold;
        }
        
        .company-info p {
            margin: 1mm 0;
            font-size: 10pt;
        }
        
        /* Receipt details */
        .receipt-details {
            float: right;
            width: 40%;
            text-align: right;
        }
        
        .receipt-details table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .receipt-details td {
            padding: 1mm 0;
            text-align: right;
            font-size: 10pt;
        }
        
        .receipt-details td.label {
            font-weight: bold;
            text-align: left;
            padding-right: 2mm;
        }
        
        /* Clear float */
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        
        /* Customer info */
        .customer-info {
            margin: 5mm 0;
            padding: 3mm;
            border: 1px solid #ccc;
            border-radius: 2mm;
            background: #f9f9f9;
        }
        
        .customer-info h4 {
            font-size: 12pt;
            margin: 0 0 2mm 0;
            font-weight: bold;
        }
        
        .customer-info p {
            margin: 1mm 0;
            font-size: 10pt;
        }
        
        /* Payment amount */
        .payment-amount {
            text-align: center;
            margin: 10mm 0;
            padding: 5mm;
            border: 3px double #333;
            border-radius: 3mm;
        }
        
        .payment-amount .amount {
            font-size: 32pt;
            font-weight: bold;
            margin: 2mm 0;
            color: #000;
        }
        
        .payment-amount .amount-in-words {
            font-size: 11pt;
            color: #666;
            margin: 2mm 0;
            font-style: italic;
        }
        
        /* Details table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5mm 0;
            font-size: 10pt;
        }
        
        .details-table th {
            background: #f0f0f0;
            padding: 2mm;
            text-align: left;
            border: 1px solid #ccc;
            font-weight: bold;
        }
        
        .details-table td {
            padding: 2mm;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        
        .details-table .label-cell {
            width: 40%;
            font-weight: bold;
            background: #f9f9f9;
        }
        
        /* Footer */
        .receipt-footer {
            position: absolute;
            bottom: 15mm;
            left: 15mm;
            right: 15mm;
            padding-top: 5mm;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
        }
        
        .signatures {
            margin-top: 10mm;
        }
        
        .signature-box {
            width: 45%;
            float: left;
            text-align: center;
            padding-top: 15mm;
        }
        
        .signature-box.right {
            float: right;
        }
        
        .signature-line {
            width: 80%;
            border-top: 1px solid #000;
            margin: 0 auto;
            padding-top: 2mm;
        }
        
        .signature-label {
            font-size: 10pt;
            font-weight: bold;
        }
        
        /* Terms */
        .terms {
            margin-top: 5mm;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
        
        /* Payment status stamp */
        .payment-stamp {
            position: absolute;
            top: 30mm;
            right: 30mm;
            transform: rotate(15deg);
            border: 3px solid #28a745;
            padding: 5mm 10mm;
            font-size: 16pt;
            font-weight: bold;
            color: #28a745;
            opacity: 0.8;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60pt;
            color: rgba(0, 0, 0, 0.05);
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
            pointer-events: none;
        }
        
        /* Print-only styles */
        @media print {
            .payment-stamp {
                display: block !important;
            }
            
            .watermark {
                display: block !important;
            }
        }
        
        /* Non-print styles */
        @media screen {
            body {
                background: #f0f0f0;
                padding: 20px;
            }
            
            .a4-container {
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            
            .payment-stamp {
                display: none;
            }
            
            .watermark {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Watermark -->
    <div class="watermark">PAID</div>
    
    <!-- Payment Stamp -->
    <div class="payment-stamp">PAID</div>
    
    <!-- A4 Container -->
    <div class="a4-container">
        
        <!-- Header -->
        <div class="receipt-header">
            <h1>PAYMENT RECEIPT</h1>
            <div class="receipt-number">Receipt No: <strong><?php echo $payment_id; ?></strong></div>
            <div class="receipt-date">Date: <?php echo $payment_date; ?></div>
        </div>
        
        <!-- Company and Receipt Details -->
        <div class="clearfix">
            <div class="company-info">
                <h3><?php echo htmlspecialchars($settings['business_name'] ?? 'APR Water Agencies'); ?></h3>
                <p><?php echo htmlspecialchars($settings['address'] ?? ''); ?></p>
                <p><?php echo $settings['city'] ?? ''; ?>, <?php echo $settings['state'] ?? ''; ?> - <?php echo $settings['pincode'] ?? ''; ?></p>
                <p>Phone: <?php echo $settings['mobile'] ?? ''; ?></p>
                <?php if (!empty($settings['email'])): ?>
                <p>Email: <?php echo $settings['email']; ?></p>
                <?php endif; ?>
            </div>
            

        </div>
        
        <!-- Customer Information -->
        <div class="customer-info">
            <h4>RECEIVED FROM</h4>
            <p><strong><?php echo htmlspecialchars($payment['shop_name']); ?></strong></p>
            <p><?php echo htmlspecialchars($payment['customer_name']); ?></p>
            <p>Contact: <?php echo $payment['customer_contact']; ?></p>
            <?php if (!empty($payment['customer_code'])): ?>
            <p>Customer Code: <?php echo $payment['customer_code']; ?></p>
            <?php endif; ?>
            <p>Address: <?php echo htmlspecialchars($payment['shop_location']); ?></p>
        </div>
        
        <!-- Payment Amount -->
        <div class="payment-amount">
            <div class="amount">₹<?php echo number_format($payment['amount'], 2); ?></div>
            <div class="amount-in-words">
                <?php 
                function numberToWords($num) {
                    $ones = array("", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine");
                    $tens = array("", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety");
                    $teens = array("Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen");
                    
                    $rupees = floor($num);
                    $paise = round(($num - $rupees) * 100);
                    
                    $words = "";
                    
                    if ($rupees >= 10000000) {
                        $words .= numberToWords($rupees / 10000000) . " Crore ";
                        $rupees %= 10000000;
                    }
                    
                    if ($rupees >= 100000) {
                        $words .= numberToWords($rupees / 100000) . " Lakh ";
                        $rupees %= 100000;
                    }
                    
                    if ($rupees >= 1000) {
                        $words .= numberToWords($rupees / 1000) . " Thousand ";
                        $rupees %= 1000;
                    }
                    
                    if ($rupees >= 100) {
                        $words .= numberToWords($rupees / 100) . " Hundred ";
                        $rupees %= 100;
                    }
                    
                    if ($rupees >= 20) {
                        $words .= $tens[floor($rupees / 10)] . " ";
                        $rupees %= 10;
                    }
                    
                    if ($rupees >= 10 && $rupees < 20) {
                        $words .= $teens[$rupees - 10] . " ";
                        $rupees = 0;
                    }
                    
                    if ($rupees > 0) {
                        $words .= $ones[$rupees] . " ";
                    }
                    
                    if (empty(trim($words))) {
                        $words = "Zero ";
                    }
                    
                    return trim($words);
                }
                
                $amount_words = numberToWords($payment['amount']);
                echo "Rupees " . $amount_words . " Only";
                ?>
            </div>
        </div>
        
        <!-- Payment Details Table -->
        <table class="details-table">
            <tr>
                <td class="label-cell">Payment Method</td>
                <td><strong><?php echo $payment_method_display; ?></strong></td>
            </tr>
            <?php if (!empty($payment['reference_no'])): ?>
            <tr>
                <td class="label-cell">Reference No</td>
                <td><?php echo $payment['reference_no']; ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($order_details)): ?>
            <tr>
                <td class="label-cell">Order Details</td>
                <td>
                    Order No: <?php echo $order_details['order_number']; ?><br>
                    Date: <?php echo date('d M, Y', strtotime($order_details['order_date'])); ?><br>
                    Total: ₹<?php echo number_format($order_details['total_amount'], 2); ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="label-cell">Collected By</td>
                <td><?php echo $payment['collected_by']; ?> (<?php echo $_SESSION['name']; ?>)</td>
            </tr>
            <?php if (!empty($payment['notes'])): ?>
            <tr>
                <td class="label-cell">Remarks</td>
                <td><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <!-- Footer -->
        <div class="receipt-footer">
            <div class="signatures clearfix">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Customer Signature</div>
                </div>
                
                <div class="signature-box right">
                    <div class="signature-line"></div>
                    <div class="signature-label">Authorized Signature</div>
                    <div><?php echo $payment['collected_by']; ?></div>
                </div>
            </div>
            
            <div class="terms">
                <p><strong>Terms & Conditions:</strong></p>
                <p>1. This is a computer generated receipt and does not require signature.</p>
                <p>2. Please keep this receipt for future reference.</p>
                <p>3. For any queries, contact: <?php echo $settings['mobile'] ?? ''; ?></p>
            </div>
        </div>
        
    </div>
    
    <!-- Action Buttons (Non-print) -->
    <div class="no-print" style="text-align: center; margin: 20px;">
        <button onclick="window.print()" style="padding: 12px 24px; font-size: 16px; margin: 5px;">
            🖨️ Print Receipt
        </button>
        <button onclick="window.close()" style="padding: 12px 24px; font-size: 16px; margin: 5px;">
            ❌ Close
        </button>
        <a href="orders.php" style="padding: 12px 24px; font-size: 16px; margin: 5px; display: inline-block;">
            📋 Back to Orders
        </a>
    </div>

    <script>
        // Auto-print if print parameter is set
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                setTimeout(function() {
                    window.print();
                }, 1000);
            }
            
            // Set page title
            document.title = "Payment Receipt - <?php echo $payment_id; ?>";
        });
        
        // After printing, close window if auto-print was triggered
        window.addEventListener('afterprint', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                setTimeout(function() {
                    window.close();
                }, 500);
            }
        });
    </script>
</body>
</html>