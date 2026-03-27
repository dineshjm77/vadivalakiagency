<?php
session_start();
include('config/config.php');

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
    
    // Check token in admin_users table
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
        // Check token in linemen table
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

// Redirect if already logged in via session
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: user/index.php');
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
        // Check in admin_users table first
        $sql = "SELECT * FROM admin_users WHERE username = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $admin = mysqli_fetch_assoc($result);
            
            if (password_verify($password, $admin['password'])) {
                // Admin login successful
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['name'] = $admin['name'];
                $_SESSION['email'] = $admin['email'];
                
                // Update last login
                $update_sql = "UPDATE admin_users SET last_login = NOW() WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $admin['id']);
                mysqli_stmt_execute($update_stmt);
                
                // Set remember me cookie if checked
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32)); // Generate secure token
                    $expiry = time() + (365 * 24 * 60 * 60); // 1 year
                    
                    // Store token in database
                    $token_sql = "UPDATE admin_users SET remember_token = ? WHERE id = ?";
                    $token_stmt = mysqli_prepare($conn, $token_sql);
                    mysqli_stmt_bind_param($token_stmt, "si", $token, $admin['id']);
                    mysqli_stmt_execute($token_stmt);
                    
                    // Set cookie
                    setcookie('remember_token', $token, $expiry, '/', '', false, true);
                }
                
                header('Location: admin/index.php');
                exit;
            } else {
                $error_message = 'Invalid username or password';
            }
        } else {
            // Check in linemen table
            $sql = "SELECT * FROM linemen WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $lineman = mysqli_fetch_assoc($result);
                
                if (password_verify($password, $lineman['password'])) {
                    // Line man login successful
                    $_SESSION['user_id'] = $lineman['id'];
                    $_SESSION['username'] = $lineman['username'];
                    $_SESSION['user_role'] = 'lineman';
                    $_SESSION['name'] = $lineman['full_name'];
                    $_SESSION['employee_id'] = $lineman['employee_id'];
                    $_SESSION['assigned_area'] = $lineman['assigned_area'];
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32)); // Generate secure token
                        $expiry = time() + (365 * 24 * 60 * 60); // 1 year
                        
                        // Store token in database
                        $token_sql = "UPDATE linemen SET remember_token = ? WHERE id = ?";
                        $token_stmt = mysqli_prepare($conn, $token_sql);
                        mysqli_stmt_bind_param($token_stmt, "si", $token, $lineman['id']);
                        mysqli_stmt_execute($token_stmt);
                        
                        // Set cookie
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    header('Location: user/index.php');
                    exit;
                } else {
                    $error_message = 'Invalid username or password';
                }
            } else {
                $error_message = 'Invalid username or password';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ecommer</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 40px 30px;
            border: 1px solid #eef2f7;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h3 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
            margin: 0;
        }
        
        .water-icon {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-right: none;
        }
        
        .toggle-password {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: none;
            cursor: pointer;
        }
        
        .btn-login {
            background-color: #3498db;
            border: none;
            color: white;
            padding: 10px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-login:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-alert {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #c33;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #95a5a6;
            font-size: 13px;
        }
        
        .login-footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .form-text {
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .input-group {
            border-radius: 6px;
        }
        
        .input-group .form-control:focus {
            box-shadow: none;
        }
        
        .input-group:focus-within {
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
            border-radius: 6px;
        }
        
        .input-group:focus-within .form-control,
        .input-group:focus-within .input-group-text {
            border-color: #3498db;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">

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
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </div>

            </form>

        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
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
        
        // Simple form validation
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