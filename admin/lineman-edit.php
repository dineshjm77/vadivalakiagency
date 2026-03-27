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
                
                // Initialize variables
                $lineman = null;
                $message = '';
                $message_type = '';
                
                // Get the ID from URL
                $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                
                if ($id <= 0) {
                    echo '<div class="alert alert-danger">Invalid Line Man ID</div>';
                    exit;
                }
                
                // Fetch line man data
                $sql = "SELECT * FROM linemen WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $lineman = mysqli_fetch_assoc($result);
                } else {
                    echo '<div class="alert alert-danger">Line Man not found</div>';
                    exit;
                }
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    // Collect form data
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
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Check if username already exists (excluding current user)
                    $check_sql = "SELECT id FROM linemen WHERE username = ? AND id != ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "si", $username, $id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $message = 'Username already exists. Please choose a different username.';
                        $message_type = 'danger';
                    } else {
                        // Update password only if provided
                        $password_update = '';
                        if (!empty($_POST['password'])) {
                            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                            $password_update = ", password = '$password'";
                        }
                        
                        // Update line man
                        $update_sql = "UPDATE linemen SET 
                            full_name = '$full_name',
                            email = '$email',
                            phone = '$phone',
                            address = '$address',
                            city = '$city',
                            state = '$state',
                            pincode = '$pincode',
                            assigned_area = '$assigned_area',
                            salary = '$salary',
                            commission = '$commission',
                            username = '$username',
                            status = '$status'
                            $password_update
                            WHERE id = ?";
                        
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        if ($password_update) {
                            // If password is being updated, we need to bind all parameters
                            $sql = "UPDATE linemen SET 
                                full_name = ?, email = ?, phone = ?, address = ?, 
                                city = ?, state = ?, pincode = ?, assigned_area = ?, 
                                salary = ?, commission = ?, username = ?, status = ?, 
                                password = ? WHERE id = ?";
                            $update_stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($update_stmt, "sssssssssssssi", 
                                $full_name, $email, $phone, $address, $city, $state, 
                                $pincode, $assigned_area, $salary, $commission, 
                                $username, $status, $password, $id);
                        } else {
                            // No password update
                            $sql = "UPDATE linemen SET 
                                full_name = ?, email = ?, phone = ?, address = ?, 
                                city = ?, state = ?, pincode = ?, assigned_area = ?, 
                                salary = ?, commission = ?, username = ?, status = ? 
                                WHERE id = ?";
                            $update_stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($update_stmt, "ssssssssssssi", 
                                $full_name, $email, $phone, $address, $city, $state, 
                                $pincode, $assigned_area, $salary, $commission, 
                                $username, $status, $id);
                        }
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            $message = 'Line Man updated successfully!';
                            $message_type = 'success';
                            
                            // Fetch updated data
                            $sql = "SELECT * FROM linemen WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $lineman = mysqli_fetch_assoc($result);
                        } else {
                            $message = 'Error updating line man: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                }
                
                // Close connection
                mysqli_close($conn);
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Edit Line Man Information</h4>

                            </div>
                            <div class="card-body">
                                <form method="POST" action="lineman-edit.php?id=<?php echo $id; ?>" id="linemanForm">
                                    <div class="row">
                                        <!-- Personal Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="full_name" required 
                                                       value="<?php echo htmlspecialchars($lineman['full_name']); ?>" 
                                                       placeholder="Enter full name" maxlength="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?php echo htmlspecialchars($lineman['email']); ?>" 
                                                       placeholder="Enter email address" maxlength="100">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" name="phone" required 
                                                       value="<?php echo htmlspecialchars($lineman['phone']); ?>" 
                                                       placeholder="Enter phone number" pattern="[0-9]{10}" 
                                                       title="Please enter a valid 10-digit phone number">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Assigned Area <span class="text-danger">*</span></label>
                                                <select class="form-select" name="assigned_area" required>
                                                    <option value="">Select Area</option>
                                                    <option value="North Zone" <?php echo ($lineman['assigned_area'] == 'North Zone') ? 'selected' : ''; ?>>North Zone</option>
                                                    <option value="South Zone" <?php echo ($lineman['assigned_area'] == 'South Zone') ? 'selected' : ''; ?>>South Zone</option>
                                                    <option value="East Zone" <?php echo ($lineman['assigned_area'] == 'East Zone') ? 'selected' : ''; ?>>East Zone</option>
                                                    <option value="West Zone" <?php echo ($lineman['assigned_area'] == 'West Zone') ? 'selected' : ''; ?>>West Zone</option>
                                                    <option value="Central Zone" <?php echo ($lineman['assigned_area'] == 'Central Zone') ? 'selected' : ''; ?>>Central Zone</option>
                                                    <option value="Other" <?php echo ($lineman['assigned_area'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="address" rows="2" 
                                                          placeholder="Enter full address" maxlength="255"><?php echo htmlspecialchars($lineman['address']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">City</label>
                                                <input type="text" class="form-control" name="city" 
                                                       value="<?php echo htmlspecialchars($lineman['city']); ?>" 
                                                       placeholder="Enter city" maxlength="50">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">State</label>
                                                <input type="text" class="form-control" name="state" 
                                                       value="<?php echo htmlspecialchars($lineman['state']); ?>" 
                                                       placeholder="Enter state" maxlength="50">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Pincode</label>
                                                <input type="text" class="form-control" name="pincode" 
                                                       value="<?php echo htmlspecialchars($lineman['pincode']); ?>" 
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
                                                           value="<?php echo $lineman['salary']; ?>" 
                                                           placeholder="Enter monthly salary" min="0" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Commission (%)</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" name="commission" 
                                                           value="<?php echo $lineman['commission']; ?>" 
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
                                                       value="<?php echo htmlspecialchars($lineman['username']); ?>" 
                                                       placeholder="Enter username for login" maxlength="50">
                                                <small class="text-muted">Must be unique. Used for login.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Password (Leave blank to keep current)</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="password" 
                                                           placeholder="Enter new password" id="password" minlength="6">
                                                    <button class="btn btn-outline-secondary" type="button" 
                                                            onclick="togglePassword()">
                                                        <i class="mdi mdi-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Minimum 6 characters (optional)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="active" <?php echo ($lineman['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo ($lineman['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="on_leave" <?php echo ($lineman['status'] == 'on_leave') ? 'selected' : ''; ?>>On Leave</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Employee ID</label>
                                                <input type="text" class="form-control" value="<?php echo $lineman['employee_id']; ?>" readonly>
                                                <small class="text-muted">Employee ID cannot be changed</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Created On</label>
                                                <input type="text" class="form-control" value="<?php echo date('d M, Y h:i A', strtotime($lineman['created_at'])); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Updated</label>
                                                <input type="text" class="form-control" value="<?php echo date('d M, Y h:i A', strtotime($lineman['updated_at'])); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-content-save me-1"></i> Update Line Man
                                        </button>
                                        <a href="lineman-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                        </a>
                                        <a href="lineman-view.php?id=<?php echo $id; ?>" class="btn btn-info ms-2">
                                            <i class="mdi mdi-eye me-1"></i> View Details
                                        </a>
                                        <button type="reset" class="btn btn-secondary ms-2">
                                            <i class="mdi mdi-refresh me-1"></i> Reset Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Information Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Line Man Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th width="40%">Employee ID:</th>
                                                <td><span class="badge bg-primary"><?php echo $lineman['employee_id']; ?></span></td>
                                            </tr>
                                            <tr>
                                                <th>Status:</th>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    if ($lineman['status'] == 'active') $status_class = 'badge-soft-success';
                                                    elseif ($lineman['status'] == 'inactive') $status_class = 'badge-soft-danger';
                                                    elseif ($lineman['status'] == 'on_leave') $status_class = 'badge-soft-warning';
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $lineman['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Assigned Area:</th>
                                                <td><?php echo $lineman['assigned_area']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Salary:</th>
                                                <td><?php echo ($lineman['salary'] > 0) ? '₹' . number_format($lineman['salary'], 2) : 'Not Set'; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th width="40%">Username:</th>
                                                <td><?php echo $lineman['username']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Commission:</th>
                                                <td><?php echo ($lineman['commission'] > 0) ? $lineman['commission'] . '%' : 'Not Set'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Joined On:</th>
                                                <td><?php echo date('d M, Y', strtotime($lineman['created_at'])); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Last Updated:</th>
                                                <td><?php echo date('d M, Y h:i A', strtotime($lineman['updated_at'])); ?></td>
                                            </tr>
                                        </table>
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

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to toggle password visibility
function togglePassword() {
    var passwordInput = document.getElementById('password');
    var icon = passwordInput.nextElementSibling.querySelector('i');
    var type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    icon.classList.toggle('mdi-eye');
    icon.classList.toggle('mdi-eye-off');
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
    
    // Password validation (only if provided)
    if (password.length > 0 && password.length < 6) {
        alert('Password must be at least 6 characters long');
        e.preventDefault();
        return false;
    }
    
    // Confirm password change if entered
    if (password.length > 0) {
        if (!confirm('Are you sure you want to change the password?')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});

// Reset form confirmation
document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
    if (!confirm('Are you sure you want to reset all changes?')) {
        e.preventDefault();
    }
});

// Auto-save draft functionality (optional)
let autoSaveTimer;
const form = document.getElementById('linemanForm');

form.addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        // Save form data to localStorage
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        localStorage.setItem('lineman_edit_draft', JSON.stringify(data));
        console.log('Draft saved');
    }, 2000);
});

// Load draft on page load (optional)
window.addEventListener('load', function() {
    const draft = localStorage.getItem('lineman_edit_draft');
    if (draft) {
        if (confirm('Found a saved draft. Load it?')) {
            const data = JSON.parse(draft);
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = data[key];
                }
            });
        }
        localStorage.removeItem('lineman_edit_draft');
    }
});
</script>

</body>

</html>