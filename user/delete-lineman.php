<?php
// delete-lineman.php
include('config/config.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    
    // Check if line man exists
    $check_sql = "SELECT id FROM linemen WHERE id = '$id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Delete the line man
        $delete_sql = "DELETE FROM linemen WHERE id = '$id'";
        
        if (mysqli_query($conn, $delete_sql)) {
            echo json_encode(['success' => true, 'message' => 'Line man deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Line man not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

mysqli_close($conn);
?>