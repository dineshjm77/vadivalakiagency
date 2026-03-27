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

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Line Man Details</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="lineman-list.php">Line Men</a></li>
                                    <li class="breadcrumb-item active">View Details</li>
                                </ol>
                            </div>

                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <?php
                // Database connection
                include('config/config.php');
                
                // Check if ID is provided
                if (!isset($_GET['id']) || empty($_GET['id'])) {
                    echo '<div class="alert alert-danger">Line Man ID not provided.</div>';
                    echo '<a href="lineman-list.php" class="btn btn-primary mt-3">Back to List</a>';
                    exit();
                }
                
                $lineman_id = mysqli_real_escape_string($conn, $_GET['id']);
                
                // Fetch line man details
                $sql = "SELECT * FROM linemen WHERE id = '$lineman_id'";
                $result = mysqli_query($conn, $sql);
                
                if (!$result || mysqli_num_rows($result) == 0) {
                    echo '<div class="alert alert-danger">Line Man not found.</div>';
                    echo '<a href="lineman-list.php" class="btn btn-primary mt-3">Back to List</a>';
                    mysqli_close($conn);
                    exit();
                }
                
                $lineman = mysqli_fetch_assoc($result);
                
                // Fetch additional statistics
                $stats_sql = "SELECT 
                    COUNT(DISTINCT customer_id) as total_customers,
                    COUNT(*) as total_orders,
                    SUM(grand_total) as total_sales,
                    AVG(grand_total) as avg_order_value
                    FROM orders WHERE lineman_id = '$lineman_id'";
                
                $stats_result = mysqli_query($conn, $stats_sql);
                $stats = mysqli_fetch_assoc($stats_result);
                
                // Fetch recent orders
                $orders_sql = "SELECT o.*, c.shop_name, c.owner_name 
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id 
                    WHERE o.lineman_id = '$lineman_id' 
                    ORDER BY o.created_at DESC 
                    LIMIT 5";
                $orders_result = mysqli_query($conn, $orders_sql);
                
                mysqli_close($conn);
                
                // Format dates
                $joined_date = date('d M, Y', strtotime($lineman['created_at']));
                $last_login = $lineman['last_login'] ? date('d M, Y H:i', strtotime($lineman['last_login'])) : 'Never';
                
                // Status badge color
                $status_class = '';
                $status_text = ucfirst(str_replace('_', ' ', $lineman['status']));
                if ($lineman['status'] == 'active') $status_class = 'bg-success';
                elseif ($lineman['status'] == 'inactive') $status_class = 'bg-danger';
                elseif ($lineman['status'] == 'on_leave') $status_class = 'bg-warning';
                ?>

                <!-- Profile Header -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-4">
                                        <div class="avatar-xxl">
                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle display-1">
                                                <?php echo strtoupper(substr($lineman['full_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="row">
                                            <div class="col-lg-8">
                                                <h4 class="card-title mb-1"><?php echo $lineman['full_name']; ?></h4>
                                                <p class="text-muted mb-2"><?php echo $lineman['employee_id']; ?></p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge <?php echo $status_class; ?> font-size-14"><?php echo $status_text; ?></span>
                                                    <span class="badge bg-info font-size-14">
                                                        <i class="mdi mdi-map-marker me-1"></i> <?php echo $lineman['assigned_area']; ?>
                                                    </span>
                                                </div>
                                                <div class="mt-3">
                                                    <p class="text-muted mb-0">
                                                        <i class="mdi mdi-account-outline me-1"></i> Username: <?php echo $lineman['username']; ?>
                                                    </p>
                                                    <p class="text-muted mb-0">
                                                        <i class="mdi mdi-calendar-check me-1"></i> Joined: <?php echo $joined_date; ?>
                                                    </p>
                                                    <p class="text-muted mb-0">
                                                        <i class="mdi mdi-clock-outline me-1"></i> Last Login: <?php echo $last_login; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="mt-3 mt-lg-0">
                                                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                                        <a href="lineman-edit.php?id=<?php echo $lineman['id']; ?>" class="btn btn-primary">
                                                            <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                        </a>
                                                        <a href="lineman-list.php" class="btn btn-light">
                                                            <i class="mdi mdi-arrow-left me-1"></i> Back
                                                        </a>
                                                        <div class="dropdown">
                                                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="mdi mdi-dots-horizontal"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item" href="#" onclick="resetPassword(<?php echo $lineman['id']; ?>, '<?php echo $lineman['full_name']; ?>')">
                                                                        <i class="mdi mdi-key-change me-1"></i> Reset Password
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="#" onclick="sendCredentials(<?php echo $lineman['id']; ?>)">
                                                                        <i class="mdi mdi-email-send me-1"></i> Send Credentials
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger" href="delete-lineman.php?id=<?php echo $lineman['id']; ?>" onclick="return confirm('Are you sure you want to delete this line man?')">
                                                                        <i class="mdi mdi-delete-outline me-1"></i> Delete
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-account-group"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Customers</p>
                                        <h4 class="mb-0"><?php echo $stats['total_customers'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle text-success rounded-2 fs-2">
                                            <i class="mdi mdi-cart"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Orders</p>
                                        <h4 class="mb-0"><?php echo $stats['total_orders'] ?? 0; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2">
                                            <i class="mdi mdi-currency-inr"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Sales</p>
                                        <h4 class="mb-0">₹<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-info-subtle text-info rounded-2 fs-2">
                                            <i class="mdi mdi-chart-line"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Avg. Order</p>
                                        <h4 class="mb-0">₹<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-account-circle-outline me-1"></i> Personal Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <th class="ps-0" width="40%">Full Name:</th>
                                                <td class="text-muted"><?php echo $lineman['full_name']; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Employee ID:</th>
                                                <td class="text-muted">
                                                    <span class="badge bg-primary-subtle text-primary"><?php echo $lineman['employee_id']; ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Email:</th>
                                                <td class="text-muted">
                                                    <?php echo !empty($lineman['email']) ? $lineman['email'] : '<span class="text-muted">Not provided</span>'; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Phone:</th>
                                                <td class="text-muted"><?php echo $lineman['phone']; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Address:</th>
                                                <td class="text-muted">
                                                    <?php 
                                                    $address_parts = [];
                                                    if (!empty($lineman['address'])) $address_parts[] = $lineman['address'];
                                                    if (!empty($lineman['city'])) $address_parts[] = $lineman['city'];
                                                    if (!empty($lineman['state'])) $address_parts[] = $lineman['state'];
                                                    if (!empty($lineman['pincode'])) $address_parts[] = $lineman['pincode'];
                                                    
                                                    echo !empty($address_parts) ? implode(', ', $address_parts) : '<span class="text-muted">Not provided</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Assigned Area:</th>
                                                <td class="text-muted">
                                                    <span class="badge bg-info-subtle text-info">
                                                        <i class="mdi mdi-map-marker me-1"></i> <?php echo $lineman['assigned_area']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Details -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-briefcase-outline me-1"></i> Employment Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <th class="ps-0" width="40%">Status:</th>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Salary:</th>
                                                <td class="text-muted">
                                                    <?php echo $lineman['salary'] > 0 ? '₹' . number_format($lineman['salary'], 2) . ' per month' : '<span class="text-muted">Not set</span>'; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Commission:</th>
                                                <td class="text-muted">
                                                    <?php echo $lineman['commission'] > 0 ? $lineman['commission'] . '% per sale' : '<span class="text-muted">No commission</span>'; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Username:</th>
                                                <td class="text-muted"><?php echo $lineman['username']; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Joined Date:</th>
                                                <td class="text-muted"><?php echo $joined_date; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Last Login:</th>
                                                <td class="text-muted"><?php echo $last_login; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Total Customers:</th>
                                                <td class="text-muted"><?php echo $stats['total_customers'] ?? 0; ?> assigned customers</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="mdi mdi-history me-1"></i> Recent Orders
                                    </h5>
                                    <a href="orders.php?lineman=<?php echo $lineman['id']; ?>" class="btn btn-sm btn-primary">
                                        View All Orders
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order #</th>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($order = mysqli_fetch_assoc($orders_result)): 
                                                $order_status_class = '';
                                                if ($order['order_status'] == 'completed') $order_status_class = 'bg-success';
                                                elseif ($order['order_status'] == 'pending') $order_status_class = 'bg-warning';
                                                elseif ($order['order_status'] == 'cancelled') $order_status_class = 'bg-danger';
                                                
                                                $payment_status_class = '';
                                                if ($order['payment_status'] == 'paid') $payment_status_class = 'bg-success';
                                                elseif ($order['payment_status'] == 'partial') $payment_status_class = 'bg-warning';
                                                elseif ($order['payment_status'] == 'pending') $payment_status_class = 'bg-danger';
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="order-view.php?id=<?php echo $order['id']; ?>" class="text-dark fw-medium">
                                                        <?php echo $order['order_number']; ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <div>
                                                        <h6 class="font-size-14 mb-1"><?php echo $order['shop_name']; ?></h6>
                                                        <p class="text-muted mb-0"><?php echo $order['owner_name']; ?></p>
                                                    </div>
                                                </td>
                                                <td><?php echo date('d M, Y', strtotime($order['created_at'])); ?></td>
                                                <td>₹<?php echo number_format($order['grand_total'], 2); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $order_status_class; ?> font-size-12">
                                                        <?php echo ucfirst($order['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $payment_status_class; ?> font-size-12">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order-view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light">
                                                        <i class="mdi mdi-eye-outline"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-cart-off display-4"></i>
                                        <h5 class="mt-2">No Orders Found</h5>
                                        <p>This line man has not processed any orders yet.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="lineman-edit.php?id=<?php echo $lineman['id']; ?>" class="btn btn-primary">
                                        <i class="mdi mdi-pencil-outline me-1"></i> Edit Profile
                                    </a>
                                    <a href="assign-customers.php?lineman_id=<?php echo $lineman['id']; ?>" class="btn btn-success">
                                        <i class="mdi mdi-account-plus me-1"></i> Assign Customers
                                    </a>
                                    <a href="reports.php?lineman=<?php echo $lineman['id']; ?>" class="btn btn-info">
                                        <i class="mdi mdi-chart-bar me-1"></i> View Reports
                                    </a>
                                    <a href="lineman-list.php" class="btn btn-light">
                                        <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include('includes/footer.php')?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset password for <strong id="resetName"></strong>?</p>
                <p class="text-muted">A new password will be generated and sent to the line man.</p>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="text" class="form-control" id="newPassword" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmReset">Reset Password</button>
                <button type="button" class="btn btn-success" id="copyPassword">
                    <i class="mdi mdi-content-copy me-1"></i> Copy
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
function resetPassword(id, name) {
    document.getElementById('resetName').textContent = name;
    
    // Generate random password
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    document.getElementById('newPassword').value = password;
    
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
    
    // Store the ID and password for confirmation
    document.getElementById('confirmReset').onclick = function() {
        // Send AJAX request to reset password
        fetch('reset-password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id + '&password=' + password
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Password reset successfully!');
                modal.hide();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Network error: ' + error);
        });
    };
    
    // Copy password functionality
    document.getElementById('copyPassword').onclick = function() {
        const passwordInput = document.getElementById('newPassword');
        passwordInput.select();
        document.execCommand('copy');
        alert('Password copied to clipboard!');
    };
}

function sendCredentials(id) {
    if (confirm('Send login credentials to this line man?')) {
        fetch('send-credentials.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Credentials sent successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Network error: ' + error);
        });
    }
}

// Print functionality
function printProfile() {
    window.print();
}
</script>

</body>

</html>