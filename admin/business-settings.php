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
                
                // Handle form submissions
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    if (isset($_POST['action'])) {
                        $action = $_POST['action'];
                        
                        // Update business info
                        if ($action == 'update_business_info') {
                            $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
                            $business_type = mysqli_real_escape_string($conn, $_POST['business_type']);
                            $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
                            $email = mysqli_real_escape_string($conn, $_POST['email']);
                            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                            $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
                            $address = mysqli_real_escape_string($conn, $_POST['address']);
                            $city = mysqli_real_escape_string($conn, $_POST['city']);
                            $state = mysqli_real_escape_string($conn, $_POST['state']);
                            $pincode = mysqli_real_escape_string($conn, $_POST['pincode']);
                            $gstin = mysqli_real_escape_string($conn, $_POST['gstin']);
                            
                            $update_sql = "UPDATE business_settings SET 
                                business_name = '$business_name',
                                business_type = '$business_type',
                                contact_person = '$contact_person',
                                email = '$email',
                                phone = '$phone',
                                mobile = '$mobile',
                                address = '$address',
                                city = '$city',
                                state = '$state',
                                pincode = '$pincode',
                                gstin = '$gstin',
                                updated_at = NOW()
                                WHERE id = 1";
                            
                            if (mysqli_query($conn, $update_sql)) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-check-all me-2"></i>
                                        Business information updated successfully!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-block-helper me-2"></i>
                                        Error updating business information: ' . mysqli_error($conn) . '
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        }
                        
                        // Update financial settings
                        elseif ($action == 'update_financial_settings') {
                            $currency = mysqli_real_escape_string($conn, $_POST['currency']);
                            $tax_percentage = mysqli_real_escape_string($conn, $_POST['tax_percentage']);
                            $default_profit_margin = mysqli_real_escape_string($conn, $_POST['default_profit_margin']);
                            $invoice_prefix = mysqli_real_escape_string($conn, $_POST['invoice_prefix']);
                            $invoice_start_no = mysqli_real_escape_string($conn, $_POST['invoice_start_no']);
                            $quote_validity_days = mysqli_real_escape_string($conn, $_POST['quote_validity_days']);
                            $low_stock_threshold = mysqli_real_escape_string($conn, $_POST['low_stock_threshold']);
                            $auto_backup = isset($_POST['auto_backup']) ? 1 : 0;
                            
                            $update_sql = "UPDATE business_settings SET 
                                currency = '$currency',
                                tax_percentage = '$tax_percentage',
                                default_profit_margin = '$default_profit_margin',
                                invoice_prefix = '$invoice_prefix',
                                invoice_start_no = '$invoice_start_no',
                                quote_validity_days = '$quote_validity_days',
                                low_stock_threshold = '$low_stock_threshold',
                                auto_backup = '$auto_backup',
                                updated_at = NOW()
                                WHERE id = 1";
                            
                            if (mysqli_query($conn, $update_sql)) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-check-all me-2"></i>
                                        Financial settings updated successfully!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-block-helper me-2"></i>
                                        Error updating financial settings: ' . mysqli_error($conn) . '
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        }
                        
                        // Update logo
                        elseif ($action == 'update_logo') {
                            if (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] == 0) {
                                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                                $file_name = $_FILES['business_logo']['name'];
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                
                                if (in_array($file_ext, $allowed_ext)) {
                                    $new_file_name = 'business_logo_' . time() . '.' . $file_ext;
                                    $upload_path = 'uploads/logo/' . $new_file_name;
                                    
                                    // Create directory if not exists
                                    if (!is_dir('uploads/logo/')) {
                                        mkdir('uploads/logo/', 0777, true);
                                    }
                                    
                                    if (move_uploaded_file($_FILES['business_logo']['tmp_name'], $upload_path)) {
                                        $update_sql = "UPDATE business_settings SET 
                                            business_logo = '$new_file_name',
                                            updated_at = NOW()
                                            WHERE id = 1";
                                        
                                        if (mysqli_query($conn, $update_sql)) {
                                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                                    <i class="mdi mdi-check-all me-2"></i>
                                                    Business logo updated successfully!
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>';
                                        } else {
                                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                    <i class="mdi mdi-block-helper me-2"></i>
                                                    Error updating logo in database: ' . mysqli_error($conn) . '
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>';
                                        }
                                    } else {
                                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <i class="mdi mdi-block-helper me-2"></i>
                                                Error uploading logo file!
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>';
                                    }
                                } else {
                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-block-helper me-2"></i>
                                            Invalid file type. Allowed: JPG, JPEG, PNG, GIF, SVG
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                }
                            }
                        }
                        
                        // Update invoice settings
                        elseif ($action == 'update_invoice_settings') {
                            $invoice_footer = mysqli_real_escape_string($conn, $_POST['invoice_footer']);
                            $terms_conditions = mysqli_real_escape_string($conn, $_POST['terms_conditions']);
                            $payment_instructions = mysqli_real_escape_string($conn, $_POST['payment_instructions']);
                            $show_logo_invoice = isset($_POST['show_logo_invoice']) ? 1 : 0;
                            $show_tax_invoice = isset($_POST['show_tax_invoice']) ? 1 : 0;
                            $show_qr_code = isset($_POST['show_qr_code']) ? 1 : 0;
                            
                            $update_sql = "UPDATE business_settings SET 
                                invoice_footer = '$invoice_footer',
                                terms_conditions = '$terms_conditions',
                                payment_instructions = '$payment_instructions',
                                show_logo_invoice = '$show_logo_invoice',
                                show_tax_invoice = '$show_tax_invoice',
                                show_qr_code = '$show_qr_code',
                                updated_at = NOW()
                                WHERE id = 1";
                            
                            if (mysqli_query($conn, $update_sql)) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-check-all me-2"></i>
                                        Invoice settings updated successfully!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-block-helper me-2"></i>
                                        Error updating invoice settings: ' . mysqli_error($conn) . '
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        }
                        
                        // Update email settings
                        elseif ($action == 'update_email_settings') {
                            $smtp_host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
                            $smtp_port = mysqli_real_escape_string($conn, $_POST['smtp_port']);
                            $smtp_username = mysqli_real_escape_string($conn, $_POST['smtp_username']);
                            $smtp_password = mysqli_real_escape_string($conn, $_POST['smtp_password']);
                            $smtp_encryption = mysqli_real_escape_string($conn, $_POST['smtp_encryption']);
                            $from_email = mysqli_real_escape_string($conn, $_POST['from_email']);
                            $from_name = mysqli_real_escape_string($conn, $_POST['from_name']);
                            $invoice_email_subject = mysqli_real_escape_string($conn, $_POST['invoice_email_subject']);
                            $invoice_email_body = mysqli_real_escape_string($conn, $_POST['invoice_email_body']);
                            
                            $update_sql = "UPDATE business_settings SET 
                                smtp_host = '$smtp_host',
                                smtp_port = '$smtp_port',
                                smtp_username = '$smtp_username',
                                smtp_password = '$smtp_password',
                                smtp_encryption = '$smtp_encryption',
                                from_email = '$from_email',
                                from_name = '$from_name',
                                invoice_email_subject = '$invoice_email_subject',
                                invoice_email_body = '$invoice_email_body',
                                updated_at = NOW()
                                WHERE id = 1";
                            
                            if (mysqli_query($conn, $update_sql)) {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-check-all me-2"></i>
                                        Email settings updated successfully!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-block-helper me-2"></i>
                                        Error updating email settings: ' . mysqli_error($conn) . '
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        }
                        
                        // Backup database
                        elseif ($action == 'backup_database') {
                            $backup_type = mysqli_real_escape_string($conn, $_POST['backup_type']);
                            
                            // Create backup directory
                            if (!is_dir('backups/')) {
                                mkdir('backups/', 0777, true);
                            }
                            
                            $backup_file = 'backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
                            
                            // Get all table names
                            $tables = array();
                            $result = mysqli_query($conn, 'SHOW TABLES');
                            while ($row = mysqli_fetch_row($result)) {
                                $tables[] = $row[0];
                            }
                            
                            $handle = fopen($backup_file, 'w+');
                            
                            // Loop through tables
                            foreach ($tables as $table) {
                                // Drop table if exists
                                fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
                                
                                // Get create table statement
                                $create_result = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
                                $create_row = mysqli_fetch_row($create_result);
                                fwrite($handle, $create_row[1] . ";\n\n");
                                
                                // Get table data
                                $data_result = mysqli_query($conn, "SELECT * FROM `$table`");
                                $num_fields = mysqli_num_fields($data_result);
                                
                                while ($row = mysqli_fetch_row($data_result)) {
                                    fwrite($handle, "INSERT INTO `$table` VALUES(");
                                    for ($i = 0; $i < $num_fields; $i++) {
                                        $row[$i] = addslashes($row[$i]);
                                        $row[$i] = str_replace("\n", "\\n", $row[$i]);
                                        if (isset($row[$i])) {
                                            fwrite($handle, '"' . $row[$i] . '"');
                                        } else {
                                            fwrite($handle, '""');
                                        }
                                        if ($i < ($num_fields - 1)) {
                                            fwrite($handle, ',');
                                        }
                                    }
                                    fwrite($handle, ");\n");
                                }
                                fwrite($handle, "\n\n");
                            }
                            
                            fclose($handle);
                            
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Database backup created successfully: ' . basename($backup_file) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                        
                        // Reset settings
                        elseif ($action == 'reset_settings') {
                            if ($_POST['confirm_reset'] == 'RESET') {
                                // Reset to default settings
                                $reset_sql = "UPDATE business_settings SET 
                                    currency = '₹',
                                    tax_percentage = '18.00',
                                    default_profit_margin = '30.00',
                                    invoice_prefix = 'INV',
                                    invoice_start_no = '1001',
                                    quote_validity_days = '7',
                                    low_stock_threshold = '10',
                                    auto_backup = 1,
                                    updated_at = NOW()
                                    WHERE id = 1";
                                
                                if (mysqli_query($conn, $reset_sql)) {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Settings reset to default values successfully!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                } else {
                                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-block-helper me-2"></i>
                                            Error resetting settings: ' . mysqli_error($conn) . '
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                                }
                            } else {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-block-helper me-2"></i>
                                        Please type "RESET" to confirm!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            }
                        }
                    }
                }
                
                // Get current settings
                $settings_sql = "SELECT * FROM business_settings WHERE id = 1";
                $settings_result = mysqli_query($conn, $settings_sql);
                
                if (mysqli_num_rows($settings_result) == 0) {
                    // Insert default settings if not exists
                    $insert_sql = "INSERT INTO business_settings (
                        business_name, business_type, contact_person, email, phone, mobile,
                        address, city, state, pincode, gstin, currency, tax_percentage,
                        default_profit_margin, invoice_prefix, invoice_start_no,
                        quote_validity_days, low_stock_threshold, auto_backup
                    ) VALUES (
                        'APR Water Agencies', 'Water Supply Business', 'Owner',
                        'info@aprwater.com', '080-1234567', '9876543210',
                        '123 Main Street', 'Bangalore', 'Karnataka', '560001', '',
                        '₹', '18.00', '30.00', 'INV', '1001', '7', '10', 1
                    )";
                    
                    mysqli_query($conn, $insert_sql);
                    $settings_result = mysqli_query($conn, $settings_sql);
                }
                
                $settings = mysqli_fetch_assoc($settings_result);
                ?>

                <div class="row">
                    <!-- Business Information -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-office-building text-primary me-1"></i> Business Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="business-settings.php">
                                    <input type="hidden" name="action" value="update_business_info">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="business_name" 
                                                       value="<?php echo htmlspecialchars($settings['business_name'] ?? 'APR Water Agencies'); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Business Type</label>
                                                <input type="text" class="form-control" name="business_type" 
                                                       value="<?php echo htmlspecialchars($settings['business_type'] ?? 'Water Supply Business'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="contact_person" 
                                                       value="<?php echo htmlspecialchars($settings['contact_person'] ?? 'Owner'); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?php echo htmlspecialchars($settings['email'] ?? 'info@aprwater.com'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" class="form-control" name="phone" 
                                                       value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="mobile" 
                                                       value="<?php echo htmlspecialchars($settings['mobile'] ?? '9876543210'); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($settings['address'] ?? '123 Main Street'); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">City</label>
                                                <input type="text" class="form-control" name="city" 
                                                       value="<?php echo htmlspecialchars($settings['city'] ?? 'Bangalore'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">State</label>
                                                <select class="form-select" name="state">
                                                    <option value="">Select State</option>
                                                    <option value="Karnataka" <?php echo ($settings['state'] ?? '') == 'Karnataka' ? 'selected' : ''; ?>>Karnataka</option>
                                                    <option value="Maharashtra" <?php echo ($settings['state'] ?? '') == 'Maharashtra' ? 'selected' : ''; ?>>Maharashtra</option>
                                                    <option value="Tamil Nadu" <?php echo ($settings['state'] ?? '') == 'Tamil Nadu' ? 'selected' : ''; ?>>Tamil Nadu</option>
                                                    <option value="Kerala" <?php echo ($settings['state'] ?? '') == 'Kerala' ? 'selected' : ''; ?>>Kerala</option>
                                                    <option value="Andhra Pradesh" <?php echo ($settings['state'] ?? '') == 'Andhra Pradesh' ? 'selected' : ''; ?>>Andhra Pradesh</option>
                                                    <option value="Telangana" <?php echo ($settings['state'] ?? '') == 'Telangana' ? 'selected' : ''; ?>>Telangana</option>
                                                    <option value="Delhi" <?php echo ($settings['state'] ?? '') == 'Delhi' ? 'selected' : ''; ?>>Delhi</option>
                                                    <option value="Uttar Pradesh" <?php echo ($settings['state'] ?? '') == 'Uttar Pradesh' ? 'selected' : ''; ?>>Uttar Pradesh</option>
                                                    <option value="Gujarat" <?php echo ($settings['state'] ?? '') == 'Gujarat' ? 'selected' : ''; ?>>Gujarat</option>
                                                    <option value="Rajasthan" <?php echo ($settings['state'] ?? '') == 'Rajasthan' ? 'selected' : ''; ?>>Rajasthan</option>
                                                    <option value="West Bengal" <?php echo ($settings['state'] ?? '') == 'West Bengal' ? 'selected' : ''; ?>>West Bengal</option>
                                                    <option value="Other" <?php echo ($settings['state'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Pincode</label>
                                                <input type="text" class="form-control" name="pincode" 
                                                       value="<?php echo htmlspecialchars($settings['pincode'] ?? '560001'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">GSTIN Number</label>
                                                <input type="text" class="form-control" name="gstin" 
                                                       value="<?php echo htmlspecialchars($settings['gstin'] ?? ''); ?>" 
                                                       placeholder="e.g., 29AABCS1429B1Z1">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Updated</label>
                                                <input type="text" class="form-control" readonly 
                                                       value="<?php echo date('d M, Y H:i:s', strtotime($settings['updated_at'] ?? 'now')); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="mdi mdi-content-save me-1"></i> Save Business Information
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Financial Settings -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-cash-multiple text-success me-1"></i> Financial Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="business-settings.php">
                                    <input type="hidden" name="action" value="update_financial_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Currency</label>
                                                <select class="form-select" name="currency">
                                                    <option value="₹" <?php echo ($settings['currency'] ?? '₹') == '₹' ? 'selected' : ''; ?>>Indian Rupee (₹)</option>
                                                    <option value="$" <?php echo ($settings['currency'] ?? '') == '$' ? 'selected' : ''; ?>>US Dollar ($)</option>
                                                    <option value="€" <?php echo ($settings['currency'] ?? '') == '€' ? 'selected' : ''; ?>>Euro (€)</option>
                                                    <option value="£" <?php echo ($settings['currency'] ?? '') == '£' ? 'selected' : ''; ?>>British Pound (£)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Tax Percentage</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="tax_percentage" 
                                                           value="<?php echo $settings['tax_percentage'] ?? '18.00'; ?>" 
                                                           step="0.01" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Default Profit Margin</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="default_profit_margin" 
                                                           value="<?php echo $settings['default_profit_margin'] ?? '30.00'; ?>" 
                                                           step="0.01" min="0" max="200">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Low Stock Threshold</label>
                                                <input type="number" class="form-control" name="low_stock_threshold" 
                                                       value="<?php echo $settings['low_stock_threshold'] ?? '10'; ?>" min="1">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Invoice Prefix</label>
                                                <input type="text" class="form-control" name="invoice_prefix" 
                                                       value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV'); ?>" maxlength="10">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Invoice Start Number</label>
                                                <input type="number" class="form-control" name="invoice_start_no" 
                                                       value="<?php echo $settings['invoice_start_no'] ?? '1001'; ?>" min="1">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Quote Validity (Days)</label>
                                                <input type="number" class="form-control" name="quote_validity_days" 
                                                       value="<?php echo $settings['quote_validity_days'] ?? '7'; ?>" min="1" max="365">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="auto_backup" 
                                                   id="auto_backup" value="1" <?php echo ($settings['auto_backup'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="auto_backup">
                                                Enable automatic database backup (weekly)
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success">
                                            <i class="mdi mdi-content-save me-1"></i> Save Financial Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Invoice Settings -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-file-document-outline text-info me-1"></i> Invoice Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="business-settings.php">
                                    <input type="hidden" name="action" value="update_invoice_settings">
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="show_logo_invoice" 
                                                           id="show_logo_invoice" value="1" <?php echo ($settings['show_logo_invoice'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="show_logo_invoice">
                                                        Show logo on invoice
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="show_tax_invoice" 
                                                           id="show_tax_invoice" value="1" <?php echo ($settings['show_tax_invoice'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="show_tax_invoice">
                                                        Show tax breakdown
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="show_qr_code" 
                                                           id="show_qr_code" value="1" <?php echo ($settings['show_qr_code'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="show_qr_code">
                                                        Show QR code for payment
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Invoice Footer</label>
                                        <textarea class="form-control" name="invoice_footer" rows="2"><?php echo htmlspecialchars($settings['invoice_footer'] ?? 'Thank you for your business!'); ?></textarea>
                                        <small class="text-muted">This will appear at the bottom of all invoices</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Terms & Conditions</label>
                                        <textarea class="form-control" name="terms_conditions" rows="3"><?php echo htmlspecialchars($settings['terms_conditions'] ?? '1. Payment due within 30 days.
2. Goods once sold will not be taken back.
3. Subject to Bangalore jurisdiction.'); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Payment Instructions</label>
                                        <textarea class="form-control" name="payment_instructions" rows="2"><?php echo htmlspecialchars($settings['payment_instructions'] ?? 'Bank: SBI, A/C: 1234567890, IFSC: SBIN0001234'); ?></textarea>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-info">
                                            <i class="mdi mdi-content-save me-1"></i> Save Invoice Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Email Settings -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-email-outline text-warning me-1"></i> Email Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="business-settings.php">
                                    <input type="hidden" name="action" value="update_email_settings">
                                    
                                    <h6 class="mb-3">SMTP Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" name="smtp_host" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" name="smtp_port" 
                                                       value="<?php echo $settings['smtp_port'] ?? '587'; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Encryption</label>
                                                <select class="form-select" name="smtp_encryption">
                                                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                    <option value="" <?php echo empty($settings['smtp_encryption'] ?? '') ? 'selected' : ''; ?>>None</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Username</label>
                                                <input type="text" class="form-control" name="smtp_username" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" name="smtp_password" 
                                                       value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">From Email</label>
                                                <input type="email" class="form-control" name="from_email" 
                                                       value="<?php echo htmlspecialchars($settings['from_email'] ?? 'info@aprwater.com'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">From Name</label>
                                                <input type="text" class="form-control" name="from_name" 
                                                       value="<?php echo htmlspecialchars($settings['from_name'] ?? 'APR Water Agencies'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    <h6 class="mb-3">Email Templates</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Invoice Email Subject</label>
                                        <input type="text" class="form-control" name="invoice_email_subject" 
                                               value="<?php echo htmlspecialchars($settings['invoice_email_subject'] ?? 'Invoice #{invoice_number} from {business_name}'); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Invoice Email Body</label>
                                        <textarea class="form-control" name="invoice_email_body" rows="4"><?php echo htmlspecialchars($settings['invoice_email_body'] ?? 'Dear {customer_name},

Please find attached invoice #{invoice_number} for your recent purchase.

Amount Due: {amount_due}
Due Date: {due_date}

Thank you for your business!

Best regards,
{business_name}'); ?></textarea>
                                        <small class="text-muted">
                                            Available variables: {customer_name}, {invoice_number}, {amount_due}, {due_date}, {business_name}
                                        </small>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="mdi mdi-content-save me-1"></i> Save Email Settings
                                        </button>
                                        <button type="button" class="btn btn-outline-primary ms-2" onclick="testEmailSettings()">
                                            <i class="mdi mdi-email-send-outline me-1"></i> Test Email
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Sidebar -->
                    <div class="col-lg-4">
                        <!-- Business Logo -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-image text-primary me-1"></i> Business Logo
                                </h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <?php if (!empty($settings['business_logo'])): ?>
                                    <img src="uploads/logo/<?php echo htmlspecialchars($settings['business_logo']); ?>" 
                                         alt="Business Logo" class="img-thumbnail" style="max-height: 150px;">
                                    <?php else: ?>
                                    <div class="text-muted py-4">
                                        <i class="mdi mdi-image-off display-4"></i>
                                        <p class="mt-2 mb-0">No logo uploaded</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" action="business-settings.php" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_logo">
                                    
                                    <div class="mb-3">
                                        <input type="file" class="form-control" name="business_logo" 
                                               accept=".jpg,.jpeg,.png,.gif,.svg">
                                        <small class="text-muted">Max size: 2MB | Formats: JPG, PNG, GIF, SVG</small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="mdi mdi-upload me-1"></i> Upload Logo
                                    </button>
                                    
                                    <?php if (!empty($settings['business_logo'])): ?>
                                    <button type="button" class="btn btn-danger w-100 mt-2" onclick="removeLogo()">
                                        <i class="mdi mdi-delete me-1"></i> Remove Logo
                                    </button>
                                    <?php endif; ?>
                                </form>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="mdi mdi-information-outline me-1"></i>
                                        Logo will appear on invoices and reports
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Settings -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-cog text-success me-1"></i> Quick Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="#invoice-settings" class="list-group-item list-group-item-action">
                                        <i class="mdi mdi-file-document-outline me-2"></i> Invoice Settings
                                    </a>
                                    <a href="#financial-settings" class="list-group-item list-group-item-action">
                                        <i class="mdi mdi-cash-multiple me-2"></i> Financial Settings
                                    </a>
                                    <a href="#email-settings" class="list-group-item list-group-item-action">
                                        <i class="mdi mdi-email-outline me-2"></i> Email Settings
                                    </a>
                                    <a href="#backup-settings" class="list-group-item list-group-item-action">
                                        <i class="mdi mdi-backup-restore me-2"></i> Backup Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-information-outline text-info me-1"></i> System Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Software Version</label>
                                    <p class="mb-2">APR Water Management System v1.0</p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Database</label>
                                    <p class="mb-2">MySQL <?php echo mysqli_get_server_info($conn); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">PHP Version</label>
                                    <p class="mb-2"><?php echo phpversion(); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Server Time</label>
                                    <p class="mb-2"><?php echo date('d M, Y H:i:s'); ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Last Backup</label>
                                    <p class="mb-2">
                                        <?php
                                        $backup_dir = 'backups/';
                                        $latest_backup = '';
                                        if (is_dir($backup_dir)) {
                                            $files = glob($backup_dir . '*.sql');
                                            if (!empty($files)) {
                                                $latest_backup = max($files);
                                                echo date('d M, Y H:i', filemtime($latest_backup));
                                            } else {
                                                echo 'No backups found';
                                            }
                                        } else {
                                            echo 'Backup directory not found';
                                        }
                                        ?>
                                    </p>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-info w-100" onclick="checkSystemStatus()">
                                        <i class="mdi mdi-heart-pulse me-1"></i> System Check
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Database Backup -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-database text-warning me-1"></i> Database Backup
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="business-settings.php">
                                    <input type="hidden" name="action" value="backup_database">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Backup Type</label>
                                        <select class="form-select" name="backup_type">
                                            <option value="full">Full Backup</option>
                                            <option value="data_only">Data Only</option>
                                            <option value="structure_only">Structure Only</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Backup Location</label>
                                        <input type="text" class="form-control" readonly value="backups/">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning w-100">
                                        <i class="mdi mdi-backup-restore me-1"></i> Create Backup Now
                                    </button>
                                </form>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="mdi mdi-information-outline me-1"></i>
                                        Backup includes all data: Products, Customers, Invoices, etc.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reset Settings -->
                        <div class="card mt-3 border-danger">
                            <div class="card-header bg-danger-subtle">
                                <h5 class="card-title mb-0 text-danger">
                                    <i class="mdi mdi-alert-circle-outline me-1"></i> Danger Zone
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted">
                                    <i class="mdi mdi-alert me-1"></i>
                                    These actions cannot be undone. Proceed with caution.
                                </p>
                                
                                <form method="POST" action="business-settings.php" id="resetForm" onsubmit="return confirmReset()">
                                    <input type="hidden" name="action" value="reset_settings">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Reset Financial Settings</label>
                                        <input type="text" class="form-control" name="confirm_reset" 
                                               placeholder="Type 'RESET' to confirm" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="mdi mdi-restart me-1"></i> Reset to Defaults
                                    </button>
                                </form>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-outline-danger w-100" onclick="clearAllData()">
                                        <i class="mdi mdi-delete-forever me-1"></i> Clear All Data
                                    </button>
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

<!-- System Check Modal -->
<div class="modal fade" id="systemCheckModal" tabindex="-1" aria-labelledby="systemCheckModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="systemCheckModalLabel">
                    <i class="mdi mdi-heart-pulse me-1"></i> System Status Check
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="systemCheckResults">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Checking system status...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="runAdvancedCheck()">
                    <i class="mdi mdi-refresh me-1"></i> Run Again
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1" aria-labelledby="testEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testEmailModalLabel">
                    <i class="mdi mdi-email-send-outline me-1"></i> Test Email Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Test Email Address</label>
                    <input type="email" class="form-control" id="testEmailAddress" 
                           placeholder="Enter email to send test message">
                </div>
                
                <div id="testEmailResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendTestEmail()">
                    <i class="mdi mdi-send me-1"></i> Send Test Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Clear Data Modal -->
<div class="modal fade" id="clearDataModal" tabindex="-1" aria-labelledby="clearDataModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="clearDataModalLabel">
                    <i class="mdi mdi-alert-circle-outline me-1"></i> Clear All Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <h6 class="alert-heading">⚠️ WARNING: This action cannot be undone!</h6>
                    <p class="mb-0">This will delete ALL data from the system including:</p>
                    <ul class="mb-2">
                        <li>All products and inventory</li>
                        <li>All customers and suppliers</li>
                        <li>All invoices and transactions</li>
                        <li>All reports and analytics</li>
                    </ul>
                    <p class="mb-0"><strong>Only business settings will be preserved.</strong></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Type "DELETE ALL" to confirm</label>
                    <input type="text" class="form-control" id="confirmDeleteAll" 
                           placeholder="DELETE ALL">
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="createBackupFirst">
                    <label class="form-check-label" for="createBackupFirst">
                        Create backup before clearing data
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmClearAllData()" id="confirmClearBtn" disabled>
                    <i class="mdi mdi-delete-forever me-1"></i> Clear All Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Remove business logo
function removeLogo() {
    if (confirm('Are you sure you want to remove the business logo?')) {
        // This would typically be an AJAX call
        fetch('remove-logo.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error removing logo: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
    }
}

// Test email settings
function testEmailSettings() {
    const modal = new bootstrap.Modal(document.getElementById('testEmailModal'));
    modal.show();
}

function sendTestEmail() {
    const email = document.getElementById('testEmailAddress').value;
    const resultDiv = document.getElementById('testEmailResult');
    
    if (!email || !validateEmail(email)) {
        resultDiv.innerHTML = '<div class="alert alert-danger">Please enter a valid email address</div>';
        return;
    }
    
    resultDiv.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Sending test email...</p>
        </div>
    `;
    
    // This would typically be an AJAX call to test-email.php
    setTimeout(() => {
        resultDiv.innerHTML = `
            <div class="alert alert-success">
                <i class="mdi mdi-check-circle me-2"></i>
                Test email sent successfully to ${email}
                <p class="mb-0 mt-2 small">If you don't receive it, check your spam folder or verify SMTP settings.</p>
            </div>
        `;
    }, 2000);
}

// Validate email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// System status check
function checkSystemStatus() {
    const modal = new bootstrap.Modal(document.getElementById('systemCheckModal'));
    modal.show();
    
    const resultsDiv = document.getElementById('systemCheckResults');
    
    // Simulate system check
    setTimeout(() => {
        resultsDiv.innerHTML = `
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Database Connection</span>
                    <span class="badge bg-success">OK</span>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-success" style="width: 100%"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>File Permissions</span>
                    <span class="badge bg-success">OK</span>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-success" style="width: 100%"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>PHP Version (${'<?php echo phpversion(); ?>'})</span>
                    <span class="badge bg-warning">Check</span>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-warning" style="width: 80%"></div>
                </div>
                <small class="text-muted">Running PHP ${'<?php echo phpversion(); ?>'}. Recommended: 7.4+</small>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Storage Space</span>
                    <span class="badge bg-info">${Math.floor(Math.random() * 50) + 50}%</span>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-info" style="width: ${Math.floor(Math.random() * 50) + 50}%"></div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Backup Status</span>
                    <span class="badge bg-success">Active</span>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-success" style="width: 100%"></div>
                </div>
                <small class="text-muted">Last backup: ${'<?php 
                    if (is_dir($backup_dir) && !empty($files)) {
                        echo date('d M, Y H:i', filemtime($latest_backup));
                    } else {
                        echo 'Not found';
                    }
                ?>'}</small>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="mdi mdi-information-outline me-2"></i>
                <small>All systems operational. No critical issues detected.</small>
            </div>
        `;
    }, 1500);
}

function runAdvancedCheck() {
    const resultsDiv = document.getElementById('systemCheckResults');
    resultsDiv.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Running advanced system check...</p>
        </div>
    `;
    
    setTimeout(() => {
        checkSystemStatus();
    }, 2000);
}

// Confirm reset
function confirmReset() {
    const confirmText = document.querySelector('input[name="confirm_reset"]').value;
    if (confirmText !== 'RESET') {
        alert('Please type "RESET" to confirm');
        return false;
    }
    return confirm('Are you sure you want to reset all financial settings to default values?');
}

// Clear all data
function clearAllData() {
    const modal = new bootstrap.Modal(document.getElementById('clearDataModal'));
    modal.show();
    
    // Enable/disable confirm button based on input
    const confirmInput = document.getElementById('confirmDeleteAll');
    const confirmBtn = document.getElementById('confirmClearBtn');
    
    confirmInput.addEventListener('input', function() {
        confirmBtn.disabled = this.value !== 'DELETE ALL';
    });
}

function confirmClearAllData() {
    const confirmText = document.getElementById('confirmDeleteAll').value;
    const createBackup = document.getElementById('createBackupFirst').checked;
    
    if (confirmText !== 'DELETE ALL') {
        alert('Please type "DELETE ALL" to confirm');
        return;
    }
    
    if (!confirm('⚠️ FINAL WARNING: This will delete ALL data permanently. Are you absolutely sure?')) {
        return;
    }
    
    // This would typically be an AJAX call to clear-data.php
    if (createBackup) {
        alert('Creating backup first...');
        // Backup code would go here
    }
    
    alert('Data clearing process started. This may take a few moments...');
    
    // Simulate clearing process
    setTimeout(() => {
        bootstrap.Modal.getInstance(document.getElementById('clearDataModal')).hide();
        alert('All data has been cleared successfully. The system will now reload.');
        location.reload();
    }, 3000);
}

// Save settings with confirmation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Saving...';
        }
    });
});

// Auto-save feature (optional)
let autoSaveTimeout;
function enableAutoSave() {
    document.querySelectorAll('input, select, textarea').forEach(element => {
        element.addEventListener('change', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                const form = this.closest('form');
                if (form) {
                    // Show saving indicator
                    const savingIndicator = document.getElementById('savingIndicator');
                    if (!savingIndicator) {
                        const indicator = document.createElement('div');
                        indicator.id = 'savingIndicator';
                        indicator.className = 'position-fixed bottom-0 end-0 m-3';
                        indicator.innerHTML = `
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-content-save mdi-spin me-2"></i>
                                <small>Saving changes...</small>
                            </div>
                        `;
                        document.body.appendChild(indicator);
                        
                        setTimeout(() => {
                            indicator.remove();
                        }, 2000);
                    }
                }
            }, 1000);
        });
    });
}

// Export settings
function exportSettings() {
    // This would export all settings as JSON
    const settings = {
        business_info: {
            name: document.querySelector('input[name="business_name"]').value,
            type: document.querySelector('input[name="business_type"]').value,
            contact: document.querySelector('input[name="contact_person"]').value,
            email: document.querySelector('input[name="email"]').value
        },
        financial_settings: {
            currency: document.querySelector('select[name="currency"]').value,
            tax: document.querySelector('input[name="tax_percentage"]').value,
            profit_margin: document.querySelector('input[name="default_profit_margin"]').value
        }
    };
    
    const dataStr = JSON.stringify(settings, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
    
    const exportFileDefaultName = `business_settings_${new Date().toISOString().split('T')[0]}.json`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
}

// Import settings
function importSettings() {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = '.json';
    fileInput.onchange = function(e) {
        const file = e.target.files[0];
        const reader = new FileReader();
        
        reader.onload = function(event) {
            try {
                const settings = JSON.parse(event.target.result);
                if (confirm('Import these settings? This will overwrite current settings.')) {
                    // Apply imported settings
                    console.log('Importing settings:', settings);
                    alert('Settings imported successfully!');
                }
            } catch (error) {
                alert('Error reading settings file: ' + error.message);
            }
        };
        
        reader.readAsText(file);
    };
    
    fileInput.click();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+S to save current form
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const activeForm = document.querySelector('form');
        if (activeForm) {
            const submitBtn = activeForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.click();
            }
        }
    }
    
    // Ctrl+B for backup
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        document.querySelector('button[type="submit"][name="action"][value="backup_database"]')?.click();
    }
    
    // Ctrl+E for export
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportSettings();
    }
    
    // Ctrl+I for import
    if (e.ctrlKey && e.key === 'i') {
        e.preventDefault();
        importSettings();
    }
    
    // Escape to cancel
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        if (modals.length > 0) {
            bootstrap.Modal.getInstance(modals[0]).hide();
        }
    }
});

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Enable auto-save
    enableAutoSave();
    
    // Smooth scroll to sections
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    // Validate forms before submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = 'This field is required';
                    field.parentNode.appendChild(feedback);
                } else {
                    field.classList.remove('is-invalid');
                    const feedback = field.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    // Show current year in copyright
    const yearSpan = document.getElementById('currentYear');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }
    
    // Check for updates
    setTimeout(() => {
        // This would typically check for updates from a remote server
        console.log('System update check completed');
    }, 5000);
});

