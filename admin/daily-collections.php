<?php
session_start();
include('config/config.php');


// Function to format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d M, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('h:i A', strtotime($datetime));
}

// Default to today's date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_lineman = isset($_GET['lineman']) ? intval($_GET['lineman']) : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

// Fetch linemen for dropdown
$linemen_sql = "SELECT id, full_name FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_result = mysqli_query($conn, $linemen_sql);

// Calculate today's summary
$summary_sql = "SELECT 
                    COUNT(DISTINCT t.customer_id) as total_customers,
                    COUNT(t.id) as total_transactions,
                    SUM(t.amount) as total_collections,
                    COUNT(DISTINCT CASE WHEN t.payment_method = 'cash' THEN t.id END) as cash_count,
                    SUM(CASE WHEN t.payment_method = 'cash' THEN t.amount ELSE 0 END) as cash_total,
                    COUNT(DISTINCT CASE WHEN t.payment_method = 'bank_transfer' THEN t.id END) as bank_count,
                    SUM(CASE WHEN t.payment_method = 'bank_transfer' THEN t.amount ELSE 0 END) as bank_total,
                    COUNT(DISTINCT CASE WHEN t.payment_method = 'upi' THEN t.id END) as upi_count,
                    SUM(CASE WHEN t.payment_method = 'upi' THEN t.amount ELSE 0 END) as upi_total,
                    COUNT(DISTINCT CASE WHEN t.payment_method = 'cheque' THEN t.id END) as cheque_count,
                    SUM(CASE WHEN t.payment_method = 'cheque' THEN t.amount ELSE 0 END) as cheque_total,
                    COUNT(DISTINCT CASE WHEN t.payment_method = 'card' THEN t.id END) as card_count,
                    SUM(CASE WHEN t.payment_method = 'card' THEN t.amount ELSE 0 END) as card_total
                FROM transactions t
                WHERE t.type = 'payment'
                AND DATE(t.created_at) = '$selected_date'";

// Add lineman filter if selected
if ($selected_lineman > 0) {
    $summary_sql .= " AND t.customer_id IN (
                        SELECT id FROM customers 
                        WHERE assigned_lineman_id = $selected_lineman
                    )";
}

// Add payment method filter
if ($payment_method !== 'all') {
    $summary_sql .= " AND t.payment_method = '$payment_method'";
}

$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Fetch daily collections data
$collections_sql = "SELECT 
                        t.id,
                        t.amount,
                        t.payment_method,
                        t.reference_no,
                        t.notes,
                        t.created_at,
                        c.id as customer_id,
                        c.customer_name,
                        c.shop_name,
                        c.customer_contact,
                        c.assigned_lineman_id,
                        l.full_name as lineman_name,
                        o.order_number,
                        o.id as order_id
                    FROM transactions t
                    JOIN customers c ON t.customer_id = c.id
                    LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
                    LEFT JOIN orders o ON t.order_id = o.id
                    WHERE t.type = 'payment'
                    AND DATE(t.created_at) = '$selected_date'";

// Add lineman filter if selected
if ($selected_lineman > 0) {
    $collections_sql .= " AND c.assigned_lineman_id = $selected_lineman";
}

// Add payment method filter
if ($payment_method !== 'all') {
    $collections_sql .= " AND t.payment_method = '$payment_method'";
}

$collections_sql .= " ORDER BY t.created_at DESC";

$collections_result = mysqli_query($conn, $collections_sql);

// Calculate yesterday's collections for comparison
$yesterday_date = date('Y-m-d', strtotime('-1 day', strtotime($selected_date)));
$yesterday_sql = "SELECT SUM(amount) as total 
                  FROM transactions 
                  WHERE type = 'payment' 
                  AND DATE(created_at) = '$yesterday_date'";

if ($selected_lineman > 0) {
    $yesterday_sql .= " AND customer_id IN (
                        SELECT id FROM customers 
                        WHERE assigned_lineman_id = $selected_lineman
                    )";
}

$yesterday_result = mysqli_query($conn, $yesterday_sql);
$yesterday = mysqli_fetch_assoc($yesterday_result);
$yesterday_total = $yesterday['total'] ?? 0;

