<?php
include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: active-customers.php');
    exit;
}

$customer_id = intval($_GET['id']);

// Verify customer belongs to this lineman
$check_sql = "SELECT * FROM customers WHERE id = $customer_id AND assigned_lineman_id = $lineman_id";
$check_result = mysqli_query($conn, $check_sql);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    header('Location: active-customers.php?error=Customer not found or unauthorized');
    exit;
}

$customer = mysqli_fetch_assoc($check_result);

// Fetch assigned areas for dropdown
$areas_sql = "SELECT DISTINCT assigned_area FROM customers 
              WHERE assigned_lineman_id = $lineman_id 
              AND assigned_area IS NOT NULL 
              AND assigned_area != '' 
              AND status = 'active' 
              ORDER BY assigned_area";
$areas_result = mysqli_query($conn, $areas_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_customer'])) {
    $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $customer_contact = mysqli_real_escape_string($conn, $_POST['customer_contact']);
    $alternate_contact = mysqli_real_escape_string($conn, $_POST['alternate_contact']);
    $shop_location = mysqli_real_escape_string($conn, $_POST['shop_location']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $customer_type = mysqli_real_escape_string($conn, $_POST['customer_type']);
    $assigned_area = mysqli_real_escape_string($conn, $_POST['assigned_area']);
    $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
    $credit_limit = floatval($_POST['credit_limit']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Validate required fields
    if (empty($shop_name) || empty($customer_name) || empty($customer_contact)) {
        $error_message = "Shop name, customer name, and contact are required fields.";
    } else {
        // Check if phone number already exists for another customer
        $phone_check_sql = "SELECT id FROM customers 
                           WHERE customer_contact = '$customer_contact' 
                           AND id != $customer_id 
                           AND assigned_lineman_id = $lineman_id";
        $phone_check_result = mysqli_query($conn, $phone_check_sql);
        
        if ($phone_check_result && mysqli_num_rows($phone_check_result) > 0) {
            $error_message = "This phone number is already registered for another customer.";
        } else {
            // Update customer
            $update_sql = "UPDATE customers SET 
                          shop_name = '$shop_name',
                          customer_name = '$customer_name',
                          customer_contact = '$customer_contact',
                          alternate_contact = '$alternate_contact',
                          shop_location = '$shop_location',
                          email = '$email',
                          customer_type = '$customer_type',
                          assigned_area = '$assigned_area',
                          payment_terms = '$payment_terms',
                          credit_limit = $credit_limit,
                          notes = '$notes',
                          updated_at = NOW()
                          WHERE id = $customer_id 
                          AND assigned_lineman_id = $lineman_id";
            
            if (mysqli_query($conn, $update_sql)) {
                $success_message = "Customer updated successfully!";
                
                // Update session message for redirect
                $_SESSION['success_message'] = "Customer updated successfully!";
                
                // Redirect to customer details page
                header("Location: customer-details.php?id=$customer_id&success=1");
                exit;
            } else {
                $error_message = "Failed to update customer: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>

<body data-sidebar="dark">

    <!-- Loader -->
    <?php include('includes/pre-loader.php') ?>

    <!-- Begin page -->
    <div id="layout-wrapper">

        <?php include('includes/topbar.php') ?>

        <!-- ========== Left Sidebar Start ========== -->
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <!--- Sidemenu -->
                <?php 
                $current_page = 'active-customers';
                include('includes/sidebar.php');
                ?>
            </div>
        </div>
        <!-- Left Sidebar End -->

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title mb-4">
                                        <i class="mdi mdi-account-edit me-1"></i>
                                        Edit Customer Details
                                    </h4>
                                    <p class="card-title-desc">
                                        Update customer information. Fields marked with <span class="text-danger">*</span> are required.
                                    </p>
                                    
                                    <form method="POST" id="editCustomerForm">
                                        <div class="row">
                                            <!-- Basic Information -->
                                            <div class="col-lg-6">
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-information-outline me-2"></i> Basic Information
                                                        </h5>
                                                        
                                                        <!-- Shop Name -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Shop/Business Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" 
                                                                   name="shop_name" 
                                                                   value="<?php echo htmlspecialchars($customer['shop_name']); ?>" 
                                                                   required>
                                                        </div>
                                                        
                                                        <!-- Customer Name -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" 
                                                                   name="customer_name" 
                                                                   value="<?php echo htmlspecialchars($customer['customer_name']); ?>" 
                                                                   required>
                                                        </div>
                                                        
                                                        <!-- Contact Information -->
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Primary Contact <span class="text-danger">*</span></label>
                                                                    <input type="tel" class="form-control" 
                                                                           name="customer_contact" 
                                                                           value="<?php echo htmlspecialchars($customer['customer_contact']); ?>" 
                                                                           required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Alternate Contact</label>
                                                                    <input type="tel" class="form-control" 
                                                                           name="alternate_contact" 
                                                                           value="<?php echo htmlspecialchars($customer['alternate_contact']); ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Email -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Email Address</label>
                                                            <input type="email" class="form-control" 
                                                                   name="email" 
                                                                   value="<?php echo htmlspecialchars($customer['email']); ?>">
                                                        </div>
                                                        
                                                        <!-- Shop Location -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Shop Location/Address <span class="text-danger">*</span></label>
                                                            <textarea class="form-control" 
                                                                      name="shop_location" 
                                                                      rows="3" 
                                                                      required><?php echo htmlspecialchars($customer['shop_location']); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Business Details -->
                                            <div class="col-lg-6">
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-briefcase-outline me-2"></i> Business Details
                                                        </h5>
                                                        
                                                        <!-- Customer Type -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Customer Type</label>
                                                            <select class="form-select" name="customer_type">
                                                                <option value="retail" <?php echo $customer['customer_type'] == 'retail' ? 'selected' : ''; ?>>Retail</option>
                                                                <option value="wholesale" <?php echo $customer['customer_type'] == 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                                                <option value="hotel" <?php echo $customer['customer_type'] == 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                                                <option value="office" <?php echo $customer['customer_type'] == 'office' ? 'selected' : ''; ?>>Office</option>
                                                                <option value="residential" <?php echo $customer['customer_type'] == 'residential' ? 'selected' : ''; ?>>Residential</option>
                                                                <option value="other" <?php echo $customer['customer_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <!-- Assigned Area -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Assigned Area</label>
                                                            <select class="form-select" name="assigned_area">
                                                                <option value="">-- Select Area --</option>
                                                                <?php while ($area = mysqli_fetch_assoc($areas_result)): ?>
                                                                <option value="<?php echo htmlspecialchars($area['assigned_area']); ?>"
                                                                        <?php echo $customer['assigned_area'] == $area['assigned_area'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($area['assigned_area']); ?>
                                                                </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                            <div class="form-text">
                                                                Select from existing areas or leave empty
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Payment Terms -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment Terms</label>
                                                            <select class="form-select" name="payment_terms" id="payment_terms">
                                                                <option value="cash" <?php echo $customer['payment_terms'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                                <option value="credit_7" <?php echo $customer['payment_terms'] == 'credit_7' ? 'selected' : ''; ?>>Credit - 7 Days</option>
                                                                <option value="credit_15" <?php echo $customer['payment_terms'] == 'credit_15' ? 'selected' : ''; ?>>Credit - 15 Days</option>
                                                                <option value="credit_30" <?php echo $customer['payment_terms'] == 'credit_30' ? 'selected' : ''; ?>>Credit - 30 Days</option>
                                                                <option value="prepaid" <?php echo $customer['payment_terms'] == 'prepaid' ? 'selected' : ''; ?>>Prepaid</option>
                                                                <option value="weekly" <?php echo $customer['payment_terms'] == 'weekly' ? 'selected' : ''; ?>>Weekly Payment</option>
                                                                <option value="monthly" <?php echo $customer['payment_terms'] == 'monthly' ? 'selected' : ''; ?>>Monthly Payment</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <!-- Credit Limit -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Credit Limit (₹)</label>
                                                            <input type="number" class="form-control" 
                                                                   name="credit_limit" 
                                                                   value="<?php echo number_format($customer['credit_limit'], 2, '.', ''); ?>" 
                                                                   step="0.01" 
                                                                   min="0">
                                                            <div class="form-text">
                                                                Set credit limit for credit customers (0 for cash customers)
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Current Balance (Read-only) -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Balance (₹)</label>
                                                            <input type="text" class="form-control" 
                                                                   value="₹<?php echo number_format($customer['current_balance'], 2); ?>" 
                                                                   readonly>
                                                            <div class="form-text">
                                                                This field is automatically calculated from transactions
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Customer Code (Read-only) -->
                                                        <div class="mb-3">
                                                            <label class="form-label">Customer Code</label>
                                                            <input type="text" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($customer['customer_code']); ?>" 
                                                                   readonly>
                                                            <div class="form-text">
                                                                Auto-generated customer identifier
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Additional Notes -->
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-note-text-outline me-2"></i> Additional Notes
                                                        </h5>
                                                        <div class="mb-3">
                                                            <textarea class="form-control" 
                                                                      name="notes" 
                                                                      rows="4" 
                                                                      placeholder="Add any additional notes or special instructions..."><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Statistics Card -->
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-chart-bar me-2"></i> Customer Statistics
                                                        </h5>
                                                        <div class="row text-center">
                                                            <div class="col-md-3">
                                                                <div class="metric-card primary">
                                                                    <div class="small text-muted">Total Purchases</div>
                                                                    <div class="h4 mb-0">₹<?php echo number_format($customer['total_purchases'], 2); ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="metric-card <?php echo $customer['current_balance'] > 0 ? 'danger' : 'success'; ?>">
                                                                    <div class="small text-muted">Current Balance</div>
                                                                    <div class="h4 mb-0">₹<?php echo number_format($customer['current_balance'], 2); ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="metric-card info">
                                                                    <div class="small text-muted">Last Purchase</div>
                                                                    <div class="h4 mb-0">
                                                                        <?php echo $customer['last_purchase_date'] ? date('d M, Y', strtotime($customer['last_purchase_date'])) : 'Never'; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="metric-card <?php echo $customer['status'] == 'active' ? 'success' : ($customer['status'] == 'inactive' ? 'warning' : 'danger'); ?>">
                                                                    <div class="small text-muted">Status</div>
                                                                    <div class="h4 mb-0"><?php echo ucfirst($customer['status']); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button type="submit" name="update_customer" class="btn btn-primary">
                                                                <i class="mdi mdi-content-save me-1"></i> Update Customer
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                                                                <i class="mdi mdi-arrow-left me-1"></i> Back
                                                            </button>
                                                            <a href="customer-details.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-info">
                                                                <i class="mdi mdi-eye me-1"></i> View Details
                                                            </a>
                                                            <a href="quick-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-success">
                                                                <i class="mdi mdi-cart-plus me-1"></i> Create Order
                                                            </a>
                                                            <a href="collect-payment.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-warning">
                                                                <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                                                                <i class="mdi mdi-account-convert me-1"></i> Change Status
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Change Status Modal -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="update-customer-status.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="changeStatusModalLabel">Change Customer Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                        
                        <div class="mb-3">
                            <p class="mb-2">Customer: <strong><?php echo htmlspecialchars($customer['shop_name']); ?></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">New Status</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <option value="active" <?php echo $customer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $customer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="blocked" <?php echo $customer['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status_notes" class="form-label">Reason for Status Change</label>
                            <textarea class="form-control" id="status_notes" name="status_notes" rows="3" 
                                      placeholder="Enter reason for status change..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <style>
        .metric-card {
            border-left: 4px solid;
            padding-left: 15px;
            margin-bottom: 10px;
        }
        
        .metric-card.primary { border-left-color: #556ee6; }
        .metric-card.success { border-left-color: #28a745; }
        .metric-card.warning { border-left-color: #ffc107; }
        .metric-card.danger { border-left-color: #dc3545; }
        .metric-card.info { border-left-color: #17a2b8; }
        
        .metric-card .h4 {
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .metric-card .small {
            font-size: 12px;
            color: #6c757d;
        }
        
        .form-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
        }
    </style>

    <script>
        // Phone number validation
        document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
            const phoneInput = document.querySelector('input[name="customer_contact"]');
            const phoneValue = phoneInput.value.trim();
            
            // Basic phone validation (10 digits)
            const phoneRegex = /^[0-9]{10}$/;
            if (!phoneRegex.test(phoneValue)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number.');
                phoneInput.focus();
                return false;
            }
            
            // Email validation if provided
            const emailInput = document.querySelector('input[name="email"]');
            const emailValue = emailInput.value.trim();
            
            if (emailValue && !isValidEmail(emailValue)) {
                e.preventDefault();
                alert('Please enter a valid email address or leave it empty.');
                emailInput.focus();
                return false;
            }
            
            return true;
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Auto-capitalize names
        document.querySelectorAll('input[name="shop_name"], input[name="customer_name"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
        
        // Format phone numbers
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 10) {
                    value = value.substr(0, 10);
                }
                e.target.value = value;
            });
        });
        
        // Show/hide credit limit based on payment terms
        const paymentTermsSelect = document.getElementById('payment_terms');
        const creditLimitInput = document.querySelector('input[name="credit_limit"]');
        
        function updateCreditLimitField() {
            const selectedTerm = paymentTermsSelect.value;
            const isCredit = selectedTerm.includes('credit') || selectedTerm === 'weekly' || selectedTerm === 'monthly';
            
            if (isCredit && parseFloat(creditLimitInput.value) === 0) {
                creditLimitInput.value = '1000.00';
            } else if (!isCredit) {
                creditLimitInput.value = '0.00';
            }
        }
        
        paymentTermsSelect.addEventListener('change', updateCreditLimitField);
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCreditLimitField();
        });
        
        // Prevent form submission on enter key in textarea
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.stopPropagation();
                }
            });
        });
        
        // Print customer details
        function printCustomerDetails() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Customer Details - <?php echo htmlspecialchars($customer['shop_name']); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
                        .info-section { margin-bottom: 30px; }
                        .info-section h3 { border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 15px; }
                        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
                        .info-item { margin-bottom: 10px; }
                        .info-label { font-weight: bold; color: #666; }
                        .info-value { color: #333; }
                        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                        .stat-box { text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
                        .stat-value { font-size: 24px; font-weight: bold; margin: 5px 0; }
                        .stat-label { font-size: 12px; color: #666; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Customer Details</h1>
                    
                    <div class="info-section">
                        <h3>Basic Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Shop Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($customer['shop_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer Name:</div>
                                <div class="info-value"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer Code:</div>
                                <div class="info-value"><?php echo $customer['customer_code']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact:</div>
                                <div class="info-value"><?php echo $customer['customer_contact']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Alternate Contact:</div>
                                <div class="info-value"><?php echo $customer['alternate_contact'] ?: 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email:</div>
                                <div class="info-value"><?php echo $customer['email'] ?: 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Location:</div>
                                <div class="info-value"><?php echo htmlspecialchars($customer['shop_location']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Business Details</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Customer Type:</div>
                                <div class="info-value"><?php echo ucfirst($customer['customer_type']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Assigned Area:</div>
                                <div class="info-value"><?php echo $customer['assigned_area'] ?: 'Not assigned'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Terms:</div>
                                <div class="info-value">
                                    <?php 
                                    $term_display = [
                                        'cash' => 'Cash',
                                        'credit_7' => 'Credit 7 Days',
                                        'credit_15' => 'Credit 15 Days',
                                        'credit_30' => 'Credit 30 Days',
                                        'prepaid' => 'Prepaid',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly'
                                    ];
                                    echo $term_display[$customer['payment_terms']] ?? ucfirst($customer['payment_terms']);
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Credit Limit:</div>
                                <div class="info-value">₹<?php echo number_format($customer['credit_limit'], 2); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status:</div>
                                <div class="info-value"><?php echo ucfirst($customer['status']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Total Purchases</div>
                            <div class="stat-value">₹<?php echo number_format($customer['total_purchases'], 2); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Current Balance</div>
                            <div class="stat-value">₹<?php echo number_format($customer['current_balance'], 2); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Last Purchase</div>
                            <div class="stat-value">
                                <?php echo $customer['last_purchase_date'] ? date('d M, Y', strtotime($customer['last_purchase_date'])) : 'Never'; ?>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Customer Since</div>
                            <div class="stat-value">
                                <?php echo date('d M, Y', strtotime($customer['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($customer['notes'])): ?>
                    <div class="info-section">
                        <h3>Additional Notes</h3>
                        <div class="info-item">
                            <div class="info-value"><?php echo htmlspecialchars($customer['notes']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                        <p>Generated by: <?php echo $_SESSION['name']; ?></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Add print button to action buttons
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelector('.d-flex.flex-wrap');
            if (actionButtons) {
                const printButton = document.createElement('button');
                printButton.type = 'button';
                printButton.className = 'btn btn-outline-info';
                printButton.innerHTML = '<i class="mdi mdi-printer me-1"></i> Print Details';
                printButton.onclick = printCustomerDetails;
                actionButtons.appendChild(printButton);
            }
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}