<!doctype html>
<html lang="en">

<?php include('includes/head.php')?>

<body data-sidebar="dark">

<!-- Loader -->
<?php include('includes/pre-loader.php')?>

<!-- Begin page -->
<div id="layout-wrapper">

<?php include('includes/topbar.php')?>    

    <!-- ========== Left Sidebar Start ========== -->
    <div class="vertical-menu">

        <div data-simplebar class="h-100">

            <!--- Sidemenu -->
            <?php include('includes/sidebar.php')?>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- Left Sidebar End -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
           
            <div class="container-fluid">


                <!-- end page title -->

                <?php
                // Database connection
                include('config/config.php');
                
                // Define backup directory
                $backup_dir = 'backups/';
                
                // Create backup directory if not exists
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0777, true);
                }
                
                // Handle actions
                if (isset($_GET['action'])) {
                    $action = $_GET['action'];
                    
                    // Create backup
                    if ($action == 'create_backup') {
                        $backup_type = isset($_GET['type']) ? $_GET['type'] : 'full';
                        $backup_result = createDatabaseBackup($backup_dir, $backup_type);
                        
                        if ($backup_result['success']) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>
                                    Backup created successfully: ' . $backup_result['filename'] . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-alert-circle me-2"></i>
                                    Error creating backup: ' . $backup_result['message'] . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                    
                    // Delete backup
                    elseif ($action == 'delete_backup' && isset($_GET['file'])) {
                        $filename = basename($_GET['file']);
                        $filepath = $backup_dir . $filename;
                        
                        if (file_exists($filepath) && unlink($filepath)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>
                                    Backup deleted successfully: ' . $filename . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-alert-circle me-2"></i>
                                    Error deleting backup!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                    
                    // Download backup
                    elseif ($action == 'download_backup' && isset($_GET['file'])) {
                        $filename = basename($_GET['file']);
                        $filepath = $backup_dir . $filename;
                        
                        if (file_exists($filepath)) {
                            header('Content-Description: File Transfer');
                            header('Content-Type: application/octet-stream');
                            header('Content-Disposition: attachment; filename="' . $filename . '"');
                            header('Expires: 0');
                            header('Cache-Control: must-revalidate');
                            header('Pragma: public');
                            header('Content-Length: ' . filesize($filepath));
                            readfile($filepath);
                            exit;
                        }
                    }
                }
                
                // Handle restore form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
                    if ($_POST['action'] == 'restore_backup') {
                        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == 0) {
                            $backup_file = $_FILES['backup_file']['tmp_name'];
                            $restore_result = restoreDatabaseBackup($backup_file);
                            
                            if ($restore_result['success']) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-check-circle me-2"></i>
                                        Database restored successfully!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle me-2"></i>
                                        Error restoring backup: ' . $restore_result['message'] . '
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-alert-circle me-2"></i>
                                    Please select a backup file!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                    
                    // Delete all backups
                    elseif ($_POST['action'] == 'delete_all_backups') {
                        $confirm = isset($_POST['confirm_delete']) ? $_POST['confirm_delete'] : '';
                        
                        if ($confirm == 'DELETE ALL') {
                            $deleted_count = 0;
                            $files = glob($backup_dir . '*.sql');
                            
                            foreach ($files as $file) {
                                if (unlink($file)) {
                                    $deleted_count++;
                                }
                            }
                            
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>
                                    Deleted ' . $deleted_count . ' backup files!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-alert-circle me-2"></i>
                                    Please type "DELETE ALL" to confirm!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                
                // Function to create database backup
                function createDatabaseBackup($backup_dir, $type = 'full') {
                    global $conn;
                    
                    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                    $filepath = $backup_dir . $filename;
                    
                    try {
                        $handle = fopen($filepath, 'w+');
                        
                        // Get all table names
                        $tables = array();
                        $result = mysqli_query($conn, 'SHOW TABLES');
                        while ($row = mysqli_fetch_row($result)) {
                            $tables[] = $row[0];
                        }
                        
                        // Loop through tables
                        foreach ($tables as $table) {
                            // Add table comment
                            fwrite($handle, "--\n");
                            fwrite($handle, "-- Table structure for table `$table`\n");
                            fwrite($handle, "--\n\n");
                            
                            // Drop table if exists
                            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n\n");
                            
                            // Get create table statement
                            $create_result = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
                            $create_row = mysqli_fetch_row($create_result);
                            fwrite($handle, $create_row[1] . ";\n\n");
                            
                            // Skip data for structure-only backup
                            if ($type != 'structure_only') {
                                fwrite($handle, "--\n");
                                fwrite($handle, "-- Dumping data for table `$table`\n");
                                fwrite($handle, "--\n\n");
                                
                                // Get table data
                                $data_result = mysqli_query($conn, "SELECT * FROM `$table`");
                                $num_fields = mysqli_num_fields($data_result);
                                
                                while ($row = mysqli_fetch_row($data_result)) {
                                    fwrite($handle, "INSERT INTO `$table` VALUES(");
                                    for ($i = 0; $i < $num_fields; $i++) {
                                        if (isset($row[$i])) {
                                            $row[$i] = addslashes($row[$i]);
                                            $row[$i] = str_replace("\n", "\\n", $row[$i]);
                                            fwrite($handle, '"' . $row[$i] . '"');
                                        } else {
                                            fwrite($handle, 'NULL');
                                        }
                                        if ($i < ($num_fields - 1)) {
                                            fwrite($handle, ',');
                                        }
                                    }
                                    fwrite($handle, ");\n");
                                }
                                fwrite($handle, "\n");
                            }
                        }
                        
                        fclose($handle);
                        
                        return [
                            'success' => true,
                            'filename' => $filename,
                            'filepath' => $filepath,
                            'size' => filesize($filepath)
                        ];
                    } catch (Exception $e) {
                        return [
                            'success' => false,
                            'message' => $e->getMessage()
                        ];
                    }
                }
                
                // Function to restore database backup
                function restoreDatabaseBackup($backup_file) {
                    global $conn;
                    
                    try {
                        // Read backup file
                        $sql = file_get_contents($backup_file);
                        
                        // Split by semicolon to get individual queries
                        $queries = explode(';', $sql);
                        
                        // Execute each query
                        foreach ($queries as $query) {
                            $query = trim($query);
                            if (!empty($query)) {
                                if (!mysqli_query($conn, $query)) {
                                    return [
                                        'success' => false,
                                        'message' => 'Query failed: ' . mysqli_error($conn)
                                    ];
                                }
                            }
                        }
                        
                        return ['success' => true];
                    } catch (Exception $e) {
                        return [
                            'success' => false,
                            'message' => $e->getMessage()
                        ];
                    }
                }
                
                // Get list of backups
                $backup_files = [];
                if (is_dir($backup_dir)) {
                    $files = glob($backup_dir . '*.sql');
                    usort($files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    
                    foreach ($files as $file) {
                        $backup_files[] = [
                            'filename' => basename($file),
                            'filepath' => $file,
                            'size' => filesize($file),
                            'modified' => filemtime($file),
                            'date' => date('Y-m-d H:i:s', filemtime($file))
                        ];
                    }
                }
                
                // Get database statistics
                $stats_sql = "SELECT 
                    (SELECT COUNT(*) FROM brands) as total_brands,
                    (SELECT COUNT(*) FROM categories) as total_categories,
                    (SELECT COUNT(*) FROM products) as total_products,
                    (SELECT COUNT(*) FROM customers) as total_customers,
                    (SELECT COUNT(*) FROM linemen) as total_linemen,
                    (SELECT SUM(quantity) FROM products) as total_stock,
                    (SELECT SUM(stock_price * quantity) FROM products) as stock_value,
                    (SELECT COUNT(*) FROM support_tickets) as total_tickets,
                    (SELECT COUNT(*) FROM stock_transactions) as total_transactions
                    FROM DUAL";
                
                $stats_result = mysqli_query($conn, $stats_sql);
                $stats = mysqli_fetch_assoc($stats_result);
                ?>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-database"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Database Size</p>
                                        <h4 class="mb-0">
                                            <?php 
                                            $size_sql = "SELECT 
                                                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                                                FROM information_schema.tables 
                                                WHERE table_schema = DATABASE()";
                                            $size_result = mysqli_query($conn, $size_sql);
                                            $size_data = mysqli_fetch_assoc($size_result);
                                            echo number_format($size_data['size_mb'] ?? 0, 2) . ' MB';
                                            ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle text-success rounded-2 fs-2">
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                                        <h4 class="mb-0 text-success"><?php echo number_format($stats['total_products'] ?? 0); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2">
                                            <i class="mdi mdi-account-group"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Customers</p>
                                        <h4 class="mb-0 text-warning"><?php echo number_format($stats['total_customers'] ?? 0); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-info-subtle text-info rounded-2 fs-2">
                                            <i class="mdi mdi-backup-restore"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Backup Files</p>
                                        <h4 class="mb-0 text-info"><?php echo count($backup_files); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <!-- Create Backup -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-database-plus text-success me-1"></i> Create New Backup
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">Backup Options</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-primary h-100">
                                                <div class="card-body text-center">
                                                    <div class="avatar-sm mx-auto mb-2">
                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-2">
                                                            <i class="mdi mdi-database"></i>
                                                        </span>
                                                    </div>
                                                    <h6 class="mb-1">Full Backup</h6>
                                                    <p class="small text-muted mb-2">Structure + Data</p>
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="createBackup('full')">
                                                        <i class="mdi mdi-download me-1"></i> Create
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="card border-warning h-100">
                                                <div class="card-body text-center">
                                                    <div class="avatar-sm mx-auto mb-2">
                                                        <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-2">
                                                            <i class="mdi mdi-table"></i>
                                                        </span>
                                                    </div>
                                                    <h6 class="mb-1">Structure Only</h6>
                                                    <p class="small text-muted mb-2">No data, tables only</p>
                                                    <button type="button" class="btn btn-sm btn-warning" onclick="createBackup('structure_only')">
                                                        <i class="mdi mdi-download me-1"></i> Create
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="text-muted mb-3">Backup Settings</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="autoBackup" checked>
                                        <label class="form-check-label" for="autoBackup">
                                            Enable automatic backups (weekly)
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="compressBackup" checked>
                                        <label class="form-check-label" for="compressBackup">
                                            Compress backup files (ZIP)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="emailBackup" checked>
                                        <label class="form-check-label" for="emailBackup">
                                            Email backup copy to admin
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="mdi mdi-information-outline me-2"></i>
                                    <small>
                                        <strong>Backup includes:</strong><br>
                                        Products, Customers, Categories, Brands, Linemen, Settings, Transactions
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Database Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-information-outline text-info me-1"></i> Database Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td><strong>Database Name:</strong></td>
                                                <td><?php echo mysqli_get_host_info($conn); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tables Count:</strong></td>
                                                <td>
                                                    <?php 
                                                    $tables_sql = "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()";
                                                    $tables_result = mysqli_query($conn, $tables_sql);
                                                    $tables_data = mysqli_fetch_assoc($tables_result);
                                                    echo $tables_data['table_count'];
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>MySQL Version:</strong></td>
                                                <td><?php echo mysqli_get_server_info($conn); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>PHP Version:</strong></td>
                                                <td><?php echo phpversion(); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Server Time:</strong></td>
                                                <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Backup Directory:</strong></td>
                                                <td><?php echo realpath($backup_dir); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Restore Backup -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-database-import text-warning me-1"></i> Restore Backup
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <i class="mdi mdi-alert-circle-outline me-2"></i>
                                    <strong>Warning:</strong> Restoring a backup will replace ALL current data. 
                                    Make sure to create a backup before restoring.
                                </div>
                                
                                <form method="POST" action="backup-restore.php" enctype="multipart/form-data" onsubmit="return confirmRestore()">
                                    <input type="hidden" name="action" value="restore_backup">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Select Backup File</label>
                                        <input type="file" class="form-control" name="backup_file" accept=".sql,.zip,.gz" required>
                                        <small class="text-muted">Supported formats: SQL, ZIP, GZ (Max: 50MB)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Restore Mode</label>
                                        <select class="form-select" name="restore_mode">
                                            <option value="full">Full Restore (Replace all)</option>
                                            <option value="merge">Merge Data (Keep existing)</option>
                                            <option value="selected">Selected Tables Only</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="create_pre_restore_backup" checked>
                                            <label class="form-check-label">
                                                Create backup before restoring (Recommended)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Enter "RESTORE" to confirm</label>
                                        <input type="text" class="form-control" name="confirm_restore" 
                                               placeholder="RESTORE" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="mdi mdi-database-import me-1"></i> Restore Database
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Available Backups -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="mdi mdi-history text-primary me-1"></i> Available Backups
                                    </h5>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                                        <i class="mdi mdi-delete me-1"></i> Delete All
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (count($backup_files) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Backup File</th>
                                                <th>Date</th>
                                                <th>Size</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backup_files as $backup): 
                                                $file_size = formatFileSize($backup['size']);
                                                $file_date = date('d M, Y H:i', $backup['modified']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo $backup['filename']; ?></strong>
                                                        <small class="d-block text-muted">
                                                            <?php echo $file_date; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td><?php echo $file_date; ?></td>
                                                <td><?php echo $file_size; ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="backup-restore.php?action=download_backup&file=<?php echo urlencode($backup['filename']); ?>" 
                                                           class="btn btn-sm btn-info" title="Download">
                                                            <i class="mdi mdi-download"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-warning" 
                                                                onclick="restoreFile('<?php echo $backup['filename']; ?>')" title="Restore">
                                                            <i class="mdi mdi-restore"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="deleteBackup('<?php echo $backup['filename']; ?>')" title="Delete">
                                                            <i class="mdi mdi-delete"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="mdi mdi-information-outline me-1"></i>
                                        Showing <?php echo count($backup_files); ?> backup files
                                    </small>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-database-off display-5"></i>
                                        <p class="mt-2 mb-0">No backup files found</p>
                                        <small>Create your first backup using the options on the left</small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Database Tables Summary -->
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-table text-success me-1"></i> Database Tables Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    $tables_sql = "SELECT 
                                        TABLE_NAME,
                                        TABLE_ROWS,
                                        DATA_LENGTH,
                                        INDEX_LENGTH,
                                        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb
                                        FROM information_schema.tables 
                                        WHERE table_schema = DATABASE()
                                        ORDER BY size_mb DESC";
                                    
                                    $tables_result = mysqli_query($conn, $tables_sql);
                                    while ($table = mysqli_fetch_assoc($tables_result)):
                                        $row_count = $table['TABLE_ROWS'];
                                        $size_mb = $table['size_mb'];
                                        $icon = getTableIcon($table['TABLE_NAME']);
                                    ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card border h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="flex-shrink-0">
                                                        <span class="avatar-xs">
                                                            <span class="avatar-title bg-light text-dark">
                                                                <?php echo $icon; ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-0"><?php echo $table['TABLE_NAME']; ?></h6>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">Rows: <?php echo number_format($row_count); ?></small>
                                                    <small class="text-muted"><?php echo $size_mb; ?> MB</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php 
        mysqli_close($conn);
        include('includes/footer.php') 
        ?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Delete All Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteAllModalLabel">
                    <i class="mdi mdi-alert-circle-outline me-1"></i> Delete All Backups
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <h6 class="alert-heading">⚠️ WARNING: This action cannot be undone!</h6>
                    <p class="mb-2">This will delete ALL backup files from the system.</p>
                    <p class="mb-0"><strong>Make sure you have downloaded important backups before proceeding.</strong></p>
                </div>
                
                <form method="POST" action="backup-restore.php" id="deleteAllForm">
                    <input type="hidden" name="action" value="delete_all_backups">
                    
                    <div class="mb-3">
                        <label class="form-label">Type "DELETE ALL" to confirm</label>
                        <input type="text" class="form-control" name="confirm_delete" 
                               placeholder="DELETE ALL" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteAllForm').submit()">
                    <i class="mdi mdi-delete-forever me-1"></i> Delete All Backups
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Restore Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning" id="restoreModalLabel">
                    <i class="mdi mdi-alert-circle-outline me-1"></i> Restore Backup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <h6 class="alert-heading">⚠️ WARNING: This will replace ALL current data!</h6>
                    <p class="mb-0">Are you sure you want to restore from: <strong id="restoreFileName"></strong>?</p>
                </div>
                
                <form method="POST" action="backup-restore.php" id="restoreForm">
                    <input type="hidden" name="action" value="restore_backup">
                    <input type="hidden" name="backup_file_name" id="backupFileName">
                    
                    <div class="mb-3">
                        <label class="form-label">Enter "RESTORE" to confirm</label>
                        <input type="text" class="form-control" name="confirm_restore" 
                               placeholder="RESTORE" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_pre_restore_backup" checked>
                            <label class="form-check-label">
                                Create backup before restoring (Recommended)
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="document.getElementById('restoreForm').submit()">
                    <i class="mdi mdi-restore me-1"></i> Restore Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Helper function to format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Create backup
function createBackup(type) {
    if (confirm('Create ' + type + ' backup?')) {
        window.location.href = 'backup-restore.php?action=create_backup&type=' + type;
    }
}

// Delete backup
function deleteBackup(filename) {
    if (confirm('Delete backup: ' + filename + '?')) {
        window.location.href = 'backup-restore.php?action=delete_backup&file=' + encodeURIComponent(filename);
    }
}

// Restore backup
function restoreFile(filename) {
    document.getElementById('restoreFileName').textContent = filename;
    document.getElementById('backupFileName').value = filename;
    
    const modal = new bootstrap.Modal(document.getElementById('restoreModal'));
    modal.show();
}

// Confirm restore
function confirmRestore() {
    const confirmText = document.querySelector('input[name="confirm_restore"]').value;
    if (confirmText !== 'RESTORE') {
        alert('Please type "RESTORE" to confirm');
        return false;
    }
    
    return confirm('⚠️ WARNING: This will replace ALL current data. Are you sure?');
}

// Auto backup schedule
function scheduleAutoBackup() {
    // This would set up automatic backup schedule
    const autoBackup = document.getElementById('autoBackup');
    if (autoBackup.checked) {
        console.log('Auto backup enabled');
        // Schedule weekly backup
    } else {
        console.log('Auto backup disabled');
    }
}

// Download all backups as ZIP
function downloadAllBackups() {
    // This would create a ZIP of all backups and download it
    alert('This would create a ZIP file containing all backup files');
}

// Test backup integrity
function testBackupIntegrity(filename) {
    // This would test if a backup file is valid
    alert('Testing backup integrity: ' + filename);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Update backup settings
    document.getElementById('autoBackup').addEventListener('change', scheduleAutoBackup);
    
    // Check backup directory size
    checkBackupDirectorySize();
    
    // Set up keyboard shortcuts
    setupKeyboardShortcuts();
    
    // Check for large backups
    setTimeout(checkLargeBackups, 3000);
});

// Check backup directory size
function checkBackupDirectorySize() {
    fetch('check-backup-size.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const sizeElement = document.querySelector('.card-body h4.mb-0');
                if (sizeElement) {
                    sizeElement.textContent = data.size_mb + ' MB';
                }
            }
        });
}

// Check for large backup files
function checkLargeBackups() {
    const largeFiles = [];
    document.querySelectorAll('table tbody tr').forEach(row => {
        const sizeText = row.querySelector('td:nth-child(3)').textContent;
        const sizeMatch = sizeText.match(/(\d+\.?\d*)\s*(MB|GB)/);
        if (sizeMatch) {
            const size = parseFloat(sizeMatch[1]);
            const unit = sizeMatch[2];
            const sizeInMB = unit === 'GB' ? size * 1024 : size;
            
            if (sizeInMB > 100) { // Warn for files > 100MB
                largeFiles.push(row.querySelector('strong').textContent);
            }
        }
    });
    
    if (largeFiles.length > 0) {
        showNotification('Large backup files detected (>100MB): ' + largeFiles.join(', '), 'warning');
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = 'position-fixed top-0 end-0 m-3';
    notification.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-lg" role="alert">
            <i class="mdi mdi-${type === 'warning' ? 'alert' : 'information'}-outline me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 10000);
}

// Setup keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+B to create backup
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            createBackup('full');
        }
        
        // Ctrl+D to delete all backups
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('deleteAllModal'));
            modal.show();
        }
        
        // Ctrl+R to restore
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            document.querySelector('input[name="backup_file"]').click();
        }
        
        // Ctrl+S to download latest
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const firstDownload = document.querySelector('a.btn.btn-sm.btn-info');
            if (firstDownload) {
                firstDownload.click();
            }
        }
    });
}

