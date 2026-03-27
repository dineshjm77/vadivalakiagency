<?php
// export-customers.php
include('config/config.php');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Customer Code',
    'Shop Name',
    'Customer Name',
    'Contact Number',
    'Alternate Contact',
    'Email',
    'Shop Location',
    'Customer Type',
    'Payment Terms',
    'Credit Limit',
    'Current Balance',
    'Total Purchases',
    'Status',
    'Created Date'
]);

// Get customers
$sql = "SELECT * FROM customers ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['customer_code'],
        $row['shop_name'],
        $row['customer_name'],
        $row['customer_contact'],
        $row['alternate_contact'],
        $row['email'],
        $row['shop_location'],
        $row['customer_type'],
        $row['payment_terms'],
        $row['credit_limit'],
        $row['current_balance'],
        $row['total_purchases'],
        $row['status'],
        $row['created_at']
    ]);
}

fclose($output);
mysqli_close($conn);
?>