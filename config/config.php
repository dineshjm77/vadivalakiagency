<?php
// Database Configuration
$db_host = "srv1740.hstgr.io";
$db_user = "u966043993_vadivalaki";
$db_pass = "Vadivalaki@29";
$db_name = "u966043993_vadivalaki";

// Create Database Connection
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check Connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8");

// Timezone
date_default_timezone_set('Asia/Kolkata');
?>