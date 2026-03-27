<?php
// optimize-database.php
include('config/config.php');
header('Content-Type: application/json');

try {
    $tables = array();
    $result = mysqli_query($conn, 'SHOW TABLES');
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    
    $optimized_tables = 0;
    foreach ($tables as $table) {
        mysqli_query($conn, "OPTIMIZE TABLE `$table`");
        $optimized_tables++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Optimized $optimized_tables tables",
        'tables' => $tables
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>