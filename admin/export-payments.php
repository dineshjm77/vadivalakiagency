<?php
session_start();
include('config/config.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please login first.");
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Fetch payment data
$payments = [];
$total_amount = 0;

if ($conn) {
    // Build SQL query
    $sql = "SELECT 
                t.id as transaction_id,
                t.payment_id,
                t.created_at as payment_date,
                t.amount,
                t.payment_method,
                t.reference_no,
                t.notes,
                c.customer_name,
                c.shop_name,
                c.customer_contact,
                c.customer_code,
                o.order_number,
                o.total_amount as order_total,
                o.order_date
            FROM transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN orders o ON t.order_id = o.id
            WHERE t.type = 'payment'";
    
    // Add date filter
    $sql .= " AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
    
    // Add customer filter
    if ($customer_id > 0) {
        $sql .= " AND t.customer_id = $customer_id";
    }
    
    // Add payment method filter
    if ($payment_method !== 'all') {
        $sql .= " AND t.payment_method = '$payment_method'";
    }
    
    // Add search filter
    if ($search_term) {
        $sql .= " AND (t.payment_id LIKE '%$search_term%' 
                      OR t.reference_no LIKE '%$search_term%'
                      OR c.customer_name LIKE '%$search_term%'
                      OR c.shop_name LIKE '%$search_term%'
                      OR o.order_number LIKE '%$search_term%')";
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
            $total_amount += $row['amount'];
        }
    }
}

