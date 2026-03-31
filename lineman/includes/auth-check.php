<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedRoles = ['lineman', 'admin', 'super_admin'];
$currentRole = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : '';

if (!in_array($currentRole, $allowedRoles, true)) {
    header('Location: ../index.php');
    exit;
}

if ($currentRole === 'lineman' && !isset($_SESSION['lineman_id']) && isset($_SESSION['user_id'])) {
    $_SESSION['lineman_id'] = (int) $_SESSION['user_id'];
}
?>
