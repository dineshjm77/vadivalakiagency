<?php

include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];

// Handle search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$customer_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
$payment_terms = isset($_GET['payment_terms']) ? mysqli_real_escape_string($conn, $_GET['payment_terms']) : 'all';
$area_filter = isset($_GET['area']) ? mysqli_real_escape_string($conn, $_GET['area']) : 'all';

// Build query for active customers assigned to this lineman
$sql = "SELECT c.*, 
               COUNT(o.id) as total_orders,
               SUM(o.total_amount) as total_sales,
               SUM(o.paid_amount) as total_paid,
               SUM(o.pending_amount) as total_pending,
               MAX(o.order_date) as last_order_date,
               MIN(o.order_date) as first_order_date
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id AND o.created_by = $lineman_id
        WHERE c.assigned_lineman_id = $lineman_id 
        AND c.status = 'active'";

$conditions = [];

// Add search condition
if (!empty($search)) {
    $conditions[] = "(c.shop_name LIKE '%$search%' OR 
                     c.customer_name LIKE '%$search%' OR 
                     c.customer_contact LIKE '%$search%' OR 
                     c.customer_code LIKE '%$search%')";
}

// Add customer type filter
if ($customer_type != 'all') {
    $conditions[] = "c.customer_type = '$customer_type'";
}

// Add payment terms filter
if ($payment_terms != 'all') {
    $conditions[] = "c.payment_terms = '$payment_terms'";
}

// Add area filter
if ($area_filter != 'all') {
    $conditions[] = "c.assigned_area LIKE '%$area_filter%'";
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Group by customer
$sql .= " GROUP BY c.id";

// Order by
$sql .= " ORDER BY c.shop_name ASC";

// Execute query
$result = mysqli_query($conn, $sql);

// Get total active customers count
$total_sql = "SELECT COUNT(*) as total FROM customers 
              WHERE assigned_lineman_id = $lineman_id 
              AND status = 'active'";
$total_result = mysqli_query($conn, $total_sql);
$total_row = mysqli_fetch_assoc($total_result);
$total_active_customers = $total_row['total'];

// Get statistics for dashboard
$stats_sql = "SELECT 
    COUNT(*) as total_customers,
    SUM(current_balance) as total_balance,
    SUM(total_purchases) as total_purchases,
    AVG(current_balance) as avg_balance,
    COUNT(CASE WHEN DATEDIFF(CURDATE(), IFNULL(last_purchase_date, '2000-01-01')) <= 7 THEN 1 END) as active_last_week,
    COUNT(CASE WHEN DATEDIFF(CURDATE(), IFNULL(last_purchase_date, '2000-01-01')) > 30 THEN 1 END) as inactive_over_month
    FROM customers 
    WHERE assigned_lineman_id = $lineman_id 
    AND status = 'active'";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get customer types for filter
$types_sql = "SELECT DISTINCT customer_type FROM customers 
              WHERE assigned_lineman_id = $lineman_id 
              AND status = 'active' 
              ORDER BY customer_type";
$types_result = mysqli_query($conn, $types_sql);

// Get payment terms for filter
$terms_sql = "SELECT DISTINCT payment_terms FROM customers 
              WHERE assigned_lineman_id = $lineman_id 
              AND status = 'active' 
              ORDER BY payment_terms";
$terms_result = mysqli_query($conn, $terms_sql);

// Get areas for filter
$areas_sql = "SELECT DISTINCT assigned_area FROM customers 
              WHERE assigned_lineman_id = $lineman_id 
              AND assigned_area IS NOT NULL 
              AND assigned_area != '' 
              AND status = 'active' 
              ORDER BY assigned_area";
$areas_result = mysqli_query($conn, $areas_sql);

// Handle customer status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $customer_id = intval($_POST['customer_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Verify customer belongs to lineman
    $check_sql = "SELECT id FROM customers WHERE id = $customer_id AND assigned_lineman_id = $lineman_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $update_sql = "UPDATE customers SET status = '$new_status', updated_at = NOW() WHERE id = $customer_id";
        if (mysqli_query($conn, $update_sql)) {
            // Log status change
            $log_sql = "INSERT INTO status_logs (customer_id, old_status, new_status, changed_by, notes, created_at) 
                        VALUES ($customer_id, 'active', '$new_status', $lineman_id, '$notes', NOW())";
            mysqli_query($conn, $log_sql);
            
            $success_message = "Customer status updated successfully!";
            // Refresh page to show updated status
            header("Location: active-customers.php?success=1");
            exit;
        } else {
            $error_message = "Failed to update customer status: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Customer not found or you don't have permission to update it.";
    }
}
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>

