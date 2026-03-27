<?php
// process-payment.php
session_start();
require_once 'config/config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['lineman_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user is active (for linemen)
if (isset($_SESSION['lineman_id'])) {
    $lineman_id = $_SESSION['lineman_id'];
    $check_sql = "SELECT status FROM linemen WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $lineman_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $lineman = mysqli_fetch_assoc($check_result);
    
    if (!$lineman || $lineman['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Your account is not active']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $payment_type = $_POST['payment_type'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'cash';
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $send_receipt = isset($_POST['send_receipt']) && $_POST['send_receipt'] === '1';
    
    // Validate input
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }
    
    if (!in_array($payment_type, ['full', 'partial'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment type']);
        exit;
    }
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
        exit;
    }
    
    if (!in_array($method, ['cash', 'bank_transfer', 'cheque', 'upi', 'card', 'other'])) {
        $method = 'other';
    }
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Get order details with customer information
        $order_sql = "SELECT o.*, c.id as customer_id, c.shop_name, c.customer_name, 
                             c.customer_contact, c.email, c.current_balance
                      FROM orders o
                      JOIN customers c ON o.customer_id = c.id
                      WHERE o.id = ?";
        $order_stmt = mysqli_prepare($conn, $order_sql);
        mysqli_stmt_bind_param($order_stmt, "i", $order_id);
        mysqli_stmt_execute($order_stmt);
        $order_result = mysqli_stmt_get_result($order_stmt);
        
        if (mysqli_num_rows($order_result) === 0) {
            throw new Exception('Order not found');
        }
        
        $order = mysqli_fetch_assoc($order_result);
        
        // Validate payment amount
        if ($payment_type === 'full') {
            if (abs($amount - $order['pending_amount']) > 0.01) { // Allow small floating point differences
                throw new Exception('Full payment amount must equal pending amount');
            }
        } else {
            if ($amount > $order['pending_amount']) {
                throw new Exception('Partial payment cannot exceed pending amount');
            }
        }
        
        // Get user who is processing the payment
        $created_by = null;
        $user_type = '';
        
        if (isset($_SESSION['admin_id'])) {
            $created_by = $_SESSION['admin_id'];
            $user_type = 'admin';
        } elseif (isset($_SESSION['lineman_id'])) {
            $created_by = $_SESSION['lineman_id'];
            $user_type = 'lineman';
        }
        
        // 2. Update order payment status
        $new_paid_amount = $order['paid_amount'] + $amount;
        $new_pending_amount = $order['pending_amount'] - $amount;
        
        // Determine new payment status
        $new_payment_status = 'pending';
        if ($new_pending_amount <= 0.01) { // Consider paid if less than 1 paisa
            $new_payment_status = 'paid';
            $new_pending_amount = 0;
        } elseif ($new_paid_amount > 0) {
            $new_payment_status = 'partial';
        }
        
        // Update order
        $update_order_sql = "UPDATE orders 
                             SET paid_amount = ?, 
                                 pending_amount = ?, 
                                 payment_status = ?,
                                 updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?";
        $update_order_stmt = mysqli_prepare($conn, $update_order_sql);
        mysqli_stmt_bind_param($update_order_stmt, "ddsi", 
            $new_paid_amount, 
            $new_pending_amount, 
            $new_payment_status,
            $order_id
        );
        
        if (!mysqli_stmt_execute($update_order_stmt)) {
            throw new Exception('Failed to update order');
        }
        
        // 3. Update customer balance
        $new_customer_balance = $order['current_balance'] - $amount;
        $update_customer_sql = "UPDATE customers 
                                SET current_balance = ?,
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE id = ?";
        $update_customer_stmt = mysqli_prepare($conn, $update_customer_sql);
        mysqli_stmt_bind_param($update_customer_stmt, "di", 
            $new_customer_balance, 
            $order['customer_id']
        );
        
        if (!mysqli_stmt_execute($update_customer_stmt)) {
            throw new Exception('Failed to update customer balance');
        }
        
        // 4. Create transaction record
        $payment_id = 'PAY' . date('YmdHis') . str_pad($order_id, 6, '0', STR_PAD_LEFT);
        
        $transaction_sql = "INSERT INTO transactions 
                            (customer_id, order_id, payment_id, type, amount, 
                             payment_method, reference_no, notes, created_by, created_at)
                            VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
        $transaction_stmt = mysqli_prepare($conn, $transaction_sql);
        mysqli_stmt_bind_param($transaction_stmt, "iissdssi",
            $order['customer_id'],
            $order_id,
            $payment_id,
            $amount,
            $method,
            $reference,
            $notes,
            $created_by
        );
        
        if (!mysqli_stmt_execute($transaction_stmt)) {
            throw new Exception('Failed to create transaction record');
        }
        
        $transaction_id = mysqli_insert_id($conn);
        
        // 5. Create payment history log
        $log_sql = "INSERT INTO payment_history 
                    (order_id, transaction_id, amount_paid, payment_method, 
                     reference_no, notes, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        mysqli_stmt_bind_param($log_stmt, "iidsssi",
            $order_id,
            $transaction_id,
            $amount,
            $method,
            $reference,
            $notes,
            $created_by
        );
        
        if (!mysqli_stmt_execute($log_stmt)) {
            throw new Exception('Failed to create payment history');
        }
        
        // 6. If order is fully paid, update status to delivered if not already
        if ($new_payment_status === 'paid' && $order['status'] !== 'delivered') {
            $update_status_sql = "UPDATE orders SET status = 'delivered' WHERE id = ?";
            $update_status_stmt = mysqli_prepare($conn, $update_status_sql);
            mysqli_stmt_bind_param($update_status_stmt, "i", $order_id);
            mysqli_stmt_execute($update_status_stmt);
        }
        
        // 7. Send receipt email if requested and customer has email
        $email_sent = false;
        if ($send_receipt && !empty($order['email'])) {
            $email_sent = sendPaymentReceipt($order, $amount, $method, $reference, $notes);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'Payment processed successfully',
            'email_sent' => $email_sent,
            'updated' => [
                'payment_status' => $new_payment_status,
                'paid_amount' => number_format($new_paid_amount, 2),
                'pending_amount' => number_format($new_pending_amount, 2)
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        
        error_log("Payment processing error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Payment processing failed: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to send payment receipt email
function sendPaymentReceipt($order, $amount, $method, $reference, $notes) {
    // Get business settings for email configuration
    global $conn;
    
    $settings_sql = "SELECT business_name, email as business_email, 
                            invoice_email_subject, invoice_email_body
                     FROM business_settings LIMIT 1";
    $settings_result = mysqli_query($conn, $settings_sql);
    $settings = mysqli_fetch_assoc($settings_sql);
    
    if (!$settings) {
        $settings = [
            'business_name' => 'APR Water Agencies',
            'business_email' => 'noreply@aprwater.com',
            'invoice_email_subject' => 'Payment Receipt - Order #{order_number}',
            'invoice_email_body' => "Dear {customer_name},\n\nThank you for your payment.\n\nOrder: {order_number}\nAmount Paid: ₹{amount}\nPayment Method: {method}\nReference: {reference}\n\nBest regards,\n{business_name}"
        ];
    }
    
    // Prepare email content
    $subject = str_replace(
        ['{order_number}', '{customer_name}', '{amount}'],
        [$order['order_number'], $order['customer_name'], number_format($amount, 2)],
        $settings['invoice_email_subject']
    );
    
    $body = str_replace(
        ['{customer_name}', '{order_number}', '{amount}', '{method}', '{reference}', '{notes}', '{business_name}'],
        [
            $order['customer_name'],
            $order['order_number'],
            number_format($amount, 2),
            ucfirst(str_replace('_', ' ', $method)),
            $reference ?: 'N/A',
            $notes ?: 'N/A',
            $settings['business_name']
        ],
        $settings['invoice_email_body']
    );
    
    // Add footer
    $body .= "\n\n---\nThis is an automated receipt. Please contact us if you have any questions.";
    
    // Email headers
    $headers = "From: " . $settings['business_email'] . "\r\n";
    $headers .= "Reply-To: " . $settings['business_email'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Try to send email
    try {
        if (mail($order['email'], $subject, $body, $headers)) {
            // Log email sent
            $log_sql = "INSERT INTO email_logs 
                        (order_id, customer_id, email_type, sent_to, subject, status, sent_at)
                        VALUES (?, ?, 'payment_receipt', ?, ?, 'sent', NOW())";
            $log_stmt = mysqli_prepare($conn, $log_sql);
            mysqli_stmt_bind_param($log_stmt, "iiss", 
                $order['id'], 
                $order['customer_id'], 
                $order['email'], 
                $subject
            );
            mysqli_stmt_execute($log_stmt);
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
    }
    
    return false;
}

// Close database connection
mysqli_close($conn);
?>