<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vendor_name = mysqli_real_escape_string($conn, $_POST['vendor_name']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $gstin = mysqli_real_escape_string($conn, $_POST['gstin']);
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
    $opening_balance = floatval($_POST['opening_balance']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    $sql = "INSERT INTO creditors (vendor_name, address, phone, gstin, company_name, email, contact_person, payment_terms, opening_balance, current_balance, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssssdds", $vendor_name, $address, $phone, $gstin, $company_name, $email, $contact_person, $payment_terms, $opening_balance, $opening_balance, $notes);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_message = "Creditor added successfully!";
    } else {
        $error_message = "Error adding creditor: " . mysqli_error($conn);
    }
}
?>

<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h4 class="mb-0">Add New Creditor</h4>
                            <a href="creditors-list.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Add Creditor Form -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vendor Name *</label>
                                            <input type="text" class="form-control" name="vendor_name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" name="company_name">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone *</label>
                                            <input type="text" class="form-control" name="phone" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contact Person</label>
                                            <input type="text" class="form-control" name="contact_person">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">GSTIN</label>
                                            <input type="text" class="form-control" name="gstin">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Payment Terms</label>
                                            <select class="form-select" name="payment_terms">
                                                <option value="cash">Cash</option>
                                                <option value="credit_7">7 Days Credit</option>
                                                <option value="credit_15">15 Days Credit</option>
                                                <option value="credit_30">30 Days Credit</option>
                                                <option value="credit_45">45 Days Credit</option>
                                                <option value="credit_60">60 Days Credit</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Opening Balance</label>
                                            <input type="number" step="0.01" class="form-control" name="opening_balance" value="0">
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3"></textarea>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="notes" rows="2"></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Save Creditor
                                            </button>
                                            <button type="reset" class="btn btn-secondary ms-2">
                                                <i class="fas fa-undo me-1"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
</body>
</html>