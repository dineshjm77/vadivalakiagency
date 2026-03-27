<?php
include('config/config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // Don't allow deletion of the main admin (id=1)
    if ($id == 1) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete the main administrator account']);
        exit;
    }
    
    // Check if admin exists
    $check_sql = "SELECT id FROM admin_users WHERE id = '$id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Delete the admin
        $delete_sql = "DELETE FROM admin_users WHERE id = '$id'";
        
        if (mysqli_query($conn, $delete_sql)) {
            echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($conn);
?>