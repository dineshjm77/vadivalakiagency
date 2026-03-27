<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// For admin pages - check if user is admin
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// For lineman pages - check if user is lineman
$is_lineman = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'lineman';

// Redirect to login if not logged in (for protected pages)
// Add this condition on pages that require login
// if (!$is_logged_in) {
//     header('Location: ../login.php');
//     exit();
// }
?>

<head>
    <meta charset="utf-8" />
    
    <!-- Dynamic title with user role -->
    <?php
    $page_title = isset($page_title) ? $page_title . ' | ' : '';
    $role_text = '';
    
    if ($is_admin) {
        $role_text = 'Admin';
    } elseif ($is_lineman) {
        $role_text = 'Line Man';
    }
    
    $title = $page_title . ($role_text ? $role_text . ' | ' : '') . 'Ecommer';
    ?>
    
    <title><?php echo htmlspecialchars($title); ?></title>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
    <meta content="Themesbrand" name="author" />
    
   <link rel="apple-touch-icon" sizes="180x180" href="assets/fav/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="assets/fav/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/fav/favicon-16x16.png">
<link rel="manifest" href="assets/fav/site.webmanifest">

    <!-- Bootstrap Css -->
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css" />

    <!-- Icons Css -->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    
    <!-- App Css-->
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css" />

</head>