// Calculate this week's collections
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d');
$week_sql = "SELECT SUM(amount) as total 
             FROM transactions 
             WHERE type = 'payment' 
             AND DATE(created_at) BETWEEN '$week_start' AND '$week_end'";

if ($selected_lineman > 0) {
    $week_sql .= " AND customer_id IN (
                    SELECT id FROM customers 
                    WHERE assigned_lineman_id = $selected_lineman
                )";
}

$week_result = mysqli_query($conn, $week_sql);
$week = mysqli_fetch_assoc($week_result);
$week_total = $week['total'] ?? 0;

// Calculate this month's collections
$month_start = date('Y-m-01');
$month_end = date('Y-m-d');
$month_sql = "SELECT SUM(amount) as total 
              FROM transactions 
              WHERE type = 'payment' 
              AND DATE(created_at) BETWEEN '$month_start' AND '$month_end'";

if ($selected_lineman > 0) {
    $month_sql .= " AND customer_id IN (
                    SELECT id FROM customers 
                    WHERE assigned_lineman_id = $selected_lineman
                )";
}

$month_result = mysqli_query($conn, $month_sql);
$month = mysqli_fetch_assoc($month_result);
$month_total = $month['total'] ?? 0;

// Fetch top collectors (linemen) for the day
$top_collectors_sql = "SELECT 
                            l.full_name,
                            COUNT(DISTINCT t.id) as transaction_count,
                            SUM(t.amount) as total_collected,
                            COUNT(DISTINCT t.customer_id) as customers_count
                        FROM transactions t
                        JOIN customers c ON t.customer_id = c.id
                        JOIN linemen l ON c.assigned_lineman_id = l.id
                        WHERE t.type = 'payment'
                        AND DATE(t.created_at) = '$selected_date'
                        GROUP BY l.id
                        ORDER BY total_collected DESC
                        LIMIT 5";

$top_collectors_result = mysqli_query($conn, $top_collectors_sql);

// Fetch payment method distribution
$payment_distribution_sql = "SELECT 
                                payment_method,
                                COUNT(*) as count,
                                SUM(amount) as total
                             FROM transactions
                             WHERE type = 'payment'
                             AND DATE(created_at) = '$selected_date'";

if ($selected_lineman > 0) {
    $payment_distribution_sql .= " AND customer_id IN (
                                    SELECT id FROM customers 
                                    WHERE assigned_lineman_id = $selected_lineman
                                )";
}

$payment_distribution_sql .= " GROUP BY payment_method ORDER BY total DESC";

