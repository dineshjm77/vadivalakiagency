<?php
session_start();
header('Content-Type: application/json');

// Include database configuration
include('config/config.php');

// Check connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : '';
$reference_no = isset($_POST['reference_no']) ? mysqli_real_escape_string($conn, $_POST['reference_no']) : '';
$payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
$notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';
$created_by = $_SESSION['user_id'];

// Validate input
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

if ($payment_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
    exit;
}

if (empty($payment_method)) {
    echo json_encode(['success' => false, 'message' => 'Payment method is required']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // 1. Get current order details
    $sql_order = "SELECT * FROM orders WHERE id = ? FOR UPDATE";
    $stmt_order = mysqli_prepare($conn, $sql_order);
    mysqli_stmt_bind_param($stmt_order, 'i', $order_id);
    mysqli_stmt_execute($stmt_order);
    $order_result = mysqli_stmt_get_result($stmt_order);
    
    if (!$order = mysqli_fetch_assoc($order_result)) {
        throw new Exception('Order not found');
    }
    
    // Check if payment amount exceeds pending amount
    if ($payment_amount > $order['pending_amount']) {
        throw new Exception('Payment amount exceeds pending amount');
    }
    
    // Get customer ID
    $customer_id = $order['customer_id'];
    
    // 2. Update order payment details
    $new_paid = $order['paid_amount'] + $payment_amount;
    $new_pending = $order['pending_amount'] - $payment_amount;
    $payment_status = $new_pending == 0 ? 'paid' : ($new_pending < $order['total_amount'] ? 'partial' : 'pending');
    $payment_date_time = $payment_date . ' ' . date('H:i:s');
    
    $sql_update_order = "UPDATE orders 
                         SET paid_amount = ?, 
                             pending_amount = ?, 
                             payment_status = ?,
                             payment_date = ?
                         WHERE id = ?";
    
    $stmt_update = mysqli_prepare($conn, $sql_update_order);
    mysqli_stmt_bind_param($stmt_update, 'ddssi', $new_paid, $new_pending, $payment_status, $payment_date_time, $order_id);
    
    if (!mysqli_stmt_execute($stmt_update)) {
        throw new Exception('Failed to update order');
    }
    
    // 3. Update customer balance
    $sql_update_customer = "UPDATE customers 
                           SET current_balance = current_balance - ? 
                           WHERE id = ?";
    
    $stmt_customer = mysqli_prepare($conn, $sql_update_customer);
    mysqli_stmt_bind_param($stmt_customer, 'di', $payment_amount, $customer_id);
    
    if (!mysqli_stmt_execute($stmt_customer)) {
        throw new Exception('Failed to update customer balance');
    }
    
    // 4. Generate payment ID
    $payment_id = 'PAY' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // 5. Insert into transactions table
    $sql_transaction = "INSERT INTO transactions 
                       (customer_id, order_id, payment_id, type, amount, payment_method, reference_no, notes, created_by) 
                       VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?)";
    
    $stmt_transaction = mysqli_prepare($conn, $sql_transaction);
    $type = 'payment';
    mysqli_stmt_bind_param($stmt_transaction, 'iisdsssi', $customer_id, $order_id, $payment_id, $payment_amount, $payment_method, $reference_no, $notes, $created_by);
    
    if (!mysqli_stmt_execute($stmt_transaction)) {
        throw new Exception('Failed to record transaction');
    }
    
    $transaction_id = mysqli_insert_id($conn);
    
    // 6. Insert into payment_history table
    $sql_payment_history = "INSERT INTO payment_history 
                           (order_id, transaction_id, amount_paid, payment_method, reference_no, notes, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_history = mysqli_prepare($conn, $sql_payment_history);
    mysqli_stmt_bind_param($stmt_history, 'iidsssi', $order_id, $transaction_id, $payment_amount, $payment_method, $reference_no, $notes, $created_by);
    
    if (!mysqli_stmt_execute($stmt_history)) {
        throw new Exception('Failed to record payment history');
    }
    
    // 7. Update customer's last purchase date if order is fully paid
    if ($payment_status == 'paid') {
        $sql_update_last_purchase = "UPDATE customers 
                                    SET last_purchase_date = CURDATE() 
                                    WHERE id = ?";
        
        $stmt_last_purchase = mysqli_prepare($conn, $sql_update_last_purchase);
        mysqli_stmt_bind_param($stmt_last_purchase, 'i', $customer_id);
        mysqli_stmt_execute($stmt_last_purchase);
        mysqli_stmt_close($stmt_last_purchase);
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Close all statements
    mysqli_stmt_close($stmt_order);
    mysqli_stmt_close($stmt_update);
    mysqli_stmt_close($stmt_customer);
    mysqli_stmt_close($stmt_transaction);
    mysqli_stmt_close($stmt_history);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment received successfully',
        'fully_paid' => $payment_status == 'paid',
        'new_pending' => $new_pending,
        'payment_id' => $payment_id,
        'transaction_id' => $transaction_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => 'Payment failed: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>