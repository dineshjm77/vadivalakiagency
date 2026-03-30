<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

function columnExists(mysqli $conn, string $table, string $column): bool {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && mysqli_num_rows($result) > 0;
}

$hasLinemanSalary = columnExists($conn, 'linemen', 'salary');
$hasLinemanCommission = columnExists($conn, 'linemen', 'commission');
$hasLinemanArea = columnExists($conn, 'linemen', 'assigned_area');
$hasLinemanLastLogin = columnExists($conn, 'linemen', 'last_login');

// Handle status toggle
if (isset($_GET['toggle_status'], $_GET['user_type'], $_GET['id'])) {
    $user_type = trim((string)$_GET['user_type']);
    $id = (int)$_GET['id'];

    $table = '';
    switch ($user_type) {
        case 'admin':
            $table = 'admin_users';
            break;
        case 'lineman':
            $table = 'linemen';
            break;
    }

    if ($table && $id > 0) {
        $sql = "UPDATE `$table` SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: manage-users.php?msg=status_updated');
        exit;
    }
}

// Handle delete user
if (isset($_GET['delete'], $_GET['user_type'], $_GET['id'])) {
    $user_type = trim((string)$_GET['user_type']);
    $id = (int)$_GET['id'];

    $table = '';
    switch ($user_type) {
        case 'admin':
            $table = 'admin_users';
            break;
        case 'lineman':
            $table = 'linemen';
            break;
    }

    if ($table && $id > 0) {
        if ($user_type === 'admin' && $id === (int)($_SESSION['user_id'] ?? 0)) {
            header('Location: manage-users.php?msg=cannot_delete_self');
            exit;
        }

        $sql = "DELETE FROM `$table` WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: manage-users.php?msg=deleted');
        exit;
    }
}

// Fetch administrators
$admins = [];
$adminSql = "SELECT id, name, email, username, role, status, last_login, created_at FROM admin_users ORDER BY created_at DESC";
$adminResult = mysqli_query($conn, $adminSql);
if ($adminResult) {
    while ($row = mysqli_fetch_assoc($adminResult)) {
        $admins[] = $row;
    }
}

// Fetch line men
$linemen = [];
$linemanSelect = "id, employee_id, full_name, email, phone, username, status, created_at";
$linemanSelect .= $hasLinemanArea ? ", assigned_area" : ", '' AS assigned_area";
$linemanSelect .= $hasLinemanLastLogin ? ", last_login" : ", NULL AS last_login";
$linemanSelect .= $hasLinemanSalary ? ", salary" : ", 0 AS salary";
$linemanSelect .= $hasLinemanCommission ? ", commission" : ", 0 AS commission";

$linemanSql = "SELECT $linemanSelect FROM linemen ORDER BY created_at DESC";
$linemanResult = mysqli_query($conn, $linemanSql);
if ($linemanResult) {
    while ($row = mysqli_fetch_assoc($linemanResult)) {
        $linemen[] = $row;
    }
}

$total_users = count($admins) + count($linemen);
$active_users = 0;
$inactive_users = 0;

