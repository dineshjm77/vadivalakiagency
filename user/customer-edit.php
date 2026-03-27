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
                
                // Check if ID is provided
                if (!isset($_GET['id']) || empty($_GET['id'])) {
                    echo '<div class="alert alert-danger">Customer ID not provided.</div>';
                    echo '<a href="customers-list.php" class="btn btn-primary mt-3">Back to Customers</a>';
                    exit();
                }
                
                $customer_id = mysqli_real_escape_string($conn, $_GET['id']);
                
                // Handle form submission for updating customer
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    // Collect form data with validation
                    $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
                    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
                    $customer_contact = mysqli_real_escape_string($conn, $_POST['customer_contact']);
                    $alternate_contact = mysqli_real_escape_string($conn, $_POST['alternate_contact']);
                    $shop_location = mysqli_real_escape_string($conn, $_POST['shop_location']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $customer_type = mysqli_real_escape_string($conn, $_POST['customer_type']);
                    $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
                    $credit_limit = mysqli_real_escape_string($conn, $_POST['credit_limit']);
                    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Check if contact number already exists (excluding current customer)
                    $check_sql = "SELECT id FROM customers WHERE customer_contact = '$customer_contact' AND id != '$customer_id'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Contact number already exists for another customer!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Update customer in database
                        $sql = "UPDATE customers SET 
                                shop_name = '$shop_name',
                                customer_name = '$customer_name',
                                customer_contact = '$customer_contact',
                                alternate_contact = '$alternate_contact',
                                shop_location = '$shop_location',
                                email = '$email',
                                customer_type = '$customer_type',
                                payment_terms = '$payment_terms',
                                credit_limit = '$credit_limit',
                                notes = '$notes',
                                status = '$status',
                                updated_at = NOW()
                                WHERE id = '$customer_id'";
                        
                        if (mysqli_query($conn, $sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Customer updated successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                
                // Fetch customer details
                $sql = "SELECT * FROM customers WHERE id = '$customer_id'";
                
                $result = mysqli_query($conn, $sql);
                
                if (!$result || mysqli_num_rows($result) == 0) {
                    echo '<div class="alert alert-danger">Customer not found.</div>';
                    echo '<a href="customers-list.php" class="btn btn-primary mt-3">Back to Customers</a>';
                    mysqli_close($conn);
                    exit();
                }
                
                $customer = mysqli_fetch_assoc($result);
                mysqli_close($conn);
                ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">Edit Customer: <?php echo htmlspecialchars($customer['shop_name']); ?></h4>
                                    <div>
                                        <span class="badge bg-primary">Code: <?php echo $customer['customer_code']; ?></span>
                                        <span class="badge <?php echo $customer['status'] == 'active' ? 'bg-success' : ($customer['status'] == 'inactive' ? 'bg-danger' : 'bg-warning'); ?> ms-2">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="customer-edit.php?id=<?php echo $customer_id; ?>" id="customerForm">
                                    <div class="row">
                                        <!-- Customer Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Customer Code</label>
                                                <input type="text" class="form-control" value="<?php echo $customer['customer_code']; ?>" readonly disabled>
                                                <small class="text-muted">Customer code cannot be changed</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Customer Type</label>
                                                <select class="form-select" name="customer_type" id="customerType">
                                                    <option value="retail" <?php echo $customer['customer_type'] == 'retail' ? 'selected' : ''; ?>>Retail Shop</option>
                                                    <option value="wholesale" <?php echo $customer['customer_type'] == 'wholesale' ? 'selected' : ''; ?>>Wholesale Dealer</option>
                                                    <option value="hotel" <?php echo $customer['customer_type'] == 'hotel' ? 'selected' : ''; ?>>Hotel/Restaurant</option>
                                                    <option value="office" <?php echo $customer['customer_type'] == 'office' ? 'selected' : ''; ?>>Office/Corporate</option>
                                                    <option value="residential" <?php echo $customer['customer_type'] == 'residential' ? 'selected' : ''; ?>>Residential</option>
                                                    <option value="other" <?php echo $customer['customer_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Shop Name/Business Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="shop_name" required 
                                                       value="<?php echo htmlspecialchars($customer['shop_name']); ?>" 
                                                       maxlength="150">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="customer_name" required 
                                                       value="<?php echo htmlspecialchars($customer['customer_name']); ?>" 
                                                       maxlength="100">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contact Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Primary Contact Number <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">+91</span>
                                                    <input type="tel" class="form-control" name="customer_contact" required 
                                                           value="<?php echo $customer['customer_contact']; ?>" 
                                                           pattern="[0-9]{10}" maxlength="10" id="primaryContact">
                                                </div>
                                                <small class="text-muted">10-digit mobile number</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Alternate Contact Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">+91</span>
                                                    <input type="tel" class="form-control" name="alternate_contact" 
                                                           value="<?php echo $customer['alternate_contact']; ?>" 
                                                           pattern="[0-9]{10}" maxlength="10" id="alternateContact">
                                                </div>
                                                <small class="text-muted">Optional secondary contact</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?php echo $customer['email']; ?>" 
                                                       maxlength="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status" id="statusSelect">
                                                    <option value="active" <?php echo $customer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $customer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="blocked" <?php echo $customer['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Location Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Shop Location/Address <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="shop_location" required rows="3" 
                                                          maxlength="500"><?php echo htmlspecialchars($customer['shop_location']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Information -->
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Payment Terms</label>
                                                <select class="form-select" name="payment_terms" id="paymentTerms">
                                                    <option value="cash" <?php echo $customer['payment_terms'] == 'cash' ? 'selected' : ''; ?>>Cash on Delivery</option>
                                                    <option value="credit_7" <?php echo $customer['payment_terms'] == 'credit_7' ? 'selected' : ''; ?>>7 Days Credit</option>
                                                    <option value="credit_15" <?php echo $customer['payment_terms'] == 'credit_15' ? 'selected' : ''; ?>>15 Days Credit</option>
                                                    <option value="credit_30" <?php echo $customer['payment_terms'] == 'credit_30' ? 'selected' : ''; ?>>30 Days Credit</option>
                                                    <option value="prepaid" <?php echo $customer['payment_terms'] == 'prepaid' ? 'selected' : ''; ?>>Prepaid</option>
                                                    <option value="weekly" <?php echo $customer['payment_terms'] == 'weekly' ? 'selected' : ''; ?>>Weekly Payment</option>
                                                    <option value="monthly" <?php echo $customer['payment_terms'] == 'monthly' ? 'selected' : ''; ?>>Monthly Payment</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Credit Limit (₹)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="credit_limit" 
                                                           value="<?php echo $customer['credit_limit']; ?>" 
                                                           min="0" step="0.01" id="creditLimit">
                                                </div>
                                                <small class="text-muted">Maximum credit allowed (0 for no credit)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Additional Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Notes/Remarks</label>
                                                <textarea class="form-control" name="notes" rows="3" 
                                                          maxlength="500"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Customer Information Card -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-info">
                                                <div class="card-header bg-info-subtle">
                                                    <h5 class="card-title mb-0 text-info">
                                                        <i class="mdi mdi-information-outline me-1"></i> Customer Information
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Created Date</h6>
                                                                <p class="mb-0"><?php echo date('d M, Y', strtotime($customer['created_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Last Updated</h6>
                                                                <p class="mb-0"><?php echo date('d M, Y', strtotime($customer['updated_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Total Purchases</h6>
                                                                <p class="mb-0 text-success">₹<?php echo number_format($customer['total_purchases'], 2); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Current Balance</h6>
                                                                <p class="mb-0 <?php echo $customer['current_balance'] > 0 ? 'text-danger' : ($customer['current_balance'] < 0 ? 'text-success' : 'text-muted'); ?>">
                                                                    ₹<?php echo number_format(abs($customer['current_balance']), 2); ?>
                                                                    <?php 
                                                                    if ($customer['current_balance'] > 0) echo '(Due)';
                                                                    elseif ($customer['current_balance'] < 0) echo '(Advance)';
                                                                    ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($customer['last_purchase_date'])): ?>
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Last Purchase</h6>
                                                                <p class="mb-0">
                                                                    <?php echo date('d M, Y', strtotime($customer['last_purchase_date'])); ?>
                                                                    <?php 
                                                                    $days_diff = (strtotime(date('Y-m-d')) - strtotime($customer['last_purchase_date'])) / (60 * 60 * 24);
                                                                    if ($days_diff > 30) {
                                                                        echo '<span class="badge bg-warning ms-2">' . $days_diff . ' days ago</span>';
                                                                    }
                                                                    ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Credit Summary Card -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary-subtle">
                                                    <h5 class="card-title mb-0 text-primary">
                                                        <i class="mdi mdi-credit-card-outline me-1"></i> Credit Summary
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Credit Limit</h6>
                                                                <h4 class="mb-0 text-warning" id="creditLimitDisplay">
                                                                    ₹<?php echo number_format($customer['credit_limit'], 2); ?>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Current Balance</h6>
                                                                <h4 class="mb-0 <?php echo $customer['current_balance'] > 0 ? 'text-danger' : ($customer['current_balance'] < 0 ? 'text-success' : 'text-muted'); ?>" id="currentBalanceDisplay">
                                                                    ₹<?php echo number_format($customer['current_balance'], 2); ?>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Credit Available</h6>
                                                                <h4 class="mb-0 <?php echo ($customer['credit_limit'] - $customer['current_balance']) > 0 ? 'text-success' : 'text-danger'; ?>" id="creditAvailableDisplay">
                                                                    ₹<?php echo number_format($customer['credit_limit'] - $customer['current_balance'], 2); ?>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php if ($customer['credit_limit'] > 0): ?>
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <div class="progress" style="height: 10px;">
                                                                <?php
                                                                $credit_usage = $customer['credit_limit'] > 0 ? ($customer['current_balance'] / $customer['credit_limit'] * 100) : 0;
                                                                $credit_usage = min($credit_usage, 100);
                                                                $progress_class = $credit_usage > 90 ? 'bg-danger' : ($credit_usage > 70 ? 'bg-warning' : 'bg-success');
                                                                ?>
                                                                <div class="progress-bar <?php echo $progress_class; ?>" 
                                                                     role="progressbar" 
                                                                     style="width: <?php echo $credit_usage; ?>%" 
                                                                     aria-valuenow="<?php echo $credit_usage; ?>" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-between mt-1">
                                                                <small class="text-muted">Credit Usage: <?php echo number_format($credit_usage, 1); ?>%</small>
                                                                <small class="text-muted">
                                                                    <?php if ($credit_usage > 90): ?>
                                                                    <span class="text-danger"><i class="mdi mdi-alert-circle me-1"></i> Near Limit</span>
                                                                    <?php elseif ($credit_usage > 70): ?>
                                                                    <span class="text-warning"><i class="mdi mdi-alert me-1"></i> High Usage</span>
                                                                    <?php else: ?>
                                                                    <span class="text-success"><i class="mdi mdi-check-circle me-1"></i> Good</span>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-content-save me-1"></i> Update Customer
                                        </button>
                                        <a href="customers-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Customers
                                        </a>
                                        <a href="customer-view.php?id=<?php echo $customer_id; ?>" class="btn btn-info ms-2">
                                            <i class="mdi mdi-eye-outline me-1"></i> View Customer
                                        </a>
                                        <a href="create-invoice.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success ms-2">
                                            <i class="mdi mdi-receipt me-1"></i> Create Invoice
                                        </a>
                                        <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="mdi mdi-delete-outline me-1"></i> Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-rocket-launch text-primary me-1"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <a href="create-invoice.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-success w-100">
                                            <i class="mdi mdi-receipt me-1"></i> New Invoice
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="payment-receive.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-primary w-100">
                                            <i class="mdi mdi-cash-multiple me-1"></i> Receive Payment
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="customer-ledger.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-info w-100">
                                            <i class="mdi mdi-book-open-variant me-1"></i> View Ledger
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="customer-statements.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-warning w-100">
                                            <i class="mdi mdi-file-chart me-1"></i> Statements
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include('includes/footer.php')?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($customer['shop_name']); ?></strong>?</p>
                <p class="text-danger">
                    <i class="mdi mdi-alert-circle-outline me-1"></i>
                    This action cannot be undone. All customer data including invoices and payments will be removed.
                </p>
                <?php if ($customer['current_balance'] != 0): ?>
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert me-2"></i>
                    Warning: This customer has a balance of ₹<?php echo number_format(abs($customer['current_balance']), 2); ?> 
                    <?php echo $customer['current_balance'] > 0 ? 'due' : 'in advance'; ?>.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete-customer.php?id=<?php echo $customer_id; ?>" class="btn btn-danger" onclick="return confirm('Are you absolutely sure? This cannot be undone!')">
                    Delete Customer
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Credit Warning Modal -->
<div class="modal fade" id="creditWarningModal" tabindex="-1" aria-labelledby="creditWarningModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning" id="creditWarningModalLabel">
                    <i class="mdi mdi-alert-circle me-1"></i> Credit Limit Warning
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Reducing the credit limit below the current balance may affect future transactions.</p>
                <div class="alert alert-info">
                    <strong>Current Balance:</strong> ₹<?php echo number_format($customer['current_balance'], 2); ?><br>
                    <strong>New Credit Limit:</strong> ₹<span id="newCreditLimit">0.00</span><br>
                    <strong>Difference:</strong> ₹<span id="creditDifference">0.00</span>
                </div>
                <p>Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmCreditChange">Proceed Anyway</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to calculate credit summary
function calculateCreditSummary() {
    const creditLimit = parseFloat(document.getElementById('creditLimit').value) || 0;
    const currentBalance = parseFloat(<?php echo $customer['current_balance']; ?>) || 0;
    
    // Update displays
    document.getElementById('creditLimitDisplay').textContent = '₹' + creditLimit.toFixed(2);
    document.getElementById('currentBalanceDisplay').textContent = '₹' + currentBalance.toFixed(2);
    
    // Calculate and display available credit
    const availableCredit = creditLimit - currentBalance;
    const availableDisplay = document.getElementById('creditAvailableDisplay');
    availableDisplay.textContent = '₹' + availableCredit.toFixed(2);
    
    // Color coding for available credit
    if (availableCredit <= 0) {
        availableDisplay.className = 'mb-0 text-danger';
    } else if (availableCredit < (creditLimit * 0.3)) {
        availableDisplay.className = 'mb-0 text-warning';
    } else {
        availableDisplay.className = 'mb-0 text-success';
    }
    
    // Color coding for current balance
    const balanceDisplay = document.getElementById('currentBalanceDisplay');
    if (currentBalance > 0) {
        balanceDisplay.className = 'mb-0 text-danger';
    } else if (currentBalance < 0) {
        balanceDisplay.className = 'mb-0 text-success';
    } else {
        balanceDisplay.className = 'mb-0 text-muted';
    }
    
    return { creditLimit, currentBalance, availableCredit };
}

// Function to check credit limit changes
function checkCreditLimitChange() {
    const creditLimitInput = document.getElementById('creditLimit');
    const newCreditLimit = parseFloat(creditLimitInput.value) || 0;
    const currentBalance = parseFloat(<?php echo $customer['current_balance']; ?>) || 0;
    const oldCreditLimit = parseFloat(<?php echo $customer['credit_limit']; ?>) || 0;
    
    // Only show warning if reducing credit limit below current balance
    if (newCreditLimit < currentBalance && newCreditLimit < oldCreditLimit) {
        const difference = currentBalance - newCreditLimit;
        
        document.getElementById('newCreditLimit').textContent = newCreditLimit.toFixed(2);
        document.getElementById('creditDifference').textContent = difference.toFixed(2);
        
        const modal = new bootstrap.Modal(document.getElementById('creditWarningModal'));
        modal.show();
        
        return false; // Prevent form submission
    }
    
    return true;
}

// Function to auto-format phone numbers
function formatPhoneNumber(input) {
    // Remove non-numeric characters
    let value = input.value.replace(/\D/g, '');
    
    // Limit to 10 digits
    if (value.length > 10) {
        value = value.slice(0, 10);
    }
    
    // Update the input value
    input.value = value;
}

// Function to validate form
function validateForm() {
    const shopName = document.querySelector('input[name="shop_name"]').value;
    const customerName = document.querySelector('input[name="customer_name"]').value;
    const customerContact = document.getElementById('primaryContact').value;
    const shopLocation = document.querySelector('textarea[name="shop_location"]').value;
    const email = document.querySelector('input[name="email"]').value;
    const creditLimit = parseFloat(document.getElementById('creditLimit').value) || 0;
    
    // Validate required fields
    if (shopName.trim().length < 2) {
        alert('Shop name must be at least 2 characters long');
        return false;
    }
    
    if (customerName.trim().length < 2) {
        alert('Customer name must be at least 2 characters long');
        return false;
    }
    
    if (!customerContact.match(/^[0-9]{10}$/)) {
        alert('Please enter a valid 10-digit contact number');
        return false;
    }
    
    if (shopLocation.trim().length < 10) {
        alert('Please enter a complete shop location/address (minimum 10 characters)');
        return false;
    }
    
    // Validate email if provided
    if (email && !isValidEmail(email)) {
        alert('Please enter a valid email address or leave it empty');
        return false;
    }
    
    // Validate alternate contact if provided
    const altContact = document.getElementById('alternateContact').value;
    if (altContact && !altContact.match(/^[0-9]{10}$/)) {
        alert('Please enter a valid 10-digit alternate contact number or leave it empty');
        return false;
    }
    
    // Validate credit limit
    if (creditLimit < 0) {
        alert('Credit limit cannot be negative');
        return false;
    }
    
    // Check credit limit change
    if (!checkCreditLimitChange()) {
        return false;
    }
    
    return true;
}

// Email validation function
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize credit summary
    calculateCreditSummary();
    
    // Add event listeners for real-time updates
    const creditLimitInput = document.getElementById('creditLimit');
    if (creditLimitInput) {
        creditLimitInput.addEventListener('input', calculateCreditSummary);
    }
    
    // Add phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhoneNumber(this);
        });
        
        // Format on page load
        formatPhoneNumber(input);
    });
    
    // Form validation
    const form = document.getElementById('customerForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
            
            // Show confirmation for significant changes
            const statusSelect = document.getElementById('statusSelect');
            const oldStatus = '<?php echo $customer['status']; ?>';
            const newStatus = statusSelect.value;
            
            if (oldStatus !== newStatus) {
                const message = `Change customer status from "${oldStatus}" to "${newStatus}"?`;
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    }
    
    // Confirm credit change
    document.getElementById('confirmCreditChange').addEventListener('click', function() {
        // Close the modal
        bootstrap.Modal.getInstance(document.getElementById('creditWarningModal')).hide();
        
        // Submit the form
        document.getElementById('customerForm').submit();
    });
    
    // Auto-check duplicate contact
    const primaryContactInput = document.getElementById('primaryContact');
    if (primaryContactInput) {
        primaryContactInput.addEventListener('blur', function() {
            if (this.value.length === 10) {
                // In a real implementation, this would be an AJAX call
                console.log('Checking duplicate contact:', this.value);
            }
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('customerForm').submit();
        }
        // Ctrl+B to go back
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            window.location.href = 'customers-list.php';
        }
        // Ctrl+V to view
        if (e.ctrlKey && e.key === 'v') {
            e.preventDefault();
            window.location.href = 'customer-view.php?id=<?php echo $customer_id; ?>';
        }
        // Ctrl+I for invoice
        if (e.ctrlKey && e.key === 'i') {
            e.preventDefault();
            window.location.href = 'create-invoice.php?customer_id=<?php echo $customer_id; ?>';
        }
        // Escape to cancel
        if (e.key === 'Escape') {
            if (confirm('Discard changes?')) {
                window.location.href = 'customer-view.php?id=<?php echo $customer_id; ?>';
            }
        }
    });
    
    // Show warning if customer has due amount
    const currentBalance = parseFloat(<?php echo $customer['current_balance']; ?>) || 0;
    if (currentBalance > 0) {
        const dueAmount = currentBalance;
        const dueWarning = document.createElement('div');
        dueWarning.className = 'alert alert-warning alert-dismissible fade show mt-3';
        dueWarning.innerHTML = `
            <i class="mdi mdi-alert-circle-outline me-2"></i>
            This customer has ₹${dueAmount.toFixed(2)} due. 
            Consider receiving payment before editing credit terms.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.querySelector('.card-body').insertBefore(dueWarning, document.querySelector('form'));
    }
    
    // Auto-save draft (optional feature)
    let saveTimeout;
    const formInputs = form.querySelectorAll('input, textarea, select');
    formInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                // In a real implementation, save draft via AJAX
                console.log('Auto-saving draft...');
            }, 2000);
        });
    });
});

// Function to reset form to original values
function resetForm() {
    if (confirm('Reset all changes to original values?')) {
        // Reload the page to get original values
        window.location.reload();
    }
}

// Function to copy customer information
function copyCustomerInfo() {
    const shopName = document.querySelector('input[name="shop_name"]').value;
    const customerName = document.querySelector('input[name="customer_name"]').value;
    const contact = document.getElementById('primaryContact').value;
    const address = document.querySelector('textarea[name="shop_location"]').value;
    
    const customerInfo = `
Shop Name: ${shopName}
Customer Name: ${customerName}
Contact: ${contact}
Address: ${address}
Customer Code: <?php echo $customer['customer_code']; ?>
    `.trim();
    
    navigator.clipboard.writeText(customerInfo).then(() => {
        alert('Customer information copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy information. Please try again.');
    });
}
</script>

</body>

</html>