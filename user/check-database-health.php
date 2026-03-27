<?php
// check-database-health.php
include('config/config.php');
header('Content-Type: application/json');

try {
    // Check connection
    $connected = mysqli_ping($conn);
    
    // Check tables
    $tables_sql = "SHOW TABLES";
    $tables_result = mysqli_query($conn, $tables_sql);
    $table_count = mysqli_num_rows($tables_result);
    
    // Check for errors
    $error_sql = "SHOW TABLE STATUS";
    $error_result = mysqli_query($conn, $error_sql);
    $errors = 0;
    while ($row = mysqli_fetch_assoc($error_result)) {
        if ($row['Comment'] != '') {
            $errors++;
        }
    }
    
    // Get database size
    $size_sql = "SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()";
    $size_result = mysqli_query($conn, $size_sql);
    $size_data = mysqli_fetch_assoc($size_result);
    
    echo json_encode([
        'success' => true,
        'connected' => $connected,
        'table_count' => $table_count,
        'tables_ok' => $errors == 0,
        'size_mb' => $size_data['size_mb'] ?? 0,
        'recommendation' => $size_data['size_mb'] > 100 ? 'Consider optimizing large tables' : 'Database is healthy'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>