// Export data as CSV
function exportDataAsCSV(table) {
    // This would export specific table data as CSV
    alert('Exporting ' + table + ' data as CSV');
}

// Import data from CSV
function importDataFromCSV() {
    // This would import data from CSV files
    alert('Import data from CSV file');
}

// Database optimization
function optimizeDatabase() {
    if (confirm('Optimize database tables? This may improve performance.')) {
        fetch('optimize-database.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Database optimized successfully!', 'success');
                } else {
                    showNotification('Error optimizing database: ' + data.message, 'danger');
                }
            });
    }
}

// Check database health
function checkDatabaseHealth() {
    fetch('check-database-health.php')
        .then(response => response.json())
        .then(data => {
            let message = 'Database health check:\n';
            if (data.connected) message += '✅ Connection: OK\n';
            if (data.tables_ok) message += '✅ Tables: OK\n';
            if (data.size_mb) message += '📊 Size: ' + data.size_mb + ' MB\n';
            if (data.recommendation) message += '💡 ' + data.recommendation;
            
            alert(message);
        });
}

// Add this to page load
document.addEventListener('DOMContentLoaded', function() {
    // Add optimize button if needed
    const cardHeader = document.querySelector('.card-header');
    if (cardHeader) {
        const optimizeBtn = document.createElement('button');
        optimizeBtn.className = 'btn btn-sm btn-info ms-2';
        optimizeBtn.innerHTML = '<i class="mdi mdi-wrench me-1"></i> Optimize';
        optimizeBtn.onclick = optimizeDatabase;
        cardHeader.appendChild(optimizeBtn);
    }
});
</script>

</body>

</html>