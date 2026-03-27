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

// Get filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily'; // daily, monthly, yearly, product, customer
$period = isset($_GET['period']) ? $_GET['period'] : date('Y-m-d'); // date, month, or year
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'today'; // today, yesterday, this_week, this_month, last_month, custom

// Custom date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Adjust date range based on selection
if ($date_range === 'today') {
    $start_date = $end_date = date('Y-m-d');
} elseif ($date_range === 'yesterday') {
    $start_date = $end_date = date('Y-m-d', strtotime('-1 day'));
} elseif ($date_range === 'this_week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d');
} elseif ($date_range === 'this_month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
} elseif ($date_range === 'last_month') {
    $start_date = date('Y-m-01', strtotime('-1 month'));
    $end_date = date('Y-m-t', strtotime('-1 month'));
} elseif ($date_range === 'this_year') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-m-d');
}

// Fetch data
$sales_data = [];
$summary_stats = [
    'total_sales' => 0,
    'total_orders' => 0,
    'total_items' => 0,
    'total_payments' => 0,
    'avg_order_value' => 0,
    'top_product' => null,
    'top_customer' => null
];

// Fetch products for dropdown
$products = [];
$products_sql = "SELECT id, product_name, product_code FROM products WHERE status = 'active' ORDER BY product_name";
$products_result = mysqli_query($conn, $products_sql);
if ($products_result) {
    while ($row = mysqli_fetch_assoc($products_result)) {
        $products[$row['id']] = $row['product_name'] . ' (' . $row['product_code'] . ')';
    }
}

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
    // Build query based on report type
    if ($report_type === 'daily') {
        // Daily sales report
        $sql = "SELECT 
                    DATE(o.order_date) as date,
                    COUNT(DISTINCT o.id) as order_count,
                    COUNT(oi.id) as item_count,
                    SUM(o.total_amount) as total_sales,
                    SUM(o.paid_amount) as total_paid,
                    SUM(o.pending_amount) as total_pending,
                    AVG(o.total_amount) as avg_order_value
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";
                
        if ($customer_id > 0) {
            $sql .= " AND o.customer_id = $customer_id";
        }
        
        if ($payment_status !== 'all') {
            $sql .= " AND o.payment_status = '$payment_status'";
        }
        
        $sql .= " GROUP BY DATE(o.order_date)
                  ORDER BY DATE(o.order_date) DESC";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales_data[] = $row;
                $summary_stats['total_sales'] += $row['total_sales'];
                $summary_stats['total_orders'] += $row['order_count'];
                $summary_stats['total_items'] += $row['item_count'];
                $summary_stats['total_payments'] += $row['total_paid'];
            }
        }
        
    } elseif ($report_type === 'monthly') {
        // Monthly sales report
        $sql = "SELECT 
                    DATE_FORMAT(o.order_date, '%Y-%m') as month,
                    COUNT(DISTINCT o.id) as order_count,
                    COUNT(oi.id) as item_count,
                    SUM(o.total_amount) as total_sales,
                    SUM(o.paid_amount) as total_paid,
                    SUM(o.pending_amount) as total_pending,
                    AVG(o.total_amount) as avg_order_value
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";
                
        if ($customer_id > 0) {
            $sql .= " AND o.customer_id = $customer_id";
        }
        
        if ($payment_status !== 'all') {
            $sql .= " AND o.payment_status = '$payment_status'";
        }
        
        $sql .= " GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
                  ORDER BY DATE_FORMAT(o.order_date, '%Y-%m') DESC";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales_data[] = $row;
                $summary_stats['total_sales'] += $row['total_sales'];
                $summary_stats['total_orders'] += $row['order_count'];
                $summary_stats['total_items'] += $row['item_count'];
                $summary_stats['total_payments'] += $row['total_paid'];
            }
        }
        
    } elseif ($report_type === 'yearly') {
        // Yearly sales report
        $sql = "SELECT 
                    YEAR(o.order_date) as year,
                    COUNT(DISTINCT o.id) as order_count,
                    COUNT(oi.id) as item_count,
                    SUM(o.total_amount) as total_sales,
                    SUM(o.paid_amount) as total_paid,
                    SUM(o.pending_amount) as total_pending,
                    AVG(o.total_amount) as avg_order_value
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";
                
        if ($customer_id > 0) {
            $sql .= " AND o.customer_id = $customer_id";
        }
        
        if ($payment_status !== 'all') {
            $sql .= " AND o.payment_status = '$payment_status'";
        }
        
        $sql .= " GROUP BY YEAR(o.order_date)
                  ORDER BY YEAR(o.order_date) DESC";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales_data[] = $row;
                $summary_stats['total_sales'] += $row['total_sales'];
                $summary_stats['total_orders'] += $row['order_count'];
                $summary_stats['total_items'] += $row['item_count'];
                $summary_stats['total_payments'] += $row['total_paid'];
            }
        }
        
    } elseif ($report_type === 'product') {
        // Product-wise sales report
        $sql = "SELECT 
                    p.id,
                    p.product_code,
                    p.product_name,
                    COUNT(oi.id) as quantity_sold,
                    SUM(oi.total) as total_sales,
                    AVG(oi.price) as avg_price,
                    p.stock_price,
                    p.customer_price,
                    (p.customer_price - p.stock_price) as profit_per_unit,
                    SUM(oi.total) - (SUM(oi.quantity) * p.stock_price) as total_profit
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";
                
        if ($customer_id > 0) {
            $sql .= " AND o.customer_id = $customer_id";
        }
        
        if ($payment_status !== 'all') {
            $sql .= " AND o.payment_status = '$payment_status'";
        }
        
        $sql .= " GROUP BY p.id
                  ORDER BY quantity_sold DESC";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales_data[] = $row;
                $summary_stats['total_sales'] += $row['total_sales'];
                $summary_stats['total_items'] += $row['quantity_sold'];
                
                // Find top product
                if (!$summary_stats['top_product'] || $row['quantity_sold'] > $summary_stats['top_product']['quantity']) {
                    $summary_stats['top_product'] = [
                        'name' => $row['product_name'],
                        'quantity' => $row['quantity_sold'],
                        'sales' => $row['total_sales']
                    ];
                }
            }
        }
        
    } elseif ($report_type === 'customer') {
        // Customer-wise sales report
        $sql = "SELECT 
                    c.id,
                    c.customer_code,
                    c.customer_name,
                    c.shop_name,
                    c.customer_contact,
                    c.customer_type,
                    COUNT(DISTINCT o.id) as order_count,
                    SUM(o.total_amount) as total_purchases,
                    SUM(o.paid_amount) as total_paid,
                    SUM(o.pending_amount) as total_pending,
                    AVG(o.total_amount) as avg_order_value,
                    MAX(o.order_date) as last_order_date
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id AND o.order_date BETWEEN '$start_date' AND '$end_date'";
                
        if ($customer_id > 0) {
            $sql .= " AND c.id = $customer_id";
        }
        
        if ($payment_status !== 'all') {
            $sql .= " AND o.payment_status = '$payment_status'";
        }
        
        $sql .= " WHERE c.status = 'active'
                  GROUP BY c.id
                  HAVING total_purchases > 0
                  ORDER BY total_purchases DESC";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales_data[] = $row;
                $summary_stats['total_sales'] += $row['total_purchases'];
                $summary_stats['total_orders'] += $row['order_count'];
                $summary_stats['total_payments'] += $row['total_paid'];
                
                // Find top customer
                if (!$summary_stats['top_customer'] || $row['total_purchases'] > $summary_stats['top_customer']['purchases']) {
                    $summary_stats['top_customer'] = [
                        'name' => $row['customer_name'],
                        'purchases' => $row['total_purchases'],
                        'orders' => $row['order_count']
                    ];
                }
            }
        }
    }
    
    // Calculate average order value
    if ($summary_stats['total_orders'] > 0) {
        $summary_stats['avg_order_value'] = $summary_stats['total_sales'] / $summary_stats['total_orders'];
    }
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

                <!-- Page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Sales Reports</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active">Sales Reports</li>
                                </ol>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Sales</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($summary_stats['total_sales']); ?>
                                        </h4>
                                        <p class="text-muted mb-0">
                                            <?php echo $summary_stats['total_orders']; ?> orders
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
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Items Sold</p>
                                        <h4 class="mb-0">
                                            <?php echo number_format($summary_stats['total_items']); ?>
                                        </h4>
                                        <p class="text-muted mb-0">Units</p>
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
                                            <i class="mdi mdi-cart"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Avg. Order Value</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($summary_stats['avg_order_value']); ?>
                                        </h4>
                                        <p class="text-muted mb-0">Per order</p>
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
                                            <i class="mdi mdi-cash-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Payments</p>
                                        <h4 class="mb-0">
                                            <?php echo formatCurrency($summary_stats['total_payments']); ?>
                                        </h4>
                                        <p class="text-muted mb-0">Received</p>
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
                                <h5 class="card-title mb-3">Report Filters</h5>
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label">Report Type</label>
                                        <select class="form-select" name="report_type" onchange="this.form.submit()">
                                            <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily Sales</option>
                                            <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly Sales</option>
                                            <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly Sales</option>
                                            <option value="product" <?php echo $report_type == 'product' ? 'selected' : ''; ?>>Product-wise</option>
                                            <option value="customer" <?php echo $report_type == 'customer' ? 'selected' : ''; ?>>Customer-wise</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Date Range</label>
                                        <select class="form-select" name="date_range" onchange="this.form.submit()">
                                            <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                                            <option value="yesterday" <?php echo $date_range == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                            <option value="this_week" <?php echo $date_range == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                            <option value="this_month" <?php echo $date_range == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                            <option value="last_month" <?php echo $date_range == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                            <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>This Year</option>
                                            <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($date_range === 'custom'): ?>
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
                                    
                                    <?php if ($report_type !== 'customer'): ?>
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
                                    <?php endif; ?>
                                    
                                    <?php if ($report_type !== 'product'): ?>
                                    <div class="col-md-2">
                                        <label class="form-label">Product</label>
                                        <select class="form-select" name="product_id">
                                            <option value="0">All Products</option>
                                            <?php foreach ($products as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" 
                                                    <?php echo $product_id == $id ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="all" <?php echo $payment_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                            <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>Paid Only</option>
                                            <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>Partial Paid</option>
                                            <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="d-flex gap-2 mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-filter me-1"></i> Apply Filters
                                            </button>
                                            <a href="sales-reports.php" class="btn btn-secondary">
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

                <!-- Report Content -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">
                                            <?php 
                                            $report_titles = [
                                                'daily' => 'Daily Sales Report',
                                                'monthly' => 'Monthly Sales Report',
                                                'yearly' => 'Yearly Sales Report',
                                                'product' => 'Product-wise Sales Report',
                                                'customer' => 'Customer-wise Sales Report'
                                            ];
                                            echo $report_titles[$report_type] ?? 'Sales Report';
                                            ?>
                                        </h4>
                                        <p class="card-title-desc">
                                            <?php 
                                            $range_text = '';
                                            if ($date_range === 'custom') {
                                                $range_text = 'From ' . date('d M, Y', strtotime($start_date)) . ' to ' . date('d M, Y', strtotime($end_date));
                                            } else {
                                                $range_text = ucfirst(str_replace('_', ' ', $date_range));
                                            }
                                            echo $range_text;
                                            ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end">
                                            <div class="me-3">
                                                <span class="text-muted">Showing:</span>
                                                <span class="fw-bold"><?php echo count($sales_data); ?> records</span>
                                            </div>
                                            <div>
                                                <span class="text-muted">Total Sales:</span>
                                                <span class="fw-bold"><?php echo formatCurrency($summary_stats['total_sales']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="salesReportTable">
                                        <thead class="table-light">
                                            <?php if ($report_type === 'daily'): ?>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Date</th>
                                                    <th>Day</th>
                                                    <th>Orders</th>
                                                    <th>Items</th>
                                                    <th>Total Sales</th>
                                                    <th>Paid Amount</th>
                                                    <th>Pending Amount</th>
                                                    <th>Avg. Order Value</th>
                                                </tr>
                                            <?php elseif ($report_type === 'monthly'): ?>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Month</th>
                                                    <th>Orders</th>
                                                    <th>Items</th>
                                                    <th>Total Sales</th>
                                                    <th>Paid Amount</th>
                                                    <th>Pending Amount</th>
                                                    <th>Avg. Order Value</th>
                                                    <th>Daily Avg.</th>
                                                </tr>
                                            <?php elseif ($report_type === 'yearly'): ?>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Year</th>
                                                    <th>Orders</th>
                                                    <th>Items</th>
                                                    <th>Total Sales</th>
                                                    <th>Paid Amount</th>
                                                    <th>Pending Amount</th>
                                                    <th>Avg. Order Value</th>
                                                    <th>Monthly Avg.</th>
                                                </tr>
                                            <?php elseif ($report_type === 'product'): ?>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Product</th>
                                                    <th>Code</th>
                                                    <th>Quantity Sold</th>
                                                    <th>Avg. Price</th>
                                                    <th>Total Sales</th>
                                                    <th>Cost Price</th>
                                                    <th>Selling Price</th>
                                                    <th>Profit Per Unit</th>
                                                    <th>Total Profit</th>
                                                    <th>Profit %</th>
                                                </tr>
                                            <?php elseif ($report_type === 'customer'): ?>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Customer</th>
                                                    <th>Contact</th>
                                                    <th>Type</th>
                                                    <th>Orders</th>
                                                    <th>Total Purchases</th>
                                                    <th>Paid Amount</th>
                                                    <th>Pending Amount</th>
                                                    <th>Avg. Order Value</th>
                                                    <th>Last Order</th>
                                                </tr>
                                            <?php endif; ?>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($sales_data)): ?>
                                                <?php $counter = 1; ?>
                                                <?php foreach ($sales_data as $row): ?>
                                                    <?php if ($report_type === 'daily'): ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td><?php echo formatDate($row['date']); ?></td>
                                                            <td><?php echo date('l', strtotime($row['date'])); ?></td>
                                                            <td><span class="badge bg-primary"><?php echo $row['order_count']; ?></span></td>
                                                            <td><span class="badge bg-info"><?php echo $row['item_count']; ?></span></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($row['total_sales']); ?></td>
                                                            <td class="text-success"><?php echo formatCurrency($row['total_paid']); ?></td>
                                                            <td class="text-danger"><?php echo formatCurrency($row['total_pending']); ?></td>
                                                            <td><?php echo formatCurrency($row['avg_order_value']); ?></td>
                                                        </tr>
                                                    <?php elseif ($report_type === 'monthly'): ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                                            <td><span class="badge bg-primary"><?php echo $row['order_count']; ?></span></td>
                                                            <td><span class="badge bg-info"><?php echo $row['item_count']; ?></span></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($row['total_sales']); ?></td>
                                                            <td class="text-success"><?php echo formatCurrency($row['total_paid']); ?></td>
                                                            <td class="text-danger"><?php echo formatCurrency($row['total_pending']); ?></td>
                                                            <td><?php echo formatCurrency($row['avg_order_value']); ?></td>
                                                            <td><?php echo formatCurrency($row['total_sales'] / 30); ?></td>
                                                        </tr>
                                                    <?php elseif ($report_type === 'yearly'): ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td><?php echo $row['year']; ?></td>
                                                            <td><span class="badge bg-primary"><?php echo $row['order_count']; ?></span></td>
                                                            <td><span class="badge bg-info"><?php echo $row['item_count']; ?></span></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($row['total_sales']); ?></td>
                                                            <td class="text-success"><?php echo formatCurrency($row['total_paid']); ?></td>
                                                            <td class="text-danger"><?php echo formatCurrency($row['total_pending']); ?></td>
                                                            <td><?php echo formatCurrency($row['avg_order_value']); ?></td>
                                                            <td><?php echo formatCurrency($row['total_sales'] / 12); ?></td>
                                                        </tr>
                                                    <?php elseif ($report_type === 'product'): ?>
                                                        <?php
                                                        $profit_percentage = 0;
                                                        if ($row['stock_price'] > 0) {
                                                            $profit_percentage = (($row['customer_price'] - $row['stock_price']) / $row['stock_price']) * 100;
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0 me-3">
                                                                        <div class="avatar-xs">
                                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                                <?php echo strtoupper(substr($row['product_name'], 0, 1)); ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['product_name']); ?></h6>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><small class="text-muted"><?php echo $row['product_code']; ?></small></td>
                                                            <td><span class="badge bg-info"><?php echo $row['quantity_sold']; ?></span></td>
                                                            <td><?php echo formatCurrency($row['avg_price']); ?></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($row['total_sales']); ?></td>
                                                            <td><?php echo formatCurrency($row['stock_price']); ?></td>
                                                            <td><?php echo formatCurrency($row['customer_price']); ?></td>
                                                            <td class="text-success"><?php echo formatCurrency($row['profit_per_unit']); ?></td>
                                                            <td class="text-success fw-bold"><?php echo formatCurrency($row['total_profit']); ?></td>
                                                            <td>
                                                                <span class="badge bg-success-subtle text-success">
                                                                    <?php echo number_format($profit_percentage, 1); ?>%
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php elseif ($report_type === 'customer'): ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0 me-3">
                                                                        <div class="avatar-xs">
                                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                                <?php echo strtoupper(substr($row['customer_name'], 0, 1)); ?>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['customer_name']); ?></h6>
                                                                        <small class="text-muted"><?php echo htmlspecialchars($row['shop_name']); ?></small>
                                                                        <br>
                                                                        <small class="text-muted"><?php echo $row['customer_code']; ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($row['customer_contact']); ?></td>
                                                            <td>
                                                                <span class="badge bg-info-subtle text-info">
                                                                    <?php echo ucfirst($row['customer_type']); ?>
                                                                </span>
                                                            </td>
                                                            <td><span class="badge bg-primary"><?php echo $row['order_count']; ?></span></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($row['total_purchases']); ?></td>
                                                            <td class="text-success"><?php echo formatCurrency($row['total_paid']); ?></td>
                                                            <td class="text-danger"><?php echo formatCurrency($row['total_pending']); ?></td>
                                                            <td><?php echo formatCurrency($row['avg_order_value']); ?></td>
                                                            <td>
                                                                <?php echo $row['last_order_date'] ? formatDate($row['last_order_date']) : 'No orders'; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-chart-line display-4"></i>
                                                            <h5 class="mt-2">No Sales Data Found</h5>
                                                            <p>No sales records found for the selected criteria</p>
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
                                            Showing <?php echo count($sales_data); ?> records
                                            <?php if ($summary_stats['total_sales'] > 0): ?>
                                                totaling <?php echo formatCurrency($summary_stats['total_sales']); ?>
                                            <?php endif; ?>
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

                <!-- Insights Section -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Sales Insights</h5>
                                <div class="row">
                                    <?php if ($report_type === 'product' && $summary_stats['top_product']): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Top Product</h6>
                                            <h4 class="mb-1"><?php echo htmlspecialchars($summary_stats['top_product']['name']); ?></h4>
                                            <p class="text-muted mb-0"><?php echo $summary_stats['top_product']['quantity']; ?> units sold</p>
                                            <p class="fw-bold"><?php echo formatCurrency($summary_stats['top_product']['sales']); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($report_type === 'customer' && $summary_stats['top_customer']): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Top Customer</h6>
                                            <h4 class="mb-1"><?php echo htmlspecialchars($summary_stats['top_customer']['name']); ?></h4>
                                            <p class="text-muted mb-0"><?php echo $summary_stats['top_customer']['orders']; ?> orders</p>
                                            <p class="fw-bold"><?php echo formatCurrency($summary_stats['top_customer']['purchases']); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Total Orders</h6>
                                            <h4 class="mb-1"><?php echo number_format($summary_stats['total_orders']); ?></h4>
                                            <p class="text-muted mb-0">In selected period</p>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Average Order Value</h6>
                                            <h4 class="mb-1"><?php echo formatCurrency($summary_stats['avg_order_value']); ?></h4>
                                            <p class="text-muted mb-0">Per transaction</p>
                                        </div>
                                    </div>
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
                                    <a href="products.php" class="btn btn-outline-success">
                                        <i class="mdi mdi-package-variant me-1"></i> View Products
                                    </a>
                                    <a href="customers.php" class="btn btn-outline-info">
                                        <i class="mdi mdi-account-group me-1"></i> View Customers
                                    </a>
                                    <a href="pending-payments.php" class="btn btn-outline-warning">
                                        <i class="mdi mdi-cash-clock me-1"></i> View Pending Payments
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

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#salesReportTable').DataTable({
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
    
    // Handle date range changes
    const dateRangeSelect = document.querySelector('select[name="date_range"]');
    if (dateRangeSelect) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                // Show custom date inputs
                const form = this.closest('form');
                const startDate = document.createElement('div');
                startDate.className = 'col-md-2';
                startDate.innerHTML = `
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                `;
                
                const endDate = document.createElement('div');
                endDate.className = 'col-md-2';
                endDate.innerHTML = `
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                `;
                
                // Insert after date range select
                this.closest('.col-md-2').after(startDate, endDate);
            } else {
                // Remove custom date inputs if they exist
                const customInputs = document.querySelectorAll('[name="start_date"], [name="end_date"]');
                customInputs.forEach(input => {
                    if (input.closest('.col-md-2')) {
                        input.closest('.col-md-2').remove();
                    }
                });
            }
        });
    }
    
    // Auto-submit when report type changes
    const reportTypeSelect = document.querySelector('select[name="report_type"]');
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
});

