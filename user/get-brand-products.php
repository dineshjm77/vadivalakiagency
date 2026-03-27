<?php
// get-brand-products.php
include('config/config.php');
header('Content-Type: application/json');

if (isset($_GET['brand_id'])) {
    $brand_id = mysqli_real_escape_string($conn, $_GET['brand_id']);
    
    $sql = "SELECT 
        id, product_code, product_name, quantity, 
        stock_price, customer_price, status
        FROM products 
        WHERE brand_id = '$brand_id'
        ORDER BY product_name ASC";
    
    $result = mysqli_query($conn, $sql);
    $products = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Convert price values to floats to ensure proper formatting
            $row['stock_price'] = (float)$row['stock_price'];
            $row['customer_price'] = (float)$row['customer_price'];
            $row['quantity'] = (int)$row['quantity'];
            $products[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'products' => $products,
            'count' => count($products)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'products' => [],
            'count' => 0,
            'message' => 'No products found for this brand'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Brand ID not provided'
    ]);
}

mysqli_close($conn);
?>