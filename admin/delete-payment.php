<?php

header('Content-Type: application/json');

// Include database configuration
include('config/config.php');

// Check connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get payment ID from POST request
$payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;

if ($payment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // 1. Get payment details with FOR UPDATE to lock the row
    $sql_payment = "SELECT 
                        t.id,
                        t.payment_id,
                        t.customer_id,
                        t.order_id,
                        t.amount,
                        t.payment_method,
                        t.reference_no,
                        t.notes,
                        o.paid_amount,
                        o.pending_amount,
                        o.total_amount,
                        o.payment_status,
                        c.current_balance
                    FROM transactions t
                    LEFT JOIN orders o ON t.order_id = o.id
                    LEFT JOIN customers c ON t.customer_id = c.id
                    WHERE t.id = ? 
                    AND t.type = 'payment'
                    FOR UPDATE";
    
    $stmt_payment = mysqli_prepare($conn, $sql_payment);
    mysqli_stmt_bind_param($stmt_payment, 'i', $payment_id);
    mysqli_stmt_execute($stmt_payment);
    $payment_result = mysqli_stmt_get_result($stmt_payment);
    
    if (!$payment = mysqli_fetch_assoc($payment_result)) {
        throw new Exception('Payment not found or already deleted');
    }
    
    mysqli_stmt_close($stmt_payment);
    
    $customer_id = $payment['customer_id'];
    $order_id = $payment['order_id'];
    $amount = $payment['amount'];
    $payment_ref = $payment['payment_id'];
    
    // Store payment details for audit before deletion
    $payment_details = [
        'payment_id' => $payment_ref,
        'amount' => $amount,
        'customer_id' => $customer_id,
        'order_id' => $order_id,
        'payment_method' => $payment['payment_method'],
        'reference_no' => $payment['reference_no'],
        'notes' => $payment['notes'],
        'deleted_by' => $_SESSION['user_id'],
        'deleted_at' => date('Y-m-d H:i:s')
    ];
    
    // 2. Update customer balance (add back the amount)
    if ($customer_id) {
        $sql_update_customer = "UPDATE customers 
                               SET current_balance = current_balance + ?,
                                   updated_at = NOW()
                               WHERE id = ?";
        
        $stmt_customer = mysqli_prepare($conn, $sql_update_customer);
        mysqli_stmt_bind_param($stmt_customer, 'di', $amount, $customer_id);
        
        if (!mysqli_stmt_execute($stmt_customer)) {
            throw new Exception('Failed to update customer balance');
        }
        
        mysqli_stmt_close($stmt_customer);
        
        // Log the change in customer status logs
        $log_sql = "INSERT INTO status_logs 
                   (customer_id, old_status, new_status, changed_by, notes) 
                   VALUES (?, 'payment_deleted', 'balance_adjusted', ?, ?)";
        
        $log_notes = "Payment deleted: " . $payment_ref . " - Amount: ₹" . number_format($amount, 2);
        $stmt_log = mysqli_prepare($conn, $log_sql);
        mysqli_stmt_bind_param($stmt_log, 'iis', $customer_id, $_SESSION['user_id'], $log_notes);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);
    }
    
    // 3. Update order payment details if order exists
    if ($order_id) {
        // Calculate new paid and pending amounts
        $new_paid_amount = $payment['paid_amount'] - $amount;
        $new_pending_amount = $payment['pending_amount'] + $amount;
        
        // Determine new payment status
        if ($new_paid_amount <= 0) {
            $new_payment_status = 'pending';
        } elseif ($new_paid_amount < $payment['total_amount']) {
            $new_payment_status = 'partial';
        } else {
            $new_payment_status = 'paid';
        }
        
        $sql_update_order = "UPDATE orders 
                            SET paid_amount = ?,
                                pending_amount = ?,
                                payment_status = ?,
                                payment_date = NULL,
                                updated_at = NOW()
                            WHERE id = ?";
        
        $stmt_order = mysqli_prepare($conn, $sql_update_order);
        mysqli_stmt_bind_param($stmt_order, 'ddsi', $new_paid_amount, $new_pending_amount, $new_payment_status, $order_id);
        
        if (!mysqli_stmt_execute($stmt_order)) {
            throw new Exception('Failed to update order');
        }
        
        mysqli_stmt_close($stmt_order);
    }
    
    // 4. Delete from payment_history table first (if exists)
    $sql_delete_history = "DELETE FROM payment_history 
                          WHERE transaction_id = ?";
    
    $stmt_history = mysqli_prepare($conn, $sql_delete_history);
    mysqli_stmt_bind_param($stmt_history, 'i', $payment_id);
    mysqli_stmt_execute($stmt_history);
    mysqli_stmt_close($stmt_history);
    
    // 5. Delete the transaction record
    $sql_delete_transaction = "DELETE FROM transactions 
                              WHERE id = ? 
                              AND type = 'payment'";
    
    $stmt_transaction = mysqli_prepare($conn, $sql_delete_transaction);
    mysqli_stmt_bind_param($stmt_transaction, 'i', $payment_id);
    
    if (!mysqli_stmt_execute($stmt_transaction)) {
        throw new Exception('Failed to delete transaction record');
    }
    
    $affected_rows = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmt_transaction);
    
    if ($affected_rows === 0) {
        throw new Exception('Transaction was not deleted');
    }
    
    // 6. Store deletion details in a simple audit table or log file
    $audit_sql = "INSERT INTO audit_log 
                 (user_id, action, details, ip_address, user_agent) 
                 VALUES (?, 'delete_payment', ?, ?, ?)";
    
    $details = json_encode($payment_details);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt_audit = mysqli_prepare($conn, $audit_sql);
    mysqli_stmt_bind_param($stmt_audit, 'isss', $_SESSION['user_id'], $details, $ip_address, $user_agent);
    mysqli_stmt_execute($stmt_audit);
    mysqli_stmt_close($stmt_audit);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment deleted successfully',
        'payment_id' => $payment_ref,
        'amount' => $amount,
        'customer_id' => $customer_id,
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    error_log('Payment deletion failed: ' . $e->getMessage() . ' - Payment ID: ' . $payment_id);
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete payment: ' . $e->getMessage()
    ]);
}

// Close connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>