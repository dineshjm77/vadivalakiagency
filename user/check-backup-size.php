<?php
// check-backup-size.php
include('config/config.php');
header('Content-Type: application/json');

$backup_dir = 'backups/';
$total_size = 0;

if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '*.sql');
    foreach ($files as $file) {
        $total_size += filesize($file);
    }
}

// Convert to MB
$size_mb = round($total_size / 1024 / 1024, 2);

// Get database size
$db_sql = "SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as db_size_mb 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()";
$db_result = mysqli_query($conn, $db_sql);
$db_data = mysqli_fetch_assoc($db_result);

echo json_encode([
    'success' => true,
    'size_mb' => $size_mb,
    'db_size_mb' => $db_data['db_size_mb'] ?? 0,
    'file_count' => count($files ?? [])
]);

mysqli_close($conn);
?>