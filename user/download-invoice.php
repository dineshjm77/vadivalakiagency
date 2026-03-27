<?php
session_start();
include('config/config.php');
include('includes/auth-check.php');

if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$order_id = intval($_GET['id']);
$lineman_id = $_SESSION['user_id'];

// Verify order belongs to lineman
$check_sql = "SELECT id FROM orders WHERE id = $order_id AND created_by = $lineman_id";
$check_result = mysqli_query($conn, $check_sql);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    header('Location: quick-order.php');
    exit;
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice-' . $order_id . '.pdf"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// In a real implementation, you would generate PDF here
// For now, redirect to print view
header("Location: view-invoice.php?id=$order_id&print=1");
exit;
?>