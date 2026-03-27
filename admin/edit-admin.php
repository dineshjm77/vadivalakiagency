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
                $admin = null;
                $message = '';
                $message_type = '';
                
                // Get the ID from URL
                $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                
                if ($id <= 0) {
                    echo '<div class="alert alert-danger">Invalid Admin ID</div>';
                    exit;
                }
                
                // Check if admin_users table exists, create if not
                $table_check = "SHOW TABLES LIKE 'admin_users'";
                $table_result = mysqli_query($conn, $table_check);
                if (mysqli_num_rows($table_result) == 0) {
                    // Create admin_users table
                    $create_table = "CREATE TABLE admin_users (
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
                    mysqli_query($conn, $create_table);
                }
                
                // Fetch admin data
                $sql = "SELECT * FROM admin_users WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $admin = mysqli_fetch_assoc($result);
                } else {
                    echo '<div class="alert alert-danger">Admin user not found</div>';
                    exit;
                }
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    // Collect form data
                    $name = mysqli_real_escape_string($conn, $_POST['name']);
                    $email = mysqli_real_escape_string($conn, $_POST['email']);
                    $username = mysqli_real_escape_string($conn, $_POST['username']);
                    $role = mysqli_real_escape_string($conn, $_POST['role']);
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Check if username already exists (excluding current user)
                    $check_sql = "SELECT id FROM admin_users WHERE username = ? AND id != ?";
                    $check_stmt = mysqli_prepare($conn, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, "si", $username, $id);
                    mysqli_stmt_execute($check_stmt);
                    $check_result = mysqli_stmt_get_result($check_stmt);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        $message = 'Username already exists. Please choose a different username.';
                        $message_type = 'danger';
                    } else {
                        // Check if email already exists (excluding current user)
                        $check_email_sql = "SELECT id FROM admin_users WHERE email = ? AND id != ?";
                        $check_email_stmt = mysqli_prepare($conn, $check_email_sql);
                        mysqli_stmt_bind_param($check_email_stmt, "si", $email, $id);
                        mysqli_stmt_execute($check_email_stmt);
                        $check_email_result = mysqli_stmt_get_result($check_email_stmt);
                        
                        if (mysqli_num_rows($check_email_result) > 0) {
                            $message = 'Email already exists. Please choose a different email.';
                            $message_type = 'danger';
                        } else {
                            // Update admin
                            $update_sql = "UPDATE admin_users SET 
                                name = ?,
                                email = ?,
                                username = ?,
                                role = ?,
                                status = ?
                                WHERE id = ?";
                            
                            $update_stmt = mysqli_prepare($conn, $update_sql);
                            mysqli_stmt_bind_param($update_stmt, "sssssi", 
                                $name, $email, $username, $role, $status, $id);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $message = 'Admin user updated successfully!';
                                $message_type = 'success';
                                
                                // Fetch updated data
                                $sql = "SELECT * FROM admin_users WHERE id = ?";
                                $stmt = mysqli_prepare($conn, $sql);
                                mysqli_stmt_bind_param($stmt, "i", $id);
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_stmt_get_result($stmt);
                                $admin = mysqli_fetch_assoc($result);
                            } else {
                                $message = 'Error updating admin user: ' . mysqli_error($conn);
                                $message_type = 'danger';
                            }
                        }
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
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Edit Admin User Information</h4>
                                <p class="card-title-desc">Update the admin details below</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="edit-admin.php?id=<?php echo $id; ?>" id="editAdminForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="name" required 
                                                       value="<?php echo htmlspecialchars($admin['name']); ?>" 
                                                       placeholder="Enter full name" maxlength="100">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" name="email" required 
                                                       value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                                       placeholder="Enter email address" maxlength="100">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="username" required 
                                                       value="<?php echo htmlspecialchars($admin['username']); ?>" 
                                                       placeholder="Enter username" maxlength="50">
                                                <small class="text-muted">Must be unique. Used for login.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <select class="form-select" name="role">
                                                    <option value="admin" <?php echo ($admin['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                    <option value="super_admin" <?php echo ($admin['role'] == 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="active" <?php echo ($admin['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo ($admin['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Admin ID</label>
                                                <input type="text" class="form-control" value="<?php echo $admin['id']; ?>" readonly>
                                                <small class="text-muted">Admin ID cannot be changed</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Created On</label>
                                                <input type="text" class="form-control" value="<?php echo date('d M, Y h:i A', strtotime($admin['created_at'])); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Last Login</label>
                                                <input type="text" class="form-control" 
                                                       value="<?php echo $admin['last_login'] ? date('d M, Y h:i A', strtotime($admin['last_login'])) : 'Never logged in'; ?>" 
                                                       readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="mdi mdi-information-outline me-2"></i>
                                        <strong>Note:</strong> To change password, use the "Change Password" option in the admin list or go to 
                                        <a href="change-admin-password.php?id=<?php echo $id; ?>" class="alert-link">Change Password</a> page.
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-content-save me-1"></i> Update Admin
                                        </button>
                                        <a href="my-profile.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Profile
                                        </a>
                                        <a href="change-admin-password.php?id=<?php echo $id; ?>" class="btn btn-warning ms-2">
                                            <i class="mdi mdi-lock-reset me-1"></i> Change Password
                                        </a>
                                        <button type="reset" class="btn btn-secondary ms-2">
                                            <i class="mdi mdi-refresh me-1"></i> Reset Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Admin Info Card -->
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="avatar-xl mx-auto mb-3">
                                        <div class="avatar-title bg-primary-subtle text-primary rounded-circle display-4">
                                            <?php echo strtoupper(substr($admin['name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <h5 class="font-size-16 mb-1"><?php echo htmlspecialchars($admin['name']); ?></h5>
                                    <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?></p>
                                    
                                    <div class="mt-3">
                                        <span class="badge <?php echo ($admin['status'] == 'active') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <h5 class="font-size-16 mb-3">Quick Information</h5>
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <th width="40%">Username:</th>
                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email:</th>
                                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Role:</th>
                                                <td>
                                                    <span class="badge <?php echo ($admin['role'] == 'super_admin') ? 'bg-danger' : 'bg-primary'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Status:</th>
                                                <td>
                                                    <span class="badge <?php echo ($admin['status'] == 'active') ? 'badge-soft-success' : 'badge-soft-danger'; ?>">
                                                        <?php echo ucfirst($admin['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Account Created:</th>
                                                <td><?php echo date('d M, Y', strtotime($admin['created_at'])); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Last Login:</th>
                                                <td><?php echo $admin['last_login'] ? date('d M, Y h:i A', strtotime($admin['last_login'])) : 'Never'; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <hr class="my-4">

                                <h5 class="font-size-16 mb-3">Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <a href="change-admin-password.php?id=<?php echo $id; ?>" class="btn btn-warning">
                                        <i class="mdi mdi-lock-reset me-1"></i> Change Password
                                    </a>
                                    <a href="my-profile.php" class="btn btn-light">
                                        <i class="mdi mdi-arrow-left me-1"></i> Back to Profile
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Permissions Info -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Role Permissions</h5>
                                <div class="mb-3">
                                    <h6 class="font-size-14">
                                        <?php if ($admin['role'] == 'super_admin'): ?>
                                            <span class="text-danger">Super Administrator</span>
                                        <?php else: ?>
                                            <span class="text-primary">Administrator</span>
                                        <?php endif; ?>
                                    </h6>
                                    <ul class="list-unstyled ps-3">
                                        <?php if ($admin['role'] == 'super_admin'): ?>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Full system access</li>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Manage all admin users</li>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Edit business settings</li>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Access all reports</li>
                                        <?php else: ?>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Manage line men</li>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Manage products</li>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> Manage customers</li>
                                            <li><i class="mdi mdi-check-circle text-success me-2"></i> View reports</li>
                                            <li><i class="mdi mdi-close-circle text-muted me-2"></i> Cannot manage admin users</li>
                                        <?php endif; ?>
                                    </ul>
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
// Form validation
document.getElementById('editAdminForm').addEventListener('submit', function(e) {
    const username = document.querySelector('input[name="username"]').value;
    const email = document.querySelector('input[name="email"]').value;
    
    // Username validation
    if (!username.match(/^[a-zA-Z0-9_]+$/)) {
        alert('Username can only contain letters, numbers, and underscores');
        e.preventDefault();
        return false;
    }
    
    if (username.length < 3) {
        alert('Username must be at least 3 characters long');
        e.preventDefault();
        return false;
    }
    
    // Email validation
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        alert('Please enter a valid email address');
        e.preventDefault();
        return false;
    }
    
    // Confirm if changing role to super_admin
    const roleSelect = document.querySelector('select[name="role"]');
    const currentRole = '<?php echo $admin["role"]; ?>';
    const newRole = roleSelect.value;
    
    if (currentRole !== newRole && newRole === 'super_admin') {
        if (!confirm('Are you sure you want to grant Super Admin privileges? This user will have full system access.')) {
            e.preventDefault();
            return false;
        }
    }
    
    // Confirm if changing status to inactive
    const statusSelect = document.querySelector('select[name="status"]');
    const currentStatus = '<?php echo $admin["status"]; ?>';
    const newStatus = statusSelect.value;
    
    if (currentStatus !== newStatus && newStatus === 'inactive') {
        if (!confirm('Are you sure you want to deactivate this admin? They will lose access immediately.')) {
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

// Auto-save draft functionality
let autoSaveTimer;
const form = document.getElementById('editAdminForm');

form.addEventListener('input', function() {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(function() {
        // Save form data to localStorage
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        data['admin_id'] = '<?php echo $id; ?>';
        localStorage.setItem('admin_edit_draft', JSON.stringify(data));
        console.log('Admin edit draft saved');
    }, 2000);
});

// Load draft on page load
window.addEventListener('load', function() {
    const draft = localStorage.getItem('admin_edit_draft');
    if (draft) {
        const data = JSON.parse(draft);
        // Only load if it's for the same admin
        if (data.admin_id === '<?php echo $id; ?>') {
            if (confirm('Found a saved draft for this admin. Load it?')) {
                Object.keys(data).forEach(key => {
                    if (key !== 'admin_id') {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = data[key];
                        }
                        // Handle select elements
                        const select = form.querySelector(`select[name="${key}"]`);
                        if (select) {
                            select.value = data[key];
                        }
                    }
                });
            }
        }
        // Clear the draft
        localStorage.removeItem('admin_edit_draft');
    }
});

// Show notification when leaving page with unsaved changes
let hasUnsavedChanges = false;

form.addEventListener('input', function() {
    hasUnsavedChanges = true;
});

window.addEventListener('beforeunload', function(e) {
    if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});

// Clear unsaved changes flag on form submit
form.addEventListener('submit', function() {
    hasUnsavedChanges = false;
});
</script>

</body>

</html>