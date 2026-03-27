<?php
// change-admin-password.php
include('config/config.php');

// Get the ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid Admin ID</div>';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $message = 'Passwords do not match!';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long!';
        $message_type = 'danger';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE admin_users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Password updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating password: ' . mysqli_error($conn);
            $message_type = 'danger';
        }
    }
}

// Fetch admin name for display
$sql = "SELECT name FROM admin_users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);

mysqli_close($conn);
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php')?>
<body data-sidebar="dark">
<!-- Loader -->
<?php include('includes/pre-loader.php')?>
<!-- Begin page -->
<div id="layout-wrapper">
<?php include('includes/topbar.php')?>    
<div class="vertical-menu">
<div data-simplebar class="h-100">
<?php include('includes/sidebar.php')?>
</div></div>
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
<div class="row">
<div class="col-12">
<div class="page-title-box d-sm-flex align-items-center justify-content-between">
<h4 class="mb-sm-0 font-size-18">Change Admin Password</h4>
</div></div></div>

<?php if (isset($message)): ?>
<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
<?php echo $message; ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card">
<div class="card-body">
<h4 class="card-title mb-4">Change Password for <?php echo htmlspecialchars($admin['name']); ?></h4>
<form method="POST" action="">
<div class="mb-3">
<label class="form-label">New Password <span class="text-danger">*</span></label>
<input type="password" class="form-control" name="new_password" required minlength="6">
</div>
<div class="mb-3">
<label class="form-label">Confirm Password <span class="text-danger">*</span></label>
<input type="password" class="form-control" name="confirm_password" required minlength="6">
</div>
<div class="mt-4">
<button type="submit" class="btn btn-primary">Update Password</button>
<a href="edit-admin.php?id=<?php echo $id; ?>" class="btn btn-light ms-2">Cancel</a>
</div>
</form>
</div></div></div></div></div></div></div></div>
<?php include('includes/scripts.php')?>
</body></html>