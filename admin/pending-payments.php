<?php
// Start session and include config
session_start();
include('config/config.php');

// Function to format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// Function to format date
function formatDate($date) {
    return date('d M, Y', strtotime($date));
}

// Function to get days difference
function getDaysDifference($date) {
    $today = new DateTime();
    $orderDate = new DateTime($date);
    $interval = $today->diff($orderDate);
    return $interval->days;
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$view_type = isset($_GET['view']) ? $_GET['view'] : 'orders'; // 'orders' or 'customers'

// Fetch data based on view type
$customers_summary = [];
$orders_summary = [];
$total_pending = 0;
$total_customers = 0;
$total_orders = 0;
$overdue_amount = 0;

// Fetch customers for dropdown
$customers = [];
$customers_sql = "SELECT id, customer_name, shop_name, customer_contact FROM customers WHERE status = 'active' ORDER BY customer_name";
$customers_result = mysqli_query($conn, $customers_sql);
if ($customers_result) {
    while ($row = mysqli_fetch_assoc($customers_result)) {
        $customers[$row['id']] = $row['customer_name'] . ' - ' . $row['shop_name'] . ' (' . $row['customer_contact'] . ')';
    }
}

if ($conn) {
    if ($view_type === 'customers') {
        // Customer-wise summary
        $sql = "SELECT 
                    c.id,
                    c.customer_code,
                    c.customer_name,
                    c.shop_name,
                    c.customer_contact,
                    c.customer_type,
                    c.current_balance,
                    c.payment_terms,
                    COUNT(DISTINCT o.id) as pending_orders_count,
                    SUM(o.pending_amount) as total_pending,
                    MAX(o.order_date) as last_order_date,
                    DATEDIFF(CURDATE(), MAX(o.order_date)) as days_since_last_order
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id 
                    AND o.pending_amount > 0 
                    AND o.status != 'cancelled'
                WHERE c.status = 'active'";
        
        if ($customer_id > 0) {
            $sql .= " AND c.id = $customer_id";
        }
        
        if ($search_term) {
            $sql .= " AND (c.customer_name LIKE '%$search_term%' 
                          OR c.shop_name LIKE '%$search_term%' 
                          OR c.customer_contact LIKE '%$search_term%'
                          OR c.customer_code LIKE '%$search_term%')";
        }
        
        $sql .= " GROUP BY c.id
                  HAVING (COUNT(DISTINCT o.id) > 0 OR c.current_balance > 0)
                  ORDER BY total_pending DESC, c.customer_name";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($sql)) {
                $customers_summary[] = $row;
                $total_pending += $row['total_pending'];
                $total_customers++;
                
                // Check if overdue
                if ($row['days_since_last_order'] > 30 && $row['total_pending'] > 0) {
                    $overdue_amount += $row['total_pending'];
                }
            }
        }
    } else {
        // Order-wise summary
        $sql = "SELECT 
                    o.*,
                    c.customer_name,
                    c.shop_name,
                    c.customer_contact,
                    c.customer_type,
                    c.current_balance,
                    DATEDIFF(CURDATE(), o.order_date) as days_passed,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE o.pending_amount > 0 
                AND o.status != 'cancelled'";
        
        // Add date filter
        $sql .= " AND o.order_date BETWEEN '$start_date' AND '$end_date'";
        
        // Add customer filter
        if ($customer_id > 0) {
            $sql .= " AND o.customer_id = $customer_id";
        }
        
        // Add payment status filter
        if ($payment_status === 'overdue') {
            $sql .= " AND DATEDIFF(CURDATE(), o.order_date) > 30";
        } elseif ($payment_status === 'partial') {
            $sql .= " AND o.paid_amount > 0 AND o.pending_amount > 0";
        } elseif ($payment_status === 'pending') {
            $sql .= " AND o.paid_amount = 0";
        }
        
        // Add search filter
        if ($search_term) {
            $sql .= " AND (o.order_number LIKE '%$search_term%' 
                          OR c.customer_name LIKE '%$search_term%'
                          OR c.shop_name LIKE '%$search_term%'
                          OR c.customer_contact LIKE '%$search_term%')";
        }
        
        $sql .= " ORDER BY o.order_date ASC, o.pending_amount DESC";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $orders_summary[] = $row;
                $total_pending += $row['pending_amount'];
                $total_orders++;
                
                // Check if overdue
                if ($row['days_passed'] > 30) {
                    $overdue_amount += $row['pending_amount'];
                }
            }
        }
    }
    
    // Get today's collections
    $today_sql = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM transactions 
                  WHERE type = 'payment' 
                  AND DATE(created_at) = CURDATE()";
    $today_result = mysqli_query($conn, $today_sql);
    $today_stats = mysqli_fetch_assoc($today_result);
    
    // Get this month's collections
    $month_sql = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM transactions 
                  WHERE type = 'payment' 
                  AND MONTH(created_at) = MONTH(CURDATE()) 
                  AND YEAR(created_at) = YEAR(CURDATE())";
    $month_result = mysqli_query($conn, $month_sql);
    $month_stats = mysqli_fetch_assoc($month_result);
    
    // Get total customers with pending
    $customers_pending_sql = "SELECT COUNT(DISTINCT customer_id) as count 
                             FROM orders 
                             WHERE pending_amount > 0 
                             AND status != 'cancelled'";
    $customers_pending_result = mysqli_query($conn, $customers_pending_sql);
    $customers_pending_stats = mysqli_fetch_assoc($customers_pending_result);
}
?>

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


                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-cash-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Pending</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($total_pending); ?>
                                        </h4>
                                        <p class="text-muted mb-0">
                                            <?php echo $view_type === 'customers' ? $total_customers . ' customers' : $total_orders . ' orders'; ?>
                                        </p>
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
                                            <i class="mdi mdi-cash-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Today's Collections</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($today_stats['total'] ?? 0); ?>
                                        </h4>
                                        <p class="text-muted mb-0"><?php echo $today_stats['count'] ?? 0; ?> payments</p>
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
                                        <span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2">
                                            <i class="mdi mdi-calendar-clock"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Overdue (30+ days)</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($overdue_amount); ?>
                                        </h4>
                                        <p class="text-muted mb-0"><?php echo $customers_pending_stats['count'] ?? 0; ?> customers</p>
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
                                            <i class="mdi mdi-calendar-month"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">This Month</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($month_stats['total'] ?? 0); ?>
                                        </h4>
                                        <p class="text-muted mb-0"><?php echo $month_stats['count'] ?? 0; ?> payments</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <!-- Filters Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Filters & View Options</h5>
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">View Type</label>
                                        <select class="form-select" name="view" onchange="this.form.submit()">
                                            <option value="orders" <?php echo $view_type == 'orders' ? 'selected' : ''; ?>>Order-wise</option>
                                            <option value="customers" <?php echo $view_type == 'customers' ? 'selected' : ''; ?>>Customer-wise</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($view_type === 'orders'): ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" 
                                               value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" 
                                               value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Customer</label>
                                        <select class="form-select" name="customer_id">
                                            <option value="0">All Customers</option>
                                            <?php foreach ($customers as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" 
                                                    <?php echo $customer_id == $id ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <?php if ($view_type === 'orders'): ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="all" <?php echo $payment_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="overdue" <?php echo $payment_status == 'overdue' ? 'selected' : ''; ?>>Overdue Only</option>
                                            <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>Partial Paid</option>
                                            <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Fully Pending</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" 
                                               value="<?php echo htmlspecialchars($search_term); ?>" 
                                               placeholder="Search...">
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="d-flex gap-2 mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-filter me-1"></i> Apply Filters
                                            </button>
                                            <a href="pending-payments.php" class="btn btn-secondary">
                                                <i class="mdi mdi-refresh me-1"></i> Reset
                                            </a>
                                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                                <i class="mdi mdi-file-excel me-1"></i> Export
                                            </button>
                                            <button type="button" class="btn btn-info" onclick="printReport()">
                                                <i class="mdi mdi-printer me-1"></i> Print
                                            </button>
                                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkPaymentModal">
                                                <i class="mdi mdi-cash-multiple me-1"></i> Bulk Payment
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content - Customer-wise View -->
                <?php if ($view_type === 'customers'): ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">Customer-wise Pending Payments</h4>
                                        <p class="card-title-desc">View pending amounts by customer</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end">
                                            <div class="me-3">
                                                <span class="text-muted">Showing:</span>
                                                <span class="fw-bold"><?php echo count($customers_summary); ?> customers</span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Total:</span>
                                                <span class="fw-bold"><?php echo formatCurrency($total_pending); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="customerPaymentsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Customer</th>
                                                <th>Contact</th>
                                                <th>Customer Type</th>
                                                <th>Payment Terms</th>
                                                <th>Current Balance</th>
                                                <th>Pending Orders</th>
                                                <th>Pending Amount</th>
                                                <th>Last Order Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($customers_summary)): ?>
                                                <?php $counter = 1; ?>
                                                <?php foreach ($customers_summary as $customer): ?>
                                                    <?php
                                                    // Determine status
                                                    $is_overdue = $customer['days_since_last_order'] > 30 && $customer['total_pending'] > 0;
                                                    $status_class = $is_overdue ? 'badge-soft-danger' : ($customer['total_pending'] > 0 ? 'badge-soft-warning' : 'badge-soft-success');
                                                    $status_text = $is_overdue ? 'Overdue' : ($customer['total_pending'] > 0 ? 'Pending' : 'Clear');
                                                    
                                                    // Determine amount class
                                                    if ($customer['total_pending'] > 5000) {
                                                        $amount_class = 'pending-high';
                                                    } elseif ($customer['total_pending'] > 1000) {
                                                        $amount_class = 'pending-medium';
                                                    } else {
                                                        $amount_class = 'pending-low';
                                                    }
                                                    
                                                    // Format dates
                                                    $last_order = $customer['last_order_date'] 
                                                        ? date('d M, Y', strtotime($customer['last_order_date'])) 
                                                        : 'No orders';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($customer['customer_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="customer-view.php?id=<?php echo $customer['id']; ?>" class="text-dark">
                                                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($customer['shop_name']); ?></p>
                                                                    <small class="text-muted"><?php echo $customer['customer_code']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <i class="mdi mdi-phone me-1 text-muted"></i>
                                                                <?php echo htmlspecialchars($customer['customer_contact']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info-subtle text-info">
                                                                <?php echo ucfirst($customer['customer_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $terms = [
                                                                'cash' => 'Cash',
                                                                'credit_7' => '7 Days',
                                                                'credit_15' => '15 Days',
                                                                'credit_30' => '30 Days',
                                                                'prepaid' => 'Prepaid',
                                                                'weekly' => 'Weekly',
                                                                'monthly' => 'Monthly'
                                                            ];
                                                            echo $terms[$customer['payment_terms']] ?? 'Cash';
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <span class="<?php echo $customer['current_balance'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                                                <?php echo formatCurrency($customer['current_balance']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-warning-subtle text-warning">
                                                                <?php echo $customer['pending_orders_count']; ?> orders
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="payment-amount <?php echo $amount_class; ?> p-2 rounded">
                                                                <?php echo formatCurrency($customer['total_pending']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo $last_order; ?>
                                                            <?php if ($customer['days_since_last_order'] > 0 && $customer['last_order_date']): ?>
                                                                <br>
                                                                <small class="text-muted">(<?php echo $customer['days_since_last_order']; ?> days ago)</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                                <?php if ($is_overdue): ?>
                                                                    <br>
                                                                    <small><?php echo $customer['days_since_last_order']; ?> days</small>
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-account-check display-4"></i>
                                                            <h5 class="mt-2">No Pending Customers</h5>
                                                            <p>All customers have cleared their payments</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination and Summary -->
                                <div class="row mt-3">
                                    <div class="col-sm-12 col-md-5">
                                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                            Showing <?php echo count($customers_summary); ?> customers with pending payments
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-7">
                                        <div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                                            <ul class="pagination justify-content-end">
                                                <li class="paginate_button page-item previous disabled" id="datatable_previous">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="0" tabindex="0" class="page-link">Previous</a>
                                                </li>
                                                <li class="paginate_button page-item active">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="1" tabindex="0" class="page-link">1</a>
                                                </li>
                                                <li class="paginate_button page-item next" id="datatable_next">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="2" tabindex="0" class="page-link">Next</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Main Content - Order-wise View -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">Order-wise Pending Payments</h4>
                                        <p class="card-title-desc">View pending amounts by individual orders</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end">
                                            <div class="me-3">
                                                <span class="text-muted">Showing:</span>
                                                <span class="fw-bold"><?php echo count($orders_summary); ?> orders</span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Total:</span>
                                                <span class="fw-bold"><?php echo formatCurrency($total_pending); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="orderPaymentsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Order No.</th>
                                                <th>Customer</th>
                                                <th>Order Date</th>
                                                <th>Due Days</th>
                                                <th>Order Status</th>
                                                <th>Total Amount</th>
                                                <th>Paid Amount</th>
                                                <th>Pending Amount</th>
                                                <th>Payment Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($orders_summary)): ?>
                                                <?php $counter = 1; ?>
                                                <?php foreach ($orders_summary as $order): ?>
                                                    <?php
                                                    // Calculate days passed
                                                    $days_passed = getDaysDifference($order['order_date']);
                                                    
                                                    // Determine status
                                                    $is_overdue = $days_passed > 30;
                                                    $status_class = $is_overdue ? 'badge-soft-danger' : 'badge-soft-warning';
                                                    $status_text = $is_overdue ? 'Overdue' : 'Pending';
                                                    
                                                    // Order status badge
                                                    $order_status_class = '';
                                                    if ($order['status'] == 'delivered') $order_status_class = 'badge-soft-success';
                                                    elseif ($order['status'] == 'processing') $order_status_class = 'badge-soft-info';
                                                    elseif ($order['status'] == 'pending') $order_status_class = 'badge-soft-warning';
                                                    
                                                    // Payment status badge
                                                    $payment_status_class = '';
                                                    $payment_status_text = '';
                                                    if ($order['payment_status'] == 'paid') {
                                                        $payment_status_class = 'badge-soft-success';
                                                        $payment_status_text = 'Paid';
                                                    } elseif ($order['payment_status'] == 'partial') {
                                                        $payment_status_class = 'badge-soft-warning';
                                                        $payment_status_text = 'Partial';
                                                    } else {
                                                        $payment_status_class = 'badge-soft-danger';
                                                        $payment_status_text = 'Pending';
                                                    }
                                                    
                                                    // Determine amount class
                                                    if ($order['pending_amount'] > 5000) {
                                                        $amount_class = 'pending-high';
                                                    } elseif ($order['pending_amount'] > 1000) {
                                                        $amount_class = 'pending-medium';
                                                    } else {
                                                        $amount_class = 'pending-low';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo htmlspecialchars($order['order_number']); ?></span>
                                                            <?php if ($order['item_count'] > 0): ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo $order['item_count']; ?> item(s)</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="customer-view.php?id=<?php echo $order['customer_id']; ?>" class="text-dark">
                                                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($order['shop_name']); ?></p>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_contact']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php echo formatDate($order['order_date']); ?>
                                                            <?php if ($order['delivery_date']): ?>
                                                                <br>
                                                                <small class="text-muted">Delivered: <?php echo formatDate($order['delivery_date']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo $days_passed; ?> days
                                                            </span>
                                                            <br>
                                                            <small class="text-muted"><?php echo $status_text; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $order_status_class; ?>">
                                                                <?php echo ucfirst($order['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo formatCurrency($order['total_amount']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="text-success"><?php echo formatCurrency($order['paid_amount']); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="payment-amount <?php echo $amount_class; ?> p-2 rounded">
                                                                <span class="fw-bold"><?php echo formatCurrency($order['pending_amount']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $payment_status_class; ?>">
                                                                <?php echo $payment_status_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="order-view.php?id=<?php echo $order['id']; ?>">
                                                                            <i class="mdi mdi-eye-outline me-1"></i> View Order
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item receive-payment-btn" href="#" 
                                                                           data-bs-toggle="modal" 
                                                                           data-bs-target="#receivePaymentModal"
                                                                           data-order-id="<?php echo $order['id']; ?>"
                                                                           data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                                                                           data-customer-name="<?php echo htmlspecialchars($order['customer_name']); ?>"
                                                                           data-pending-amount="<?php echo $order['pending_amount']; ?>"
                                                                           data-total-amount="<?php echo $order['total_amount']; ?>"
                                                                           data-paid-amount="<?php echo $order['paid_amount']; ?>">
                                                                            <i class="mdi mdi-cash-check me-1"></i> Receive Payment
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank">
                                                                            <i class="mdi mdi-receipt me-1"></i> Generate Invoice
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="customer-view.php?id=<?php echo $order['customer_id']; ?>">
                                                                            <i class="mdi mdi-account-outline me-1"></i> View Customer
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-cash-remove display-4"></i>
                                                            <h5 class="mt-2">No Pending Orders</h5>
                                                            <p>All orders have been paid</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination and Summary -->
                                <div class="row mt-3">
                                    <div class="col-sm-12 col-md-5">
                                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                            Showing <?php echo count($orders_summary); ?> orders totaling <?php echo formatCurrency($total_pending); ?>
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-7">
                                        <div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                                            <ul class="pagination justify-content-end">
                                                <li class="paginate_button page-item previous disabled" id="datatable_previous">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="0" tabindex="0" class="page-link">Previous</a>
                                                </li>
                                                <li class="paginate_button page-item active">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="1" tabindex="0" class="page-link">1</a>
                                                </li>
                                                <li class="paginate_button page-item next" id="datatable_next">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="2" tabindex="0" class="page-link">Next</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics Section -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Payment Distribution</h5>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Overdue Payments</span>
                                    <span class="fw-bold"><?php echo formatCurrency($overdue_amount); ?></span>
                                </div>
                                <div class="progress mb-3" style="height: 6px;">
                                    <div class="progress-bar bg-danger" role="progressbar" 
                                         style="width: <?php echo $total_pending > 0 ? ($overdue_amount / $total_pending * 100) : 0; ?>%"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Current Pending</span>
                                    <span class="fw-bold"><?php echo formatCurrency($total_pending - $overdue_amount); ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $total_pending > 0 ? (($total_pending - $overdue_amount) / $total_pending * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <a href="add-order.php" class="btn btn-outline-primary">
                                        <i class="mdi mdi-plus-circle me-1"></i> Create New Order
                                    </a>
                                    <a href="customers.php" class="btn btn-outline-success">
                                        <i class="mdi mdi-account-group me-1"></i> View All Customers
                                    </a>
                                    <a href="payment-history.php" class="btn btn-outline-info">
                                        <i class="mdi mdi-history me-1"></i> View Payment History
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary" onclick="sendReminders()">
                                        <i class="mdi mdi-email-alert me-1"></i> Send Payment Reminders
                                    </button>
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

<!-- Receive Payment Modal -->
<div class="modal fade" id="receivePaymentModal" tabindex="-1" aria-labelledby="receivePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="process-payment.php" id="paymentForm">
                <input type="hidden" name="order_id" id="modal_order_id">
                <input type="hidden" name="customer_id" id="modal_customer_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="receivePaymentModalLabel">Receive Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Number</label>
                        <input type="text" class="form-control" id="modal_order_number" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="modal_customer_name" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" id="modal_total_amount" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Already Paid</label>
                            <input type="text" class="form-control" id="modal_paid_amount" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pending Amount</label>
                        <input type="text" class="form-control" id="modal_pending_amount" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="form-text">
                            <small>Maximum amount: ₹<span id="max_amount">0</span></small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" name="reference_no" 
                               placeholder="Transaction ID, UPI Ref, Cheque No., etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" 
                                  placeholder="Any additional information..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Receive Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle receive payment button click
    const receivePaymentBtns = document.querySelectorAll('.receive-payment-btn');
    const modal = document.getElementById('receivePaymentModal');
    
    receivePaymentBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const orderNumber = this.getAttribute('data-order-number');
            const customerName = this.getAttribute('data-customer-name');
            const pendingAmount = this.getAttribute('data-pending-amount');
            const totalAmount = this.getAttribute('data-total-amount');
            const paidAmount = this.getAttribute('data-paid-amount');
            
            // Set modal values
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_order_number').value = orderNumber;
            document.getElementById('modal_customer_name').value = customerName;
            document.getElementById('modal_total_amount').value = '₹' + parseFloat(totalAmount).toFixed(2);
            document.getElementById('modal_paid_amount').value = '₹' + parseFloat(paidAmount).toFixed(2);
            document.getElementById('modal_pending_amount').value = '₹' + parseFloat(pendingAmount).toFixed(2);
            document.getElementById('max_amount').textContent = parseFloat(pendingAmount).toFixed(2);
            
            // Set max value for payment amount
            const paymentAmountInput = document.getElementById('payment_amount');
            paymentAmountInput.max = pendingAmount;
            paymentAmountInput.value = pendingAmount;
        });
    });
    
    // Validate payment amount
    const paymentForm = document.getElementById('paymentForm');
    paymentForm.addEventListener('submit', function(e) {
        const paymentAmount = parseFloat(document.getElementById('payment_amount').value);
        const maxAmount = parseFloat(document.getElementById('max_amount').textContent);
        
        if (paymentAmount > maxAmount) {
            e.preventDefault();
            alert('Payment amount cannot exceed pending amount');
            return false;
        }
        
        if (paymentAmount <= 0) {
            e.preventDefault();
            alert('Please enter a valid payment amount');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        submitBtn.disabled = true;
        
        return true;
    });
    
    // Auto-submit date filters when dates change
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    [startDateInput, endDateInput].forEach(input => {
        input.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Initialize DataTables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#customerPaymentsTable, #orderPaymentsTable').DataTable({
            "pageLength": 25,
            "order": [[0, 'asc']],
            "language": {
                "paginate": {
                    "previous": "<i class='mdi mdi-chevron-left'>",
                    "next": "<i class='mdi mdi-chevron-right'>"
                }
            },
            "drawCallback": function() {
                $('.dataTables_paginate > .pagination').addClass('pagination-rounded');
            }
        });
    }
});

// Export to Excel function
function exportToExcel() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Create export URL
    let exportUrl = 'export-pending-payments.php?';
    
    // Add all current filters
    params.forEach((value, key) => {
        exportUrl += `${key}=${encodeURIComponent(value)}&`;
    });
    
    // Add export format
    exportUrl += 'format=excel';
    
    // Open export in new tab
    window.open(exportUrl, '_blank');
}

// Print report function
function printReport() {
    const viewType = '<?php echo $view_type; ?>';
    const printContent = document.querySelector('.card').outerHTML;
    const originalContent = document.body.innerHTML;
    const printTitle = 'Pending Payments Report - ' + new Date().toLocaleDateString();
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${printTitle}</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .table { width: 100%; border-collapse: collapse; }
                .table th, .table td { border: 1px solid #ddd; padding: 6px; }
                .table th { background-color: #f2f2f2; text-align: left; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                .summary-box { 
                    background: #f8f9fa; 
                    border: 1px solid #dee2e6; 
                    padding: 10px; 
                    margin: 10px 0; 
                    border-radius: 4px;
                }
                .no-print { display: none; }
                @media print {
                    .no-print { display: none; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="text-align: center; margin: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Print Report
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; margin-left: 10px; cursor: pointer;">
                    Close
                </button>
            </div>
            
            <div style="text-align: center; margin-bottom: 20px;">
                <h2>Pending Payments Report</h2>
                <p>
                    View: ${viewType === 'customers' ? 'Customer-wise' : 'Order-wise'} | 
                    Generated on: ${new Date().toLocaleDateString()}
                </p>
            </div>
            
            <div class="summary-box">
                <h4>Summary</h4>
                <p>Total Pending: ${<?php echo formatCurrency($total_pending); ?>} | 
                   Overdue: ${<?php echo formatCurrency($overdue_amount); ?>} | 
                   ${viewType === 'customers' ? 'Customers: ' . $total_customers : 'Orders: ' . $total_orders}</p>
            </div>
            
            ${printContent}
            
            <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
                <p>Report generated by APR Water Agencies</p>
                <p>Page generated on: ${new Date().toLocaleString()}</p>
            </div>
        </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Send payment reminders function
function sendReminders() {
    if (confirm('Send payment reminders to all customers with overdue payments?')) {
        fetch('send-payment-reminders.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment reminders sent successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Network error: ' + error);
            });
    }
}

// Set default date range to last 30 days
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    // If dates are empty, set to last 30 days
    if (startDateInput && !startDateInput.value) {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
    }
    
    if (endDateInput && !endDateInput.value) {
        endDateInput.value = new Date().toISOString().split('T')[0];
    }
});
</script>

<style>
.pending-high {
    background-color: #f8d7da;
    color: #721c24;
    border-radius: 4px;
}
.pending-medium {
    background-color: #fff3cd;
    color: #856404;
    border-radius: 4px;
}
.pending-low {
    background-color: #d1ecf1;
    color: #0c5460;
    border-radius: 4px;
}
.payment-amount {
    font-weight: 600;
    text-align: center;
}
</style>

</body>

</html>
<?php
// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>