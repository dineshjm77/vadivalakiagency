<?php
// auth-check.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// You can add additional security checks here if needed
?>