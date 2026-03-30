<?php
session_start();
include('config/config.php');

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
    
    // Check in admin_users table
    $sql = "SELECT * FROM admin_users WHERE remember_token = ? AND status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $admin = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['name'] = $admin['name'];
        $_SESSION['email'] = $admin['email'];
        header('Location: admin/index.php');
        exit;
    } else {
        // Check in collection_staff table
        $sql = "SELECT * FROM collection_staff WHERE remember_token = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $staff = mysqli_fetch_assoc($result);
            $_SESSION['user_id'] = $staff['id'];
            $_SESSION['username'] = $staff['username'];
            $_SESSION['user_role'] = 'collection';
            $_SESSION['name'] = $staff['full_name'];
            $_SESSION['employee_id'] = $staff['employee_id'];
            $_SESSION['assigned_area'] = $staff['assigned_area'];
            header('Location: collection/index.php');
            exit;
        } else {
            // Check in distributors table
            $sql = "SELECT * FROM distributors WHERE remember_token = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $token);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $distributor = mysqli_fetch_assoc($result);
                $_SESSION['user_id'] = $distributor['id'];
                $_SESSION['username'] = $distributor['username'];
                $_SESSION['user_role'] = 'distributor';
                $_SESSION['name'] = $distributor['contact_person'];
                $_SESSION['company_name'] = $distributor['company_name'];
                $_SESSION['distributor_code'] = $distributor['distributor_code'];
                header('Location: distributor/index.php');
                exit;
            } else {
                // Check in linemen table
                $sql = "SELECT * FROM linemen WHERE remember_token = ? AND status = 'active'";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "s", $token);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $lineman = mysqli_fetch_assoc($result);
                    $_SESSION['user_id'] = $lineman['id'];
                    $_SESSION['username'] = $lineman['username'];
                    $_SESSION['user_role'] = 'lineman';
                    $_SESSION['name'] = $lineman['full_name'];
                    $_SESSION['employee_id'] = $lineman['employee_id'];
                    $_SESSION['assigned_area'] = $lineman['assigned_area'];
                    header('Location: user/index.php');
                    exit;
                }
            }
        }
    }
}

