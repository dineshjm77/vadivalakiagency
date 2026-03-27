<?php

include('config/config.php');
include('includes/auth-check.php');

// Ensure only authorized users can access this page
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin', 'lineman'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Set default date range (today)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$lineman_id = isset($_GET['lineman_id']) ? intval($_GET['lineman_id']) : 0;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
}

// Ensure end date is not before start date
if (strtotime($end_date) < strtotime($start_date)) {
    $end_date = $start_date;
}

// Calculate date difference
$date_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
if ($date_diff > 365) {
    // Limit to 1 year for performance
    $end_date = date('Y-m-d', strtotime($start_date . ' + 365 days'));
}

// Fetch daily sales report
$daily_sales_sql = "SELECT 
                    DATE(o.order_date) as order_day,
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT o.customer_id) as total_customers,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN oi.total ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN o.payment_status = 'pending' THEN oi.total ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN o.payment_status = 'partial' THEN oi.total ELSE 0 END) as partial_amount
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $daily_sales_sql .= " AND o.customer_id = $customer_id";
}

$daily_sales_sql .= " GROUP BY DATE(o.order_date) ORDER BY order_day DESC";

$daily_sales_result = mysqli_query($conn, $daily_sales_sql);

// Fetch top selling products
$top_products_sql = "SELECT 
                    p.product_name,
                    p.product_code,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    AVG(oi.price) as avg_price,
                    COUNT(DISTINCT o.id) as order_count
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $top_products_sql .= " AND o.customer_id = $customer_id";
}

$top_products_sql .= " GROUP BY p.id ORDER BY total_sales DESC LIMIT 10";
$top_products_result = mysqli_query($conn, $top_products_sql);

// Fetch top customers
$top_customers_sql = "SELECT 
                    c.customer_name,
                    c.shop_name,
                    c.customer_contact,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    AVG(oi.total) as avg_order_value,
                    MAX(o.order_date) as last_order_date
                    FROM customers c
                    JOIN orders o ON c.id = o.customer_id
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $top_customers_sql .= " AND c.id = $customer_id";
}

$top_customers_sql .= " GROUP BY c.id ORDER BY total_sales DESC LIMIT 10";
$top_customers_result = mysqli_query($conn, $top_customers_sql);

// Fetch payment summary
$payment_summary_sql = "SELECT 
                    o.payment_method,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(oi.total) as total_amount,
                    AVG(oi.total) as avg_amount
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $payment_summary_sql .= " AND o.customer_id = $customer_id";
}

$payment_summary_sql .= " GROUP BY o.payment_method ORDER BY total_amount DESC";
$payment_summary_result = mysqli_query($conn, $payment_summary_sql);

// Fetch linemen performance
$linemen_performance_sql = "SELECT 
                    l.full_name,
                    l.employee_id,
                    COUNT(DISTINCT o.id) as order_count,
                    COUNT(DISTINCT o.customer_id) as customer_count,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    AVG(oi.total) as avg_order_value
                    FROM linemen l
                    JOIN customers c ON l.id = c.assigned_lineman_id
                    JOIN orders o ON c.id = o.customer_id
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($lineman_id > 0) {
    $linemen_performance_sql .= " AND l.id = $lineman_id";
}

$linemen_performance_sql .= " GROUP BY l.id ORDER BY total_sales DESC";
$linemen_performance_result = mysqli_query($conn, $linemen_performance_sql);

// Fetch profit analysis
$profit_analysis_sql = "SELECT 
                    DATE(o.order_date) as order_day,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    SUM(oi.quantity * p.stock_price) as total_cost,
                    SUM(oi.total - (oi.quantity * p.stock_price)) as total_profit,
                    AVG((oi.total - (oi.quantity * p.stock_price)) / oi.total * 100) as avg_profit_percentage
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $profit_analysis_sql .= " AND o.customer_id = $customer_id";
}

$profit_analysis_sql .= " GROUP BY DATE(o.order_date) ORDER BY order_day DESC";
$profit_analysis_result = mysqli_query($conn, $profit_analysis_sql);

// Fetch overall summary
$overall_summary_sql = "SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    COUNT(DISTINCT o.customer_id) as total_customers,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN oi.total ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN o.payment_status = 'pending' THEN oi.total ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN o.payment_status = 'partial' THEN oi.total ELSE 0 END) as partial_amount,
                    SUM(oi.quantity * p.stock_price) as total_cost,
                    SUM(oi.total - (oi.quantity * p.stock_price)) as total_profit,
                    AVG((oi.total - (oi.quantity * p.stock_price)) / oi.total * 100) as avg_profit_percentage
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $overall_summary_sql .= " AND o.customer_id = $customer_id";
}

$overall_summary_result = mysqli_query($conn, $overall_summary_sql);
$overall_summary = mysqli_fetch_assoc($overall_summary_result);

// Fetch customers for dropdown
$customers_sql = "SELECT id, shop_name, customer_name FROM customers WHERE status = 'active' ORDER BY shop_name";
$customers_result = mysqli_query($conn, $customers_sql);

// Fetch linemen for dropdown
$linemen_sql = "SELECT id, full_name, employee_id FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_result = mysqli_query($conn, $linemen_sql);

// Fetch hourly sales data
$hourly_sales_sql = "SELECT 
                    HOUR(o.order_date) as hour_of_day,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $hourly_sales_sql .= " AND o.customer_id = $customer_id";
}

$hourly_sales_sql .= " GROUP BY HOUR(o.order_date) ORDER BY hour_of_day";
$hourly_sales_result = mysqli_query($conn, $hourly_sales_sql);

// Fetch category-wise sales
$category_sales_sql = "SELECT 
                    c.category_name,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.total) as total_sales,
                    SUM(oi.quantity * p.stock_price) as total_cost,
                    SUM(oi.total - (oi.quantity * p.stock_price)) as total_profit
                    FROM categories c
                    JOIN products p ON c.id = p.category_id
                    JOIN order_items oi ON p.id = oi.product_id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.order_date) BETWEEN '$start_date' AND '$end_date'";

