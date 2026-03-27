<?php
include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Check if customer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: active-customers.php');
    exit;
}

$customer_id = intval($_GET['id']);

// Verify customer belongs to this lineman
$check_sql = "SELECT * FROM customers WHERE id = $customer_id AND assigned_lineman_id = $lineman_id";
$check_result = mysqli_query($conn, $check_sql);

if (!$check_result || mysqli_num_rows($check_result) == 0) {
    header('Location: active-customers.php?error=Customer not found or unauthorized');
    exit;
}

$customer = mysqli_fetch_assoc($check_result);

// Get customer statistics
$stats_sql = "SELECT 
    COUNT(o.id) as total_orders,
    SUM(o.total_amount) as total_sales,
    SUM(o.paid_amount) as total_paid,
    SUM(o.pending_amount) as total_pending,
    MAX(o.order_date) as last_order_date,
    MIN(o.order_date) as first_order_date,
    AVG(o.total_amount) as avg_order_value
    FROM orders o
    WHERE o.customer_id = $customer_id 
    AND o.created_by = $lineman_id";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result) ?? [];

// Calculate days since last order
$days_since_last = $stats['last_order_date'] ? 
    floor((time() - strtotime($stats['last_order_date'])) / (60 * 60 * 24)) : 999;

// Get recent orders (last 5)
$recent_orders_sql = "SELECT o.*, 
    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE o.customer_id = $customer_id 
    AND o.created_by = $lineman_id
    ORDER BY o.created_at DESC 
    LIMIT 5";

$recent_orders_result = mysqli_query($conn, $recent_orders_sql);

// Get recent payments (last 5)
$recent_payments_sql = "SELECT t.* 
    FROM transactions t
    WHERE t.customer_id = $customer_id 
    AND t.created_by = $lineman_id
    ORDER BY t.created_at DESC 
    LIMIT 5";

$recent_payments_result = mysqli_query($conn, $recent_payments_sql);

// Get monthly purchase trend (last 6 months)
$trend_sql = "SELECT 
    DATE_FORMAT(o.order_date, '%b %Y') as month,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as monthly_total,
    SUM(o.paid_amount) as monthly_paid
    FROM orders o
    WHERE o.customer_id = $customer_id 
    AND o.created_by = $lineman_id
    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
    ORDER BY DATE_FORMAT(o.order_date, '%Y-%m') DESC";

$trend_result = mysqli_query($conn, $trend_sql);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    $old_status = $customer['status'];
    
    $update_sql = "UPDATE customers SET status = '$new_status', updated_at = NOW() 
                   WHERE id = $customer_id AND assigned_lineman_id = $lineman_id";
    
    if (mysqli_query($conn, $update_sql)) {
        // Log status change
        $log_sql = "INSERT INTO status_logs (customer_id, old_status, new_status, changed_by, notes, created_at) 
                    VALUES ($customer_id, '$old_status', '$new_status', $lineman_id, '$notes', NOW())";
        mysqli_query($conn, $log_sql);
        
        // Refresh customer data
        $check_result = mysqli_query($conn, $check_sql);
        $customer = mysqli_fetch_assoc($check_result);
        
        $success_message = "Customer status updated successfully!";
    } else {
        $error_message = "Failed to update status: " . mysqli_error($conn);
    }
}

