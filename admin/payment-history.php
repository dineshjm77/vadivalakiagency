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

// Function to format datetime
function formatDateTime($datetime) {
    return date('d M, Y h:i A', strtotime($datetime));
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$search_term = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Fetch payment history
$payments = [];
$total_amount = 0;
$total_count = 0;

// Fetch customers for dropdown
$customers = [];
$customers_sql = "SELECT id, customer_name, shop_name FROM customers WHERE status = 'active' ORDER BY customer_name";
$customers_result = mysqli_query($conn, $customers_sql);
if ($customers_result) {
    while ($row = mysqli_fetch_assoc($customers_result)) {
        $customers[$row['id']] = $row['customer_name'] . ' - ' . $row['shop_name'];
    }
}

if ($conn) {
    // Build SQL query
    $sql = "SELECT 
                t.*,
                t.id as transaction_id,
                t.created_at as payment_date,
                c.customer_name,
                c.shop_name,
                c.customer_contact,
                o.order_number,
                o.total_amount as order_total
            FROM transactions t
            LEFT JOIN customers c ON t.customer_id = c.id
            LEFT JOIN orders o ON t.order_id = o.id
            WHERE t.type = 'payment'";
    
    // Add date filter
    $sql .= " AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";
    
    // Add customer filter
    if ($customer_id > 0) {
        $sql .= " AND t.customer_id = $customer_id";
    }
    
    // Add payment method filter
    if ($payment_method !== 'all') {
        $sql .= " AND t.payment_method = '$payment_method'";
    }
    
    // Add search filter
    if ($search_term) {
        $sql .= " AND (t.payment_id LIKE '%$search_term%' 
                      OR t.reference_no LIKE '%$search_term%'
                      OR c.customer_name LIKE '%$search_term%'
                      OR c.shop_name LIKE '%$search_term%'
                      OR o.order_number LIKE '%$search_term%')";
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
            $total_amount += $row['amount'];
            $total_count++;
        }
    }
    
    // Get summary statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount,
                    payment_method,
                    COUNT(*) as method_count
                  FROM transactions 
                  WHERE type = 'payment' 
                  AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
    
    if ($customer_id > 0) {
        $stats_sql .= " AND customer_id = $customer_id";
    }
    
    $stats_sql .= " GROUP BY payment_method";
    $stats_result = mysqli_query($conn, $stats_sql);
    $payment_stats = [];
    $total_payments_all = 0;
    $total_amount_all = 0;
    
    if ($stats_result) {
        while ($row = mysqli_fetch_assoc($stats_result)) {
            $payment_stats[$row['payment_method']] = $row;
            $total_payments_all += $row['total_payments'];
            $total_amount_all += $row['total_amount'];
        }
    }
    
    // Get today's collections
    $today_sql = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM transactions 
                  WHERE type = 'payment' 
                  AND DATE(created_at) = CURDATE()";
    $today_result = mysqli_query($conn, $today_sql);
    $today_stats = mysqli_fetch_assoc($today_result);
    
    // Get yesterday's collections
    $yesterday_sql = "SELECT COUNT(*) as count, SUM(amount) as total 
                      FROM transactions 
                      WHERE type = 'payment' 
                      AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    $yesterday_result = mysqli_query($conn, $yesterday_sql);
    $yesterday_stats = mysqli_fetch_assoc($yesterday_result);
    
    // Get this month's collections
    $month_sql = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM transactions 
                  WHERE type = 'payment' 
                  AND MONTH(created_at) = MONTH(CURDATE()) 
                  AND YEAR(created_at) = YEAR(CURDATE())";
    $month_result = mysqli_query($conn, $month_sql);
    $month_stats = mysqli_fetch_assoc($month_result);
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Collected</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($total_amount_all); ?>
                                        </h4>
                                        <p class="text-muted mb-0"><?php echo $total_payments_all; ?> payments</p>
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

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2">
                                            <i class="mdi mdi-cash-refund"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Yesterday</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($yesterday_stats['total'] ?? 0); ?>
                                        </h4>
                                        <p class="text-muted mb-0"><?php echo $yesterday_stats['count'] ?? 0; ?> payments</p>
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
                                <h5 class="card-title mb-3">Filters</h5>
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" 
                                               value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" 
                                               value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <div class="col-md-3">
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
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Method</label>
                                        <select class="form-select" name="payment_method">
                                            <option value="all" <?php echo $payment_method == 'all' ? 'selected' : ''; ?>>All Methods</option>
                                            <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="bank_transfer" <?php echo $payment_method == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="upi" <?php echo $payment_method == 'upi' ? 'selected' : ''; ?>>UPI</option>
                                            <option value="cheque" <?php echo $payment_method == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                            <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Card</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" 
                                               value="<?php echo htmlspecialchars($search_term); ?>" 
                                               placeholder="Search by Payment ID, Reference, Customer, Order...">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex gap-2 mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-filter me-1"></i> Apply Filters
                                            </button>
                                            <a href="payment-history.php" class="btn btn-secondary">
                                                <i class="mdi mdi-refresh me-1"></i> Reset
                                            </a>
                                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                                <i class="mdi mdi-file-excel me-1"></i> Export
                                            </button>
                                            <button type="button" class="btn btn-info" onclick="printReport()">
                                                <i class="mdi mdi-printer me-1"></i> Print
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Statistics -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Payment Method Statistics</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Payment Method</th>
                                                <th>Number of Payments</th>
                                                <th>Total Amount</th>
                                                <th>Average Payment</th>
                                                <th>Percentage</th>
                                                <th>Min Amount</th>
                                                <th>Max Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($payment_stats)): ?>
                                                <?php foreach ($payment_stats as $method => $stats): ?>
                                                    <?php
                                                    $percentage = $total_amount_all > 0 ? ($stats['total_amount'] / $total_amount_all * 100) : 0;
                                                    $method_names = [
                                                        'cash' => 'Cash',
                                                        'bank_transfer' => 'Bank Transfer',
                                                        'upi' => 'UPI',
                                                        'cheque' => 'Cheque',
                                                        'card' => 'Card'
                                                    ];
                                                    $method_name = $method_names[$method] ?? ucfirst($method);
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-primary-subtle text-primary">
                                                                <?php echo $method_name; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $stats['total_payments']; ?></td>
                                                        <td><?php echo formatCurrency($stats['total_amount']); ?></td>
                                                        <td><?php echo formatCurrency($stats['avg_amount']); ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                                    <div class="progress-bar" role="progressbar" 
                                                                         style="width: <?php echo $percentage; ?>%"></div>
                                                                </div>
                                                                <span class="ms-2"><?php echo number_format($percentage, 1); ?>%</span>
                                                            </div>
                                                        </td>
                                                        <td><?php echo formatCurrency($stats['min_amount']); ?></td>
                                                        <td><?php echo formatCurrency($stats['max_amount']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">No payment statistics available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">Payment History</h4>
                                        <p class="card-title-desc">All payment transactions recorded in the system</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end">
                                            <div class="me-3">
                                                <span class="text-muted">Showing:</span>
                                                <span class="fw-bold"><?php echo $total_count; ?> payments</span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Total:</span>
                                                <span class="fw-bold"><?php echo formatCurrency($total_amount); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="paymentHistoryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Payment ID</th>
                                                <th>Date & Time</th>
                                                <th>Customer</th>
                                                <th>Order No.</th>
                                                <th>Payment Method</th>
                                                <th>Reference No.</th>
                                                <th>Amount</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($payments)): ?>
                                                <?php $counter = 1; ?>
                                                <?php foreach ($payments as $payment): ?>
                                                    <?php
                                                    // Payment method badge colors
                                                    $method_classes = [
                                                        'cash' => 'badge-soft-success',
                                                        'bank_transfer' => 'badge-soft-primary',
                                                        'upi' => 'badge-soft-info',
                                                        'cheque' => 'badge-soft-warning',
                                                        'card' => 'badge-soft-secondary'
                                                    ];
                                                    $method_class = $method_classes[$payment['payment_method']] ?? 'badge-soft-dark';
                                                    
                                                    // Payment method display names
                                                    $method_names = [
                                                        'cash' => 'Cash',
                                                        'bank_transfer' => 'Bank Transfer',
                                                        'upi' => 'UPI',
                                                        'cheque' => 'Cheque',
                                                        'card' => 'Card'
                                                    ];
                                                    $method_name = $method_names[$payment['payment_method']] ?? ucfirst($payment['payment_method']);
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo htmlspecialchars($payment['payment_id']); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php echo formatDateTime($payment['payment_date']); ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($payment['customer_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <?php echo htmlspecialchars($payment['customer_name']); ?>
                                                                    </h5>
                                                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($payment['shop_name']); ?></p>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($payment['customer_contact']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['order_number']): ?>
                                                                <a href="order-view.php?id=<?php echo $payment['order_id']; ?>" class="text-primary">
                                                                    <?php echo htmlspecialchars($payment['order_number']); ?>
                                                                </a>
                                                                <br>
                                                                <small class="text-muted"><?php echo formatCurrency($payment['order_total']); ?></small>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $method_class; ?>">
                                                                <?php echo $method_name; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['reference_no']): ?>
                                                                <span class="text-muted"><?php echo htmlspecialchars($payment['reference_no']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold text-success">
                                                                <?php echo formatCurrency($payment['amount']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['notes']): ?>
                                                                <small class="text-muted" title="<?php echo htmlspecialchars($payment['notes']); ?>">
                                                                    <?php echo strlen($payment['notes']) > 30 ? substr($payment['notes'], 0, 30) . '...' : $payment['notes']; ?>
                                                                </small>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-cash-multiple display-4"></i>
                                                            <h5 class="mt-2">No Payment History Found</h5>
                                                            <p>No payments recorded for the selected filters</p>
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
                                            Showing <?php echo $total_count; ?> payments totaling <?php echo formatCurrency($total_amount); ?>
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

                <!-- Daily Summary -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Daily Payment Summary</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Number of Payments</th>
                                                <th>Total Amount</th>
                                                <th>Cash</th>
                                                <th>Bank Transfer</th>
                                                <th>UPI</th>
                                                <th>Cheque</th>
                                                <th>Card</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get daily summary
                                            if ($conn) {
                                                $daily_sql = "SELECT 
                                                                DATE(created_at) as payment_date,
                                                                COUNT(*) as payment_count,
                                                                SUM(amount) as daily_total,
                                                                SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_total,
                                                                SUM(CASE WHEN payment_method = 'bank_transfer' THEN amount ELSE 0 END) as bank_total,
                                                                SUM(CASE WHEN payment_method = 'upi' THEN amount ELSE 0 END) as upi_total,
                                                                SUM(CASE WHEN payment_method = 'cheque' THEN amount ELSE 0 END) as cheque_total,
                                                                SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END) as card_total
                                                              FROM transactions 
                                                              WHERE type = 'payment' 
                                                              AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
                                                if ($customer_id > 0) {
                                                    $daily_sql .= " AND customer_id = $customer_id";
                                                }
                                                $daily_sql .= " GROUP BY DATE(created_at) ORDER BY payment_date DESC LIMIT 10";
                                                
                                                $daily_result = mysqli_query($conn, $daily_sql);
                                                
                                                if ($daily_result && mysqli_num_rows($daily_result) > 0) {
                                                    while ($day = mysqli_fetch_assoc($daily_result)) {
                                                        ?>
                                                        <tr>
                                                            <td><?php echo formatDate($day['payment_date']); ?></td>
                                                            <td><?php echo $day['payment_count']; ?></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($day['daily_total']); ?></td>
                                                            <td><?php echo formatCurrency($day['cash_total']); ?></td>
                                                            <td><?php echo formatCurrency($day['bank_total']); ?></td>
                                                            <td><?php echo formatCurrency($day['upi_total']); ?></td>
                                                            <td><?php echo formatCurrency($day['cheque_total']); ?></td>
                                                            <td><?php echo formatCurrency($day['card_total']); ?></td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center">No daily summary available</td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
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

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit date filters when dates change
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    [startDateInput, endDateInput].forEach(input => {
        input.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Initialize DataTable if available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#paymentHistoryTable').DataTable({
            "pageLength": 25,
            "order": [[2, 'desc']],
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
    let exportUrl = 'export-payments.php?';
    
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
    const printContent = document.querySelector('.card').outerHTML;
    const originalContent = document.body.innerHTML;
    const printTitle = 'Payment History Report - ' + new Date().toLocaleDateString();
    
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
                <h2>Payment History Report</h2>
                <p>
                    Period: ${document.querySelector('input[name="start_date"]').value} to ${document.querySelector('input[name="end_date"]').value} | 
                    Generated on: ${new Date().toLocaleDateString()}
                </p>
            </div>
            
            <div class="summary-box">
                <h4>Summary</h4>
                <p>Total Payments: ${<?php echo $total_count; ?>} | Total Amount: ${<?php echo $total_amount; ?>}</p>
                <p>Date Range: ${document.querySelector('input[name="start_date"]').value} to ${document.querySelector('input[name="end_date"]').value}</p>
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

</body>

</html>
<?php
// Close database connection
if (isset($conn) && $conn) {
    mysqli_close($conn);
}
?>