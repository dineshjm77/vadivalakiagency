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
                
                // Initialize messages
                $message = '';
                $message_type = '';
                
                // Handle Business Settings Update
                if (isset($_POST['update_business'])) {
                    $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
                    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
                    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
                    $address = mysqli_real_escape_string($conn, $_POST['address']);
                    $city = mysqli_real_escape_string($conn, $_POST['city']);
                    $state = mysqli_real_escape_string($conn, $_POST['state']);
                    $pincode = mysqli_real_escape_string($conn, $_POST['pincode']);
                    $gstin = mysqli_real_escape_string($conn, $_POST['gstin']);
                    
                    // Check if business settings exist
                    $check_sql = "SELECT id FROM business_settings LIMIT 1";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        // Update existing
                        $sql = "UPDATE business_settings SET 
                                business_name = '$business_name',
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
                    } else {
                        // Insert new
                        $sql = "INSERT INTO business_settings (
                                business_name, contact_person, email, phone, mobile, 
                                address, city, state, pincode, gstin, created_at, updated_at
                                ) VALUES (
                                '$business_name', '$contact_person', '$email', '$phone', '$mobile',
                                '$address', '$city', '$state', '$pincode', '$gstin', NOW(), NOW()
                                )";
                    }
                    
                    if (mysqli_query($conn, $sql)) {
                        $message = 'Business settings updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating business settings: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                }
                
                // Handle Admin User Creation
                if (isset($_POST['add_admin'])) {
                    $admin_name = mysqli_real_escape_string($conn, $_POST['admin_name']);
                    $admin_email = mysqli_real_escape_string($conn, $_POST['admin_email']);
                    $admin_username = mysqli_real_escape_string($conn, $_POST['admin_username']);
                    $admin_password = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                    
                    // Check if username already exists
                    $check_sql = "SELECT id FROM admin_users WHERE username = '$admin_username' OR email = '$admin_email'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $message = 'Username or Email already exists. Please choose different credentials.';
                        $message_type = 'danger';
                    } else {
                        // Create admin table if not exists
                        $create_table_sql = "CREATE TABLE IF NOT EXISTS admin_users (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            email VARCHAR(100) UNIQUE NOT NULL,
                            username VARCHAR(50) UNIQUE NOT NULL,
                            password VARCHAR(255) NOT NULL,
                            role ENUM('admin', 'super_admin') DEFAULT 'admin',
                            status ENUM('active', 'inactive') DEFAULT 'active',
                            last_login DATETIME,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )";
                        mysqli_query($conn, $create_table_sql);
                        
                        // Insert admin user
                        $sql = "INSERT INTO admin_users (name, email, username, password, role, status) 
                                VALUES ('$admin_name', '$admin_email', '$admin_username', '$admin_password', 'admin', 'active')";
                        
                        if (mysqli_query($conn, $sql)) {
                            $message = 'Admin user created successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Error creating admin user: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                }
                
                // Fetch business settings
                $business_settings = [];
                $sql = "SELECT * FROM business_settings LIMIT 1";
                $result = mysqli_query($conn, $sql);
                if ($result && mysqli_num_rows($result) > 0) {
                    $business_settings = mysqli_fetch_assoc($result);
                }
                
                // Fetch admin users
                $admin_users = [];
                $admin_sql = "SELECT * FROM admin_users ORDER BY created_at DESC";
                $admin_result = mysqli_query($conn, $admin_sql);
                if ($admin_result) {
                    while ($row = mysqli_fetch_assoc($admin_result)) {
                        $admin_users[] = $row;
                    }
                }
                
                // Close connection
                mysqli_close($conn);
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Business Settings Card -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Business Information</h4>

                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="business_name" required
                                               value="<?php echo isset($business_settings['business_name']) ? htmlspecialchars($business_settings['business_name']) : 'APR Water Agencies'; ?>">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="contact_person" required
                                                       value="<?php echo isset($business_settings['contact_person']) ? htmlspecialchars($business_settings['contact_person']) : 'Owner'; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">GSTIN Number</label>
                                                <input type="text" class="form-control" name="gstin"
                                                       value="<?php echo isset($business_settings['gstin']) ? htmlspecialchars($business_settings['gstin']) : ''; ?>"
                                                       placeholder="Ex: 27AABCU9603R1ZX">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email"
                                                       value="<?php echo isset($business_settings['email']) ? htmlspecialchars($business_settings['email']) : ''; ?>"
                                                       placeholder="business@example.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" name="phone"
                                                       value="<?php echo isset($business_settings['phone']) ? htmlspecialchars($business_settings['phone']) : ''; ?>"
                                                       placeholder="Landline number">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="mobile" required
                                               value="<?php echo isset($business_settings['mobile']) ? htmlspecialchars($business_settings['mobile']) : '9876543210'; ?>"
                                               pattern="[0-9]{10}" placeholder="10-digit mobile number">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2"><?php echo isset($business_settings['address']) ? htmlspecialchars($business_settings['address']) : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">City</label>
                                                <input type="text" class="form-control" name="city"
                                                       value="<?php echo isset($business_settings['city']) ? htmlspecialchars($business_settings['city']) : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">State</label>
                                                <input type="text" class="form-control" name="state"
                                                       value="<?php echo isset($business_settings['state']) ? htmlspecialchars($business_settings['state']) : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Pincode</label>
                                                <input type="text" class="form-control" name="pincode"
                                                       value="<?php echo isset($business_settings['pincode']) ? htmlspecialchars($business_settings['pincode']) : ''; ?>"
                                                       pattern="[0-9]{6}" placeholder="6-digit pincode">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" name="update_business" class="btn btn-primary">
                                            <i class="mdi mdi-content-save me-1"></i> Save Business Settings
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Admin Users Card -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Admin Users</h4>

                            </div>
                            <div class="card-body">
                                <!-- Add New Admin Form -->
                                <div class="mb-4">
                                    <h5 class="font-size-14 mb-3">Add New Admin</h5>
                                    <form method="POST" action="" id="addAdminForm">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="admin_name" required
                                                           placeholder="Enter full name">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control" name="admin_email" required
                                                           placeholder="Enter email address">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="admin_username" required
                                                           placeholder="Choose username">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" name="admin_password" required
                                                               id="adminPassword" placeholder="Enter password" minlength="6">
                                                        <button class="btn btn-outline-secondary" type="button"
                                                                onclick="toggleAdminPassword()">
                                                            <i class="mdi mdi-eye"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Minimum 6 characters</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <button type="submit" name="add_admin" class="btn btn-success">
                                                <i class="mdi mdi-plus-circle-outline me-1"></i> Add Admin User
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <hr>
                                
                                <!-- Existing Admin Users List -->
                                <h5 class="font-size-14 mb-3">Existing Admin Users</h5>
                                <?php if (empty($admin_users)): ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-account-off display-4 text-muted"></i>
                                        <h5 class="mt-2">No Admin Users Found</h5>
                                        <p class="text-muted">Add your first admin user above</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Name</th>
                                                    <th>Username</th>
                                                    <th>Email</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($admin_users as $index => $admin): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <div class="avatar-xs">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($admin['name']); ?></h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo ($admin['status'] == 'active') ? 'badge-soft-success' : 'badge-soft-danger'; ?>">
                                                            <?php echo ucfirst($admin['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-light btn-sm dropdown-toggle" type="button" 
                                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="mdi mdi-dots-horizontal"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item" href="edit-admin.php?id=<?php echo $admin['id']; ?>">
                                                                        <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="change-admin-password.php?id=<?php echo $admin['id']; ?>">
                                                                        <i class="mdi mdi-lock-reset me-1"></i> Change Password
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger delete-admin" href="#" 
                                                                       data-id="<?php echo $admin['id']; ?>" data-name="<?php echo htmlspecialchars($admin['name']); ?>">
                                                                        <i class="mdi mdi-delete-outline me-1"></i> Delete
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Card -->
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">My Profile Information</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Your Name</label>
                                                    <input type="text" class="form-control" value="Admin User" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Your Email</label>
                                                    <input type="email" class="form-control" value="admin@aprwater.com" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Your Username</label>
                                                    <input type="text" class="form-control" value="admin" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Your Role</label>
                                                    <input type="text" class="form-control" value="Super Administrator" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Last Login</label>
                                                    <input type="text" class="form-control" value="<?php echo date('d M, Y h:i A'); ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Account Created</label>
                                                    <input type="text" class="form-control" value="<?php echo date('d M, Y'); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="avatar-xl mx-auto mb-3">
                                                <div class="avatar-title bg-primary-subtle rounded-circle display-4">
                                                    <i class="mdi mdi-account text-primary"></i>
                                                </div>
                                            </div>
                                            <h5 class="font-size-16 mb-1">Admin User</h5>
                                            <p class="text-muted">Super Administrator</p>
                                            
                                            <div class="mt-4">
                                                <a href="change-password.php" class="btn btn-primary btn-sm me-2">
                                                    <i class="mdi mdi-lock-reset me-1"></i> Change Password
                                                </a>
                                                <a href="logout.php" class="btn btn-light btn-sm">
                                                    <i class="mdi mdi-logout me-1"></i> Logout
                                                </a>
                                            </div>
                                        </div>
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

<!-- Delete Admin Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAdminModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete admin user: <strong id="deleteAdminName"></strong>?</p>
                <p class="text-danger">This action cannot be undone. The admin will lose access immediately.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteAdmin">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Toggle admin password visibility
function toggleAdminPassword() {
    const passwordInput = document.getElementById('adminPassword');
    const icon = passwordInput.nextElementSibling.querySelector('i');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    icon.classList.toggle('mdi-eye');
    icon.classList.toggle('mdi-eye-off');
}

// Form validation for admin user
document.getElementById('addAdminForm').addEventListener('submit', function(e) {
    const password = document.getElementById('adminPassword').value;
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters long');
        e.preventDefault();
        return false;
    }
    
    return true;
});

// Delete admin user functionality
let deleteAdminId = null;
let deleteAdminName = null;

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('delete-admin')) {
        e.preventDefault();
        deleteAdminId = e.target.getAttribute('data-id');
        deleteAdminName = e.target.getAttribute('data-name');
        document.getElementById('deleteAdminName').textContent = deleteAdminName;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteAdminModal'));
        deleteModal.show();
    }
});

document.getElementById('confirmDeleteAdmin').addEventListener('click', function() {
    if (deleteAdminId) {
        // Send AJAX request to delete admin
        fetch('delete-admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + deleteAdminId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove row from table
                const row = document.querySelector(`.delete-admin[data-id="${deleteAdminId}"]`)?.closest('tr');
                if (row) {
                    row.remove();
                    showAlert('Admin user deleted successfully!', 'success');
                }
            } else {
                showAlert('Error deleting admin user: ' + data.message, 'danger');
            }
            
            // Hide modal
            bootstrap.Modal.getInstance(document.getElementById('deleteAdminModal')).hide();
        })
        .catch(error => {
            showAlert('Network error: ' + error, 'danger');
            bootstrap.Modal.getInstance(document.getElementById('deleteAdminModal')).hide();
        });
    }
});

// Helper function to show alerts
function showAlert(message, type) {
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert-dismissible');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        <i class="mdi mdi-check-circle me-2"></i> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Auto-hide alert after 5 seconds
setTimeout(() => {
    const alert = document.querySelector('.alert-dismissible');
    if (alert) {
        alert.remove();
    }
}, 5000);
</script>

</body>

</html>