<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

$creditor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($creditor_id == 0) {
    header('Location: creditors-list.php');
    exit;
}

// Fetch creditor details
$sql = "SELECT * FROM creditors WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $creditor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$creditor = mysqli_fetch_assoc($result);

if (!$creditor) {
    header('Location: creditors-list.php');
    exit;
}

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
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    $sql = "UPDATE creditors SET vendor_name = ?, address = ?, phone = ?, gstin = ?, company_name = ?, email = ?, contact_person = ?, payment_terms = ?, notes = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssssssssi", $vendor_name, $address, $phone, $gstin, $company_name, $email, $contact_person, $payment_terms, $notes, $creditor_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_message = "Creditor updated successfully!";
        // Refresh data
        $sql = "SELECT * FROM creditors WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $creditor_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $creditor = mysqli_fetch_assoc($result);
    } else {
        $error_message = "Error updating creditor: " . mysqli_error($conn);
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
                            <h4 class="mb-0">Edit Creditor</h4>
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

                <!-- Edit Creditor Form -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Vendor Name *</label>
                                            <input type="text" class="form-control" name="vendor_name" value="<?php echo htmlspecialchars($creditor['vendor_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($creditor['company_name']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone *</label>
                                            <input type="text" class="form-control" name="phone" value="<?php echo $creditor['phone']; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($creditor['email']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Contact Person</label>
                                            <input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($creditor['contact_person']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">GSTIN</label>
                                            <input type="text" class="form-control" name="gstin" value="<?php echo $creditor['gstin']; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Payment Terms</label>
                                            <select class="form-select" name="payment_terms">
                                                <option value="cash" <?php echo $creditor['payment_terms'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                <option value="credit_7" <?php echo $creditor['payment_terms'] == 'credit_7' ? 'selected' : ''; ?>>7 Days Credit</option>
                                                <option value="credit_15" <?php echo $creditor['payment_terms'] == 'credit_15' ? 'selected' : ''; ?>>15 Days Credit</option>
                                                <option value="credit_30" <?php echo $creditor['payment_terms'] == 'credit_30' ? 'selected' : ''; ?>>30 Days Credit</option>
                                                <option value="credit_45" <?php echo $creditor['payment_terms'] == 'credit_45' ? 'selected' : ''; ?>>45 Days Credit</option>
                                                <option value="credit_60" <?php echo $creditor['payment_terms'] == 'credit_60' ? 'selected' : ''; ?>>60 Days Credit</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($creditor['address']); ?></textarea>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Notes</label>
                                            <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($creditor['notes']); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Update Creditor
                                            </button>
                                            <a href="creditors-list.php" class="btn btn-secondary ms-2">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </a>
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