<?php
// delete-transaction.php
include('config/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $transaction_id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // Get transaction details first
    $get_sql = "SELECT * FROM stock_transactions WHERE id = '$transaction_id'";
    $get_result = mysqli_query($conn, $get_sql);
    
    if (mysqli_num_rows($get_result) > 0) {
        $transaction = mysqli_fetch_assoc($get_result);
        $product_id = $transaction['product_id'];
        
        // Get current product stock
        $product_sql = "SELECT quantity FROM products WHERE id = '$product_id'";
        $product_result = mysqli_query($conn, $product_sql);
        $product = mysqli_fetch_assoc($product_result);
        $current_qty = $product['quantity'];
        
        // Calculate original quantity before transaction
        if ($transaction['transaction_type'] == 'adjustment') {
            // For adjustments, revert to previous quantity
            $new_qty = $transaction['previous_quantity'];
        } else {
            // For purchases/sales, subtract/add the transaction quantity
            if ($transaction['transaction_type'] == 'purchase') {
                $new_qty = $current_qty - $transaction['quantity'];
            } else {
                $new_qty = $current_qty + $transaction['quantity'];
            }
        }
        
        // Update product stock
        $update_sql = "UPDATE products SET quantity = '$new_qty' WHERE id = '$product_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            // Delete the transaction
            $delete_sql = "DELETE FROM stock_transactions WHERE id = '$transaction_id'";
            if (mysqli_query($conn, $delete_sql)) {
                echo json_encode(['success' => true, 'message' => 'Transaction deleted and stock updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting transaction: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating product stock: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($conn);
?>