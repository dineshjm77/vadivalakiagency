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
                    // Include config from config folder
                    include('config/config.php');
                    // Collect form data with validation
                    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                    $address = mysqli_real_escape_string($conn, $_POST['address']);
                    $city = mysqli_real_escape_string($conn, $_POST['city']);
                    $state = mysqli_real_escape_string($conn, $_POST['state']);
                    $pincode = mysqli_real_escape_string($conn, $_POST['pincode']);
                    $assigned_area = mysqli_real_escape_string($conn, $_POST['assigned_area']);
                    $salary = mysqli_real_escape_string($conn, $_POST['salary']);
                    $commission = mysqli_real_escape_string($conn, $_POST['commission']);
                    $username = mysqli_real_escape_string($conn, $_POST['username']);
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Generate employee ID
                    $employee_id = 'LM' . date('ym') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                    
                    // Check if username already exists
                    $check_sql = "SELECT id FROM linemen WHERE username = '$username'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-block-helper me-2"></i>
                                Username already exists. Please choose a different username.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Insert into database
                        $sql = "INSERT INTO linemen (employee_id, full_name, email, phone, address, city, state, pincode, assigned_area, salary, commission, username, password, status, created_at) 
                                VALUES ('$employee_id', '$full_name', '$email', '$phone', '$address', '$city', '$state', '$pincode', '$assigned_area', '$salary', '$commission', '$username', '$password', '$status', NOW())";
                        
                        if (mysqli_query($conn, $sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Line Man added successfully! Employee ID: ' . $employee_id . '
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
                                <h4 class="card-title">Line Man Information</h4>

                            </div>
                            <div class="card-body">
                                <form method="POST" action="add-lineman.php" id="linemanForm">
                                    <div class="row">
                                        <!-- Personal Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="full_name" required 
                                                       placeholder="Enter full name" maxlength="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       placeholder="Enter email address" maxlength="100">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" name="phone" required 
                                                       placeholder="Enter phone number" pattern="[0-9]{10}" 
                                                       title="Please enter a valid 10-digit phone number">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Assigned Area <span class="text-danger">*</span></label>
                                                <select class="form-select" name="assigned_area" required>
                                                    <option value="">Select Area</option>
                                                    <option value="North Zone">North Zone</option>
                                                    <option value="South Zone">South Zone</option>
                                                    <option value="East Zone">East Zone</option>
                                                    <option value="West Zone">West Zone</option>
                                                    <option value="Central Zone">Central Zone</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="address" rows="2" 
                                                          placeholder="Enter full address" maxlength="255"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">City</label>
                                                <input type="text" class="form-control" name="city" 
                                                       placeholder="Enter city" maxlength="50">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">State</label>
                                                <input type="text" class="form-control" name="state" 
                                                       placeholder="Enter state" maxlength="50">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Pincode</label>
                                                <input type="text" class="form-control" name="pincode" 
                                                       placeholder="Enter pincode" pattern="[0-9]{6}" 
                                                       title="Please enter a valid 6-digit pincode">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Employment Details -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Salary (Monthly)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="salary" 
                                                           placeholder="Enter monthly salary" min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Commission (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="commission" 
                                                           placeholder="Enter commission percentage" min="0" max="100" step="0.01">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <small class="text-muted">Commission percentage per sale (0-100%)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Login Credentials -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="username" required 
                                                       placeholder="Enter username for login" maxlength="50">
                                                <small class="text-muted">Must be unique. Used for login.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="password" required 
                                                           placeholder="Enter password" id="password" minlength="6">
                                                    <button class="btn btn-outline-secondary" type="button" 
                                                            onclick="togglePassword()">
                                                        <i class="mdi mdi-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Minimum 6 characters</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="active" selected>Active</option>
                                                    <option value="inactive">Inactive</option>
                                                    <option value="on_leave">On Leave</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Line Man
                                        </button>
                                        <button type="reset" class="btn btn-secondary ms-2">
                                            <i class="mdi mdi-refresh me-1"></i> Reset
                                        </button>
                                        <a href="lineman-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Instructions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary"><i class="mdi mdi-alert-circle-outline me-1"></i> Required Information:</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="mdi mdi-check-circle text-success me-1"></i> Full Name</li>
                                            <li><i class="mdi mdi-check-circle text-success me-1"></i> Phone Number (10 digits)</li>
                                            <li><i class="mdi mdi-check-circle text-success me-1"></i> Assigned Area</li>
                                            <li><i class="mdi mdi-check-circle text-success me-1"></i> Username (Unique)</li>
                                            <li><i class="mdi mdi-check-circle text-success me-1"></i> Password (Min 6 chars)</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary"><i class="mdi mdi-information-outline me-1"></i> Notes:</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="mdi mdi-identifier me-1 text-info"></i> Employee ID: Auto-generated</li>
                                            <li><i class="mdi mdi-shield-lock me-1 text-info"></i> Password: Encrypted for security</li>
                                            <li><i class="mdi mdi-percent me-1 text-info"></i> Commission: Optional (0-100%)</li>
                                            <li><i class="mdi mdi-checkbox-marked-circle me-1 text-info"></i> Status: Default is Active</li>
                                            <li><i class="mdi mdi-login me-1 text-info"></i> Login: Available immediately</li>
                                        </ul>
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

<!-- Right bar overlay-->


<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to toggle password visibility
function togglePassword() {
    var passwordInput = document.getElementById('password');
    var type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
}

// Form validation
document.getElementById('linemanForm').addEventListener('submit', function(e) {
    var phone = document.querySelector('input[name="phone"]').value;
    var password = document.getElementById('password').value;
    
    // Phone validation
    if (!/^\d{10}$/.test(phone)) {
        alert('Please enter a valid 10-digit phone number');
        e.preventDefault();
        return false;
    }
    
    // Password validation
    if (password.length < 6) {
        alert('Password must be at least 6 characters long');
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

</body>

</html>