<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "UPDATE creditors SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header('Location: creditors-list.php?msg=status_updated');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Check if there are any purchases or payments
    $check_sql = "SELECT COUNT(*) as count FROM creditor_purchases WHERE creditor_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($check_result);
    
    if ($row['count'] > 0) {
        header('Location: creditors-list.php?msg=cannot_delete');
        exit;
    }
    
    $sql = "DELETE FROM creditors WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        header('Location: creditors-list.php?msg=deleted');
        exit;
    }
}

// Fetch all creditors
$creditors = [];
$sql = "SELECT * FROM creditors ORDER BY vendor_name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $creditors[] = $row;
}

// Get statistics
$total_creditors = count($creditors);
$total_balance = 0;
$total_purchases = 0;
foreach ($creditors as $c) {
    $total_balance += $c['current_balance'];
    $total_purchases += $c['total_purchases'];
}
$active_creditors = 0;
foreach ($creditors as $c) {
    if ($c['status'] == 'active') $active_creditors++;
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
                            <h4 class="mb-0">Creditors List</h4>
                            <a href="add-creditor.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> Add New Creditor
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] == 'status_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i> Creditor status updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif ($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i> Creditor deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif ($_GET['msg'] == 'cannot_delete'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-2"></i> Cannot delete creditor with existing transactions!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-muted mb-1">Total Creditors</p>
                                        <h3 class="mb-0"><?php echo $total_creditors; ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-4">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-muted mb-1">Active Creditors</p>
                                        <h3 class="mb-0 text-success"><?php echo $active_creditors; ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-success-subtle text-success rounded-circle fs-4">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-muted mb-1">Total Balance</p>
                                        <h3 class="mb-0 text-warning"><?php echo '₹' . number_format($total_balance, 2); ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-warning-subtle text-warning rounded-circle fs-4">
                                            <i class="fas fa-rupee-sign"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-muted mb-1">Total Purchases</p>
                                        <h3 class="mb-0 text-info"><?php echo '₹' . number_format($total_purchases, 2); ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-info-subtle text-info rounded-circle fs-4">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Creditors Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered mb-0" id="creditorsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Vendor Name</th>
                                                <th>Company</th>
                                                <th>Phone</th>
                                                <th>GSTIN</th>
                                                <th>Total Purchases</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($creditors as $index => $creditor): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($creditor['vendor_name']); ?></strong>
                                                    <?php if ($creditor['contact_person']): ?>
                                                    <br><small class="text-muted">Contact: <?php echo htmlspecialchars($creditor['contact_person']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($creditor['company_name'] ?: '-'); ?></td>
                                                <td><?php echo $creditor['phone']; ?></td>
                                                <td><small><?php echo $creditor['gstin'] ?: '-'; ?></small></td>
                                                <td><?php echo '₹' . number_format($creditor['total_purchases'], 2); ?></td>
                                                <td class="<?php echo $creditor['current_balance'] > 0 ? 'text-warning' : 'text-success'; ?>">
                                                    <?php echo '₹' . number_format($creditor['current_balance'], 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $creditor['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo ucfirst($creditor['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="view-creditor.php?id=<?php echo $creditor['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit-creditor.php?id=<?php echo $creditor['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?toggle_status=1&id=<?php echo $creditor['id']; ?>" 
                                                           class="btn btn-sm <?php echo $creditor['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                           onclick="return confirm('Change status?')">
                                                            <i class="fas <?php echo $creditor['status'] == 'active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                        </a>
                                                        <?php if ($creditor['total_purchases'] == 0): ?>
                                                        <a href="?delete=1&id=<?php echo $creditor['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Delete this creditor? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($creditors)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
                                                    <i class="fas fa-building fs-1 d-block mb-2"></i>
                                                    No creditors found. Click "Add New Creditor" to add one.
                                                </td>
                                            </tr>
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

<script>
$(document).ready(function() {
    $('#creditorsTable').DataTable({
        "pageLength": 25,
        "order": [[1, "asc"]],
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ creditors"
        }
    });
});
</script>

</body>
</html>