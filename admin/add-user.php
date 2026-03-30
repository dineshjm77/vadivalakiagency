<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

$success_message = '';
$error_message = '';

// Generate codes
function generateCode($type) {
    $prefix = $type === 'admin' ? 'ADM' : 'LM';
    return $prefix . date('ymd') . rand(100, 999);
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim((string)$_POST['action']);

    if ($action === 'add_admin') {
        $name = mysqli_real_escape_string($conn, trim((string)($_POST['name'] ?? '')));
        $email = mysqli_real_escape_string($conn, trim((string)($_POST['email'] ?? '')));
        $username = mysqli_real_escape_string($conn, trim((string)($_POST['username'] ?? '')));
        $rawPassword = (string)($_POST['password'] ?? '');
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);
        $role = mysqli_real_escape_string($conn, trim((string)($_POST['role'] ?? 'admin')));

        if ($name === '' || $email === '' || $username === '' || $rawPassword === '') {
            $error_message = 'Please fill all required Administrator fields.';
        } else {
            $checkSql = "SELECT id FROM admin_users WHERE username = ? OR email = ? LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, 'ss', $username, $email);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);

            if ($checkRes && mysqli_num_rows($checkRes) > 0) {
                $error_message = 'Admin username or email already exists.';
            } else {
                $sql = "INSERT INTO admin_users (name, email, username, password, role, status)
                        VALUES (?, ?, ?, ?, ?, 'active')";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $username, $password, $role);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Admin user added successfully!';
                } else {
                    $error_message = 'Error adding admin: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($checkStmt);
        }
    }

    elseif ($action === 'add_lineman') {
        $employee_id = mysqli_real_escape_string($conn, trim((string)($_POST['employee_id'] ?? '')));
        $full_name = mysqli_real_escape_string($conn, trim((string)($_POST['full_name'] ?? '')));
        $phone = mysqli_real_escape_string($conn, trim((string)($_POST['phone'] ?? '')));
        $email = mysqli_real_escape_string($conn, trim((string)($_POST['email'] ?? '')));
        $username = mysqli_real_escape_string($conn, trim((string)($_POST['username'] ?? '')));
        $rawPassword = (string)($_POST['password'] ?? '');
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);
        $assigned_area = mysqli_real_escape_string($conn, trim((string)($_POST['assigned_area'] ?? '')));
        $address = mysqli_real_escape_string($conn, trim((string)($_POST['address'] ?? '')));

        if ($employee_id === '' || $full_name === '' || $phone === '' || $username === '' || $rawPassword === '') {
            $error_message = 'Please fill all required Line Man fields.';
        } else {
            $checkSql = "SELECT id FROM linemen WHERE employee_id = ? OR username = ? OR phone = ? LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, 'sss', $employee_id, $username, $phone);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);

            if ($checkRes && mysqli_num_rows($checkRes) > 0) {
                $error_message = 'Line man employee ID, username, or phone already exists.';
            } else {
                $sql = "INSERT INTO linemen (employee_id, full_name, email, phone, username, password, assigned_area, address, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                $stmt = mysqli_prepare($conn, $sql);
                // 8 placeholders, so 8 type letters and 8 variables
                mysqli_stmt_bind_param($stmt, 'ssssssss', $employee_id, $full_name, $email, $phone, $username, $password, $assigned_area, $address);

                if (mysqli_stmt_execute($stmt)) {
                    $success_message = 'Line man added successfully!';
                } else {
                    $error_message = 'Error adding line man: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($checkStmt);
        }
    }
}
?>

<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body p-0">
                                <ul class="nav nav-tabs nav-tabs-custom" role="tablist" style="padding: 0 20px; margin-top: 15px;">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#add-admin" role="tab">
                                            <i class="fas fa-user-shield me-1"></i> Administrator
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#add-lineman" role="tab">
                                            <i class="fas fa-motorcycle me-1"></i> Line Man
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content p-4">
                                    <div class="tab-pane active" id="add-admin" role="tabpanel">
                                        <div class="row">
                                            <div class="col-lg-8 mx-auto">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="add_admin">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h5 class="card-title mb-0">Administrator Details</h5>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Full Name *</label>
                                                                    <input type="text" class="form-control" name="name" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Email *</label>
                                                                    <input type="email" class="form-control" name="email" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Username *</label>
                                                                    <input type="text" class="form-control" name="username" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Password *</label>
                                                                    <div class="input-group">
                                                                        <input type="password" class="form-control" name="password" id="admin_password" required>
                                                                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('admin_password')">
                                                                            <i class="fas fa-key"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Role *</label>
                                                                    <select class="form-select" name="role" required>
                                                                        <option value="admin">Admin</option>
                                                                        <option value="super_admin">Super Admin</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer">
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-save me-1"></i> Create Admin
                                                            </button>
                                                            <button type="reset" class="btn btn-secondary ms-2">
                                                                <i class="fas fa-undo me-1"></i> Reset
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane" id="add-lineman" role="tabpanel">
                                        <div class="row">
                                            <div class="col-lg-10 mx-auto">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="add_lineman">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h5 class="card-title mb-0">Line Man Details</h5>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Employee ID *</label>
                                                                    <input type="text" class="form-control" name="employee_id" value="<?php echo generateCode('lineman'); ?>" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Full Name *</label>
                                                                    <input type="text" class="form-control" name="full_name" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Phone *</label>
                                                                    <input type="text" class="form-control" name="phone" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Email</label>
                                                                    <input type="email" class="form-control" name="email">
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Username *</label>
                                                                    <input type="text" class="form-control" name="username" required>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Password *</label>
                                                                    <div class="input-group">
                                                                        <input type="password" class="form-control" name="password" id="lm_password" required>
                                                                        <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('lm_password')">
                                                                            <i class="fas fa-key"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label class="form-label">Assigned Area</label>
                                                                    <input type="text" class="form-control" name="assigned_area" placeholder="e.g., Central Zone">
                                                                </div>
                                                                <div class="col-md-12 mb-3">
                                                                    <label class="form-label">Address</label>
                                                                    <textarea class="form-control" name="address" rows="2"></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-footer">
                                                            <button type="submit" class="btn btn-primary">
                                                                <i class="fas fa-save me-1"></i> Create Line Man
                                                            </button>
                                                            <button type="reset" class="btn btn-secondary ms-2">
                                                                <i class="fas fa-undo me-1"></i> Reset
                                                            </button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
<script>
function generatePassword(fieldId) {
    const length = 10;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    document.getElementById(fieldId).value = password;
}
</script>
</body>
</html>