// Redirect if already logged in via session
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header('Location: admin/index.php');
            break;
        case 'collection':
            header('Location: collection/index.php');
            break;
        case 'distributor':
            header('Location: distributor/index.php');
            break;
        case 'lineman':
            header('Location: user/index.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}

$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password';
    } else {
        $login_success = false;
        $user_role = '';
        
        // Check in admin_users table
        $sql = "SELECT * FROM admin_users WHERE username = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $admin = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['name'] = $admin['name'];
                $_SESSION['email'] = $admin['email'];
                $_SESSION['role_type'] = $admin['role'];
                
                // Update last login
                $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $admin['id']);
                mysqli_stmt_execute($update_stmt);
                
                // Set remember me cookie if checked
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (365 * 24 * 60 * 60);
                    
                    $token_sql = "UPDATE admin_users SET remember_token = ? WHERE id = ?";
                    $token_stmt = mysqli_prepare($conn, $token_sql);
                    mysqli_stmt_bind_param($token_stmt, "si", $token, $admin['id']);
                    mysqli_stmt_execute($token_stmt);
                    
                    setcookie('remember_token', $token, $expiry, '/', '', false, true);
                }
                
                $login_success = true;
                header('Location: admin/index.php');
                exit;
            }
        }
        
        // If not admin, check in collection_staff table
        if (!$login_success) {
            $sql = "SELECT * FROM collection_staff WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $staff = mysqli_fetch_assoc($result);
                
                if (password_verify($password, $staff['password'])) {
                    $_SESSION['user_id'] = $staff['id'];
                    $_SESSION['username'] = $staff['username'];
                    $_SESSION['user_role'] = 'collection';
                    $_SESSION['name'] = $staff['full_name'];
                    $_SESSION['employee_id'] = $staff['employee_id'];
                    $_SESSION['assigned_area'] = $staff['assigned_area'];
                    
                    // Update last login
                    $update_sql = "UPDATE collection_staff SET last_login = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $staff['id']);
                    mysqli_stmt_execute($update_stmt);
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (365 * 24 * 60 * 60);
                        
                        $token_sql = "UPDATE collection_staff SET remember_token = ? WHERE id = ?";
                        $token_stmt = mysqli_prepare($conn, $token_sql);
                        mysqli_stmt_bind_param($token_stmt, "si", $token, $staff['id']);
                        mysqli_stmt_execute($token_stmt);
                        
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    $login_success = true;
                    header('Location: collection/index.php');
                    exit;
                }
            }
        }
        
        // If not collection staff, check in distributors table
        if (!$login_success) {
            $sql = "SELECT * FROM distributors WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $distributor = mysqli_fetch_assoc($result);
                
                if (password_verify($password, $distributor['password'])) {
                    $_SESSION['user_id'] = $distributor['id'];
                    $_SESSION['username'] = $distributor['username'];
                    $_SESSION['user_role'] = 'distributor';
                    $_SESSION['name'] = $distributor['contact_person'];
                    $_SESSION['company_name'] = $distributor['company_name'];
                    $_SESSION['distributor_code'] = $distributor['distributor_code'];
                    $_SESSION['assigned_area'] = $distributor['assigned_area'];
                    
                    // Update last login
                    $update_sql = "UPDATE distributors SET last_login = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $distributor['id']);
                    mysqli_stmt_execute($update_stmt);
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (365 * 24 * 60 * 60);
                        
                        $token_sql = "UPDATE distributors SET remember_token = ? WHERE id = ?";
                        $token_stmt = mysqli_prepare($conn, $token_sql);
                        mysqli_stmt_bind_param($token_stmt, "si", $token, $distributor['id']);
                        mysqli_stmt_execute($token_stmt);
                        
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    $login_success = true;
                    header('Location: distributor/index.php');
                    exit;
                }
            }
        }
        
        // If not distributor, check in linemen table
        if (!$login_success) {
            $sql = "SELECT * FROM linemen WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $lineman = mysqli_fetch_assoc($result);
                
                if (password_verify($password, $lineman['password'])) {
                    $_SESSION['user_id'] = $lineman['id'];
                    $_SESSION['username'] = $lineman['username'];
                    $_SESSION['user_role'] = 'lineman';
                    $_SESSION['name'] = $lineman['full_name'];
                    $_SESSION['employee_id'] = $lineman['employee_id'];
                    $_SESSION['assigned_area'] = $lineman['assigned_area'];
                    
                    // Update last login
                    $update_sql = "UPDATE linemen SET last_login = NOW() WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $lineman['id']);
                    mysqli_stmt_execute($update_stmt);
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (365 * 24 * 60 * 60);
                        
                        $token_sql = "UPDATE linemen SET remember_token = ? WHERE id = ?";
                        $token_stmt = mysqli_prepare($conn, $token_sql);
                        mysqli_stmt_bind_param($token_stmt, "si", $token, $lineman['id']);
                        mysqli_stmt_execute($token_stmt);
                        
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    $login_success = true;
                    header('Location: user/index.php');
                    exit;
                }
            }
        }
        
        if (!$login_success) {
            $error_message = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ecommer Business Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 45px 40px;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .logo-icon i {
            font-size: 40px;
            color: white;
        }
        
        .login-header h3 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 26px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 13px;
            margin: 0;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 1px solid #e0e4e8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.1);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #e0e4e8;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        
        .toggle-password {
            background-color: #f8f9fa;
            border: 1px solid #e0e4e8;
            border-left: none;
            cursor: pointer;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-alert {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            border: 1px solid #fcc;
            color: #c33;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 25px;
            font-size: 13px;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eef2f7;
            color: #95a5a6;
            font-size: 12px;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .brand-tagline {
            text-align: center;
            margin-top: 15px;
            color: rgba(255,255,255,0.8);
            font-size: 12px;
        }
        
        .brand-tagline a {
            color: white;
            text-decoration: none;
        }
        
        .brand-tagline a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h3>Ecommer.in</h3>
                <p>Business Management System</p>
            </div>
            
            <?php if ($error_message): ?>
            <div class="error-alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php" id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control" name="username" 
                               placeholder="Enter your username" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" name="password" 
                               id="password" placeholder="Enter your password" required>
                        <span class="input-group-text toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" name="remember_me" id="remember_me">
                    <label class="form-check-label" for="remember_me">
                        <i class="fas fa-check-circle me-1"></i> Remember me
                    </label>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                </div>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> Ecommer.in - All Rights Reserved</p>
            </div>
        </div>
        <div class="brand-tagline">
            <p>Secure Login | Multi-role Access</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="username"]').focus();
        });
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (username === '') {
                e.preventDefault();
                alert('Please enter your username');
                return false;
            }
            
            if (password === '') {
                e.preventDefault();
                alert('Please enter your password');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>