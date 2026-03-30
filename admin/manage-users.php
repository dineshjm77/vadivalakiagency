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
if (isset($_GET['toggle_status']) && isset($_GET['user_type']) && isset($_GET['id'])) {
    $user_type = $_GET['user_type'];
    $id = intval($_GET['id']);
    
    $table = '';
    $status_field = 'status';
    
    switch($user_type) {
        case 'admin':
            $table = 'admin_users';
            break;
        case 'collection':
            $table = 'collection_staff';
            break;
        case 'distributor':
            $table = 'distributors';
            break;
        case 'lineman':
            $table = 'linemen';
            break;
    }
    
    if ($table) {
        $sql = "UPDATE $table SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        header('Location: manage-users.php?msg=status_updated');
        exit;
    }
}

// Handle delete user
if (isset($_GET['delete']) && isset($_GET['user_type']) && isset($_GET['id'])) {
    $user_type = $_GET['user_type'];
    $id = intval($_GET['id']);
    
    $table = '';
    switch($user_type) {
        case 'admin':
            $table = 'admin_users';
            break;
        case 'collection':
            $table = 'collection_staff';
            break;
        case 'distributor':
            $table = 'distributors';
            break;
        case 'lineman':
            $table = 'linemen';
            break;
    }
    
    if ($table && $id != $_SESSION['user_id']) {
        $sql = "DELETE FROM $table WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        header('Location: manage-users.php?msg=deleted');
        exit;
    } else {
        header('Location: manage-users.php?msg=cannot_delete_self');
        exit;
    }
}

// Fetch all users
$admins = [];
$sql = "SELECT id, name, email, username, role, status, last_login, created_at FROM admin_users ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $admins[] = $row;
}

$collection_staff = [];
$sql = "SELECT * FROM collection_staff ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $collection_staff[] = $row;
}

$distributors = [];
$sql = "SELECT * FROM distributors ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $distributors[] = $row;
}

$linemen = [];
$sql = "SELECT * FROM linemen ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $linemen[] = $row;
}

// Get counts
$total_users = count($admins) + count($collection_staff) + count($distributors) + count($linemen);
$active_users = 0;
$inactive_users = 0;