// Display success message from redirect
if (isset($_GET['success'])) {
    $success_message = "Customer updated successfully!";
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
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Customer Header Card -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <!-- Customer Avatar -->
                                        <div class="flex-shrink-0 me-4">
                                            <div class="customer-avatar-xl">
                                                <?php 
                                                $initials = strtoupper(substr($customer['shop_name'], 0, 1) . substr($customer['customer_name'], 0, 1));
                                                ?>
                                                <span><?php echo $initials; ?></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Customer Info -->
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h4 class="mb-1"><?php echo htmlspecialchars($customer['shop_name']); ?></h4>
                                                    <p class="text-muted mb-2">
                                                        <i class="mdi mdi-account-outline me-1"></i>
                                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="mdi mdi-identifier me-1"></i>
                                                        <?php echo $customer['customer_code']; ?>
                                                    </p>
                                                    <div class="mb-3">
                                                        <span class="badge <?php echo getStatusBadgeClass($customer['status']); ?> me-2">
                                                            <?php echo ucfirst($customer['status']); ?>
                                                        </span>
                                                        <span class="badge <?php echo getTypeBadgeClass($customer['customer_type']); ?> me-2">
                                                            <?php echo ucfirst($customer['customer_type']); ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark me-2">
                                                            <?php 
                                                            $term_display = [
                                                                'cash' => 'Cash',
                                                                'credit_7' => 'Credit 7D',
                                                                'credit_15' => 'Credit 15D',
                                                                'credit_30' => 'Credit 30D',
                                                                'prepaid' => 'Prepaid',
                                                                'weekly' => 'Weekly',
                                                                'monthly' => 'Monthly'
                                                            ];
                                                            echo $term_display[$customer['payment_terms']] ?? ucfirst($customer['payment_terms']);
                                                            ?>
                                                        </span>
                                                        <?php if ($customer['assigned_area']): ?>
                                                        <span class="badge bg-info bg-opacity-10 text-info">
                                                            <i class="mdi mdi-map-marker-outline me-1"></i>
                                                            <?php echo $customer['assigned_area']; ?>
                                                        </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0">
                                                    <div class="dropdown">
                                                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="mdi mdi-dots-horizontal"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item" href="edit-customer.php?id=<?php echo $customer_id; ?>">
                                                                    <i class="mdi mdi-pencil-outline me-1"></i> Edit Customer
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="quick-order.php?customer_id=<?php echo $customer_id; ?>">
                                                                    <i class="mdi mdi-cart-plus me-1"></i> Create Order
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="collect-payment.php?customer_id=<?php echo $customer_id; ?>">
                                                                    <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                                </a>
                                                            </li>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <button class="dropdown-item text-warning" type="button" 
                                                                        data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                                                    <i class="mdi mdi-account-convert me-1"></i> Change Status
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item text-danger" type="button" onclick="deleteCustomer(<?php echo $customer_id; ?>)">
                                                                    <i class="mdi mdi-delete-outline me-1"></i> Delete Customer
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Contact Information -->
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <i class="mdi mdi-phone me-2 text-primary"></i>
                                                        <strong><?php echo $customer['customer_contact']; ?></strong>
                                                        <?php if ($customer['alternate_contact']): ?>
                                                        <br><small class="text-muted ms-4">Alt: <?php echo $customer['alternate_contact']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <?php if ($customer['email']): ?>
                                                        <i class="mdi mdi-email-outline me-2 text-primary"></i>
                                                        <a href="mailto:<?php echo $customer['email']; ?>" class="text-decoration-none">
                                                            <?php echo $customer['email']; ?>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-2">
                                                        <i class="mdi mdi-map-marker-outline me-2 text-primary"></i>
                                                        <span class="text-muted"><?php echo htmlspecialchars($customer['shop_location']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Orders">Total Orders</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['total_orders'] ?? 0; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-chart-line"></i>
                                                </span>
                                                <span><?php echo $stats['total_orders'] ? 'Orders placed' : 'No orders yet'; ?></span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-cart-outline"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Total Sales">Total Sales</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <?php if ($stats['total_orders'] > 0): ?>
                                                <span class="text-info me-2">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                                <span>Avg: ₹<?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?></span>
                                                <?php else: ?>
                                                <span>No sales yet</span>
                                                <?php endif; ?>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Current Balance">Current Balance</h5>
                                            <h3 class="my-2 py-1 <?php echo $customer['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                ₹<?php echo number_format($customer['current_balance'], 2); ?>
                                            </h3>
                                            <p class="mb-0 text-muted">
                                                <?php if ($customer['current_balance'] > 0): ?>
                                                <span class="text-danger me-2">
                                                    <i class="mdi mdi-alert-circle"></i>
                                                </span>
                                                <span>Pending collection</span>
                                                <?php else: ?>
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-check-circle"></i>
                                                </span>
                                                <span>No balance due</span>
                                                <?php endif; ?>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Last Order">Last Order</h5>
                                            <h3 class="my-2 py-1">
                                                <?php 
                                                if ($stats['last_order_date']) {
                                                    echo date('d M', strtotime($stats['last_order_date']));
                                                } else {
                                                    echo 'Never';
                                                }
                                                ?>
                                            </h3>
                                            <p class="mb-0 text-muted">
                                                <?php if ($stats['last_order_date']): ?>
                                                <span class="<?php echo $days_since_last <= 7 ? 'text-success' : ($days_since_last <= 30 ? 'text-warning' : 'text-danger'); ?> me-2">
                                                    <i class="mdi mdi-calendar-clock"></i>
                                                </span>
                                                <span><?php echo $days_since_last; ?> days ago</span>
                                                <?php else: ?>
                                                <span>No orders placed</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-calendar-month"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Main Content -->
                    <div class="row">
                        <!-- Left Column: Recent Activity -->
                        <div class="col-lg-8">
                            <!-- Recent Orders -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">
                                            <i class="mdi mdi-cart-outline me-2"></i> Recent Orders
                                        </h5>
                                        <a href="orders.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-primary">
                                            View All Orders
                                        </a>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Date</th>
                                                    <th>Items</th>
                                                    <th>Total</th>
                                                    <th>Paid</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($recent_orders_result && mysqli_num_rows($recent_orders_result) > 0) {
                                                    while ($order = mysqli_fetch_assoc($recent_orders_result)) {
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <a href="view-invoice.php?id=<?php echo $order['id']; ?>" class="fw-bold">
                                                                    <?php echo $order['order_number']; ?>
                                                                </a>
                                                            </td>
                                                            <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                                            <td><?php echo $order['item_count']; ?> items</td>
                                                            <td class="fw-bold">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                            <td>
                                                                <span class="<?php echo $order['paid_amount'] >= $order['total_amount'] ? 'text-success' : 'text-warning'; ?>">
                                                                    ₹<?php echo number_format($order['paid_amount'], 2); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo getOrderStatusBadgeClass($order['status']); ?>">
                                                                    <?php echo ucfirst($order['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="view-invoice.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="mdi mdi-eye"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-cart-off display-4"></i>
                                                                <h5 class="mt-2">No Orders Found</h5>
                                                                <p>This customer hasn't placed any orders yet</p>
                                                                <a href="quick-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                                                                    Create First Order
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Transactions -->
                            <div class="card mt-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">
                                            <i class="mdi mdi-cash-multiple me-2"></i> Recent Transactions
                                        </h5>
                                        <a href="customer-transactions.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-primary">
                                            View All Transactions
                                        </a>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Type</th>
                                                    <th>Order #</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($recent_payments_result && mysqli_num_rows($recent_payments_result) > 0) {
                                                    while ($payment = mysqli_fetch_assoc($recent_payments_result)) {
                                                        ?>
                                                        <tr>
                                                            <td><?php echo date('d M, Y H:i', strtotime($payment['created_at'])); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo $payment['type'] == 'payment' ? 'bg-success' : 'bg-danger'; ?>">
                                                                    <?php echo ucfirst($payment['type']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($payment['order_id']): ?>
                                                                <a href="view-invoice.php?id=<?php echo $payment['order_id']; ?>" class="text-primary">
                                                                    <?php echo $payment['reference_no']; ?>
                                                                </a>
                                                                <?php else: ?>
                                                                <?php echo $payment['reference_no'] ?: 'N/A'; ?>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="fw-bold <?php echo $payment['type'] == 'payment' ? 'text-success' : 'text-danger'; ?>">
                                                                ₹<?php echo number_format($payment['amount'], 2); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-light text-dark">
                                                                    <?php echo ucfirst($payment['payment_method']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted"><?php echo $payment['notes'] ? substr($payment['notes'], 0, 30) . (strlen($payment['notes']) > 30 ? '...' : '') : '--'; ?></small>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-cash-off display-4"></i>
                                                                <h5 class="mt-2">No Transactions Found</h5>
                                                                <p>No payment transactions recorded yet</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Customer Details & Quick Actions -->
                        <div class="col-lg-4">
                            <!-- Customer Information Card -->
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-information-outline me-2"></i> Customer Information
                                    </h5>
                                    
                                    <div class="list-group list-group-flush">
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Customer Code:</span>
                                            <span class="fw-bold"><?php echo $customer['customer_code']; ?></span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Customer Type:</span>
                                            <span class="badge <?php echo getTypeBadgeClass($customer['customer_type']); ?>">
                                                <?php echo ucfirst($customer['customer_type']); ?>
                                            </span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Payment Terms:</span>
                                            <span class="fw-bold">
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
                                                echo $term_display[$customer['payment_terms']] ?? ucfirst($customer['payment_terms']);
                                                ?>
                                            </span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Credit Limit:</span>
                                            <span class="fw-bold">₹<?php echo number_format($customer['credit_limit'], 2); ?></span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Total Purchases:</span>
                                            <span class="fw-bold text-success">₹<?php echo number_format($customer['total_purchases'], 2); ?></span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">First Order:</span>
                                            <span class="fw-bold">
                                                <?php 
                                                if ($stats['first_order_date']) {
                                                    echo date('d M, Y', strtotime($stats['first_order_date']));
                                                } else {
                                                    echo 'No orders';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Created On:</span>
                                            <span class="fw-bold"><?php echo date('d M, Y', strtotime($customer['created_at'])); ?></span>
                                        </div>
                                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                            <span class="text-muted">Last Updated:</span>
                                            <span class="fw-bold"><?php echo date('d M, Y H:i', strtotime($customer['updated_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions Card -->
                            <div class="card mt-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-rocket-launch-outline me-2"></i> Quick Actions
                                    </h5>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="quick-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                            <i class="mdi mdi-cart-plus me-2"></i> Create New Order
                                        </a>
                                        <a href="collect-payment.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">
                                            <i class="mdi mdi-cash me-2"></i> Collect Payment
                                        </a>
                                        <a href="edit-customer.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-primary">
                                            <i class="mdi mdi-pencil me-2"></i> Edit Customer Details
                                        </a>
                                        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                            <i class="mdi mdi-account-convert me-2"></i> Change Status
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="printCustomerDetails()">
                                            <i class="mdi mdi-printer me-2"></i> Print Details
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Monthly Purchase Trend -->
                            <?php if (mysqli_num_rows($trend_result) > 0): ?>
                            <div class="card mt-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-chart-line me-2"></i> Monthly Purchase Trend
                                    </h5>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Month</th>
                                                    <th>Orders</th>
                                                    <th>Total</th>
                                                    <th>Paid</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($trend = mysqli_fetch_assoc($trend_result)): ?>
                                                <tr>
                                                    <td><?php echo $trend['month']; ?></td>
                                                    <td><?php echo $trend['order_count']; ?></td>
                                                    <td class="fw-bold">₹<?php echo number_format($trend['monthly_total'], 2); ?></td>
                                                    <td class="text-success">₹<?php echo number_format($trend['monthly_paid'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Notes Card -->
                            <?php if (!empty($customer['notes'])): ?>
                            <div class="card mt-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-note-text-outline me-2"></i> Customer Notes
                                    </h5>
                                    <div class="bg-light p-3 rounded">
                                        <p class="mb-0"><?php echo htmlspecialchars($customer['notes']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
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
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">Update Customer Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <p class="mb-2">Customer: <strong><?php echo htmlspecialchars($customer['shop_name']); ?></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $customer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $customer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="blocked" <?php echo $customer['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
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

    <style>
        .customer-avatar-xl {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 36px;
        }
        
        .card-h-100 {
            height: 100%;
        }
        
        .list-group-item {
            padding-left: 0;
            padding-right: 0;
        }
        
        .badge-soft-primary { background-color: rgba(85, 110, 230, 0.1); color: #556ee6; }
        .badge-soft-success { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .badge-soft-warning { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .badge-soft-info { background-color: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .badge-soft-secondary { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .badge-soft-danger { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
    </style>

    <script>
        // Helper functions for badge classes
        <?php 
        function getStatusBadgeClass($status) {
            $classes = [
                'active' => 'badge-soft-success',
                'inactive' => 'badge-soft-warning',
                'blocked' => 'badge-soft-danger'
            ];
            return $classes[$status] ?? 'badge-soft-secondary';
        }
        
        function getTypeBadgeClass($type) {
            $classes = [
                'retail' => 'badge-soft-primary',
                'wholesale' => 'badge-soft-success',
                'hotel' => 'badge-soft-warning',
                'office' => 'badge-soft-info',
                'residential' => 'badge-soft-secondary',
                'other' => 'badge-soft-secondary'
            ];
            return $classes[$type] ?? 'badge-soft-secondary';
        }
        
        function getOrderStatusBadgeClass($status) {
            $classes = [
                'pending' => 'bg-warning',
                'processing' => 'bg-info',
                'delivered' => 'bg-success',
                'cancelled' => 'bg-danger'
            ];
            return $classes[$status] ?? 'bg-secondary';
        }
        ?>

        // Delete customer confirmation
        function deleteCustomer(customerId) {
            if (confirm('Are you sure you want to delete this customer? This action cannot be undone and will delete all associated orders and transactions.')) {
                window.location.href = 'delete-customer.php?id=' + customerId;
            }
        }

        // Print customer details
        function printCustomerDetails() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Customer Details - <?php echo htmlspecialchars($customer['shop_name']); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .avatar { width: 80px; height: 80px; border-radius: 50%; background: #667eea; color: white; 
                                 display: flex; align-items: center; justify-content: center; font-size: 32px;
                                 font-weight: bold; margin: 0 auto 15px; }
                        .info-section { margin-bottom: 30px; }
                        .info-section h3 { border-bottom: 2px solid #333; padding-bottom: 5px; margin-bottom: 15px; }
                        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
                        .info-item { margin-bottom: 10px; }
                        .info-label { font-weight: bold; color: #666; }
                        .info-value { color: #333; }
                        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                        .stat-box { text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
                        .stat-value { font-size: 20px; font-weight: bold; margin: 5px 0; }
                        .stat-label { font-size: 12px; color: #666; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        .text-success { color: #28a745; }
                        .text-danger { color: #dc3545; }
                        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                        .badge-success { background: #d4edda; color: #155724; }
                        .badge-warning { background: #fff3cd; color: #856404; }
                        .badge-danger { background: #f8d7da; color: #721c24; }
                        .badge-info { background: #d1ecf1; color: #0c5460; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="avatar"><?php echo strtoupper(substr($customer['shop_name'], 0, 1) . substr($customer['customer_name'], 0, 1)); ?></div>
                        <h1><?php echo htmlspecialchars($customer['shop_name']); ?></h1>
                        <p><?php echo htmlspecialchars($customer['customer_name']); ?> | <?php echo $customer['customer_code']; ?></p>
                    </div>
                    
                    <div class="info-section">
                        <h3>Contact Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Primary Contact:</div>
                                <div class="info-value"><?php echo $customer['customer_contact']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Alternate Contact:</div>
                                <div class="info-value"><?php echo $customer['alternate_contact'] ?: 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email:</div>
                                <div class="info-value"><?php echo $customer['email'] ?: 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Location:</div>
                                <div class="info-value"><?php echo htmlspecialchars($customer['shop_location']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3>Business Details</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Customer Type:</div>
                                <div class="info-value">
                                    <span class="badge <?php echo getTypeBadgeClass($customer['customer_type']); ?>">
                                        <?php echo ucfirst($customer['customer_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status:</div>
                                <div class="info-value">
                                    <span class="badge <?php echo getStatusBadgeClass($customer['status']); ?>">
                                        <?php echo ucfirst($customer['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Terms:</div>
                                <div class="info-value">
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
                                    echo $term_display[$customer['payment_terms']] ?? ucfirst($customer['payment_terms']);
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Credit Limit:</div>
                                <div class="info-value">₹<?php echo number_format($customer['credit_limit'], 2); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Assigned Area:</div>
                                <div class="info-value"><?php echo $customer['assigned_area'] ?: 'Not assigned'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer Since:</div>
                                <div class="info-value"><?php echo date('d M, Y', strtotime($customer['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-value">₹<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Current Balance</div>
                            <div class="stat-value <?php echo $customer['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                ₹<?php echo number_format($customer['current_balance'], 2); ?>
                            </div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Last Order</div>
                            <div class="stat-value">
                                <?php 
                                if ($stats['last_order_date']) {
                                    echo date('d M, Y', strtotime($stats['last_order_date']));
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Orders -->
                    <?php if (mysqli_num_rows($recent_orders_result) > 0): ?>
                    <div class="info-section">
                        <h3>Recent Orders</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($recent_orders_result, 0);
                                while ($order = mysqli_fetch_assoc($recent_orders_result)): 
                                ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
                                    <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                    <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($order['paid_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo getOrderStatusBadgeClass($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($customer['notes'])): ?>
                    <div class="info-section">
                        <h3>Additional Notes</h3>
                        <div class="info-item">
                            <div class="info-value"><?php echo htmlspecialchars($customer['notes']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                        <p>Generated by: <?php echo $_SESSION['name']; ?></p>
                        <p>APR Water Agencies - Customer Management System</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Quick navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+E for edit
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    window.location.href = 'edit-customer.php?id=<?php echo $customer_id; ?>';
                }
                // Ctrl+O for new order
                if (e.ctrlKey && e.key === 'o') {
                    e.preventDefault();
                    window.location.href = 'quick-order.php?customer_id=<?php echo $customer_id; ?>';
                }
                // Ctrl+P for print
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    printCustomerDetails();
                }
            });
            
            // Add tooltips for keyboard shortcuts
            const tooltips = {
                'editLink': 'Ctrl+E - Edit Customer',
                'orderLink': 'Ctrl+O - Create Order',
                'printBtn': 'Ctrl+P - Print Details'
            };
            
            Object.keys(tooltips).forEach(key => {
                const element = document.getElementById(key);
                if (element) {
                    element.setAttribute('title', tooltips[key]);
                    element.setAttribute('data-bs-toggle', 'tooltip');
                }
            });
            
            // Initialize Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}