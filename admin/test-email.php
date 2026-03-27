<?php
// test-email.php
include('config/config.php');
header('Content-Type: application/json');

if (isset($_POST['email'])) {
    $to = mysqli_real_escape_string($conn, $_POST['email']);
    $settings_sql = "SELECT * FROM business_settings WHERE id = 1";
    $settings_result = mysqli_query($conn, $settings_sql);
    $settings = mysqli_fetch_assoc($settings_result);
    
    // Use PHPMailer or similar for actual email sending
    // This is a simplified example
    $subject = "Test Email from " . $settings['business_name'];
    $message = "This is a test email to verify your email settings.";
    $headers = "From: " . $settings['from_email'];
    
    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => true, 'message' => 'Test email sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No email provided']);
}

mysqli_close($conn);
?>