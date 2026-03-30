<?php
session_start();
include('config/config.php');

function redirect_by_role(string $role): void {
    switch ($role) {
        case 'admin':
        case 'super_admin':
            header('Location: admin/index.php');
            break;
        case 'collection':
            header('Location: collection/index.php');
            break;
        case 'distributor':
            header('Location: distributor/index.php');
            break;
        case 'lineman':
            header('Location: lineman/index.php');
            break;
        default:
            header('Location: index.php');
            break;
    }
    exit;
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);

    $sql = "SELECT * FROM admin_users WHERE remember_token = ? AND status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $admin = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = (int)$admin['id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['name'] = $admin['name'];
        $_SESSION['email'] = $admin['email'];
        $_SESSION['role_type'] = $admin['role'];
        redirect_by_role('admin');
    }

    $sql = "SELECT * FROM collection_staff WHERE remember_token = ? AND status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $staff = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = (int)$staff['id'];
        $_SESSION['username'] = $staff['username'];
        $_SESSION['user_role'] = 'collection';
        $_SESSION['name'] = $staff['full_name'];
        $_SESSION['employee_id'] = $staff['employee_id'];
        $_SESSION['assigned_area'] = $staff['assigned_area'];
        redirect_by_role('collection');
    }

    $sql = "SELECT * FROM distributors WHERE remember_token = ? AND status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $distributor = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = (int)$distributor['id'];
        $_SESSION['username'] = $distributor['username'];
        $_SESSION['user_role'] = 'distributor';
        $_SESSION['name'] = $distributor['contact_person'];
        $_SESSION['company_name'] = $distributor['company_name'];
        $_SESSION['distributor_code'] = $distributor['distributor_code'];
        $_SESSION['assigned_area'] = $distributor['assigned_area'];
        redirect_by_role('distributor');
    }

    $sql = "SELECT * FROM linemen WHERE remember_token = ? AND status = 'active'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        $lineman = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = (int)$lineman['id'];
        $_SESSION['lineman_id'] = (int)$lineman['id'];
        $_SESSION['username'] = $lineman['username'];
        $_SESSION['user_role'] = 'lineman';
        $_SESSION['name'] = $lineman['full_name'];
        $_SESSION['employee_id'] = $lineman['employee_id'];
        $_SESSION['assigned_area'] = $lineman['assigned_area'];
        redirect_by_role('lineman');
    }
}