$payment_distribution_result = mysqli_query($conn, $payment_distribution_sql);

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="daily-collections-' . $selected_date . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th colspan="8">Daily Collections Report - ' . formatDate($selected_date) . '</th></tr>';
    echo '<tr><th>Time</th><th>Customer</th><th>Contact</th><th>Lineman</th><th>Order #</th><th>Amount</th><th>Payment Method</th><th>Reference</th></tr>';
    
    mysqli_data_seek($collections_result, 0);
    while ($row = mysqli_fetch_assoc($collections_result)) {
        echo '<tr>';
        echo '<td>' . formatDateTime($row['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($row['customer_name']) . ' - ' . htmlspecialchars($row['shop_name']) . '</td>';
        echo '<td>' . $row['customer_contact'] . '</td>';
        echo '<td>' . ($row['lineman_name'] ? $row['lineman_name'] : 'N/A') . '</td>';
        echo '<td>' . ($row['order_number'] ? $row['order_number'] : 'N/A') . '</td>';
        echo '<td>' . formatCurrency($row['amount']) . '</td>';
        echo '<td>' . ucfirst($row['payment_method']) . '</td>';
        echo '<td>' . $row['reference_no'] . '</td>';
        echo '</tr>';
    }
    
    echo '<tr style="background-color: #f2f2f2;">';
    echo '<td colspan="5" align="right"><strong>Total Collections:</strong></td>';
    echo '<td><strong>' . formatCurrency($summary['total_collections'] ?? 0) . '</strong></td>';
    echo '<td colspan="2"></td>';
    echo '</tr>';
    
    echo '</table>';
    exit();
}

// Quick date navigation
$prev_date = date('Y-m-d', strtotime('-1 day', strtotime($selected_date)));
$next_date = date('Y-m-d', strtotime('+1 day', strtotime($selected_date)));
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php')?>
<body data-sidebar="dark">
<?php include('includes/pre-loader.php')?>
<div id="layout-wrapper">
    <?php include('includes/topbar.php')?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php')?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">



                <!-- Date Navigation -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">Collections for <?php echo formatDate($selected_date); ?></h5>
                                        <p class="card-title-desc mb-0">Track daily payment collections</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="?date=<?php echo $prev_date; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="mdi mdi-chevron-left"></i> Previous
                                        </a>
                                        <input type="date" class="form-control form-control-sm" id="datePicker" 
                                               value="<?php echo $selected_date; ?>" style="width: 140px;">
                                        <a href="?date=<?php echo $next_date; ?>" class="btn btn-sm btn-outline-primary <?php echo $next_date > date('Y-m-d') ? 'disabled' : ''; ?>">
                                            Next <i class="mdi mdi-chevron-right"></i>
                                        </a>
                                        <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-primary">
                                            Today
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Today's Collections</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_collections'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">
                                            <?php echo $summary['total_transactions'] ?? 0; ?> transactions • 
                                            <?php echo $summary['total_customers'] ?? 0; ?> customers
                                        </p>
                                        <?php if ($yesterday_total > 0): ?>
                                            <?php 
                                            $change = $summary['total_collections'] - $yesterday_total;
                                            $percent = $yesterday_total > 0 ? ($change / $yesterday_total) * 100 : 0;
                                            ?>
                                            <small class="<?php echo $change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <i class="mdi mdi-arrow-<?php echo $change >= 0 ? 'up' : 'down'; ?>"></i>
                                                <?php echo $change >= 0 ? '+' : ''; ?><?php echo formatCurrency($change); ?> 
                                                (<?php echo number_format($percent, 1); ?>%) from yesterday
                                            </small>
                                        <?php endif; ?>
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
                                            <i class="mdi mdi-cash"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Cash Collections</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['cash_total'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $summary['cash_count'] ?? 0; ?> cash transactions</p>
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
                                            <i class="mdi mdi-bank"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Digital Payments</p>
                                        <h4 class="mb-0">
                                            <?php 
                                            $digital_total = ($summary['bank_total'] ?? 0) + ($summary['upi_total'] ?? 0) + ($summary['card_total'] ?? 0);
                                            echo formatCurrency($digital_total);
                                            ?>
                                        </h4>
                                        <p class="text-muted mb-0">
                                            <?php 
                                            $digital_count = ($summary['bank_count'] ?? 0) + ($summary['upi_count'] ?? 0) + ($summary['card_count'] ?? 0);
                                            echo $digital_count; ?> digital transactions
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
                                        <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2">
                                            <i class="mdi mdi-calendar-week"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Week's Total</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($week_total); ?></h4>
                                        <p class="text-muted mb-0">
                                            <?php echo date('d M', strtotime($week_start)); ?> - <?php echo date('d M', strtotime($week_end)); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Filter Collections</h5>
                                <form method="GET" action="" class="row g-3">
                                    <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Assigned Lineman</label>
                                        <select class="form-select" name="lineman">
                                            <option value="0">All Linemen</option>
                                            <?php while ($lineman = mysqli_fetch_assoc($linemen_result)): ?>
                                                <option value="<?php echo $lineman['id']; ?>" 
                                                    <?php echo $selected_lineman == $lineman['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($lineman['full_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                            <?php mysqli_data_seek($linemen_result, 0); ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
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
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="d-flex gap-2 w-100">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-filter me-1"></i> Apply Filters
                                            </button>
                                            <a href="?date=<?php echo $selected_date; ?>" class="btn btn-secondary">
                                                <i class="mdi mdi-refresh me-1"></i> Reset
                                            </a>
                                            <a href="?date=<?php echo $selected_date; ?>&lineman=<?php echo $selected_lineman; ?>&payment_method=<?php echo $payment_method; ?>&export=excel" 
                                               class="btn btn-success">
                                                <i class="mdi mdi-file-excel me-1"></i> Export
                                            </a>
                                            <button onclick="printReport()" class="btn btn-info">
                                                <i class="mdi mdi-printer me-1"></i> Print
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="d-flex gap-2">
                                            <a href="?date=<?php echo $selected_date; ?>&lineman=<?php echo $selected_lineman; ?>" 
                                               class="btn btn-sm btn-outline-primary <?php echo $payment_method == 'cash' ? 'active' : ''; ?>">
                                                Cash Only
                                            </a>
                                            <a href="?date=<?php echo $selected_date; ?>&lineman=<?php echo $selected_lineman; ?>&payment_method=digital" 
                                               class="btn btn-sm btn-outline-success">
                                                Digital Only
                                            </a>
                                            <a href="?date=<?php echo $selected_date; ?>&payment_method=<?php echo $payment_method; ?>" 
                                               class="btn btn-sm btn-outline-info">
                                                All Linemen
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="row">
                    <!-- Collections Table -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Payment Collections</h5>
                                    <span class="badge bg-primary">
                                        Total: <?php echo formatCurrency($summary['total_collections'] ?? 0); ?>
                                    </span>
                                </div>
                                
                                <?php if (mysqli_num_rows($collections_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Customer</th>
                                                    <th>Lineman</th>
                                                    <th>Order #</th>
                                                    <th class="text-end">Amount</th>
                                                    <th>Method</th>
                                                    <th>Reference</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($collection = mysqli_fetch_assoc($collections_result)): ?>
                                                    <?php
                                                    // Determine method badge class
                                                    $method_class = '';
                                                    switch ($collection['payment_method']) {
                                                        case 'cash':
                                                            $method_class = 'bg-success-subtle text-success';
                                                            break;
                                                        case 'bank_transfer':
                                                            $method_class = 'bg-primary-subtle text-primary';
                                                            break;
                                                        case 'upi':
                                                            $method_class = 'bg-info-subtle text-info';
                                                            break;
                                                        case 'cheque':
                                                            $method_class = 'bg-warning-subtle text-warning';
                                                            break;
                                                        case 'card':
                                                            $method_class = 'bg-danger-subtle text-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <i class="mdi mdi-clock-outline text-muted me-1"></i>
                                                                <?php echo formatDateTime($collection['created_at']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-xs me-3">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($collection['customer_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <a href="customer-view.php?id=<?php echo $collection['customer_id']; ?>" class="text-dark fw-medium">
                                                                        <?php echo htmlspecialchars($collection['customer_name']); ?>
                                                                    </a><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($collection['shop_name']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($collection['lineman_name']): ?>
                                                                <span class="badge bg-warning-subtle text-warning">
                                                                    <i class="mdi mdi-account-hard-hat me-1"></i><?php echo $collection['lineman_name']; ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Not assigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($collection['order_number']): ?>
                                                                <a href="order-view.php?id=<?php echo $collection['order_id']; ?>" class="text-primary">
                                                                    <?php echo $collection['order_number']; ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="fw-bold text-success"><?php echo formatCurrency($collection['amount']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $method_class; ?>">
                                                                <i class="mdi mdi-<?php 
                                                                    switch($collection['payment_method']) {
                                                                        case 'cash': echo 'cash'; break;
                                                                        case 'bank_transfer': echo 'bank'; break;
                                                                        case 'upi': echo 'cellphone'; break;
                                                                        case 'cheque': echo 'note-text'; break;
                                                                        case 'card': echo 'credit-card'; break;
                                                                        default: echo 'cash';
                                                                    }
                                                                ?> me-1"></i>
                                                                <?php echo ucfirst($collection['payment_method']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo $collection['reference_no']; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="receipt.php?transaction_id=<?php echo $collection['id']; ?>" target="_blank">
                                                                            <i class="mdi mdi-receipt me-1"></i> View Receipt
                                                                        </a>
                                                                    </li>
                                                                    <?php if ($collection['order_id']): ?>
                                                                        <li>
                                                                            <a class="dropdown-item" href="order-view.php?id=<?php echo $collection['order_id']; ?>">
                                                                                <i class="mdi mdi-eye me-1"></i> View Order
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="customer-view.php?id=<?php echo $collection['customer_id']; ?>">
                                                                            <i class="mdi mdi-account-outline me-1"></i> View Customer
                                                                        </a>
                                                                    </li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger" href="#" onclick="voidCollection(<?php echo $collection['id']; ?>)">
                                                                            <i class="mdi mdi-cancel me-1"></i> Void Collection
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Summary Row -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="alert alert-success">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <strong>Total Collections Summary:</strong>
                                                    </div>
                                                    <div class="col-md-6 text-end">
                                                        <strong><?php echo formatCurrency($summary['total_collections'] ?? 0); ?></strong>
                                                        <small class="text-muted ms-2">
                                                            (<?php echo $summary['total_transactions'] ?? 0; ?> transactions from 
                                                            <?php echo $summary['total_customers'] ?? 0; ?> customers)
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="mdi mdi-cash-remove display-4 text-muted"></i>
                                        <h5 class="mt-3">No Collections Today</h5>
                                        <p class="text-muted">No payment collections found for <?php echo formatDate($selected_date); ?></p>
                                        <?php if ($selected_lineman > 0 || $payment_method != 'all'): ?>
                                            <a href="?date=<?php echo $selected_date; ?>" class="btn btn-primary mt-2">
                                                <i class="mdi mdi-refresh me-1"></i> View All Collections
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics & Insights -->
                    <div class="col-lg-4">
                        <!-- Payment Method Distribution -->
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Payment Method Distribution</h5>
                                <?php if (mysqli_num_rows($payment_distribution_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Method</th>
                                                    <th class="text-end">Count</th>
                                                    <th class="text-end">Amount</th>
                                                    <th class="text-end">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_amount = $summary['total_collections'] ?? 0;
                                                while ($method = mysqli_fetch_assoc($payment_distribution_result)): 
                                                    $percentage = $total_amount > 0 ? ($method['total'] / $total_amount) * 100 : 0;
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-light text-dark">
                                                                <?php echo ucfirst($method['payment_method']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end"><?php echo $method['count']; ?></td>
                                                        <td class="text-end fw-bold"><?php echo formatCurrency($method['total']); ?></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-info"><?php echo number_format($percentage, 1); ?>%</span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="mt-3">
                                        <div class="progress" style="height: 6px;">
                                            <?php 
                                            mysqli_data_seek($payment_distribution_result, 0);
                                            $colors = ['success', 'primary', 'info', 'warning', 'danger'];
                                            $i = 0;
                                            while ($method = mysqli_fetch_assoc($payment_distribution_result)): 
                                                $percentage = $total_amount > 0 ? ($method['total'] / $total_amount) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-<?php echo $colors[$i % count($colors)]; ?>" 
                                                     role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                                <?php $i++; ?>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-0">No payment method data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top Collectors -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Top Collectors (Linemen)</h5>
                                <?php if (mysqli_num_rows($top_collectors_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Lineman</th>
                                                    <th class="text-end">Amount</th>
                                                    <th class="text-end">Customers</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $rank = 1; while ($collector = mysqli_fetch_assoc($top_collectors_result)): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-primary"><?php echo $rank++; ?></span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($collector['full_name']); ?></td>
                                                        <td class="text-end fw-bold text-success"><?php echo formatCurrency($collector['total_collected']); ?></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-info"><?php echo $collector['customers_count']; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-0">No collector data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Quick Stats</h5>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center mb-2">
                                            <h6 class="mb-1">Month to Date</h6>
                                            <h5 class="mb-0 text-primary"><?php echo formatCurrency($month_total); ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center mb-2">
                                            <h6 class="mb-1">Week to Date</h6>
                                            <h5 class="mb-0 text-success"><?php echo formatCurrency($week_total); ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center">
                                            <h6 class="mb-1">Yesterday</h6>
                                            <h5 class="mb-0 text-info"><?php echo formatCurrency($yesterday_total); ?></h5>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2 text-center">
                                            <h6 class="mb-1">Avg/Day (Week)</h6>
                                            <h5 class="mb-0 text-warning">
                                                <?php 
                                                $days_in_week = date('N', strtotime($week_end)) - date('N', strtotime($week_start)) + 1;
                                                $avg_daily = $days_in_week > 0 ? $week_total / $days_in_week : 0;
                                                echo formatCurrency($avg_daily);
                                                ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Quick Actions</h5>
                                <div class="d-grid gap-2">
                                    <a href="pending-payments.php" class="btn btn-outline-warning">
                                        <i class="mdi mdi-cash-clock me-1"></i> View Pending Payments
                                    </a>
                                    <a href="add-order.php" class="btn btn-outline-primary">
                                        <i class="mdi mdi-plus-circle me-1"></i> Create New Order
                                    </a>
                                    <a href="customers.php" class="btn btn-outline-success">
                                        <i class="mdi mdi-account-group me-1"></i> View All Customers
                                    </a>
                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#addCollectionModal">
                                        <i class="mdi mdi-cash-plus me-1"></i> Add Manual Collection
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Comparison -->
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">7-Day Collection Trend</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Day</th>
                                                <th class="text-end">Total Collections</th>
                                                <th class="text-end">Cash</th>
                                                <th class="text-end">Digital</th>
                                                <th class="text-end">Transactions</th>
                                                <th class="text-end">Customers</th>
                                                <th>Trend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get last 7 days data
                                            for ($i = 6; $i >= 0; $i--):
                                                $date = date('Y-m-d', strtotime("-$i days", strtotime($selected_date)));
                                                $day_name = date('D', strtotime($date));
                                                
                                                $day_sql = "SELECT 
                                                            SUM(amount) as total,
                                                            SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_total,
                                                            SUM(CASE WHEN payment_method != 'cash' THEN amount ELSE 0 END) as digital_total,
                                                            COUNT(*) as transaction_count,
                                                            COUNT(DISTINCT customer_id) as customer_count
                                                          FROM transactions 
                                                          WHERE type = 'payment' 
                                                          AND DATE(created_at) = '$date'";
                                                
                                                if ($selected_lineman > 0) {
                                                    $day_sql .= " AND customer_id IN (
                                                                    SELECT id FROM customers 
                                                                    WHERE assigned_lineman_id = $selected_lineman
                                                                )";
                                                }
                                                
                                                $day_result = mysqli_query($conn, $day_sql);
                                                $day_data = mysqli_fetch_assoc($day_result);
                                                
                                                $is_today = $date == $selected_date;
                                                $row_class = $is_today ? 'table-primary' : '';
                                            ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td>
                                                        <a href="?date=<?php echo $date; ?>" class="<?php echo $is_today ? 'fw-bold' : ''; ?>">
                                                            <?php echo formatDate($date); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo $day_name; ?></span>
                                                    </td>
                                                    <td class="text-end fw-bold"><?php echo formatCurrency($day_data['total'] ?? 0); ?></td>
                                                    <td class="text-end text-success"><?php echo formatCurrency($day_data['cash_total'] ?? 0); ?></td>
                                                    <td class="text-end text-primary"><?php echo formatCurrency($day_data['digital_total'] ?? 0); ?></td>
                                                    <td class="text-end"><?php echo $day_data['transaction_count'] ?? 0; ?></td>
                                                    <td class="text-end"><?php echo $day_data['customer_count'] ?? 0; ?></td>
                                                    <td>
                                                        <?php if ($day_data['total'] > 0): ?>
                                                            <span class="text-success">
                                                                <i class="mdi mdi-trending-up"></i>
                                                            </span>
                                                        <?php elseif ($day_data['total'] == 0): ?>
                                                            <span class="text-muted">-</span>
                                                        <?php else: ?>
                                                            <span class="text-danger">
                                                                <i class="mdi mdi-trending-down"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php')?>
    </div>
</div>

<!-- Add Manual Collection Modal -->
<div class="modal fade" id="addCollectionModal" tabindex="-1" aria-labelledby="addCollectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="add-collection.php">
                <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCollectionModalLabel">Add Manual Collection</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select class="form-select" name="customer_id" required>
                            <option value="">Select Customer</option>
                            <?php
                            $customers_sql = "SELECT id, customer_name, shop_name FROM customers WHERE status = 'active' ORDER BY customer_name";
                            $customers_result = mysqli_query($conn, $customers_sql);
                            while ($cust = mysqli_fetch_assoc($customers_result)) {
                                echo '<option value="' . $cust['id'] . '">' . htmlspecialchars($cust['customer_name'] . ' - ' . $cust['shop_name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
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
                        <input type="text" class="form-control" name="reference_no" placeholder="Transaction ID, UPI Ref, Cheque No., etc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Collection Date</label>
                        <input type="datetime-local" class="form-control" name="collection_date" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Any additional information..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Collection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php')?>
<?php include('includes/scripts.php')?>

<script>
// Date picker functionality
document.getElementById('datePicker').addEventListener('change', function() {
    const selectedDate = this.value;
    const url = new URL(window.location.href);
    url.searchParams.set('date', selectedDate);
    window.location.href = url.toString();
});

// Function to void a collection
function voidCollection(transactionId) {
    if (confirm('Are you sure you want to void this collection? This action cannot be undone.')) {
        fetch('void-collection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'transaction_id=' + transactionId + '&date=<?php echo $selected_date; ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Collection voided successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Network error: ' + error);
        });
    }
}

// Print report function
function printReport() {
    const printContent = document.querySelector('.card').outerHTML;
    const originalContent = document.body.innerHTML;
    const printTitle = 'Daily Collections Report - ' + '<?php echo formatDate($selected_date); ?>';
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>${printTitle}</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; }
                .table { width: 100%; border-collapse: collapse; }
                .table th, .table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                .table th { background-color: #f2f2f2; font-weight: bold; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .text-success { color: #28a745; }
                .text-primary { color: #007bff; }
                .summary-box { 
                    background: #f8f9fa; 
                    border: 1px solid #dee2e6; 
                    padding: 10px; 
                    margin: 10px 0; 
                    border-radius: 4px;
                }
                .no-print { display: none; }
                @page { margin: 0.5cm; }
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
            
            <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 10px;">
                <h2 style="color: #007bff; margin: 0;">Daily Collections Report</h2>
                <p style="margin: 5px 0;">
                    <strong>Date:</strong> <?php echo formatDate($selected_date); ?> | 
                    <strong>Generated on:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                </p>
            </div>
            
            <div class="summary-box">
                <h4 style="margin: 0 0 10px 0; color: #495057;">Summary</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                    <div>
                        <strong>Total Collections:</strong><br>
                        <?php echo formatCurrency($summary['total_collections'] ?? 0); ?>
                    </div>
                    <div>
                        <strong>Total Transactions:</strong><br>
                        <?php echo $summary['total_transactions'] ?? 0; ?>
                    </div>
                    <div>
                        <strong>Total Customers:</strong><br>
                        <?php echo $summary['total_customers'] ?? 0; ?>
                    </div>
                    <div>
                        <strong>Cash Collections:</strong><br>
                        <?php echo formatCurrency($summary['cash_total'] ?? 0); ?>
                    </div>
                </div>
            </div>
            
            ${printContent}
            
            <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
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

// Auto-refresh every 5 minutes for real-time updates
setTimeout(() => {
    if (document.hasFocus()) {
        location.reload();
    }
}, 300000); // 5 minutes

// Real-time update indicator
document.addEventListener('DOMContentLoaded', function() {
    const updateTime = document.createElement('div');
    updateTime.className = 'text-muted text-end mb-2';
    updateTime.innerHTML = `<small><i class="mdi mdi-clock-outline"></i> Last updated: ${new Date().toLocaleTimeString()}</small>`;
    document.querySelector('.card-body .d-flex.justify-content-between').appendChild(updateTime);
});
</script>

<style>
/* Custom styles for daily collections */
.collection-row:hover {
    background-color: #f8f9fa;
}
.method-badge {
    font-size: 11px;
    padding: 3px 8px;
}
.date-navigation {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
}
.stat-card {
    border-left: 4px solid #0d6efd;
    padding-left: 15px;
}
.cash-card {
    border-left: 4px solid #198754;
}
.digital-card {
    border-left: 4px solid #0dcaf0;
}
.week-card {
    border-left: 4px solid #ffc107;
}
.trend-up {
    color: #198754;
    font-weight: bold;
}
.trend-down {
    color: #dc3545;
    font-weight: bold;
}
</style>

</body>
</html>
<?php mysqli_close($conn); ?>