// Set headers for download
if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="payment_history_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Create Excel content
    $excel_content = "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #f2f2f2; font-weight: bold; text-align: left; }
            th, td { border: 1px solid #ddd; padding: 6px; }
            .header { background-color: #4CAF50; color: white; padding: 10px; text-align: center; }
            .summary { background-color: #f8f9fa; padding: 8px; margin: 10px 0; border: 1px solid #dee2e6; }
            .total-row { font-weight: bold; background-color: #e8f4f8; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
        </style>
    </head>
    <body>";
    
    // Add report header
    $excel_content .= "<div class='header'>
        <h2>APR Water Agencies - Payment History Report</h2>
        <h4>Generated on: " . date('d M Y h:i A') . "</h4>
    </div>";
    
    // Add summary information
    $excel_content .= "<div class='summary'>
        <strong>Report Summary:</strong><br>
        Date Range: $start_date to $end_date<br>
        Total Payments: " . count($payments) . "<br>
        Total Amount: ₹" . number_format($total_amount, 2) . "<br>";
    
    if ($customer_id > 0 && isset($customers[$customer_id])) {
        $excel_content .= "Customer: " . htmlspecialchars($customers[$customer_id]) . "<br>";
    }
    
    if ($payment_method != 'all') {
        $excel_content .= "Payment Method: " . ucfirst($payment_method) . "<br>";
    }
    
    $excel_content .= "</div>";
    
    // Add payments table
    if (!empty($payments)) {
        $excel_content .= "<table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Payment ID</th>
                    <th>Date & Time</th>
                    <th>Customer Name</th>
                    <th>Shop Name</th>
                    <th>Contact</th>
                    <th>Order No.</th>
                    <th>Order Date</th>
                    <th>Order Total</th>
                    <th>Payment Method</th>
                    <th>Reference No.</th>
                    <th>Amount (₹)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>";
        
        $counter = 1;
        foreach ($payments as $payment) {
            // Format payment method name
            $method_names = [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'upi' => 'UPI',
                'cheque' => 'Cheque',
                'card' => 'Card'
            ];
            $method_name = $method_names[$payment['payment_method']] ?? ucfirst($payment['payment_method']);
            
            $excel_content .= "<tr>
                <td>" . $counter++ . "</td>
                <td>" . htmlspecialchars($payment['payment_id'] ?? 'N/A') . "</td>
                <td>" . date('d M Y h:i A', strtotime($payment['payment_date'])) . "</td>
                <td>" . htmlspecialchars($payment['customer_name']) . "</td>
                <td>" . htmlspecialchars($payment['shop_name']) . "</td>
                <td>" . htmlspecialchars($payment['customer_contact']) . "</td>
                <td>" . htmlspecialchars($payment['order_number'] ?? 'N/A') . "</td>
                <td>" . ($payment['order_date'] ? date('d M Y', strtotime($payment['order_date'])) : 'N/A') . "</td>
                <td class='text-right'>" . ($payment['order_total'] ? '₹' . number_format($payment['order_total'], 2) : 'N/A') . "</td>
                <td>" . $method_name . "</td>
                <td>" . htmlspecialchars($payment['reference_no'] ?? '') . "</td>
                <td class='text-right'><strong>₹" . number_format($payment['amount'], 2) . "</strong></td>
                <td>" . htmlspecialchars($payment['notes'] ?? '') . "</td>
            </tr>";
        }
        
        // Add total row
        $excel_content .= "<tr class='total-row'>
            <td colspan='11' class='text-right'><strong>TOTAL:</strong></td>
            <td class='text-right'><strong>₹" . number_format($total_amount, 2) . "</strong></td>
            <td></td>
        </tr>";
        
        $excel_content .= "</tbody></table>";
        
        // Add payment method summary
        $excel_content .= "<br><br><h3>Payment Method Summary</h3>";
        
        if ($conn) {
            $stats_sql = "SELECT 
                            payment_method,
                            COUNT(*) as count,
                            SUM(amount) as total
                          FROM transactions 
                          WHERE type = 'payment' 
                          AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
            
            if ($customer_id > 0) {
                $stats_sql .= " AND customer_id = $customer_id";
            }
            
            if ($payment_method != 'all') {
                $stats_sql .= " AND payment_method = '$payment_method'";
            }
            
            $stats_sql .= " GROUP BY payment_method";
            $stats_result = mysqli_query($conn, $stats_sql);
            
            if ($stats_result && mysqli_num_rows($stats_result) > 0) {
                $excel_content .= "<table>
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Number of Payments</th>
                            <th>Total Amount (₹)</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>";
                
                while ($stat = mysqli_fetch_assoc($stats_result)) {
                    $percentage = $total_amount > 0 ? ($stat['total'] / $total_amount * 100) : 0;
                    $method_name = $method_names[$stat['payment_method']] ?? ucfirst($stat['payment_method']);
                    
                    $excel_content .= "<tr>
                        <td>" . $method_name . "</td>
                        <td>" . $stat['count'] . "</td>
                        <td class='text-right'>₹" . number_format($stat['total'], 2) . "</td>
                        <td class='text-right'>" . number_format($percentage, 1) . "%</td>
                    </tr>";
                }
                
                $excel_content .= "<tr class='total-row'>
                    <td><strong>TOTAL</strong></td>
                    <td><strong>" . count($payments) . "</strong></td>
                    <td class='text-right'><strong>₹" . number_format($total_amount, 2) . "</strong></td>
                    <td class='text-right'><strong>100%</strong></td>
                </tr>";
                
                $excel_content .= "</tbody></table>";
            }
        }
        
        // Add daily summary
        $excel_content .= "<br><br><h3>Daily Summary (Last 10 Days)</h3>";
        
        if ($conn) {
            $daily_sql = "SELECT 
                            DATE(created_at) as payment_date,
                            COUNT(*) as payment_count,
                            SUM(amount) as daily_total,
                            SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_total,
                            SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount ELSE 0 END) as bank_total,
                            SUM(CASE WHEN payment_method = 'upi' THEN amount ELSE 0 END) as upi_total,
                            SUM(CASE WHEN payment_method = 'cheque' THEN amount ELSE 0 END) as cheque_total,
                            SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END) as card_total
                          FROM transactions 
                          WHERE type = 'payment' 
                          AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
            
            if ($customer_id > 0) {
                $daily_sql .= " AND customer_id = $customer_id";
            }
            
            if ($payment_method != 'all') {
                $daily_sql .= " AND payment_method = '$payment_method'";
            }
            
            $daily_sql .= " GROUP BY DATE(created_at) ORDER BY payment_date DESC LIMIT 10";
            $daily_result = mysqli_query($conn, $daily_sql);
            
            if ($daily_result && mysqli_num_rows($daily_result) > 0) {
                $excel_content .= "<table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>No. of Payments</th>
                            <th>Total Amount (₹)</th>
                            <th>Cash (₹)</th>
                            <th>Bank Transfer (₹)</th>
                            <th>UPI (₹)</th>
                            <th>Cheque (₹)</th>
                            <th>Card (₹)</th>
                        </tr>
                    </thead>
                    <tbody>";
                
                while ($day = mysqli_fetch_assoc($daily_result)) {
                    $excel_content .= "<tr>
                        <td>" . date('d M Y', strtotime($day['payment_date'])) . "</td>
                        <td>" . $day['payment_count'] . "</td>
                        <td class='text-right'>₹" . number_format($day['daily_total'], 2) . "</td>
                        <td class='text-right'>₹" . number_format($day['cash_total'], 2) . "</td>
                        <td class='text-right'>₹" . number_format($day['bank_total'], 2) . "</td>
                        <td class='text-right'>₹" . number_format($day['upi_total'], 2) . "</td>
                        <td class='text-right'>₹" . number_format($day['cheque_total'], 2) . "</td>
                        <td class='text-right'>₹" . number_format($day['card_total'], 2) . "</td>
                    </tr>";
                }
                
                $excel_content .= "</tbody></table>";
            }
        }
        
    } else {
        $excel_content .= "<p style='text-align: center; padding: 20px;'>No payment data found for the selected filters.</p>";
    }
    
    // Add footer
    $excel_content .= "<br><br><hr>
    <div style='text-align: center; font-size: 10px; color: #666; margin-top: 30px;'>
        <p>Report generated by APR Water Agencies</p>
        <p>Generated on: " . date('d M Y h:i A') . "</p>
        <p>© " . date('Y') . " APR Water Agencies. All rights reserved.</p>
    </div>";
    
    $excel_content .= "</body></html>";
    
    // Output Excel content
    echo $excel_content;
    
} elseif ($format == 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_history_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");
    
    // Add headers
    fputcsv($output, [
        'Payment ID',
        'Date & Time',
        'Customer Name',
        'Shop Name',
        'Contact',
        'Customer Code',
        'Order Number',
        'Order Date',
        'Order Total',
        'Payment Method',
        'Reference No',
        'Amount (₹)',
        'Notes'
    ]);
    
    // Add data rows
    foreach ($payments as $payment) {
        // Format payment method name
        $method_names = [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'upi' => 'UPI',
            'cheque' => 'Cheque',
            'card' => 'Card'
        ];
        $method_name = $method_names[$payment['payment_method']] ?? ucfirst($payment['payment_method']);
        
        fputcsv($output, [
            $payment['payment_id'] ?? 'N/A',
            date('d M Y h:i A', strtotime($payment['payment_date'])),
            $payment['customer_name'],
            $payment['shop_name'],
            $payment['customer_contact'],
            $payment['customer_code'],
            $payment['order_number'] ?? 'N/A',
            $payment['order_date'] ? date('d M Y', strtotime($payment['order_date'])) : 'N/A',
            $payment['order_total'] ? '₹' . number_format($payment['order_total'], 2) : 'N/A',
            $method_name,
            $payment['reference_no'] ?? '',
            '₹' . number_format($payment['amount'], 2),
            $payment['notes'] ?? ''
        ]);
    }
    
    // Add summary row
    fputcsv($output, ['', '', '', '', '', '', '', '', '', '', 'TOTAL:', '₹' . number_format($total_amount, 2), '']);
    
    fclose($output);
    
} elseif ($format == 'pdf') {
    // PDF Export (HTML format that can be printed as PDF)
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="payment_history_' . date('Y-m-d_H-i-s') . '.html"');
    
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Payment History Report</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .report-header { text-align: center; margin-bottom: 20px; }
            .summary-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin: 10px 0; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #4CAF50; color: white; }
            .total-row { font-weight: bold; background-color: #e8f4f8; }
            .text-right { text-align: right; }
            .page-break { page-break-before: always; }
            @media print {
                body { margin: 0; padding: 20px; }
                .no-print { display: none; }
                table { width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class='report-header'>
            <h1>APR Water Agencies</h1>
            <h3>Payment History Report</h3>
            <p>Generated on: " . date('d M Y h:i A') . "</p>
        </div>
        
        <div class='summary-box'>
            <h4>Report Summary</h4>
            <p><strong>Date Range:</strong> $start_date to $end_date</p>
            <p><strong>Total Payments:</strong> " . count($payments) . "</p>
            <p><strong>Total Amount:</strong> ₹" . number_format($total_amount, 2) . "</p>";
    
    if ($customer_id > 0 && isset($customers[$customer_id])) {
        echo "<p><strong>Customer:</strong> " . htmlspecialchars($customers[$customer_id]) . "</p>";
    }
    
    if ($payment_method != 'all') {
        echo "<p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>";
    }
    
    echo "</div>";
    
    if (!empty($payments)) {
        echo "<h4>Payment Details</h4>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Payment ID</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Order No.</th>
                    <th>Method</th>
                    <th>Amount (₹)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>";
        
        $counter = 1;
        foreach ($payments as $payment) {
            // Format payment method name
            $method_names = [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'upi' => 'UPI',
                'cheque' => 'Cheque',
                'card' => 'Card'
            ];
            $method_name = $method_names[$payment['payment_method']] ?? ucfirst($payment['payment_method']);
            
            echo "<tr>
                <td>" . $counter++ . "</td>
                <td>" . htmlspecialchars($payment['payment_id'] ?? 'N/A') . "</td>
                <td>" . date('d M Y h:i A', strtotime($payment['payment_date'])) . "</td>
                <td>" . htmlspecialchars($payment['customer_name']) . "<br>
                    <small>" . htmlspecialchars($payment['shop_name']) . "</small></td>
                <td>" . htmlspecialchars($payment['order_number'] ?? 'N/A') . "</td>
                <td>" . $method_name . "</td>
                <td class='text-right'>₹" . number_format($payment['amount'], 2) . "</td>
                <td>" . htmlspecialchars(substr($payment['notes'] ?? '', 0, 50)) . "</td>
            </tr>";
        }
        
        echo "<tr class='total-row'>
            <td colspan='6' class='text-right'><strong>TOTAL:</strong></td>
            <td class='text-right'><strong>₹" . number_format($total_amount, 2) . "</strong></td>
            <td></td>
        </tr>";
        
        echo "</tbody></table>";
    } else {
        echo "<p style='text-align: center; padding: 20px;'>No payment data found for the selected filters.</p>";
    }
    
    echo "<div style='margin-top: 30px; text-align: center; font-size: 10px; color: #666;'>
        <p>© " . date('Y') . " APR Water Agencies. All rights reserved.</p>
    </div>
    
    <div class='no-print' style='text-align: center; margin: 20px;'>
        <button onclick='window.print()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;'>
            Print Report
        </button>
    </div>
    
    <script>
        // Auto-print when opened
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
    </script>
    
    </body>
    </html>";
}

// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
exit;
?>