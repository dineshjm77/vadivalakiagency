<?php
// logout.php
session_start();
include('config/config.php');

// Clear remember token from database if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] == 'admin') {
        $sql = "UPDATE admin_users SET remember_token = NULL WHERE id = ?";
    } else if ($_SESSION['user_role'] == 'lineman') {
        $sql = "UPDATE linemen SET remember_token = NULL WHERE id = ?";
    }
    
    if (isset($sql)) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
    }
}

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
// Note: This will destroy the session, and not just the session data
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Clear the remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Clear all other cookies that might be related to the session
setcookie('PHPSESSID', '', time() - 3600, '/');
setcookie('session_token', '', time() - 3600, '/');

// Clear any browser cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Add headers to prevent back button access after logout
header("Location: index.php");
echo '<script>window.history.replaceState({}, document.title, "index.php");</script>';

// JavaScript to clear client-side storage
echo '<script>
    // Clear localStorage and sessionStorage
    localStorage.clear();
    sessionStorage.clear();
    
    // Clear form cache
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>';

// Redirect to login page
header("Location: index.php");
exit();
?>