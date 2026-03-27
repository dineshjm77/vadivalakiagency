<!doctype html>
<html lang="en">

<?php include('includes/head.php')?>

<body data-sidebar="dark">

<!-- Loader -->
<?php include('includes/pre-loader.php')?>

<!-- Begin page -->
<div id="layout-wrapper">

<?php include('includes/topbar.php')?>    

    <!-- ========== Left Sidebar Start ========== -->
    <div class="vertical-menu">

        <div data-simplebar class="h-100">

            <!--- Sidemenu -->
            <?php include('includes/sidebar.php')?>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- Left Sidebar End -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
           
            <div class="container-fluid">

                <!-- end page title -->

                <?php
                // Telegram Bot Configuration
                define('TELEGRAM_BOT_TOKEN', '8463402032:AAE2oITsdGs-NasVPuRakSS_uCnuM-xlXoA');
                define('TELEGRAM_CHAT_ID', '1203355744'); // Your chat ID
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $name = trim($_POST['name'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $subject = trim($_POST['subject'] ?? '');
                    $message = trim($_POST['message'] ?? '');
                    
                    $errors = [];
                    
                    // Validation
                    if (empty($name)) $errors[] = 'Name is required';
                    if (empty($email) && empty($phone)) $errors[] = 'Email or phone is required';
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email';
                    if (empty($subject)) $errors[] = 'Subject is required';
                    if (empty($message)) $errors[] = 'Message is required';
                    
                    if (empty($errors)) {
                        // Send Telegram notification
                        $telegram_message = "
                        📨 *New Contact Form Submission*
                        
                        👤 *Name:* $name
                        📧 *Email:* " . ($email ?: 'Not provided') . "
                        📱 *Phone:* " . ($phone ?: 'Not provided') . "
                        
                        📋 *Subject:* $subject
                        
                        💬 *Message:*
                        $message
                        
                        🕒 *Time:* " . date('d M, Y H:i:s') . "
                        ";
                        
                        $telegram_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
                        $telegram_data = [
                            'chat_id' => TELEGRAM_CHAT_ID,
                            'text' => $telegram_message,
                            'parse_mode' => 'Markdown'
                        ];
                        
                        $ch = curl_init($telegram_url);
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $telegram_data);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $telegram_result = curl_exec($ch);
                        curl_close($ch);
                        
                        // Send email
                        $to = "info@ecommer.in, help@ecommer.in";
                        $email_subject = "Contact Form: $subject";
                        $email_body = "
                        Name: $name
                        Email: $email
                        Phone: $phone
                        Subject: $subject
                        
                        Message:
                        $message
                        
                        Sent: " . date('Y-m-d H:i:s') . "
                        ";
                        
                        $headers = "From: APR Water Agencies <no-reply@aprwater.com>\r\n";
                        
                        // Send email
                        mail($to, $email_subject, $email_body, $headers);
                        
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>
                                Thank you! Your message has been sent. We will contact you soon.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle me-2"></i>
                                <strong>Please fix these errors:</strong><br>
                                ' . implode('<br>', $errors) . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    }
                }
                ?>

                <div class="row">
                    <!-- Contact Details -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">
                                    <i class="mdi mdi-contact-mail text-primary me-1"></i> Contact Information
                                </h5>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">
                                        <i class="mdi mdi-email-outline me-2"></i> Email Support
                                    </h6>
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="mdi mdi-email text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">General Inquiries</h6>
                                            <a href="mailto:info@ecommer.in" class="text-muted">info@ecommer.in</a>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <i class="mdi mdi-help-circle text-warning"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Technical Support</h6>
                                            <a href="mailto:help@ecommer.in" class="text-muted">help@ecommer.in</a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">
                                        <i class="mdi mdi-phone-outline me-2"></i> Phone Support
                                    </h6>
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-shrink-0">
                                            <i class="mdi mdi-phone text-success"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Primary Number</h6>
                                            <a href="tel:+919003552650" class="text-muted">+91 9003552650</a>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <i class="mdi mdi-phone-in-talk text-info"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">Secondary Number</h6>
                                            <a href="tel:+919003559510" class="text-muted">+91 9003559510</a>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="mdi mdi-clock-outline me-1"></i>
                                            Mon–Sat, 10 AM–7 PM (IST)
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">
                                        <i class="mdi mdi-map-marker-outline me-2"></i> Address
                                    </h6>
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <i class="mdi mdi-office-building text-danger"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="mb-1 small">
                                                A15, Sandhanagounder Complex,<br>
                                                Sogathur X Road,<br>
                                                Pennagaram Main Road,<br>
                                                Dharmapuri, 636809<br>
                                                Tamil Nadu, India
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h6 class="text-muted mb-3">
                                        <i class="mdi mdi-telegram me-2"></i> Telegram Support
                                    </h6>
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <i class="mdi mdi-send text-info"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="mb-1">
                                                <a href="https://t.me/aprsupplybot" target="_blank" class="text-info">
                                                    <i class="mdi mdi-telegram me-1"></i>
                                                    @aprsupplybot
                                                </a>
                                            </p>
                                            <small class="text-muted">
                                                Get instant notifications via Telegram
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Form -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">
                                    <i class="mdi mdi-message-text-outline text-success me-1"></i> Contact Form
                                </h5>
                                
                                <form method="POST" action="help-support.php">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="name" 
                                                       placeholder="Enter your full name" required
                                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       placeholder="you@example.com"
                                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                                <small class="text-muted">Required if phone number not provided</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" name="phone" 
                                                       placeholder="+91 9876543210"
                                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                                <small class="text-muted">Required if email not provided</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="subject" 
                                                       placeholder="Brief description of your inquiry" required
                                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="message" rows="6" 
                                                  placeholder="Please provide detailed information..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-success">
                                            <i class="mdi mdi-send me-1"></i> Send Message
                                        </button>
                                        <button type="reset" class="btn btn-light ms-2">
                                            <i class="mdi mdi-refresh me-1"></i> Reset
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="mdi mdi-information-outline me-1"></i>
                                            Your message will be sent to both email and Telegram for instant notification.
                                        </small>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include('includes/footer.php') ?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Simple form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const email = this.querySelector('input[name="email"]').value.trim();
    const phone = this.querySelector('input[name="phone"]').value.trim();
    
    if (!email && !phone) {
        e.preventDefault();
        alert('Please provide either email or phone number');
        return false;
    }
    
    if (email && !validateEmail(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Sending...';
    
    return true;
});

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Auto-fill form from session (if available)
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['user_name'])): ?>
    document.querySelector('input[name="name"]').value = '<?php echo addslashes($_SESSION["user_name"]); ?>';
    <?php endif; ?>
    
    <?php if (isset($_SESSION['user_email'])): ?>
    document.querySelector('input[name="email"]').value = '<?php echo addslashes($_SESSION["user_email"]); ?>';
    <?php endif; ?>
    
    <?php if (isset($_SESSION['user_phone'])): ?>
    document.querySelector('input[name="phone"]').value = '<?php echo addslashes($_SESSION["user_phone"]); ?>';
    <?php endif; ?>
});
</script>

</body>

</html>