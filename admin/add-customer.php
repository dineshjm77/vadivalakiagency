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
                // Database connection and form processing
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    include('config/config.php');
                    
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
                    
                    // Generate customer code
                    $customer_code = 'CUST' . date('ym') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                    
                    // Check if customer contact already exists
                    $check_sql = "SELECT id FROM customers WHERE customer_contact = '$customer_contact'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Customer with this contact number already exists!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Insert into database
                        $sql = "INSERT INTO customers (
                            customer_code, shop_name, customer_name, customer_contact, 
                            alternate_contact, shop_location, email, customer_type,
                            payment_terms, credit_limit, notes, status, created_at
                        ) VALUES (
                            '$customer_code', '$shop_name', '$customer_name', '$customer_contact',
                            '$alternate_contact', '$shop_location', '$email', '$customer_type',
                            '$payment_terms', '$credit_limit', '$notes', '$status', NOW()
                        )";
                        
                        if (mysqli_query($conn, $sql)) {
                            $customer_id = mysqli_insert_id($conn);
                            
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Customer added successfully! Customer Code: ' . $customer_code . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                            
                            // Clear form after successful submission
                            echo '<script>
                                setTimeout(function() {
                                    document.querySelector("form").reset();
                                }, 100);
                            </script>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                    
                    mysqli_close($conn);
                }
                ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Customer Information</h4>

                            </div>
                            <div class="card-body">
                                <form method="POST" action="add-customer.php" id="customerForm">
                                    <div class="row">
                                        <!-- Shop Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Shop Name/Business Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="shop_name" required 
                                                       placeholder="e.g., Sri Balaji Agencies, Lakshmi Stores" maxlength="150">
                                                <small class="text-muted">Official business/shop name</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Customer Type</label>
                                                <select class="form-select" name="customer_type">
                                                    <option value="retail">Retail Shop</option>
                                                    <option value="wholesale">Wholesale Dealer</option>
                                                    <option value="hotel">Hotel/Restaurant</option>
                                                    <option value="office">Office/Corporate</option>
                                                    <option value="residential">Residential</option>
                                                    <option value="other">Other</option>
                                                </select>
                                                <small class="text-muted">Type of customer business</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="customer_name" required 
                                                       placeholder="Owner/Manager name" maxlength="100">
                                                <small class="text-muted">Primary contact person</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       placeholder="customer@example.com" maxlength="100">
                                                <small class="text-muted">For invoices and communication</small>
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
                                                           placeholder="9876543210" pattern="[0-9]{10}" maxlength="10">
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
                                                           placeholder="9876543210" pattern="[0-9]{10}" maxlength="10">
                                                </div>
                                                <small class="text-muted">Optional secondary contact</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Location Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Shop Location/Address <span class="text-danger">*</span></label>
                                                <textarea class="form-control" name="shop_location" required rows="3" 
                                                          placeholder="Full shop address with landmark" maxlength="500"></textarea>
                                                <small class="text-muted">Complete address for delivery</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Information -->
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Payment Terms</label>
                                                <select class="form-select" name="payment_terms">
                                                    <option value="cash">Cash on Delivery</option>
                                                    <option value="credit_7">7 Days Credit</option>
                                                    <option value="credit_15">15 Days Credit</option>
                                                    <option value="credit_30">30 Days Credit</option>
                                                    <option value="prepaid">Prepaid</option>
                                                    <option value="weekly">Weekly Payment</option>
                                                    <option value="monthly">Monthly Payment</option>
                                                </select>
                                                <small class="text-muted">Payment terms for this customer</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Credit Limit (₹)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="credit_limit" 
                                                           placeholder="0.00" min="0" step="0.01" value="0">
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
                                                          placeholder="Additional information, special instructions, etc." 
                                                          maxlength="500"></textarea>
                                                <small class="text-muted">Any special notes about this customer</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="active" selected>Active</option>
                                                    <option value="inactive">Inactive</option>
                                                    <option value="blocked">Blocked</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Customer Summary Card -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary-subtle">
                                                    <h5 class="card-title mb-0 text-primary">
                                                        <i class="mdi mdi-account-check me-1"></i> Customer Summary
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Customer Type</h6>
                                                                <h4 class="mb-0 text-info" id="customerTypeDisplay">Retail Shop</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Payment Terms</h6>
                                                                <h4 class="mb-0 text-success" id="paymentTermsDisplay">Cash on Delivery</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Credit Limit</h6>
                                                                <h4 class="mb-0 text-warning" id="creditLimitDisplay">₹0.00</h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-account-plus me-1"></i> Add Customer
                                        </button>
                                        <button type="reset" class="btn btn-secondary ms-2">
                                            <i class="mdi mdi-refresh me-1"></i> Reset
                                        </button>
                                        <a href="customers-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Customers
                                        </a>
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

        <?php include('includes/footer.php')?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to update customer summary
