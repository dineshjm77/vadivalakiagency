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
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$min_amount = isset($_GET['min_amount']) ? floatval($_GET['min_amount']) : 0;
$max_amount = isset($_GET['max_amount']) ? floatval($_GET['max_amount']) : 0;
$days_filter = isset($_GET['days']) ? intval($_GET['days']) : 0;
$priority = isset($_GET['priority']) ? mysqli_real_escape_string($conn, $_GET['priority']) : 'all';

// Build query for pending payments
$sql = "SELECT o.*, 
               c.shop_name, c.customer_name, c.customer_contact, 
               c.shop_location, c.customer_code, c.current_balance,
               DATEDIFF(CURDATE(), o.order_date) as days_pending
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.created_by = $lineman_id 
        AND o.payment_status IN ('pending', 'partial')
        AND o.pending_amount > 0";

$conditions = [];

// Add search condition
if (!empty($search)) {
    $conditions[] = "(o.order_number LIKE '%$search%' OR 
                     c.shop_name LIKE '%$search%' OR 
                     c.customer_name LIKE '%$search%' OR 
                     c.customer_contact LIKE '%$search%')";
}

// Add customer filter
if ($customer_id > 0) {
    $conditions[] = "o.customer_id = $customer_id";
}

// Add amount range filters
if ($min_amount > 0) {
    $conditions[] = "o.pending_amount >= $min_amount";
}
if ($max_amount > 0) {
    $conditions[] = "o.pending_amount <= $max_amount";
}

// Add days filter
if ($days_filter > 0) {
    $conditions[] = "DATEDIFF(CURDATE(), o.order_date) >= $days_filter";
}

