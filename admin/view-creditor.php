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

// Fetch payment history
$payments = [];
$sql = "SELECT * FROM creditor_payments WHERE creditor_id = ? ORDER BY payment_date DESC LIMIT 20";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $creditor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $payments[] = $row;
}

// Fetch purchase history
$purchases = [];
$sql = "SELECT * FROM creditor_purchases WHERE creditor_id = ? ORDER BY purchase_date DESC LIMIT 20";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $creditor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $purchases[] = $row;
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
                            <h4 class="mb-0">Creditor Details</h4>
                            <div>
                                <a href="edit-creditor.php?id=<?php echo $creditor_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <a href="creditors-list.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Creditor Information -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Creditor Information</h5>
                                <hr>
                                <p><strong>Vendor Name:</strong> <?php echo htmlspecialchars($creditor['vendor_name']); ?></p>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($creditor['company_name'] ?: '-'); ?></p>
                                <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($creditor['contact_person'] ?: '-'); ?></p>
                                <p><strong>Phone:</strong> <?php echo $creditor['phone']; ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($creditor['email'] ?: '-'); ?></p>
                                <p><strong>GSTIN:</strong> <?php echo $creditor['gstin'] ?: '-'; ?></p>
                                <p><strong>Payment Terms:</strong> <?php echo str_replace('_', ' ', ucfirst($creditor['payment_terms'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge <?php echo $creditor['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($creditor['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Financial Summary</h5>
                                <hr>
                                <p><strong>Opening Balance:</strong> <?php echo '₹' . number_format($creditor['opening_balance'], 2); ?></p>
                                <p><strong>Total Purchases:</strong> <?php echo '₹' . number_format($creditor['total_purchases'], 2); ?></p>
                                <p><strong>Total Paid:</strong> <?php echo '₹' . number_format($creditor['total_paid'], 2); ?></p>
                                <p><strong>Current Balance:</strong> 
                                    <span class="<?php echo $creditor['current_balance'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                        <?php echo '₹' . number_format($creditor['current_balance'], 2); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Address</h5>
                                <hr>
                                <p><?php echo nl2br(htmlspecialchars($creditor['address'] ?: 'No address provided')); ?></p>
                                <?php if ($creditor['notes']): ?>
                                <hr>
                                <h6>Notes:</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($creditor['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase History -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Purchase History</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr><th>Date</th><th>Invoice No</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($purchases as $purchase): ?>
                                             <tr>
                                                 <td><?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></td>
                                                 <td><?php echo htmlspecialchars($purchase['invoice_no'] ?: '-'); ?></td>
                                                 <td><?php echo '₹' . number_format($purchase['total_amount'], 2); ?></td>
                                                 <td><?php echo '₹' . number_format($purchase['paid_amount'], 2); ?></td>
                                                 <td><?php echo '₹' . number_format($purchase['total_amount'] - $purchase['paid_amount'], 2); ?></td>
                                                 <td>
                                                     <span class="badge bg-<?php echo $purchase['payment_status'] == 'paid' ? 'success' : ($purchase['payment_status'] == 'partial' ? 'warning' : 'danger'); ?>">
                                                         <?php echo ucfirst($purchase['payment_status']); ?>
                                                     </span>
                                                 </td>
                                             </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($purchases)): ?>
                                            <tr><td colspan="6" class="text-center text-muted">No purchase records found</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Payment History</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Notes</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                             <tr>
                                                 <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                                 <td class="text-success"><?php echo '₹' . number_format($payment['amount'], 2); ?></td>
                                                 <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                 <td><?php echo htmlspecialchars($payment['reference_no'] ?: '-'); ?></td>
                                                 <td><?php echo htmlspecialchars($payment['notes'] ?: '-'); ?></td>
                                             </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($payments)): ?>
                                            <tr><td colspan="5" class="text-center text-muted">No payment records found</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
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