foreach($admins as $u) { if($u['status']=='active') $active_users++; else $inactive_users++; }
foreach($collection_staff as $u) { if($u['status']=='active') $active_users++; else $inactive_users++; }
foreach($distributors as $u) { if($u['status']=='active') $active_users++; else $inactive_users++; }
foreach($linemen as $u) { if($u['status']=='active') $active_users++; else $inactive_users++; }
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
                
                <!-- Page Title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-flex align-items-center justify-content-between">
                            <h4 class="mb-0">Manage Users</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">User Management</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] == 'status_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i> User status updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif ($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-circle me-2"></i> User deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php elseif ($_GET['msg'] == 'cannot_delete_self'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle me-2"></i> You cannot delete your own account!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <p class="text-muted mb-1">Total Users</p>
                                        <h3 class="mb-0"><?php echo $total_users; ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-4">
                                            <i class="fas fa-users"></i>
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
                                        <p class="text-muted mb-1">Active Users</p>
                                        <h3 class="mb-0 text-success"><?php echo $active_users; ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-success-subtle text-success rounded-circle fs-4">
                                            <i class="fas fa-user-check"></i>
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
                                        <p class="text-muted mb-1">Inactive Users</p>
                                        <h3 class="mb-0 text-warning"><?php echo $inactive_users; ?></h3>
                                    </div>
                                    <div class="avatar-sm">
                                        <div class="avatar-title bg-warning-subtle text-warning rounded-circle fs-4">
                                            <i class="fas fa-user-slash"></i>
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
                                        <p class="text-muted mb-1">Add New</p>
                                        <a href="add-user.php" class="btn btn-primary">
                                            <i class="fas fa-plus-circle me-1"></i> Add User
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Tables Tabs -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#admins-tab" role="tab">
                                            <i class="fas fa-user-shield me-1"></i> Administrators 
                                            <span class="badge bg-primary ms-1"><?php echo count($admins); ?></span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#collection-tab" role="tab">
                                            <i class="fas fa-user-tie me-1"></i> Collection Staff
                                            <span class="badge bg-info ms-1"><?php echo count($collection_staff); ?></span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#distributors-tab" role="tab">
                                            <i class="fas fa-truck me-1"></i> Distributors
                                            <span class="badge bg-success ms-1"><?php echo count($distributors); ?></span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#linemen-tab" role="tab">
                                            <i class="fas fa-motorcycle me-1"></i> Line Men
                                            <span class="badge bg-warning ms-1"><?php echo count($linemen); ?></span>
                                        </a>
                                    </li>
                                </ul>
                                
                                <div class="tab-content p-3">
                                    
                                    <!-- Administrators Table -->
                                    <div class="tab-pane active" id="admins-tab" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Username</th>
                                                        <th>Role</th>
                                                        <th>Status</th>
                                                        <th>Last Login</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($admins as $admin): ?>
                                                    <tr>
                                                        <td><?php echo $admin['id']; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($admin['name']); ?></strong>
                                                        </td>
                                                        <td><?php echo $admin['email']; ?></td>
                                                        <td><?php echo $admin['username']; ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $admin['role'] == 'super_admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                                                <?php echo ucfirst($admin['role']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $admin['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($admin['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php echo $admin['last_login'] ? date('d M Y H:i', strtotime($admin['last_login'])) : '-'; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="?toggle_status=1&user_type=admin&id=<?php echo $admin['id']; ?>" 
                                                                   class="btn btn-sm <?php echo $admin['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                                   onclick="return confirm('Change status?')">
                                                                    <i class="fas <?php echo $admin['status'] == 'active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                                    <?php echo $admin['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </a>
                                                                <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                                <a href="?delete=1&user_type=admin&id=<?php echo $admin['id']; ?>" 
                                                                   class="btn btn-sm btn-danger"
                                                                   onclick="return confirm('Delete this admin? This action cannot be undone.')">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($admins)): ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">No administrators found</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Collection Staff Table -->
                                    <div class="tab-pane" id="collection-tab" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Employee ID</th>
                                                        <th>Name</th>
                                                        <th>Phone</th>
                                                        <th>Username</th>
                                                        <th>Area</th>
                                                        <th>Total Collected</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($collection_staff as $staff): ?>
                                                    <tr>
                                                        <td><?php echo $staff['employee_id']; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                                                        </td>
                                                        <td><?php echo $staff['phone']; ?></td>
                                                        <td><?php echo $staff['username']; ?></td>
                                                        <td><?php echo $staff['assigned_area'] ?: '-'; ?></td>
                                                        <td><?php echo '₹' . number_format($staff['total_collected'], 2); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $staff['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($staff['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="?toggle_status=1&user_type=collection&id=<?php echo $staff['id']; ?>" 
                                                                   class="btn btn-sm <?php echo $staff['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                                   onclick="return confirm('Change status?')">
                                                                    <i class="fas <?php echo $staff['status'] == 'active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                                    <?php echo $staff['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </a>
                                                                <a href="?delete=1&user_type=collection&id=<?php echo $staff['id']; ?>" 
                                                                   class="btn btn-sm btn-danger"
                                                                   onclick="return confirm('Delete this collection staff? This action cannot be undone.')">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($collection_staff)): ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">No collection staff found</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Distributors Table -->
                                    <div class="tab-pane" id="distributors-tab" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Company</th>
                                                        <th>Contact Person</th>
                                                        <th>Phone</th>
                                                        <th>Commission</th>
                                                        <th>Total Sales</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($distributors as $dist): ?>
                                                    <tr>
                                                        <td><?php echo $dist['distributor_code']; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($dist['company_name']); ?></strong>
                                                        </td>
                                                        <td><?php echo $dist['contact_person']; ?></td>
                                                        <td><?php echo $dist['phone']; ?></td>
                                                        <td><?php echo $dist['commission_percentage']; ?>%</td>
                                                        <td><?php echo '₹' . number_format($dist['total_sales'], 2); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $dist['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($dist['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="?toggle_status=1&user_type=distributor&id=<?php echo $dist['id']; ?>" 
                                                                   class="btn btn-sm <?php echo $dist['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                                   onclick="return confirm('Change status?')">
                                                                    <i class="fas <?php echo $dist['status'] == 'active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                                    <?php echo $dist['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </a>
                                                                <a href="?delete=1&user_type=distributor&id=<?php echo $dist['id']; ?>" 
                                                                   class="btn btn-sm btn-danger"
                                                                   onclick="return confirm('Delete this distributor? This action cannot be undone.')">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($distributors)): ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">No distributors found</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Line Men Table -->
                                    <div class="tab-pane" id="linemen-tab" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Employee ID</th>
                                                        <th>Name</th>
                                                        <th>Phone</th>
                                                        <th>Username</th>
                                                        <th>Area</th>
                                                        <th>Salary</th>
                                                        <th>Commission</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($linemen as $lineman): ?>
                                                    <tr>
                                                        <td><?php echo $lineman['employee_id']; ?></td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($lineman['full_name']); ?></strong>
                                                        </td>
                                                        <td><?php echo $lineman['phone']; ?></td>
                                                        <td><?php echo $lineman['username']; ?></td>
                                                        <td><?php echo $lineman['assigned_area'] ?: '-'; ?></td>
                                                        <td><?php echo '₹' . number_format($lineman['salary'], 2); ?></td>
                                                        <td><?php echo $lineman['commission']; ?>%</td>
                                                        <td>
                                                            <span class="badge <?php echo $lineman['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                <?php echo ucfirst($lineman['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <a href="?toggle_status=1&user_type=lineman&id=<?php echo $lineman['id']; ?>" 
                                                                   class="btn btn-sm <?php echo $lineman['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>"
                                                                   onclick="return confirm('Change status?')">
                                                                    <i class="fas <?php echo $lineman['status'] == 'active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                                    <?php echo $lineman['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </a>
                                                                <a href="?delete=1&user_type=lineman&id=<?php echo $lineman['id']; ?>" 
                                                                   class="btn btn-sm btn-danger"
                                                                   onclick="return confirm('Delete this line man? This action cannot be undone.')">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($linemen)): ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center text-muted">No line men found</td>
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
            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>

<script>
// Search functionality
document.querySelectorAll('.nav-link').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function(e) {
        // Optional: Add search filter for each table
    });
});
</script>

</body>
</html>