// Add priority filter
if ($priority != 'all') {
    if ($priority == 'high') {
        $conditions[] = "o.pending_amount >= 1000";
    } elseif ($priority == 'medium') {
        $conditions[] = "o.pending_amount BETWEEN 500 AND 999.99";
    } elseif ($priority == 'low') {
        $conditions[] = "o.pending_amount < 500";
    } elseif ($priority == 'overdue') {
        $conditions[] = "DATEDIFF(CURDATE(), o.order_date) > 30";
    }
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Order by priority (overdue first, then by amount)
$sql .= " ORDER BY 
            CASE 
                WHEN DATEDIFF(CURDATE(), o.order_date) > 30 THEN 1
                WHEN o.pending_amount >= 1000 THEN 2
                WHEN o.pending_amount >= 500 THEN 3
                ELSE 4
            END,
            o.order_date ASC,
            o.pending_amount DESC";

// Execute query
$result = mysqli_query($conn, $sql);

// Calculate total statistics - FIXED SQL SYNTAX
$stats_sql = "SELECT 
    COUNT(*) as total_pending,
    SUM(pending_amount) as total_amount,
    AVG(pending_amount) as avg_amount,
    SUM(CASE WHEN DATEDIFF(CURDATE(), order_date) > 30 THEN 1 ELSE 0 END) as overdue_count,
    SUM(CASE WHEN DATEDIFF(CURDATE(), order_date) > 30 THEN pending_amount ELSE 0 END) as overdue_amount,
    SUM(CASE WHEN pending_amount >= 1000 THEN 1 ELSE 0 END) as high_priority_count,
    SUM(CASE WHEN pending_amount BETWEEN 500 AND 999.99 THEN 1 ELSE 0 END) as medium_priority_count,
    SUM(CASE WHEN pending_amount < 500 THEN 1 ELSE 0 END) as low_priority_count,
    MIN(order_date) as oldest_pending,
    MAX(order_date) as newest_pending
    FROM orders 
    WHERE created_by = $lineman_id 
    AND payment_status IN ('pending', 'partial')
    AND pending_amount > 0";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Get customers with pending payments for filter dropdown
$customers_sql = "SELECT DISTINCT c.id, c.shop_name, c.customer_name, c.customer_code
                  FROM customers c
                  JOIN orders o ON c.id = o.customer_id
                  WHERE o.created_by = $lineman_id 
                  AND o.payment_status IN ('pending', 'partial')
                  AND o.pending_amount > 0
                  ORDER BY c.shop_name";
$customers_result = mysqli_query($conn, $customers_sql);

// Get customer statistics for summary
$customer_stats_sql = "SELECT 
    c.id, c.shop_name, c.customer_name,
    COUNT(o.id) as pending_orders,
    SUM(o.pending_amount) as total_pending,
    MAX(o.order_date) as last_order_date,
    MIN(o.order_date) as first_pending_date,
    DATEDIFF(CURDATE(), MIN(o.order_date)) as max_days_pending
    FROM customers c
    JOIN orders o ON c.id = o.customer_id
    WHERE o.created_by = $lineman_id 
    AND o.payment_status IN ('pending', 'partial')
    AND o.pending_amount > 0
    GROUP BY c.id
    ORDER BY total_pending DESC
    LIMIT 10";

$customer_stats_result = mysqli_query($conn, $customer_stats_sql);

// Handle bulk payment collection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['collect_selected'])) {
        $selected_orders = $_POST['selected_orders'] ?? [];
        
        if (empty($selected_orders)) {
            $error_message = "Please select at least one order to collect payment";
        } else {
            $total_selected = count($selected_orders);
            $total_amount = 0;
            
            // Calculate total amount
            foreach ($selected_orders as $order_id) {
                $order_sql = "SELECT pending_amount FROM orders WHERE id = ? AND created_by = ?";
                $stmt = mysqli_prepare($conn, $order_sql);
                mysqli_stmt_bind_param($stmt, "ii", $order_id, $lineman_id);
                mysqli_stmt_execute($stmt);
                $order_result = mysqli_stmt_get_result($stmt);
                $order_data = mysqli_fetch_assoc($order_result);
                
                if ($order_data) {
                    $total_amount += $order_data['pending_amount'];
                }
            }
            
            // Redirect to collect payment page with selected orders
            $order_ids_param = implode(',', $selected_orders);
            header("Location: collect-payment.php?bulk=1&orders=" . urlencode($order_ids_param) . "&total=" . $total_amount);
            exit;
        }
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
                $current_page = 'pending-payments';
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
                        Payment collected successfully!
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
                                            <h5 class="text-muted fw-normal mt-0" title="Pending Orders">Pending Orders</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['total_pending'] ?? 0; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-warning me-2">
                                                    <i class="mdi mdi-alert-circle"></i>
                                                </span>
                                                <span>Require collection</span>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Total Amount">Total Amount</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-danger me-2">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                                <span>Pending collection</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-danger bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-danger text-danger">
                                                    <i class="mdi mdi-cash"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Overdue Payments">Overdue (>30 days)</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['overdue_count'] ?? 0; ?></h3>
                                            <p class="mb-0 text-muted">
                                                ₹<?php echo number_format($stats['overdue_amount'] ?? 0, 2); ?>
                                                <span class="text-danger ms-1">
                                                    <i class="mdi mdi-alert-octagon"></i>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-danger bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-danger text-danger">
                                                    <i class="mdi mdi-calendar-alert"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Oldest Pending">Oldest Pending</h5>
                                            <h3 class="my-2 py-1">
                                                <?php 
                                                if ($stats['oldest_pending']) {
                                                    $days = floor((time() - strtotime($stats['oldest_pending'])) / (60 * 60 * 24));
                                                    echo $days . ' days';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </h3>
                                            <p class="mb-0 text-muted">
                                                <?php echo $stats['oldest_pending'] ? date('d M', strtotime($stats['oldest_pending'])) : 'No pending'; ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-calendar-clock"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Priority Breakdown -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Payment Priority Breakdown</h5>
                                    <div class="row">
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-danger text-danger font-size-18">
                                                            <i class="mdi mdi-alert-octagon"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">High Priority</h5>
                                                    <p class="text-muted mb-0">₹1000+</p>
                                                    <h4 class="mt-2"><?php echo $stats['high_priority_count'] ?? 0; ?> orders</h4>
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
                                                    <h5 class="font-size-16 mb-1">Medium Priority</h5>
                                                    <p class="text-muted mb-0">₹500-999</p>
                                                    <h4 class="mt-2"><?php echo $stats['medium_priority_count'] ?? 0; ?> orders</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-info text-info font-size-18">
                                                            <i class="mdi mdi-information"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Low Priority</h5>
                                                    <p class="text-muted mb-0">Under ₹500</p>
                                                    <h4 class="mt-2"><?php echo $stats['low_priority_count'] ?? 0; ?> orders</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-success text-success font-size-18">
                                                            <i class="mdi mdi-chart-line"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Avg. Amount</h5>
                                                    <p class="text-muted mb-0">Per order</p>
                                                    <h4 class="mt-2">₹<?php echo number_format($stats['avg_amount'] ?? 0, 2); ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Top Customers with Pending Payments -->
                    <?php if ($customer_stats_result && mysqli_num_rows($customer_stats_result) > 0): ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-account-group me-2"></i> Top Customers with Pending Payments
                                    </h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th class="text-end">Pending Orders</th>
                                                    <th class="text-end">Total Pending</th>
                                                    <th class="text-end">Max Days Pending</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                mysqli_data_seek($customer_stats_result, 0);
                                                while ($customer_stat = mysqli_fetch_assoc($customer_stats_result)): 
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($customer_stat['shop_name']); ?></h6>
                                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($customer_stat['customer_name']); ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge bg-warning-subtle text-warning">
                                                            <?php echo $customer_stat['pending_orders']; ?> orders
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-danger fw-bold">
                                                        ₹<?php echo number_format($customer_stat['total_pending'], 2); ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <?php 
                                                        $days_class = '';
                                                        if ($customer_stat['max_days_pending'] > 30) {
                                                            $days_class = 'badge bg-danger';
                                                        } elseif ($customer_stat['max_days_pending'] > 15) {
                                                            $days_class = 'badge bg-warning';
                                                        } else {
                                                            $days_class = 'badge bg-info';
                                                        }
                                                        ?>
                                                        <span class="<?php echo $days_class; ?>">
                                                            <?php echo $customer_stat['max_days_pending']; ?> days
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="collect-payment.php?customer_id=<?php echo $customer_stat['id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="mdi mdi-cash me-1"></i> Collect
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pending Payments Table -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">All Pending Payments</h4>
                                            <p class="card-title-desc">Manage and collect pending payments</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                                <form method="POST" class="d-inline" id="bulkCollectForm">
                                                    <button type="submit" name="collect_selected" class="btn btn-success">
                                                        <i class="mdi mdi-cash-multiple me-1"></i> Collect Selected
                                                    </button>
                                                </form>
                                                <a href="daily-collection.php" class="btn btn-primary">
                                                    <i class="mdi mdi-cash-register me-1"></i> Daily Collection
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Search & Filter Form -->
                                    <form method="GET" class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="search" 
                                                   value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search orders/customers...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="customer_id">
                                                <option value="0">All Customers</option>
                                                <?php while ($cust = mysqli_fetch_assoc($customers_result)): ?>
                                                <option value="<?php echo $cust['id']; ?>" 
                                                    <?php echo $customer_id == $cust['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cust['shop_name']); ?>
                                                    (<?php echo $cust['customer_code']; ?>)
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="priority">
                                                <option value="all">All Priorities</option>
                                                <option value="high" <?php echo $priority == 'high' ? 'selected' : ''; ?>>High (₹1000+)</option>
                                                <option value="medium" <?php echo $priority == 'medium' ? 'selected' : ''; ?>>Medium (₹500-999)</option>
                                                <option value="low" <?php echo $priority == 'low' ? 'selected' : ''; ?>>Low (<₹500)</option>
                                                <option value="overdue" <?php echo $priority == 'overdue' ? 'selected' : ''; ?>>Overdue (>30 days)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control" name="min_amount" 
                                                   value="<?php echo $min_amount; ?>"
                                                   placeholder="Min Amount" step="0.01">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control" name="max_amount" 
                                                   value="<?php echo $max_amount; ?>"
                                                   placeholder="Max Amount" step="0.01">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="mdi mdi-filter"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <?php if (!empty($search) || $customer_id > 0 || $min_amount > 0 || $max_amount > 0 || $days_filter > 0 || $priority != 'all'): ?>
                                    <div class="mb-3">
                                        <a href="pending-payments.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Quick Filters -->
                                    <div class="mb-3">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="filterOverdue()">
                                                <i class="mdi mdi-alert-octagon me-1"></i> Overdue (>30 days)
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterHighPriority()">
                                                <i class="mdi mdi-alert me-1"></i> High Priority
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm" onclick="filterOldest()">
                                                <i class="mdi mdi-clock-alert me-1"></i> Oldest First
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="filterLargest()">
                                                <i class="mdi mdi-currency-inr me-1"></i> Largest Amount
                                            </button>
                                        </div>
                                    </div>

                                    <form method="POST" id="mainForm">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="5%">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                                            </div>
                                                        </th>
                                                        <th>Order #</th>
                                                        <th>Customer</th>
                                                        <th>Order Date</th>
                                                        <th>Days Pending</th>
                                                        <th>Total Amount</th>
                                                        <th>Paid Amount</th>
                                                        <th>Pending Amount</th>
                                                        <th>Priority</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    if ($result && mysqli_num_rows($result) > 0) {
                                                        while ($row = mysqli_fetch_assoc($result)) {
                                                            // Format dates
                                                            $order_date = date('d M, Y', strtotime($row['order_date']));
                                                            $days_pending = $row['days_pending'];
                                                            
                                                            // Determine priority
                                                            $priority_level = '';
                                                            $priority_class = '';
                                                            $priority_icon = '';
                                                            
                                                            if ($days_pending > 30) {
                                                                $priority_level = 'Overdue';
                                                                $priority_class = 'danger';
                                                                $priority_icon = 'mdi-alert-octagon';
                                                            } elseif ($row['pending_amount'] >= 1000) {
                                                                $priority_level = 'High';
                                                                $priority_class = 'danger';
                                                                $priority_icon = 'mdi-alert';
                                                            } elseif ($row['pending_amount'] >= 500) {
                                                                $priority_level = 'Medium';
                                                                $priority_class = 'warning';
                                                                $priority_icon = 'mdi-alert-circle';
                                                            } else {
                                                                $priority_level = 'Low';
                                                                $priority_class = 'info';
                                                                $priority_icon = 'mdi-information';
                                                            }
                                                            
                                                            // Days pending badge color
                                                            $days_class = '';
                                                            if ($days_pending > 30) {
                                                                $days_class = 'badge bg-danger';
                                                            } elseif ($days_pending > 15) {
                                                                $days_class = 'badge bg-warning';
                                                            } elseif ($days_pending > 7) {
                                                                $days_class = 'badge bg-info';
                                                            } else {
                                                                $days_class = 'badge bg-success';
                                                            }
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input order-checkbox" 
                                                                               type="checkbox" 
                                                                               name="selected_orders[]" 
                                                                               value="<?php echo $row['id']; ?>"
                                                                               data-amount="<?php echo $row['pending_amount']; ?>">
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <a href="view-invoice.php?id=<?php echo $row['id']; ?>" class="text-dark fw-medium">
                                                                        <?php echo $row['order_number']; ?>
                                                                    </a>
                                                                </td>
                                                                <td>
                                                                    <div>
                                                                        <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($row['shop_name']); ?></h5>
                                                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                                                                        <small class="text-muted"><?php echo $row['customer_contact']; ?></small>
                                                                    </div>
                                                                </td>
                                                                <td><?php echo $order_date; ?></td>
                                                                <td>
                                                                    <span class="<?php echo $days_class; ?>">
                                                                        <?php echo $days_pending; ?> days
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <h5 class="font-size-14 mb-1">₹<?php echo number_format($row['total_amount'], 2); ?></h5>
                                                                </td>
                                                                <td>
                                                                    <span class="text-success">₹<?php echo number_format($row['paid_amount'], 2); ?></span>
                                                                </td>
                                                                <td>
                                                                    <h5 class="font-size-14 mb-1 text-danger">₹<?php echo number_format($row['pending_amount'], 2); ?></h5>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $priority_class; ?>-subtle text-<?php echo $priority_class; ?>">
                                                                        <i class="mdi <?php echo $priority_icon; ?> me-1"></i>
                                                                        <?php echo $priority_level; ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="dropdown">
                                                                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                            <i class="mdi mdi-dots-horizontal"></i>
                                                                        </button>
                                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                                            <li>
                                                                                <a class="dropdown-item" href="view-invoice.php?id=<?php echo $row['id']; ?>">
                                                                                    <i class="mdi mdi-eye-outline me-1"></i> View Invoice
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="collect-payment.php?order_id=<?php echo $row['id']; ?>">
                                                                                    <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="print-invoice.php?id=<?php echo $row['id']; ?>" target="_blank">
                                                                                    <i class="mdi mdi-printer me-1"></i> Print Invoice
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item" href="whatsapp://send?text=Payment Reminder: Order <?php echo $row['order_number']; ?>%0AAmount: ₹<?php echo $row['pending_amount']; ?>%0ADate: <?php echo $order_date; ?>%0AStatus: Pending" 
                                                                                   onclick="return checkMobile()">
                                                                                    <i class="mdi mdi-whatsapp me-1"></i> Send Reminder
                                                                                </a>
                                                                            </li>
                                                                            <li><hr class="dropdown-divider"></li>
                                                                            <li>
                                                                                <a class="dropdown-item text-info" href="customer-details.php?id=<?php echo $row['customer_id']; ?>">
                                                                                    <i class="mdi mdi-account me-1"></i> Customer Profile
                                                                                </a>
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
                                                            <td colspan="10" class="text-center py-4">
                                                                <div class="text-muted">
                                                                    <i class="mdi mdi-cash-check display-4"></i>
                                                                    <h5 class="mt-2">No Pending Payments</h5>
                                                                    <p>All payments have been collected!</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Selected Summary -->
                                        <div class="row mt-3" id="selectedSummary" style="display: none;">
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <i class="mdi mdi-checkbox-multiple-marked me-2"></i>
                                                            <span id="selectedCount">0</span> orders selected | 
                                                            Total: ₹<span id="selectedTotal">0.00</span>
                                                        </div>
                                                        <div>
                                                            <button type="submit" name="collect_selected" class="btn btn-success btn-sm">
                                                                <i class="mdi mdi-cash-multiple me-1"></i> Collect Selected
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <div class="row mt-3">
                                            <div class="col-sm-12 col-md-5">
                                                <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                                    Showing <?php echo mysqli_num_rows($result); ?> pending payments
                                                </div>
                                            </div>
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
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedSummary();
        });

        // Update selected summary
        function updateSelectedSummary() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const count = checkboxes.length;
            let total = 0;
            
            checkboxes.forEach(checkbox => {
                const amount = parseFloat(checkbox.getAttribute('data-amount'));
                total += amount;
            });
            
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('selectedTotal').textContent = total.toFixed(2);
            
            const summaryDiv = document.getElementById('selectedSummary');
            if (count > 0) {
                summaryDiv.style.display = 'block';
            } else {
                summaryDiv.style.display = 'none';
            }
        }

        // Checkbox change event
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('order-checkbox')) {
                updateSelectedSummary();
            }
        });

        // Quick filter functions
        function filterOverdue() {
            window.location.href = 'pending-payments.php?priority=overdue';
        }

        function filterHighPriority() {
            window.location.href = 'pending-payments.php?priority=high';
        }

        function filterOldest() {
            window.location.href = 'pending-payments.php?days=30';
        }

        function filterLargest() {
            window.location.href = 'pending-payments.php?min_amount=1000';
        }

        // WhatsApp reminder check
        function checkMobile() {
            if (!navigator.userAgent.match(/iPhone|iPad|iPod|Android/i)) {
                alert('Please open this page on your mobile device to send WhatsApp reminders.');
                return false;
            }
            return true;
        }

        // Export pending payments
        function exportPayments() {
            const search = '<?php echo $search; ?>';
            const customerId = '<?php echo $customer_id; ?>';
            const minAmount = '<?php echo $min_amount; ?>';
            const maxAmount = '<?php echo $max_amount; ?>';
            const priority = '<?php echo $priority; ?>';
            
            window.location.href = `export-payments.php?search=${encodeURIComponent(search)}&customer_id=${customerId}&min_amount=${minAmount}&max_amount=${maxAmount}&priority=${priority}`;
        }

        // Print pending payments list
        function printPayments() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Pending Payments Report - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        .text-danger { color: #dc3545; }
                        .text-success { color: #28a745; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Pending Payments Report</h1>
                    <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <p><strong>Total Pending:</strong> ₹<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></p>
                    <p><strong>Pending Orders:</strong> <?php echo $stats['total_pending'] ?? 0; ?></p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Order No</th>
                                <th>Customer</th>
                                <th>Order Date</th>
                                <th>Days</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Pending</th>
                                <th>Priority</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            mysqli_data_seek($result, 0);
                            $print_counter = 1;
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $order_date = date('d M, Y', strtotime($row['order_date']));
                                    $days_pending = $row['days_pending'];
                                    
                                    // Determine priority
                                    $priority_level = '';
                                    if ($days_pending > 30) {
                                        $priority_level = 'Overdue';
                                    } elseif ($row['pending_amount'] >= 1000) {
                                        $priority_level = 'High';
                                    } elseif ($row['pending_amount'] >= 500) {
                                        $priority_level = 'Medium';
                                    } else {
                                        $priority_level = 'Low';
                                    }
                                    
                                    echo '<tr>';
                                    echo '<td>' . $print_counter++ . '</td>';
                                    echo '<td>' . $row['order_number'] . '</td>';
                                    echo '<td>' . htmlspecialchars($row['shop_name']) . '</td>';
                                    echo '<td>' . $order_date . '</td>';
                                    echo '<td>' . $days_pending . '</td>';
                                    echo '<td class="text-right">₹' . number_format($row['total_amount'], 2) . '</td>';
                                    echo '<td class="text-right text-success">₹' . number_format($row['paid_amount'], 2) . '</td>';
                                    echo '<td class="text-right text-danger">₹' . number_format($row['pending_amount'], 2) . '</td>';
                                    echo '<td>' . $priority_level . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>₹<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                        <p>Total Pending Amount: <strong class="text-danger">₹<?php echo number_format($stats['total_amount'] ?? 0, 2); ?></strong></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Add print and export buttons
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelector('.d-flex.flex-wrap');
            if (actionButtons) {
                // Add print button
                const printButton = document.createElement('button');
                printButton.type = 'button';
                printButton.className = 'btn btn-outline-info';
                printButton.innerHTML = '<i class="mdi mdi-printer me-1"></i> Print';
                printButton.onclick = printPayments;
                actionButtons.appendChild(printButton);
                
                // Add export button
                const exportButton = document.createElement('button');
                exportButton.type = 'button';
                exportButton.className = 'btn btn-outline-secondary';
                exportButton.innerHTML = '<i class="mdi mdi-download me-1"></i> Export';
                exportButton.onclick = exportPayments;
                actionButtons.appendChild(exportButton);
            }
            
            // Initialize selected summary
            updateSelectedSummary();
        });

        // Auto-submit filters
        document.querySelectorAll('select[name="priority"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search on enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
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