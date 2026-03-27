<?php
// remove-logo.php
include('config/config.php');
header('Content-Type: application/json');

// Check if logo exists
$sql = "SELECT business_logo FROM business_settings WHERE id = 1";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($conn, $sql);

if ($row && !empty($row['business_logo'])) {
    $logo_path = 'uploads/logo/' . $row['business_logo'];
    
    // Delete file if exists
    if (file_exists($logo_path)) {
        unlink($logo_path);
    }
    
    // Update database
    $update_sql = "UPDATE business_settings SET business_logo = NULL WHERE id = 1";
    if (mysqli_query($conn, $update_sql)) {
        echo json_encode(['success' => true, 'message' => 'Logo removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No logo found']);
}

mysqli_close($conn);
?>