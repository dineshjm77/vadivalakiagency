<?php
// change-customer-status.php
include('config/config.php');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Validate status
    $valid_statuses = ['active', 'inactive', 'blocked'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Update customer status
    $sql = "UPDATE customers SET status = '$status', updated_at = NOW() WHERE id = '$id'";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($conn);
?>