if ($customer_id > 0) {
    $category_sales_sql .= " AND o.customer_id = $customer_id";
}

$category_sales_sql .= " GROUP BY c.id ORDER BY total_sales DESC";
$category_sales_result = mysqli_query($conn, $category_sales_sql);

// Calculate previous period for comparison
$prev_start_date = date('Y-m-d', strtotime($start_date . ' -' . ($date_diff + 1) . ' days'));
$prev_end_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

// Fetch previous period summary for comparison
$prev_summary_sql = "SELECT 
                    SUM(oi.total) as total_sales,
                    SUM(oi.quantity * p.stock_price) as total_cost,
                    SUM(oi.total - (oi.quantity * p.stock_price)) as total_profit
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE DATE(o.order_date) BETWEEN '$prev_start_date' AND '$prev_end_date'";

if ($customer_id > 0) {
    $prev_summary_sql .= " AND o.customer_id = $customer_id";
}

$prev_summary_result = mysqli_query($conn, $prev_summary_sql);
$prev_summary = mysqli_fetch_assoc($prev_summary_result);

// Calculate growth percentages
$sales_growth = 0;
$profit_growth = 0;
if ($prev_summary && $prev_summary['total_sales'] > 0) {
    $sales_growth = (($overall_summary['total_sales'] - $prev_summary['total_sales']) / $prev_summary['total_sales']) * 100;
    $profit_growth = (($overall_summary['total_profit'] - $prev_summary['total_profit']) / $prev_summary['total_profit']) * 100;
}

// Calculate conversion rate (orders per customer)
$conversion_rate = $overall_summary['total_customers'] > 0 ? 
                   ($overall_summary['total_orders'] / $overall_summary['total_customers']) : 0;

// Calculate average order value
$avg_order_value = $overall_summary['total_orders'] > 0 ? 
                   ($overall_summary['total_sales'] / $overall_summary['total_orders']) : 0;

// Calculate average profit per order
$avg_profit_per_order = $overall_summary['total_orders'] > 0 ? 
                        ($overall_summary['total_profit'] / $overall_summary['total_orders']) : 0;

// Ensure all statistics have default values
$overall_summary = array_merge([
    'total_orders' => 0,
    'total_customers' => 0,
    'total_quantity' => 0,
    'total_sales' => 0,
    'paid_amount' => 0,
    'pending_amount' => 0,
    'partial_amount' => 0,
    'total_cost' => 0,
    'total_profit' => 0,
    'avg_profit_percentage' => 0
], $overall_summary ?? []);

