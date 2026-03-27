<?php
header('Content-Type: application/json');



// Include database configuration
include('config/config.php');

// Check connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get order ID from request
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Query to get order details
$sql = "SELECT 
            o.id,
            o.order_number,
            o.total_amount,
            o.paid_amount,
            o.pending_amount,
            o.order_date,
            o.payment_status,
            o.status as order_status,
            c.customer_name,
            c.shop_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'id' => $row['id'],
        'order_number' => $row['order_number'],
        'total_amount' => number_format($row['total_amount'], 2),
        'paid_amount' => number_format($row['paid_amount'], 2),
        'pending_amount' => number_format($row['pending_amount'], 2),
        'order_date' => $row['order_date'],
        'payment_status' => $row['payment_status'],
        'order_status' => $row['order_status'],
        'customer_name' => $row['customer_name'],
        'shop_name' => $row['shop_name'],
        'raw_pending' => $row['pending_amount']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>