function updateSummary() {
    // Update customer type display
    const customerType = document.querySelector('select[name="customer_type"]').value;
    const typeDisplay = document.getElementById('customerTypeDisplay');
    const typeText = document.querySelector('select[name="customer_type"] option:checked').text;
    typeDisplay.textContent = typeText;
    
    // Update payment terms display
    const paymentTerms = document.querySelector('select[name="payment_terms"]').value;
    const termsDisplay = document.getElementById('paymentTermsDisplay');
    const termsText = document.querySelector('select[name="payment_terms"] option:checked').text;
    termsDisplay.textContent = termsText;
    
    // Update credit limit display
    const creditLimit = parseFloat(document.querySelector('input[name="credit_limit"]').value) || 0;
    const limitDisplay = document.getElementById('creditLimitDisplay');
    limitDisplay.textContent = '₹' + creditLimit.toFixed(2);
    
    // Color coding for credit limit
    if (creditLimit == 0) {
        limitDisplay.className = 'mb-0 text-secondary';
    } else if (creditLimit <= 5000) {
        limitDisplay.className = 'mb-0 text-success';
    } else if (creditLimit <= 20000) {
        limitDisplay.className = 'mb-0 text-warning';
    } else {
        limitDisplay.className = 'mb-0 text-danger';
    }
}

// Add event listeners for real-time updates
document.addEventListener('DOMContentLoaded', function() {
    // Listen for changes in form fields
    const formFields = [
        'customer_type',
        'payment_terms',
        'credit_limit'
    ];
    
    formFields.forEach(fieldName => {
        const field = document.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.addEventListener('change', updateSummary);
            field.addEventListener('input', updateSummary);
        }
    });
    
    // Form validation
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        const shopName = document.querySelector('input[name="shop_name"]').value;
        const customerName = document.querySelector('input[name="customer_name"]').value;
        const customerContact = document.querySelector('input[name="customer_contact"]').value;
        const shopLocation = document.querySelector('textarea[name="shop_location"]').value;
        const email = document.querySelector('input[name="email"]').value;
        
        // Validate required fields
        if (shopName.trim().length < 2) {
            alert('Shop name must be at least 2 characters long');
            e.preventDefault();
            return false;
        }
        
        if (customerName.trim().length < 2) {
            alert('Customer name must be at least 2 characters long');
            e.preventDefault();
            return false;
        }
        
        if (!customerContact.match(/^[0-9]{10}$/)) {
            alert('Please enter a valid 10-digit contact number');
            e.preventDefault();
            return false;
        }
        
        if (shopLocation.trim().length < 10) {
            alert('Please enter a complete shop location/address (minimum 10 characters)');
            e.preventDefault();
            return false;
        }
        
        // Validate email if provided
        if (email && !isValidEmail(email)) {
            alert('Please enter a valid email address or leave it empty');
            e.preventDefault();
            return false;
        }
        
        // Validate alternate contact if provided
        const altContact = document.querySelector('input[name="alternate_contact"]').value;
        if (altContact && !altContact.match(/^[0-9]{10}$/)) {
            alert('Please enter a valid 10-digit alternate contact number or leave it empty');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Quick fill examples
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('quick-fill-example')) {
            e.preventDefault();
            const exampleType = e.target.getAttribute('data-example');
            
            if (exampleType === 'retail') {
                document.querySelector('input[name="shop_name"]').value = 'Sri Balaji Agencies';
                document.querySelector('input[name="customer_name"]').value = 'Ravi Kumar';
                document.querySelector('input[name="customer_contact"]').value = '9876543210';
                document.querySelector('input[name="alternate_contact"]').value = '8765432109';
                document.querySelector('textarea[name="shop_location"]').value = 'Main Road, Near Temple, Chennai - 600001';
                document.querySelector('input[name="email"]').value = 'ravi@sribalaji.com';
                document.querySelector('select[name="customer_type"]').value = 'retail';
                document.querySelector('select[name="payment_terms"]').value = 'credit_7';
                document.querySelector('input[name="credit_limit"]').value = '5000';
            } else if (exampleType === 'hotel') {
                document.querySelector('input[name="shop_name"]').value = 'Grand Hotel';
                document.querySelector('input[name="customer_name"]').value = 'Manager Singh';
                document.querySelector('input[name="customer_contact"]').value = '7654321098';
                document.querySelector('input[name="alternate_contact"]').value = '';
                document.querySelector('textarea[name="shop_location"]').value = '5 Star Avenue, Hotel Zone, Chennai - 600002';
                document.querySelector('input[name="email"]').value = 'purchase@grandhotel.com';
                document.querySelector('select[name="customer_type"]').value = 'hotel';
                document.querySelector('select[name="payment_terms"]').value = 'credit_15';
                document.querySelector('input[name="credit_limit"]').value = '20000';
            } else if (exampleType === 'residential') {
                document.querySelector('input[name="shop_name"]').value = 'Home Delivery';
                document.querySelector('input[name="customer_name"]').value = 'Mr. Sharma';
                document.querySelector('input[name="customer_contact"]').value = '6543210987';
                document.querySelector('input[name="alternate_contact"]').value = '6432109876';
                document.querySelector('textarea[name="shop_location"]').value = 'Flat No. 203, Sunshine Apartments, Anna Nagar, Chennai - 600040';
                document.querySelector('input[name="email"]').value = 'sharma.family@example.com';
                document.querySelector('select[name="customer_type"]').value = 'residential';
                document.querySelector('select[name="payment_terms"]').value = 'cash';
                document.querySelector('input[name="credit_limit"]').value = '0';
            }
            
            updateSummary();
        }
    });
    
    // Initialize summary
    updateSummary();
    
    // Auto-format phone numbers
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
    });
});

// Email validation function
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

// Quick check for duplicate contact
function checkDuplicateContact() {
    const contact = document.querySelector('input[name="customer_contact"]').value;
    
    if (contact.length === 10) {
        // In a real implementation, this would be an AJAX call
        // For now, we'll just show a placeholder
        console.log('Checking duplicate for:', contact);
    }
}
</script>

</body>

</html>