$prev_summary = array_merge([
    'total_sales' => 0,
    'total_cost' => 0,
    'total_profit' => 0
], $prev_summary ?? []);
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>
<style>
    /* Improved, contrast-first CSS to ensure numbers are visible */
    :root{
        --primary-600: #556ee6;
        --success-700: #198754;
        --success-600: #28a745;
        --danger-700: #b02a37;
        --warning-700: #856404;
        --muted-700: #495057;
    }

    /* Card baseline */
    .card {
        border: 1px solid #e9ecef;
        box-shadow: 0 0.75rem 1.5rem rgba(18, 38, 63, 0.03);
        background: #fff;
    }
    .card-body { padding: 1.25rem; color: var(--muted-700); }

    /* Stat numbers */
    .stat-number {
        font-size: 24px;
        font-weight: 700;
        margin: 10px 0;
        color: var(--muted-700);
        line-height: 1.2;
    }

    /* Summary cards */
    .summary-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 18px;
        text-align: center;
        transition: all 0.18s ease;
    }
    .summary-card h6 { font-size: 13px; margin: 0; font-weight: 600; color: #6c757d; }
    .summary-card h3 { font-size: 22px; margin: 8px 0 0; font-weight: 700; color: var(--muted-700); }

    /* Specific colors for positive/negative/neutral metrics inside summary cards for stronger contrast */
    .summary-card.bg-success,
    .summary-card.bg-success * { color-adjust: exact; -webkit-print-color-adjust: exact; }
    .summary-card.bg-success { background-color: rgba(25, 135, 84, 0.06); border-color: rgba(25, 135, 84, 0.12); }
    .summary-card.bg-success h3,
    .summary-card.bg-success h6 { color: var(--success-700) !important; }

    .summary-card.bg-warning { background-color: rgba(133, 100, 4, 0.06); border-color: rgba(133, 100, 4, 0.12); }
    .summary-card.bg-warning h3,
    .summary-card.bg-warning h6 { color: var(--warning-700) !important; }

    .summary-card.bg-danger { background-color: rgba(176, 42, 55, 0.06); border-color: rgba(176, 42, 55, 0.12); }
    .summary-card.bg-danger h3,
    .summary-card.bg-danger h6 { color: var(--danger-700) !important; }

    /* ensure badges, small text remain readable */
    .text-muted { color: #6c757d !important; opacity: 0.95; }
    small { color: #6c757d; }

    /* Progress bars and other UI */
    .progress { background-color: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden; }
    .progress-bar { background-color: var(--primary-600); }

    /* Table and text visibility */
    .table th, .table td { color: #343a40; vertical-align: middle; }
    .table thead th { background: #f8f9fa; }

    /* Buttons and inputs */
    .btn { font-weight: 500; }

    /* Small fixes for print */
    @media print {
        .no-print { display:none; }
        .summary-card { page-break-inside: avoid; }
    }
</style>

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
                $current_page = 'daily-report';
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

                    <!-- Header -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h4 class="card-title mb-0">Daily Sales Report</h4>
                            <p class="card-title-desc">Detailed sales analysis and profit reports</p>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                                <button type="button" class="btn btn-primary" onclick="printReport()">
                                    <i class="mdi mdi-printer me-1"></i> Print Report
                                </button>
                                <button type="button" class="btn btn-success" onclick="exportReport()">
                                    <i class="mdi mdi-download me-1"></i> Export Report
                                </button>
                                <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#emailReportModal">
                                    <i class="mdi mdi-email me-1"></i> Email Report
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Report Filters</h5>
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label for="start_date" class="form-label">Start Date</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                                   value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="end_date" class="form-label">End Date</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                                   value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="customer_id" class="form-label">Customer</label>
                                            <select class="form-select" id="customer_id" name="customer_id">
                                                <option value="0">All Customers</option>
                                                <?php while ($cust = mysqli_fetch_assoc($customers_result)): ?>
                                                <option value="<?php echo $cust['id']; ?>" 
                                                    <?php echo $customer_id == $cust['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cust['shop_name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="lineman_id" class="form-label">Lineman</label>
                                            <?php mysqli_data_seek($linemen_result, 0); ?>
                                            <select class="form-select" id="lineman_id" name="lineman_id">
                                                <option value="0">All Linemen</option>
                                                <?php while ($lineman = mysqli_fetch_assoc($linemen_result)): ?>
                                                <option value="<?php echo $lineman['id']; ?>" 
                                                    <?php echo $lineman_id == $lineman['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($lineman['full_name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label for="report_type" class="form-label">Report Type</label>
                                            <select class="form-select" id="report_type" name="report_type">
                                                <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                                                <option value="profit" <?php echo $report_type == 'profit' ? 'selected' : ''; ?>>Profit Report</option>
                                                <option value="detailed" <?php echo $report_type == 'detailed' ? 'selected' : ''; ?>>Detailed Report</option>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="mdi mdi-filter me-1"></i> Apply Filters
                                                </button>
                                                <a href="daily-report.php" class="btn btn-outline-secondary">
                                                    <i class="mdi mdi-refresh me-1"></i> Reset Filters
                                                </a>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('today')">Today</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('yesterday')">Yesterday</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('week')">This Week</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('month')">This Month</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('year')">This Year</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                    
                                    <?php if ($customer_id > 0 || $lineman_id > 0 || $start_date != date('Y-m-d') || $end_date != date('Y-m-d')): ?>
                                    <div class="mt-3">
                                        <div class="alert alert-info">
                                            <i class="mdi mdi-information-outline me-2"></i>
                                            <strong>Report Period:</strong> 
                                            <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?>
                                            <?php if ($customer_id > 0): ?>
                                            | <strong>Customer:</strong> <?php 
                                                mysqli_data_seek($customers_result, 0);
                                                while ($cust = mysqli_fetch_assoc($customers_result)) {
                                                    if ($cust['id'] == $customer_id) {
                                                        echo htmlspecialchars($cust['shop_name']);
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <?php endif; ?>
                                            <?php if ($lineman_id > 0): ?>
                                            | <strong>Lineman:</strong> <?php 
                                                mysqli_data_seek($linemen_result, 0);
                                                while ($lineman = mysqli_fetch_assoc($linemen_result)) {
                                                    if ($lineman['id'] == $lineman_id) {
                                                        echo htmlspecialchars($lineman['full_name']);
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Summary Statistics -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Total Sales</h5>
                                            <h3 class="my-2 py-1 stat-number">₹<?php echo number_format($overall_summary['total_sales'], 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="<?php echo $sales_growth >= 0 ? 'text-success' : 'text-danger'; ?> me-2">
                                                    <i class="mdi mdi-arrow-<?php echo $sales_growth >= 0 ? 'up' : 'down'; ?>"></i>
                                                    <?php echo number_format(abs($sales_growth), 1); ?>%
                                                </span>
                                                <span>vs previous period</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-cash"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Total Profit</h5>
                                            <h3 class="my-2 py-1 stat-number text-success">₹<?php echo number_format($overall_summary['total_profit'], 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="<?php echo $profit_growth >= 0 ? 'text-success' : 'text-danger'; ?> me-2">
                                                    <i class="mdi mdi-arrow-<?php echo $profit_growth >= 0 ? 'up' : 'down'; ?>"></i>
                                                    <?php echo number_format(abs($profit_growth), 1); ?>%
                                                </span>
                                                <span>vs previous period</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                    <i class="mdi mdi-chart-line"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0">Total Orders</h5>
                                            <h3 class="my-2 py-1 stat-number text-primary"><?php echo number_format($overall_summary['total_orders']); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-info me-2">
                                                    <i class="mdi mdi-cart"></i>
                                                </span>
                                                <span>Avg: ₹<?php echo number_format($avg_order_value, 2); ?> per order</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-shopping"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FIXED: Paid / Partial / Pending amounts now inside strongly-contrasted summary-cards -->
                        <div class="col-xl-3 col-md-6">
                            <div class="summary-card bg-success">
                                <h6 class="mb-2">Paid Amount</h6>
                                <h3 class="mb-0">₹<?php echo number_format($overall_summary['paid_amount'], 2); ?></h3>
                                <p class="text-muted mb-0 small">Received payments</p>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Additional Payment Status row (Partial / Pending) -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="summary-card bg-warning">
                                <h6 class="mb-2">Partial Amount</h6>
                                <h3 class="mb-0">₹<?php echo number_format($overall_summary['partial_amount'], 2); ?></h3>
                                <p class="text-muted mb-0 small">Partial payments</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card bg-danger">
                                <h6 class="mb-2">Pending Amount</h6>
                                <h3 class="mb-0">₹<?php echo number_format($overall_summary['pending_amount'], 2); ?></h3>
                                <p class="text-muted mb-0 small">Outstanding payments</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="summary-card">
                                <h6 class="mb-2">Profit Margin</h6>
                                <h3 class="mb-0"><?php echo number_format($overall_summary['avg_profit_percentage'], 1); ?>%</h3>
                                <p class="text-muted mb-0 small">Avg profit per order: ₹<?php echo number_format($avg_profit_per_order, 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Summary -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Detailed Summary</h5>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="summary-card">
                                                <h6 class="text-muted mb-2">Total Customers</h6>
                                                <h3 class="mb-0 text-dark"><?php echo number_format($overall_summary['total_customers']); ?></h3>
                                                <p class="text-muted mb-0">Active customers</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="summary-card">
                                                <h6 class="text-muted mb-2">Total Quantity</h6>
                                                <h3 class="mb-0 text-dark"><?php echo number_format($overall_summary['total_quantity']); ?></h3>
                                                <p class="text-muted mb-0">Units sold</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="summary-card">
                                                <h6 class="text-muted mb-2">Total Cost</h6>
                                                <h3 class="mb-0 text-dark">₹<?php echo number_format($overall_summary['total_cost'], 2); ?></h3>
                                                <p class="text-muted mb-0">Cost of goods</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="summary-card">
                                                <h6 class="text-muted mb-2">Conversion Rate</h6>
                                                <h3 class="mb-0 text-dark"><?php echo number_format($conversion_rate, 2); ?></h3>
                                                <p class="text-muted mb-0">Orders per customer</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Payment Status -->
                                    <div class="row mt-4">
                                        <div class="col-md-4">
                                            <div class="summary-card bg-success">
                                                <h6 class="text-success mb-2">Paid Amount</h6>
                                                <h3 class="mb-0">₹<?php echo number_format($overall_summary['paid_amount'], 2); ?></h3>
                                                <p class="text-muted mb-0">Received payments</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="summary-card bg-warning">
                                                <h6 class="text-warning mb-2">Partial Amount</h6>
                                                <h3 class="mb-0">₹<?php echo number_format($overall_summary['partial_amount'], 2); ?></h3>
                                                <p class="text-muted mb-0">Partial payments</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="summary-card bg-danger">
                                                <h6 class="text-danger mb-2">Pending Amount</h6>
                                                <h3 class="mb-0">₹<?php echo number_format($overall_summary['pending_amount'], 2); ?></h3>
                                                <p class="text-muted mb-0">Outstanding payments</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Daily Sales & Profit Chart -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Daily Sales & Profit Trend</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-center">Customers</th>
                                                    <th class="text-center">Quantity</th>
                                                    <th class="text-end">Sales Amount</th>
                                                    <th class="text-end">Cost</th>
                                                    <th class="text-end">Profit</th>
                                                    <th class="text-center">Margin %</th>
                                                    <th class="text-end">Paid</th>
                                                    <th class="text-end">Pending</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($daily_sales_result && mysqli_num_rows($daily_sales_result) > 0) {
                                                    mysqli_data_seek($daily_sales_result, 0);
                                                    while ($daily = mysqli_fetch_assoc($daily_sales_result)) {
                                                        // Get profit data for this day
                                                        $profit_sql = "SELECT 
                                                                    SUM(oi.quantity * p.stock_price) as total_cost,
                                                                    SUM(oi.total - (oi.quantity * p.stock_price)) as total_profit,
                                                                    AVG((oi.total - (oi.quantity * p.stock_price)) / oi.total * 100) as avg_profit_percentage
                                                                    FROM orders o
                                                                    JOIN order_items oi ON o.id = oi.order_id
                                                                    JOIN products p ON oi.product_id = p.id
                                                                    WHERE DATE(o.order_date) = '{$daily['order_day']}'";
                                                        
                                                        if ($customer_id > 0) {
                                                            $profit_sql .= " AND o.customer_id = $customer_id";
                                                        }
                                                        
                                                        $profit_result = mysqli_query($conn, $profit_sql);
                                                        $profit_data = mysqli_fetch_assoc($profit_result) ?? ['total_cost' => 0, 'total_profit' => 0, 'avg_profit_percentage' => 0];
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <strong class="text-dark"><?php echo date('d M, Y', strtotime($daily['order_day'])); ?></strong>
                                                            </td>
                                                            <td class="text-center text-dark"><?php echo $daily['total_orders']; ?></td>
                                                            <td class="text-center text-dark"><?php echo $daily['total_customers']; ?></td>
                                                            <td class="text-center text-dark"><?php echo number_format($daily['total_quantity']); ?></td>
                                                            <td class="text-end">
                                                                <strong class="text-dark">₹<?php echo number_format($daily['total_sales'], 2); ?></strong>
                                                            </td>
                                                            <td class="text-end text-muted">₹<?php echo number_format($profit_data['total_cost'], 2); ?></td>
                                                            <td class="text-end text-success">
                                                                <strong>₹<?php echo number_format($profit_data['total_profit'], 2); ?></strong>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge <?php echo $profit_data['avg_profit_percentage'] >= 30 ? 'bg-success-subtle text-success' : ($profit_data['avg_profit_percentage'] >= 20 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'); ?>">
                                                                    <?php echo number_format($profit_data['avg_profit_percentage'], 1); ?>%
                                                                </span>
                                                            </td>
                                                            <td class="text-end text-success">₹<?php echo number_format($daily['paid_amount'], 2); ?></td>
                                                            <td class="text-end text-danger">₹<?php echo number_format($daily['pending_amount'] + $daily['partial_amount'], 2); ?></td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-chart-line display-4"></i>
                                                                <h5 class="mt-2 text-dark">No Sales Data</h5>
                                                                <p class="text-dark">No sales found for the selected period</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr class="text-dark">
                                                    <th>Total</th>
                                                    <th class="text-center"><?php echo $overall_summary['total_orders']; ?></th>
                                                    <th class="text-center"><?php echo $overall_summary['total_customers']; ?></th>
                                                    <th class="text-center"><?php echo number_format($overall_summary['total_quantity']); ?></th>
                                                    <th class="text-end">₹<?php echo number_format($overall_summary['total_sales'], 2); ?></th>
                                                    <th class="text-end">₹<?php echo number_format($overall_summary['total_cost'], 2); ?></th>
                                                    <th class="text-end">₹<?php echo number_format($overall_summary['total_profit'], 2); ?></th>
                                                    <th class="text-center"><?php echo number_format($overall_summary['avg_profit_percentage'], 1); ?>%</th>
                                                    <th class="text-end">₹<?php echo number_format($overall_summary['paid_amount'], 2); ?></th>
                                                    <th class="text-end">₹<?php echo number_format(($overall_summary['pending_amount']) + ($overall_summary['partial_amount']), 2); ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Top Products & Customers -->
                    <div class="row mb-4">
                        <!-- Top Selling Products -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Top Selling Products</h5>
                                    
                                    <?php if ($top_products_result && mysqli_num_rows($top_products_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th class="text-center">Qty Sold</th>
                                                    <th class="text-end">Sales</th>
                                                    <th class="text-center">Avg Price</th>
                                                    <th class="text-center">Orders</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $rank = 1;
                                                mysqli_data_seek($top_products_result, 0);
                                                while ($product = mysqli_fetch_assoc($top_products_result)): 
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-1 text-dark"><?php echo $rank++; ?>. <?php echo htmlspecialchars($product['product_name']); ?></h6>
                                                            <p class="text-muted mb-0"><?php echo $product['product_code']; ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="text-center fw-bold text-dark"><?php echo number_format($product['total_quantity']); ?></td>
                                                    <td class="text-end">
                                                        <strong class="text-dark">₹<?php echo number_format($product['total_sales'], 2); ?></strong>
                                                    </td>
                                                    <td class="text-center text-dark">₹<?php echo number_format($product['avg_price'], 2); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <?php echo $product['order_count']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-package-variant display-4 text-muted"></i>
                                        <p class="mt-2 text-dark">No product sales data</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Customers -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Top Customers</h5>
                                    
                                    <?php if ($top_customers_result && mysqli_num_rows($top_customers_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Sales</th>
                                                    <th class="text-center">Last Order</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $rank = 1;
                                                mysqli_data_seek($top_customers_result, 0);
                                                while ($customer = mysqli_fetch_assoc($top_customers_result)): 
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-1 text-dark"><?php echo $rank++; ?>. <?php echo htmlspecialchars($customer['shop_name']); ?></h6>
                                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($customer['customer_name']); ?></p>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info-subtle text-info">
                                                            <?php echo $customer['order_count']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center text-dark"><?php echo number_format($customer['total_quantity']); ?></td>
                                                    <td class="text-end">
                                                        <strong class="text-dark">₹<?php echo number_format($customer['total_sales'], 2); ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <small class="text-dark"><?php echo date('d M', strtotime($customer['last_order_date'])); ?></small>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-account-group display-4 text-muted"></i>
                                        <p class="mt-2 text-dark">No customer sales data</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Category Sales & Payment Methods -->
                    <div class="row mb-4">
                        <!-- Category-wise Sales -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Category-wise Sales</h5>
                                    
                                    <?php if ($category_sales_result && mysqli_num_rows($category_sales_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Category</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Sales</th>
                                                    <th class="text-end">Profit</th>
                                                    <th class="text-center">Margin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                mysqli_data_seek($category_sales_result, 0);
                                                while ($category = mysqli_fetch_assoc($category_sales_result)): 
                                                    $margin = $category['total_sales'] > 0 ? ($category['total_profit'] / $category['total_sales']) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong class="text-dark"><?php echo htmlspecialchars($category['category_name']); ?></strong>
                                                    </td>
                                                    <td class="text-center text-dark"><?php echo $category['order_count']; ?></td>
                                                    <td class="text-center text-dark"><?php echo number_format($category['total_quantity']); ?></td>
                                                    <td class="text-end text-dark">₹<?php echo number_format($category['total_sales'], 2); ?></td>
                                                    <td class="text-end text-success">₹<?php echo number_format($category['total_profit'], 2); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?php echo $margin >= 30 ? 'bg-success-subtle text-success' : ($margin >= 20 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'); ?>">
                                                            <?php echo number_format($margin, 1); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-tag-multiple display-4 text-muted"></i>
                                        <p class="mt-2 text-dark">No category sales data</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Payment Methods Summary</h5>
                                    
                                    <?php if ($payment_summary_result && mysqli_num_rows($payment_summary_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Payment Method</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-end">Total Amount</th>
                                                    <th class="text-end">Average</th>
                                                    <th class="text-center">Share</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_payment_amount = 0;
                                                mysqli_data_seek($payment_summary_result, 0);
                                                while ($payment = mysqli_fetch_assoc($payment_summary_result)) {
                                                    $total_payment_amount += $payment['total_amount'];
                                                }
                                                mysqli_data_seek($payment_summary_result, 0);
                                                
                                                while ($payment = mysqli_fetch_assoc($payment_summary_result)): 
                                                    $share = $total_payment_amount > 0 ? ($payment['total_amount'] / $total_payment_amount) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong class="text-dark"><?php echo ucfirst($payment['payment_method']); ?></strong>
                                                    </td>
                                                    <td class="text-center text-dark"><?php echo $payment['order_count']; ?></td>
                                                    <td class="text-end">
                                                        <strong class="text-dark">₹<?php echo number_format($payment['total_amount'], 2); ?></strong>
                                                    </td>
                                                    <td class="text-end text-dark">₹<?php echo number_format($payment['avg_amount'], 2); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info-subtle text-info">
                                                            <?php echo number_format($share, 1); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="text-dark">
                                                    <th>Total</th>
                                                    <th class="text-center"><?php echo $overall_summary['total_orders']; ?></th>
                                                    <th class="text-end">₹<?php echo number_format($total_payment_amount, 2); ?></th>
                                                    <th class="text-end">₹<?php echo number_format($total_payment_amount / max(1, $overall_summary['total_orders']), 2); ?></th>
                                                    <th class="text-center">100%</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-credit-card-multiple display-4 text-muted"></i>
                                        <p class="mt-2 text-dark">No payment data</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Linemen Performance -->
                    <?php if ($linemen_performance_result && mysqli_num_rows($linemen_performance_result) > 0): ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Linemen Performance</h5>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Lineman</th>
                                                    <th class="text-center">Employee ID</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-center">Customers</th>
                                                    <th class="text-center">Quantity</th>
                                                    <th class="text-end">Sales</th>
                                                    <th class="text-end">Avg Order</th>
                                                    <th class="text-center">Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $rank = 1;
                                                mysqli_data_seek($linemen_performance_result, 0);
                                                while ($lineman = mysqli_fetch_assoc($linemen_performance_result)): 
                                                    $performance_score = $lineman['total_sales'] > 0 ? ($lineman['total_sales'] / max(1, $overall_summary['total_sales'])) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <h6 class="mb-1 text-dark"><?php echo $rank++; ?>. <?php echo htmlspecialchars($lineman['full_name']); ?></h6>
                                                        </div>
                                                    </td>
                                                    <td class="text-center text-dark"><?php echo $lineman['employee_id']; ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <?php echo $lineman['order_count']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center text-dark"><?php echo $lineman['customer_count']; ?></td>
                                                    <td class="text-center text-dark"><?php echo number_format($lineman['total_quantity']); ?></td>
                                                    <td class="text-end">
                                                        <strong class="text-dark">₹<?php echo number_format($lineman['total_sales'], 2); ?></strong>
                                                    </td>
                                                    <td class="text-end text-dark">₹<?php echo number_format($lineman['avg_order_value'], 2); ?></td>
                                                    <td class="text-center">
                                                        <div class="progress" style="height: 8px;">
                                                            <div class="progress-bar bg-success" style="width: <?php echo min(100, $performance_score); ?>%"></div>
                                                        </div>
                                                        <small class="text-dark"><?php echo number_format($performance_score, 1); ?>%</small>
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
                    <!-- end row -->
                    <?php endif; ?>

                    <!-- Hourly Sales Analysis -->
                    <?php if ($hourly_sales_result && mysqli_num_rows($hourly_sales_result) > 0): ?>
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Hourly Sales Analysis</h5>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Hour</th>
                                                    <th class="text-center">Orders</th>
                                                    <th class="text-center">Quantity</th>
                                                    <th class="text-end">Sales</th>
                                                    <th class="text-center">Avg Sale</th>
                                                    <th class="text-center">Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $hourly_data = [];
                                                mysqli_data_seek($hourly_sales_result, 0);
                                                while ($hour = mysqli_fetch_assoc($hourly_sales_result)) {
                                                    $hourly_data[$hour['hour_of_day']] = $hour;
                                                }
                                                
                                                for ($hour = 0; $hour < 24; $hour++): 
                                                    $hour_data = $hourly_data[$hour] ?? ['order_count' => 0, 'total_quantity' => 0, 'total_sales' => 0];
                                                    $avg_sale = $hour_data['order_count'] > 0 ? $hour_data['total_sales'] / $hour_data['order_count'] : 0;
                                                    $performance = $overall_summary['total_orders'] > 0 ? ($hour_data['order_count'] / $overall_summary['total_orders']) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong class="text-dark"><?php echo sprintf('%02d:00', $hour); ?> - <?php echo sprintf('%02d:00', $hour + 1); ?></strong>
                                                    </td>
                                                    <td class="text-center text-dark"><?php echo $hour_data['order_count']; ?></td>
                                                    <td class="text-center text-dark"><?php echo number_format($hour_data['total_quantity']); ?></td>
                                                    <td class="text-end text-dark">₹<?php echo number_format($hour_data['total_sales'], 2); ?></td>
                                                    <td class="text-center text-dark">₹<?php echo number_format($avg_sale, 2); ?></td>
                                                    <td class="text-center">
                                                        <div class="progress" style="height: 6px; width: 80px; margin: 0 auto;">
                                                            <div class="progress-bar bg-info" style="width: <?php echo min(100, $performance * 10); ?>%"></div>
                                                        </div>
                                                        <small class="text-dark"><?php echo number_format($performance, 1); ?>%</small>
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
                    <!-- end row -->
                    <?php endif; ?>

                    <!-- Profit Analysis Details -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Detailed Profit Analysis</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3">
                                                <h6 class="text-muted mb-3">Profit Breakdown</h6>
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-dark">Total Sales Revenue</span>
                                                        <span class="fw-bold text-dark">₹<?php echo number_format($overall_summary['total_sales'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-dark">Total Cost of Goods</span>
                                                        <span class="text-muted">₹<?php echo number_format($overall_summary['total_cost'], 2); ?></span>
                                                    </div>
                                                    <hr>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-dark">Gross Profit</span>
                                                        <span class="text-success fw-bold">₹<?php echo number_format($overall_summary['total_profit'], 2); ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-dark">Gross Margin</span>
                                                        <span class="text-warning fw-bold"><?php echo number_format($overall_summary['avg_profit_percentage'], 1); ?>%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3">
                                                <h6 class="text-muted mb-3">Key Performance Indicators</h6>
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center">
                                                            <h6 class="text-muted mb-1">Avg. Order Value</h6>
                                                            <h4 class="mb-0 text-dark">₹<?php echo number_format($avg_order_value, 2); ?></h4>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center">
                                                            <h6 class="text-muted mb-1">Avg. Profit per Order</h6>
                                                            <h4 class="mb-0 text-dark">₹<?php echo number_format($avg_profit_per_order, 2); ?></h4>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center">
                                                            <h6 class="text-muted mb-1">Conversion Rate</h6>
                                                            <h4 class="mb-0 text-dark"><?php echo number_format($conversion_rate, 2); ?></h4>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center">
                                                            <h6 class="text-muted mb-1">Sales Growth</h6>
                                                            <h4 class="mb-0 <?php echo $sales_growth >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                <?php echo number_format($sales_growth, 1); ?>%
                                                            </h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Profit Trend Chart -->
                                    <div class="mt-4">
                                        <h6 class="text-muted mb-3">Profit Trend (Last 7 Days)</h6>
                                        <div class="row">
                                            <?php
                                            // Get last 7 days profit data
                                            $last_7_days_sql = "SELECT 
                                                            DATE(o.order_date) as order_day,
                                                            SUM(oi.total - (oi.quantity * p.stock_price)) as daily_profit
                                                            FROM orders o
                                                            JOIN order_items oi ON o.id = oi.order_id
                                                            JOIN products p ON oi.product_id = p.id
                                                            WHERE DATE(o.order_date) BETWEEN DATE_SUB('$end_date', INTERVAL 6 DAY) AND '$end_date'";
                                            
                                            if ($customer_id > 0) {
                                                $last_7_days_sql .= " AND o.customer_id = $customer_id";
                                            }
                                            
                                            $last_7_days_sql .= " GROUP BY DATE(o.order_date) ORDER BY order_day";
                                            $last_7_days_result = mysqli_query($conn, $last_7_days_sql);
                                            
                                            $profit_chart_data = [];
                                            while ($row = mysqli_fetch_assoc($last_7_days_result)) {
                                                $profit_chart_data[$row['order_day']] = $row['daily_profit'];
                                            }
                                            
                                            // Fill missing days with 0
                                            for ($i = 6; $i >= 0; $i--) {
                                                $date = date('Y-m-d', strtotime("-$i days", strtotime($end_date)));
                                                if (!isset($profit_chart_data[$date])) {
                                                    $profit_chart_data[$date] = 0;
                                                }
                                            }
                                            
                                            ksort($profit_chart_data);
                                            ?>
                                            <div class="col-12">
                                                <div class="border rounded p-3">
                                                    <div class="row text-center">
                                                        <?php foreach ($profit_chart_data as $date => $profit): ?>
                                                        <div class="col">
                                                            <div class="mb-2">
                                                                <small class="text-muted d-block"><?php echo date('D', strtotime($date)); ?></small>
                                                                <small class="text-muted d-block"><?php echo date('d M', strtotime($date)); ?></small>
                                                            </div>
                                                            <div class="progress" style="height: 100px;">
                                                                <?php
                                                                $max_profit = max($profit_chart_data) > 0 ? max($profit_chart_data) : 1;
                                                                $height = ($profit / $max_profit) * 100;
                                                                $bar_class = $profit > 0 ? 'bg-success' : ($profit < 0 ? 'bg-danger' : 'bg-secondary');
                                                                ?>
                                                                <div class="progress-bar <?php echo $bar_class; ?>" 
                                                                     style="height: <?php echo $height; ?>%; margin-top: <?php echo 100 - $height; ?>%;"></div>
                                                            </div>
                                                            <div class="mt-2">
                                                                <small class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                                    ₹<?php echo number_format($profit, 0); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Email Report Modal -->
    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
    <div class="modal fade" id="emailReportModal" tabindex="-1" aria-labelledby="emailReportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="send-report-email.php" id="emailReportForm">
                    <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
                    <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <input type="hidden" name="lineman_id" value="<?php echo $lineman_id; ?>">
                    <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="emailReportModalLabel">Email Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="email_to" class="form-label">Email To *</label>
                            <input type="email" class="form-control" id="email_to" name="email_to" 
                                   required placeholder="recipient@example.com">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject" 
                                   value="Sales Report: <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_message" class="form-label">Message</label>
                            <textarea class="form-control" id="email_message" name="email_message" rows="4">
Please find attached the sales report for the period <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?>.

Key Highlights:
- Total Sales: ₹<?php echo number_format($overall_summary['total_sales'], 2); ?>
- Total Profit: ₹<?php echo number_format($overall_summary['total_profit'], 2); ?>
- Profit Margin: <?php echo number_format($overall_summary['avg_profit_percentage'], 1); ?>%

Best regards,
<?php echo $_SESSION['name']; ?>
                            </textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_attachments" name="include_attachments" checked>
                                <label class="form-check-label" for="include_attachments">
                                    Include report attachments (PDF & Excel)
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="mdi mdi-information-outline me-2"></i>
                            The report will be sent as an email with attachments.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Set date range functions
        function setDateRange(range) {
            const today = new Date();
            let startDate = new Date();
            let endDate = new Date();
            
            switch(range) {
                case 'today':
                    startDate = today;
                    endDate = today;
                    break;
                case 'yesterday':
                    startDate = new Date(today.setDate(today.getDate() - 1));
                    endDate = startDate;
                    break;
                case 'week':
                    startDate = new Date(today.setDate(today.getDate() - today.getDay()));
                    endDate = new Date();
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = new Date();
                    break;
                case 'year':
                    startDate = new Date(today.getFullYear(), 0, 1);
                    endDate = new Date();
                    break;
            }
            
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }
        
        // Print report
        function printReport() {
            const printWindow = window.open('', '_blank');
            const now = new Date();
            const formattedDate = now.toLocaleDateString('en-IN', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true 
            });
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Daily Sales Report - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
                        h1 { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                        .summary-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; text-align: center; background:#fff; }
                        .summary-value { font-size: 24px; font-weight: bold; margin: 10px 0; color: #000; }
                        .summary-label { color: #666; font-size: 14px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; color: #000; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        .text-success { color: #198754; }
                        .text-danger { color: #b02a37; }
                        .text-warning { color: #856404; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Daily Sales Report</h1>
                    
                    <div style="margin-bottom: 20px;">
                        <p><strong>Report Period:</strong> <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?></p>
                        <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                        <p><strong>Generated On:</strong> ${formattedDate}</p>
                    </div>
                    
                    <h2>Summary Statistics</h2>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="summary-label">Total Sales</div>
                            <div class="summary-value">₹<?php echo number_format($overall_summary['total_sales'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Total Profit</div>
                            <div class="summary-value">₹<?php echo number_format($overall_summary['total_profit'], 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Total Orders</div>
                            <div class="summary-value"><?php echo number_format($overall_summary['total_orders']); ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-label">Profit Margin</div>
                            <div class="summary-value"><?php echo number_format($overall_summary['avg_profit_percentage'], 1); ?>%</div>
                        </div>
                    </div>
                    
                    <h2>Daily Sales Details</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-center">Orders</th>
                                <th class="text-center">Customers</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-right">Sales</th>
                                <th class="text-right">Profit</th>
                                <th class="text-center">Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($daily_sales_result && mysqli_num_rows($daily_sales_result) > 0) {
                                mysqli_data_seek($daily_sales_result, 0);
                                while ($daily = mysqli_fetch_assoc($daily_sales_result)) {
                                    $profit_sql = "SELECT 
                                                SUM(oi.quantity * p.stock_price) as total_cost,
                                                SUM(oi.total - (oi.quantity * p.stock_price)) as total_profit,
                                                AVG((oi.total - (oi.quantity * p.stock_price)) / oi.total * 100) as avg_profit_percentage
                                                FROM orders o
                                                JOIN order_items oi ON o.id = oi.order_id
                                                JOIN products p ON oi.product_id = p.id
                                                WHERE DATE(o.order_date) = '{$daily['order_day']}'";
                                    
                                    if ($customer_id > 0) {
                                        $profit_sql .= " AND o.customer_id = $customer_id";
                                    }
                                    
                                    $profit_result = mysqli_query($conn, $profit_sql);
                                    $profit_data = mysqli_fetch_assoc($profit_result) ?? ['total_cost' => 0, 'total_profit' => 0, 'avg_profit_percentage' => 0];
                                    ?>
                                    <tr>
                                        <td><?php echo date('d M, Y', strtotime($daily['order_day'])); ?></td>
                                        <td class="text-center"><?php echo $daily['total_orders']; ?></td>
                                        <td class="text-center"><?php echo $daily['total_customers']; ?></td>
                                        <td class="text-center"><?php echo number_format($daily['total_quantity']); ?></td>
                                        <td class="text-right">₹<?php echo number_format($daily['total_sales'], 2); ?></td>
                                        <td class="text-right">₹<?php echo number_format($profit_data['total_profit'], 2); ?></td>
                                        <td class="text-center"><?php echo number_format($profit_data['avg_profit_percentage'], 1); ?>%</td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-center"><?php echo $overall_summary['total_orders']; ?></th>
                                <th class="text-center"><?php echo $overall_summary['total_customers']; ?></th>
                                <th class="text-center"><?php echo number_format($overall_summary['total_quantity']); ?></th>
                                <th class="text-right">₹<?php echo number_format($overall_summary['total_sales'], 2); ?></th>
                                <th class="text-right">₹<?php echo number_format($overall_summary['total_profit'], 2); ?></th>
                                <th class="text-center"><?php echo number_format($overall_summary['avg_profit_percentage'], 1); ?>%</th>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <?php if ($top_products_result && mysqli_num_rows($top_products_result) > 0): ?>
                    <h2>Top 10 Products</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product Name</th>
                                <th class="text-center">Qty Sold</th>
                                <th class="text-right">Sales</th>
                                <th class="text-right">Avg Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($top_products_result, 0);
                            $rank = 1;
                            while ($product = mysqli_fetch_assoc($top_products_result)): 
                            ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td class="text-center"><?php echo number_format($product['total_quantity']); ?></td>
                                <td class="text-right">₹<?php echo number_format($product['total_sales'], 2); ?></td>
                                <td class="text-right">₹<?php echo number_format($product['avg_price'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    
                    <div style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid #ddd;">
                        <p>Report generated by APR Water Agencies</p>
                        <p>For any queries, contact: info@aprwater.com</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        
        // Export report
        function exportReport() {
            const searchParams = new URLSearchParams({
                start_date: '<?php echo $start_date; ?>',
                end_date: '<?php echo $end_date; ?>',
                customer_id: '<?php echo $customer_id; ?>',
                lineman_id: '<?php echo $lineman_id; ?>',
                report_type: '<?php echo $report_type; ?>',
                export: '1'
            });
            
            window.location.href = `export-report.php?${searchParams.toString()}`;
        }
        
        // Auto-submit form on date change
        document.getElementById('report_type').addEventListener('change', function() {
            if (this.value === 'detailed') {
                // Show loading indicator
                const form = this.closest('form');
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Loading...';
                submitBtn.disabled = true;
                
                // Submit form after a brief delay
                setTimeout(() => {
                    form.submit();
                }, 100);
            }
        });
        
        // Initialize date inputs max value
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('start_date').max = today;
            document.getElementById('end_date').max = today;
            
            // Set end date min to start date
            document.getElementById('start_date').addEventListener('change', function() {
                document.getElementById('end_date').min = this.value;
                if (document.getElementById('end_date').value < this.value) {
                    document.getElementById('end_date').value = this.value;
                }
            });
            
            document.getElementById('end_date').addEventListener('change', function() {
                if (this.value < document.getElementById('start_date').value) {
                    this.value = document.getElementById('start_date').value;
                }
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