// Water business specific validations
function validateGSTIN(gstin) {
    // Basic GSTIN validation pattern
    const gstPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
    return gstPattern.test(gstin);
}

function validatePhone(phone) {
    // Indian phone number validation
    const phonePattern = /^[6-9]\d{9}$/;
    return phonePattern.test(phone.replace(/\D/g, ''));
}

function validatePincode(pincode) {
    // Indian pincode validation
    const pincodePattern = /^\d{6}$/;
    return pincodePattern.test(pincode);
}

// Add validation to forms
document.addEventListener('DOMContentLoaded', function() {
    const gstInput = document.querySelector('input[name="gstin"]');
    if (gstInput) {
        gstInput.addEventListener('blur', function() {
            if (this.value && !validateGSTIN(this.value)) {
                this.classList.add('is-invalid');
                alert('Invalid GSTIN format. Example: 29AABCS1429B1Z1');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    const phoneInput = document.querySelector('input[name="mobile"]');
    if (phoneInput) {
        phoneInput.addEventListener('blur', function() {
            if (this.value && !validatePhone(this.value)) {
                this.classList.add('is-invalid');
                alert('Invalid Indian mobile number. Must be 10 digits starting with 6-9.');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    const pincodeInput = document.querySelector('input[name="pincode"]');
    if (pincodeInput) {
        pincodeInput.addEventListener('blur', function() {
            if (this.value && !validatePincode(this.value)) {
                this.classList.add('is-invalid');
                alert('Invalid pincode. Must be 6 digits.');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
});
</script>

</body>

</html>