if (isset($_SESSION['user_id'])) {
    redirect_by_role((string)($_SESSION['user_role'] ?? ''));
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember_me = isset($_POST['remember_me']);

    if ($username === '' || $password === '') {
        $error_message = 'Please enter both username and password';
    } else {
        $login_success = false;

        $sql = "SELECT * FROM admin_users WHERE username = ? AND status = 'active'";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            $admin = mysqli_fetch_assoc($result);
            if (password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = (int)$admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['name'] = $admin['name'];
                $_SESSION['email'] = $admin['email'];
                $_SESSION['role_type'] = $admin['role'];
                mysqli_query($conn, "UPDATE admin_users SET last_login = NOW() WHERE id = " . (int)$admin['id']);
                if ($remember_me) {
                    $token = bin2hex(random_bytes(32));
                    mysqli_query($conn, "UPDATE admin_users SET remember_token = '" . mysqli_real_escape_string($conn, $token) . "' WHERE id = " . (int)$admin['id']);
                    setcookie('remember_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                }
                $login_success = true;
                redirect_by_role('admin');
            }
        }

        if (!$login_success) {
            $sql = "SELECT * FROM collection_staff WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $staff = mysqli_fetch_assoc($result);
                if (password_verify($password, $staff['password'])) {
                    $_SESSION['user_id'] = (int)$staff['id'];
                    $_SESSION['username'] = $staff['username'];
                    $_SESSION['user_role'] = 'collection';
                    $_SESSION['name'] = $staff['full_name'];
                    $_SESSION['employee_id'] = $staff['employee_id'];
                    $_SESSION['assigned_area'] = $staff['assigned_area'];
                    mysqli_query($conn, "UPDATE collection_staff SET last_login = NOW() WHERE id = " . (int)$staff['id']);
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        mysqli_query($conn, "UPDATE collection_staff SET remember_token = '" . mysqli_real_escape_string($conn, $token) . "' WHERE id = " . (int)$staff['id']);
                        setcookie('remember_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                    }
                    $login_success = true;
                    redirect_by_role('collection');
                }
            }
        }

        if (!$login_success) {
            $sql = "SELECT * FROM distributors WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $distributor = mysqli_fetch_assoc($result);
                if (password_verify($password, $distributor['password'])) {
                    $_SESSION['user_id'] = (int)$distributor['id'];
                    $_SESSION['username'] = $distributor['username'];
                    $_SESSION['user_role'] = 'distributor';
                    $_SESSION['name'] = $distributor['contact_person'];
                    $_SESSION['company_name'] = $distributor['company_name'];
                    $_SESSION['distributor_code'] = $distributor['distributor_code'];
                    $_SESSION['assigned_area'] = $distributor['assigned_area'];
                    mysqli_query($conn, "UPDATE distributors SET last_login = NOW() WHERE id = " . (int)$distributor['id']);
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        mysqli_query($conn, "UPDATE distributors SET remember_token = '" . mysqli_real_escape_string($conn, $token) . "' WHERE id = " . (int)$distributor['id']);
                        setcookie('remember_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                    }
                    $login_success = true;
                    redirect_by_role('distributor');
                }
            }
        }

        if (!$login_success) {
            $sql = "SELECT * FROM linemen WHERE username = ? AND status = 'active'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $lineman = mysqli_fetch_assoc($result);
                if (password_verify($password, $lineman['password'])) {
                    $_SESSION['user_id'] = (int)$lineman['id'];
                    $_SESSION['lineman_id'] = (int)$lineman['id'];
                    $_SESSION['username'] = $lineman['username'];
                    $_SESSION['user_role'] = 'lineman';
                    $_SESSION['name'] = $lineman['full_name'];
                    $_SESSION['employee_id'] = $lineman['employee_id'];
                    $_SESSION['assigned_area'] = $lineman['assigned_area'];
                    mysqli_query($conn, "UPDATE linemen SET last_login = NOW() WHERE id = " . (int)$lineman['id']);
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        mysqli_query($conn, "UPDATE linemen SET remember_token = '" . mysqli_real_escape_string($conn, $token) . "' WHERE id = " . (int)$lineman['id']);
                        setcookie('remember_token', $token, time() + (365 * 24 * 60 * 60), '/', '', false, true);
                    }
                    $login_success = true;
                    redirect_by_role('lineman');
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family:'Segoe UI',Tahoma,Verdana,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px;}
        .login-container {max-width:420px; width:100%;}
        .login-card {background:#fff; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.3); padding:45px 40px;}
        .login-header {text-align:center; margin-bottom:40px;}
        .logo-icon {width:80px; height:80px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;}
        .logo-icon i {font-size:40px; color:white;}
        .form-control,.input-group-text{border-radius:10px;}
        .btn-login {background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); border:none; color:#fff; padding:12px; border-radius:10px; font-weight:600; width:100%;}
        .error-alert {background:linear-gradient(135deg,#fee 0%,#fdd 100%); border:1px solid #fcc; color:#c33; border-radius:10px; padding:12px 15px; margin-bottom:25px; font-size:13px;}
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="logo-icon"><i class="fas fa-store"></i></div>
            <h3>Ecommer.in</h3>
            <p class="text-muted mb-0">Business Management System</p>
        </div>
        <?php if ($error_message): ?><div class="error-alert"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <form method="POST" action="index.php" id="loginForm">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" name="username" placeholder="Enter your username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Enter your password" required>
                    <span class="input-group-text" id="togglePassword" style="cursor:pointer;"><i class="fas fa-eye"></i></span>
                </div>
            </div>
            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" name="remember_me" id="remember_me">
                <label class="form-check-label" for="remember_me">Remember me</label>
            </div>
            <button type="submit" class="btn btn-login"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
        </form>
        <div class="text-center mt-4 text-muted small">&copy; <?php echo date('Y'); ?> Ecommer.in - All Rights Reserved</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tp = document.getElementById('togglePassword');
if (tp) tp.addEventListener('click', function(){ const p = document.getElementById('password'); const i = this.querySelector('i'); if (p.type === 'password') { p.type = 'text'; i.classList.replace('fa-eye','fa-eye-slash'); } else { p.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }});
document.addEventListener('DOMContentLoaded', ()=> { const u=document.querySelector('input[name="username"]'); if(u) u.focus(); });
</script>
</body>
</html>
