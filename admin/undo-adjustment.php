<?php
// undo-adjustment.php
include('config/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $transaction_id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // Get adjustment details
    $get_sql = "SELECT * FROM stock_transactions WHERE id = '$transaction_id' AND transaction_type = 'adjustment'";
    $get_result = mysqli_query($conn, $get_sql);
    
    if (mysqli_num_rows($get_result) > 0) {
        $adjustment = mysqli_fetch_assoc($get_result);
        $product_id = $adjustment['product_id'];
        
        // Calculate reverse adjustment
        $reverse_qty = $adjustment['quantity'];
        $reverse_type = $adjustment['previous_quantity'] < $adjustment['new_quantity'] ? 'decrease' : 'increase';
        
        // Get current product stock
        $product_sql = "SELECT quantity FROM products WHERE id = '$product_id'";
        $product_result = mysqli_query($conn, $product_sql);
        $product = mysqli_fetch_assoc($product_result);
        $current_qty = $product['quantity'];
        
        // Calculate new quantity after reverse
        if ($reverse_type == 'increase') {
            $new_qty = $current_qty + $reverse_qty;
        } else {
            $new_qty = $current_qty - $reverse_qty;
            if ($new_qty < 0) $new_qty = 0;
        }
        
        // Update product stock
        $update_sql = "UPDATE products SET quantity = '$new_qty' WHERE id = '$product_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            // Record the reverse adjustment
            $reverse_sql = "INSERT INTO stock_transactions (
                product_id, transaction_type, quantity, stock_price,
                previous_quantity, new_quantity, notes, created_at
            ) VALUES (
                '$product_id', 'adjustment', '$reverse_qty', '0.00',
                '$current_qty', '$new_qty', 'Undo of adjustment #$transaction_id', NOW()
            )";
            
            if (mysqli_query($conn, $reverse_sql)) {
                echo json_encode(['success' => true, 'message' => 'Adjustment undone successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error recording reverse adjustment: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating product stock: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Adjustment not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($conn);
?>