// Export to Excel function
function exportToExcel() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Create export URL
    let exportUrl = 'export-sales-report.php?';
    
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
    const reportType = '<?php echo $report_type; ?>';
    const dateRange = '<?php echo $date_range; ?>';
    const startDate = '<?php echo $start_date; ?>';
    const endDate = '<?php echo $end_date; ?>';
    
    const printContent = document.querySelector('.card').outerHTML;
    const originalContent = document.body.innerHTML;
    const printTitle = 'Sales Report - ' + new Date().toLocaleDateString();
    
    let rangeText = '';
    if (dateRange === 'custom') {
        rangeText = `From ${new Date(startDate).toLocaleDateString()} to ${new Date(endDate).toLocaleDateString()}`;
    } else {
        rangeText = dateRange.charAt(0).toUpperCase() + dateRange.slice(1).replace('_', ' ');
    }
    
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
                    @page { margin: 0.5cm; }
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
                <h2 style="color: #007bff; margin: 0;">Sales Report</h2>
                <p style="margin: 5px 0;">
                    <strong>Report Type:</strong> ${reportType.charAt(0).toUpperCase() + reportType.slice(1)} | 
                    <strong>Period:</strong> ${rangeText}
                </p>
                <p style="margin: 5px 0;">
                    <strong>Generated on:</strong> ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}
                </p>
            </div>
            
            <div class="summary-box">
                <h4 style="margin: 0 0 10px 0; color: #495057;">Summary</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                    <div>
                        <strong>Total Sales:</strong><br>
                        ${<?php echo formatCurrency($summary_stats['total_sales']); ?>}
                    </div>
                    <div>
                        <strong>Total Orders:</strong><br>
                        ${<?php echo $summary_stats['total_orders']; ?>}
                    </div>
                    <div>
                        <strong>Items Sold:</strong><br>
                        ${<?php echo $summary_stats['total_items']; ?>}
                    </div>
                    <div>
                        <strong>Avg. Order Value:</strong><br>
                        ${<?php echo formatCurrency($summary_stats['avg_order_value']); ?>}
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
</script>

<style>
/* Custom styles for sales reports */
.sales-high {
    background-color: #d4edda;
    color: #155724;
    border-radius: 4px;
}
.sales-medium {
    background-color: #fff3cd;
    color: #856404;
    border-radius: 4px;
}
.sales-low {
    background-color: #d1ecf1;
    color: #0c5460;
    border-radius: 4px;
}
.profit-high {
    background-color: #d4edda;
    color: #155724;
    border-radius: 4px;
}
.profit-low {
    background-color: #f8d7da;
    color: #721c24;
    border-radius: 4px;
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