foreach ($admins as $u) {
    if (($u['status'] ?? '') === 'active') {
        $active_users++;
    } else {
        $inactive_users++;
    }
}
foreach ($linemen as $u) {
    if (($u['status'] ?? '') === 'active') {
        $active_users++;
    } else {
        $inactive_users++;
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
                    <?php if ($_GET['msg'] === 'status_updated'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-circle me-2"></i> User status updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] === 'deleted'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-check-circle me-2"></i> User deleted successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php elseif ($_GET['msg'] === 'cannot_delete_self'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle me-2"></i> You cannot delete your own account!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

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
                                <div class="d-flex justify-content-between align-items-center h-100">
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

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-bs-toggle="tab" href="#admins-tab" role="tab">
                                                <i class="fas fa-user-shield me-1"></i> Administrators
                                                <span class="badge bg-primary ms-1"><?php echo count($admins); ?></span>
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-bs-toggle="tab" href="#linemen-tab" role="tab">
                                                <i class="fas fa-motorcycle me-1"></i> Line Men
                                                <span class="badge bg-warning ms-1"><?php echo count($linemen); ?></span>
                                            </a>
                                        </li>
                                    </ul>

                                    <div class="search-box" style="min-width: 280px;">
                                        <input type="text" class="form-control" id="userSearch" placeholder="Search users...">
                                        <i class="ri-search-line search-icon"></i>
                                    </div>
                                </div>

                                <div class="tab-content p-0 pt-2">
                                    <div class="tab-pane active" id="admins-tab" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered mb-0 user-table" id="adminsTable">
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
                                                            <td><?php echo (int)$admin['id']; ?></td>
                                                            <td><strong><?php echo htmlspecialchars((string)$admin['name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars((string)$admin['email']); ?></td>
                                                            <td><?php echo htmlspecialchars((string)$admin['username']); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo ($admin['role'] === 'super_admin') ? 'bg-danger' : 'bg-primary'; ?>">
                                                                    <?php echo ucfirst(str_replace('_', ' ', (string)$admin['role'])); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo ($admin['status'] === 'active') ? 'bg-success' : 'bg-secondary'; ?>">
                                                                    <?php echo ucfirst((string)$admin['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php echo !empty($admin['last_login']) ? date('d M Y h:i A', strtotime($admin['last_login'])) : '-'; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <a href="?toggle_status=1&user_type=admin&id=<?php echo (int)$admin['id']; ?>"
                                                                       class="btn btn-sm <?php echo ($admin['status'] === 'active') ? 'btn-warning' : 'btn-success'; ?>"
                                                                       onclick="return confirm('Change status?')">
                                                                        <i class="fas <?php echo ($admin['status'] === 'active') ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                                    </a>
                                                                    <?php if ((int)$admin['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                                                        <a href="?delete=1&user_type=admin&id=<?php echo (int)$admin['id']; ?>"
                                                                           class="btn btn-sm btn-danger"
                                                                           onclick="return confirm('Delete this admin? This action cannot be undone.')">
                                                                            <i class="fas fa-trash"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($admins)): ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted py-4">No administrators found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="tab-pane" id="linemen-tab" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered mb-0 user-table" id="linemenTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Employee ID</th>
                                                        <th>Name</th>
                                                        <th>Phone</th>
                                                        <th>Email</th>
                                                        <th>Username</th>
                                                        <th>Area</th>
                                                        <th>Status</th>
                                                        <th>Last Login</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($linemen as $lineman): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars((string)$lineman['employee_id']); ?></td>
                                                            <td><strong><?php echo htmlspecialchars((string)$lineman['full_name']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars((string)$lineman['phone']); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($lineman['email'] ?? '')); ?></td>
                                                            <td><?php echo htmlspecialchars((string)$lineman['username']); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($lineman['assigned_area'] ?? '-')); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo ($lineman['status'] === 'active') ? 'bg-success' : 'bg-secondary'; ?>">
                                                                    <?php echo ucfirst((string)$lineman['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php echo !empty($lineman['last_login']) ? date('d M Y h:i A', strtotime($lineman['last_login'])) : '-'; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <a href="?toggle_status=1&user_type=lineman&id=<?php echo (int)$lineman['id']; ?>"
                                                                       class="btn btn-sm <?php echo ($lineman['status'] === 'active') ? 'btn-warning' : 'btn-success'; ?>"
                                                                       onclick="return confirm('Change status?')">
                                                                        <i class="fas <?php echo ($lineman['status'] === 'active') ? 'fa-ban' : 'fa-check-circle'; ?>"></i>
                                                                    </a>
                                                                    <a href="?delete=1&user_type=lineman&id=<?php echo (int)$lineman['id']; ?>"
                                                                       class="btn btn-sm btn-danger"
                                                                       onclick="return confirm('Delete this line man? This action cannot be undone.')">
                                                                        <i class="fas fa-trash"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($linemen)): ?>
                                                        <tr>
                                                            <td colspan="9" class="text-center text-muted py-4">No line men found</td>
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
document.getElementById('userSearch').addEventListener('input', function () {
    const term = this.value.toLowerCase().trim();
    document.querySelectorAll('.user-table tbody tr').forEach(function (row) {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
});
</script>
</body>
</html>
