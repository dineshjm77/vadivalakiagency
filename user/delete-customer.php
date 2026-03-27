<?php
// delete-customer.php
include('config/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // Check if customer exists
    $check_sql = "SELECT id FROM customers WHERE id = '$id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Check if customer has any invoices
        $invoice_check = "SELECT id FROM invoices WHERE customer_id = '$id' LIMIT 1";
        $invoice_result = mysqli_query($conn, $invoice_check);
        
        if (mysqli_num_rows($invoice_result) > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete customer with existing invoices']);
        } else {
            // Delete the customer
            $delete_sql = "DELETE FROM customers WHERE id = '$id'";
            
            if (mysqli_query($conn, $delete_sql)) {
                echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($conn);
?>