<body data-sidebar="dark">

    <!-- Loader -->
    <?php include('includes/pre-loader.php') ?>

    <!-- Begin page -->
    <div id="layout-wrapper">

        <?php include('includes/topbar.php') ?>

        <!-- ========== Left Sidebar Start ========== -->
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <!--- Sidemenu -->
                <?php 
                $current_page = 'active-customers';
                include('includes/sidebar.php');
                ?>
            </div>
        </div>
        <!-- Left Sidebar End -->

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">


                    <!-- Messages -->
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        Operation completed successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Active Customers">Active Customers</h5>
                                            <h3 class="my-2 py-1"><?php echo $total_active_customers; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-arrow-up-bold"></i>
                                                </span>
                                                <span>Total assigned</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-account-group"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Balance">Total Balance</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_balance'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-danger me-2">
                                                    <i class="mdi mdi-alert-circle"></i>
                                                </span>
                                                <span>Pending collection</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-warning bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-warning text-warning">
                                                    <i class="mdi mdi-cash-clock"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Purchases">Total Purchases</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_purchases'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-chart-line"></i>
                                                </span>
                                                <span>Lifetime value</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Recent Activity">Active Last Week</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['active_last_week'] ?? 0; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <?php if ($total_active_customers > 0): ?>
                                                <span class="text-success me-2">
                                                    <?php echo round(($stats['active_last_week'] / $total_active_customers) * 100, 1); ?>%
                                                </span>
                                                <?php endif; ?>
                                                <span>Active rate</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-calendar-week"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Customer Status Overview -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Customer Health Overview</h5>
                                    <div class="row">
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-success text-success font-size-18">
                                                            <i class="mdi mdi-check-circle"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Good Standing</h5>
                                                    <?php
                                                    $good_standing_sql = "SELECT COUNT(*) as count FROM customers 
                                                                         WHERE assigned_lineman_id = $lineman_id 
                                                                         AND status = 'active' 
                                                                         AND current_balance <= 0";
                                                    $good_result = mysqli_query($conn, $good_standing_sql);
                                                    $good_row = mysqli_fetch_assoc($good_result);
                                                    ?>
                                                    <p class="text-muted mb-0"><?php echo $good_row['count']; ?> Customers</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-warning text-warning font-size-18">
                                                            <i class="mdi mdi-alert-circle"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Credit Balance</h5>
                                                    <?php
                                                    $credit_sql = "SELECT COUNT(*) as count FROM customers 
                                                                  WHERE assigned_lineman_id = $lineman_id 
                                                                  AND status = 'active' 
                                                                  AND current_balance > 0 
                                                                  AND current_balance <= credit_limit";
                                                    $credit_result = mysqli_query($conn, $credit_sql);
                                                    $credit_row = mysqli_fetch_assoc($credit_result);
                                                    ?>
                                                    <p class="text-muted mb-0"><?php echo $credit_row['count']; ?> Customers</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-danger text-danger font-size-18">
                                                            <i class="mdi mdi-alert-octagon"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Over Limit</h5>
                                                    <?php
                                                    $overlimit_sql = "SELECT COUNT(*) as count FROM customers 
                                                                     WHERE assigned_lineman_id = $lineman_id 
                                                                     AND status = 'active' 
                                                                     AND current_balance > credit_limit";
                                                    $overlimit_result = mysqli_query($conn, $overlimit_sql);
                                                    $overlimit_row = mysqli_fetch_assoc($overlimit_result);
                                                    ?>
                                                    <p class="text-muted mb-0"><?php echo $overlimit_row['count']; ?> Customers</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-info text-info font-size-18">
                                                            <i class="mdi mdi-clock"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Inactive > Month</h5>
                                                    <p class="text-muted mb-0"><?php echo $stats['inactive_over_month'] ?? 0; ?> Customers</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Customers Table -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">Active Customers List</h4>
                                            <p class="card-title-desc">Manage and track your active customers</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                                <a href="add-customer.php" class="btn btn-success">
                                                    <i class="mdi mdi-plus-circle-outline me-1"></i> Add New Customer
                                                </a>
                                                <a href="my-customers.php" class="btn btn-outline-primary">
                                                    <i class="mdi mdi-account-group me-1"></i> All Customers
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Search & Filter Form -->
                                    <form method="GET" class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="search" 
                                                   value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search customers...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="type">
                                                <option value="all">All Types</option>
                                                <?php while ($type = mysqli_fetch_assoc($types_result)): ?>
                                                <option value="<?php echo $type['customer_type']; ?>" 
                                                    <?php echo $customer_type == $type['customer_type'] ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($type['customer_type']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="payment_terms">
                                                <option value="all">All Terms</option>
                                                <?php mysqli_data_seek($terms_result, 0); ?>
                                                <?php while ($term = mysqli_fetch_assoc($terms_result)): ?>
                                                <option value="<?php echo $term['payment_terms']; ?>" 
                                                    <?php echo $payment_terms == $term['payment_terms'] ? 'selected' : ''; ?>>
                                                    <?php 
                                                    $term_display = [
                                                        'cash' => 'Cash',
                                                        'credit_7' => 'Credit 7 Days',
                                                        'credit_15' => 'Credit 15 Days',
                                                        'credit_30' => 'Credit 30 Days',
                                                        'prepaid' => 'Prepaid',
                                                        'weekly' => 'Weekly',
                                                        'monthly' => 'Monthly'
                                                    ];
                                                    echo $term_display[$term['payment_terms']] ?? ucfirst($term['payment_terms']);
                                                    ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="area">
                                                <option value="all">All Areas</option>
                                                <?php mysqli_data_seek($areas_result, 0); ?>
                                                <?php while ($area = mysqli_fetch_assoc($areas_result)): ?>
                                                <option value="<?php echo $area['assigned_area']; ?>" 
                                                    <?php echo $area_filter == $area['assigned_area'] ? 'selected' : ''; ?>>
                                                    <?php echo $area['assigned_area']; ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="mdi mdi-filter"></i>
                                            </button>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-secondary w-100" onclick="exportCustomers()">
                                                <i class="mdi mdi-download me-1"></i> Export
                                            </button>
                                        </div>
                                    </form>

                                    <?php if (!empty($search) || $customer_type != 'all' || $payment_terms != 'all' || $area_filter != 'all'): ?>
                                    <div class="mb-3">
                                        <a href="active-customers.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Contact</th>
                                                    <th>Type</th>
                                                    <th>Payment Terms</th>
                                                    <th>Current Balance</th>
                                                    <th>Total Sales</th>
                                                    <th>Last Order</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result && mysqli_num_rows($result) > 0) {
                                                    while ($row = mysqli_fetch_assoc($result)) {
                                                        // Format dates
                                                        $last_order = $row['last_order_date'] ? date('d M, Y', strtotime($row['last_order_date'])) : 'Never';
                                                        $first_order = $row['first_order_date'] ? date('M Y', strtotime($row['first_order_date'])) : 'New';
                                                        
                                                        // Calculate days since last order
                                                        $days_since_last = $row['last_order_date'] ? floor((time() - strtotime($row['last_order_date'])) / (60 * 60 * 24)) : 999;
                                                        
                                                        // Payment terms display
                                                        $term_display = [
                                                            'cash' => 'Cash',
                                                            'credit_7' => 'Credit 7D',
                                                            'credit_15' => 'Credit 15D',
                                                            'credit_30' => 'Credit 30D',
                                                            'prepaid' => 'Prepaid',
                                                            'weekly' => 'Weekly',
                                                            'monthly' => 'Monthly'
                                                        ];
                                                        $payment_term_display = $term_display[$row['payment_terms']] ?? ucfirst($row['payment_terms']);
                                                        
                                                        // Customer type badge color
                                                        $type_class = '';
                                                        switch($row['customer_type']) {
                                                            case 'retail': $type_class = 'badge-soft-primary'; break;
                                                            case 'wholesale': $type_class = 'badge-soft-success'; break;
                                                            case 'hotel': $type_class = 'badge-soft-warning'; break;
                                                            case 'office': $type_class = 'badge-soft-info'; break;
                                                            default: $type_class = 'badge-soft-secondary';
                                                        }
                                                        
                                                        // Balance badge color
                                                        $balance_class = '';
                                                        if ($row['current_balance'] <= 0) {
                                                            $balance_class = 'badge-soft-success';
                                                        } elseif ($row['current_balance'] > 0 && $row['current_balance'] <= $row['credit_limit']) {
                                                            $balance_class = 'badge-soft-warning';
                                                        } else {
                                                            $balance_class = 'badge-soft-danger';
                                                        }
                                                        
                                                        // Activity indicator
                                                        $activity_class = '';
                                                        $activity_text = '';
                                                        if ($days_since_last <= 7) {
                                                            $activity_class = 'bg-success';
                                                            $activity_text = 'Active';
                                                        } elseif ($days_since_last <= 30) {
                                                            $activity_class = 'bg-warning';
                                                            $activity_text = 'Moderate';
                                                        } elseif ($days_since_last <= 90) {
                                                            $activity_class = 'bg-danger';
                                                            $activity_text = 'Inactive';
                                                        } else {
                                                            $activity_class = 'bg-secondary';
                                                            $activity_text = 'Dormant';
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="customer-details.php?id=<?php echo $row['id']; ?>" class="text-dark">
                                                                            <?php echo htmlspecialchars($row['shop_name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <p class="text-muted mb-0">
                                                                        <?php echo htmlspecialchars($row['customer_name']); ?>
                                                                        <?php if (!empty($row['customer_code'])): ?>
                                                                        <br><small class="text-muted">Code: <?php echo $row['customer_code']; ?></small>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                    <small class="text-muted">
                                                                        <i class="mdi mdi-map-marker-outline"></i> 
                                                                        <?php echo htmlspecialchars(substr($row['shop_location'], 0, 30)); ?>
                                                                        <?php if (strlen($row['shop_location']) > 30): ?>...<?php endif; ?>
                                                                    </small>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <p class="mb-1"><?php echo $row['customer_contact']; ?></p>
                                                                <?php if (!empty($row['alternate_contact'])): ?>
                                                                <p class="mb-0"><small><?php echo $row['alternate_contact']; ?></small></p>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo $type_class; ?> font-size-12">
                                                                    <?php echo ucfirst($row['customer_type']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-light text-dark font-size-12">
                                                                    <?php echo $payment_term_display; ?>
                                                                </span>
                                                                <?php if ($row['credit_limit'] > 0): ?>
                                                                <br><small class="text-muted">Limit: ₹<?php echo number_format($row['credit_limit'], 2); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1 <?php echo $row['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                                        ₹<?php echo number_format($row['current_balance'], 2); ?>
                                                                    </h5>
                                                                    <span class="badge <?php echo $balance_class; ?> font-size-12">
                                                                        <?php 
                                                                        if ($row['current_balance'] <= 0) {
                                                                            echo 'Paid';
                                                                        } elseif ($row['current_balance'] > 0 && $row['current_balance'] <= $row['credit_limit']) {
                                                                            echo 'Within Limit';
                                                                        } else {
                                                                            echo 'Over Limit';
                                                                        }
                                                                        ?>
                                                                    </span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">₹<?php echo number_format($row['total_sales'] ?? 0, 2); ?></h5>
                                                                    <p class="text-muted mb-0">
                                                                        <?php echo $row['total_orders'] ?? 0; ?> orders
                                                                    </p>
                                                                    <small class="text-muted">Since: <?php echo $first_order; ?></small>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <p class="mb-1"><?php echo $last_order; ?></p>
                                                                    <span class="badge <?php echo $activity_class; ?> font-size-12">
                                                                        <?php echo $activity_text; ?>
                                                                    </span>
                                                                    <?php if ($row['last_order_date']): ?>
                                                                    <br><small class="text-muted"><?php echo $days_since_last; ?> days ago</small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-soft-success font-size-12">
                                                                    <?php echo ucfirst($row['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <i class="mdi mdi-dots-horizontal"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                                        <li>
                                                                            <a class="dropdown-item" href="customer-details.php?id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-eye-outline me-1"></i> View Details
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="quick-order.php?customer_id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-cart-plus me-1"></i> Create Order
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="collect-payment.php?customer_id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="edit-customer.php?id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-pencil me-1"></i> Edit Customer
                                                                            </a>
                                                                        </li>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li>
                                                                            <button class="dropdown-item text-warning" type="button" 
                                                                                    data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                                                    data-customer-id="<?php echo $row['id']; ?>"
                                                                                    data-customer-name="<?php echo htmlspecialchars($row['shop_name']); ?>">
                                                                                <i class="mdi mdi-account-convert me-1"></i> Change Status
                                                                            </button>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="9" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-account-off display-4"></i>
                                                                <h5 class="mt-2">No Active Customers Found</h5>
                                                                <p>Add customers using the "Add New Customer" button</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <div class="row mt-3">
                                        <div class="col-sm-12 col-md-5">
                                            <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                                Showing <?php echo mysqli_num_rows($result); ?> active customers
                                            </div>
                                        </div>
                                        <div class="col-sm-12 col-md-7">
                                            <div class="d-flex justify-content-end">
                                                <ul class="pagination pagination-rounded mb-0">
                                                    <li class="page-item disabled">
                                                        <a class="page-link" href="#" aria-label="Previous">
                                                            <i class="mdi mdi-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                                                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                                                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                                                    <li class="page-item"><a class="page-link" href="#">5</a></li>
                                                    <li class="page-item">
                                                        <a class="page-link" href="#" aria-label="Next">
                                                            <i class="mdi mdi-chevron-right"></i>
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
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="updateStatusForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">Update Customer Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="modal_customer_id">
                        
                        <div class="mb-3">
                            <p class="mb-2">Customer: <strong id="modal_customer_name"></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Reason for Status Change</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Enter reason for status change..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Update Status Modal
        document.addEventListener('DOMContentLoaded', function() {
            const updateStatusModal = document.getElementById('updateStatusModal');
            
            if (updateStatusModal) {
                updateStatusModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const customerId = button.getAttribute('data-customer-id');
                    const customerName = button.getAttribute('data-customer-name');
                    
                    document.getElementById('modal_customer_id').value = customerId;
                    document.getElementById('modal_customer_name').textContent = customerName;
                });
            }
        });

        // Export customers to CSV
        function exportCustomers() {
            const search = '<?php echo $search; ?>';
            const type = '<?php echo $customer_type; ?>';
            const payment_terms = '<?php echo $payment_terms; ?>';
            const area = '<?php echo $area_filter; ?>';
            
            window.location.href = `export-customers.php?type=active&search=${encodeURIComponent(search)}&customer_type=${type}&payment_terms=${payment_terms}&area=${area}`;
        }

        // Quick filters
        function filterByBalance(balanceType) {
            let url = 'active-customers.php?';
            
            switch(balanceType) {
                case 'paid':
                    window.location.href = url + 'balance=paid';
                    break;
                case 'credit':
                    window.location.href = url + 'balance=credit';
                    break;
                case 'overdue':
                    window.location.href = url + 'balance=overdue';
                    break;
            }
        }

        function filterByActivity(days) {
            window.location.href = `active-customers.php?last_order=${days}`;
        }

        // Search on enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Auto-submit filters
        document.querySelectorAll('select[name="type"], select[name="payment_terms"], select[name="area"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Print customer list
        function printCustomerList() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Active Customers List - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Active Customers List</h1>
                    <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <p><strong>Total Active Customers:</strong> <?php echo $total_active_customers; ?></p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Shop Name</th>
                                <th>Customer Name</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Balance</th>
                                <th>Last Order</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            mysqli_data_seek($result, 0);
                            $print_counter = 1;
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $last_order = $row['last_order_date'] ? date('d M, Y', strtotime($row['last_order_date'])) : 'Never';
                                    echo '<tr>';
                                    echo '<td>' . $print_counter++ . '</td>';
                                    echo '<td>' . htmlspecialchars($row['shop_name']) . '</td>';
                                    echo '<td>' . htmlspecialchars($row['customer_name']) . '</td>';
                                    echo '<td>' . $row['customer_contact'] . '</td>';
                                    echo '<td>' . ucfirst($row['customer_type']) . '</td>';
                                    echo '<td>₹' . number_format($row['current_balance'], 2) . '</td>';
                                    echo '<td>' . $last_order . '</td>';
                                    echo '<td>' . ucfirst($row['status']) . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Add print button
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelector('.d-flex.flex-wrap');
            if (actionButtons) {
                const printButton = document.createElement('button');
                printButton.type = 'button';
                printButton.className = 'btn btn-outline-info';
                printButton.innerHTML = '<i class="mdi mdi-printer me-1"></i> Print List';
                printButton.onclick = printCustomerList;
                actionButtons.appendChild(printButton);
            }
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}