<?php
// lineman-dashboard.php
// Advanced Realtime Lineman Dashboard
include('config/config.php');
include('includes/auth-check.php');

// Authorization
if (!in_array($_SESSION['user_role'], ['lineman', 'admin', 'super_admin'])) {
    header('Location: index.php');
    exit;
}

// Determine lineman id
$lineman_id = isset($_SESSION['lineman_id']) ? intval($_SESSION['lineman_id']) : 0;

if ($lineman_id <= 0 && (!empty($_SESSION['username']) || !empty($_SESSION['email']))) {
    $username = isset($_SESSION['username']) ? mysqli_real_escape_string($conn, $_SESSION['username']) : '';
    $email = isset($_SESSION['email']) ? mysqli_real_escape_string($conn, $_SESSION['email']) : '';

    $whereParts = [];
    if ($username !== '') $whereParts[] = "username = '$username'";
    if ($email !== '') $whereParts[] = "email = '$email'";
    if (count($whereParts) > 0) {
        $sql = "SELECT id FROM linemen WHERE (" . implode(' OR ', $whereParts) . ") AND status = 'active' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $lineman_id = intval($row['id']);
            $_SESSION['lineman_id'] = $lineman_id;
        }
    }
}

if ($_SESSION['user_role'] === 'lineman' && $lineman_id <= 0) {
    echo "<div class='alert alert-danger'>Lineman profile not linked. Please contact admin.</div>";
    exit;
}

// Get lineman details
$lineman_info = [];
if ($lineman_id > 0) {
    $sql = "SELECT l.*, 
            (SELECT COUNT(*) FROM customers WHERE assigned_lineman_id = l.id AND status = 'active') as total_customers,
            (SELECT COUNT(*) FROM orders o 
             JOIN customers c ON o.customer_id = c.id 
             WHERE c.assigned_lineman_id = l.id 
             AND DATE(o.created_at) = CURDATE()) as today_orders
            FROM linemen l WHERE l.id = $lineman_id";
    $result = mysqli_query($conn, $sql);
    $lineman_info = mysqli_fetch_assoc($result);
}

// AJAX endpoints
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'stats';
    $lid = intval(isset($_GET['lineman_id']) ? $_GET['lineman_id'] : $lineman_id);
    
    // Today's date and periods
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $start_of_month = date('Y-m-01');
    $last_month_start = date('Y-m-01', strtotime('-1 month'));
    
    if ($action === 'stats') {
        // Comprehensive statistics
        $response = [];
        
        // 1. Today's Performance
        $sql_today = "SELECT 
            COUNT(DISTINCT t.id) as txn_count,
            IFNULL(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0) as collected,
            IFNULL(SUM(CASE WHEN t.type = 'refund' THEN t.amount ELSE 0 END), 0) as refunds,
            COUNT(DISTINCT t.customer_id) as customers_served,
            MAX(t.created_at) as last_txn_time
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE DATE(t.created_at) = '$today' 
            AND c.assigned_lineman_id = $lid";
        
        $result = mysqli_query($conn, $sql_today);
        $response['today'] = mysqli_fetch_assoc($result) ?: [];
        
        // 2. Yesterday comparison
        $sql_yesterday = "SELECT 
            IFNULL(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0) as collected_yesterday
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE DATE(t.created_at) = '$yesterday'
            AND c.assigned_lineman_id = $lid";
        
        $result = mysqli_query($conn, $sql_yesterday);
        $yesterday_data = mysqli_fetch_assoc($result);
        $response['yesterday'] = $yesterday_data;
        
        // 3. Orders Summary
        $sql_orders = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN DATE(o.created_at) = '$today' THEN 1 ELSE 0 END) as orders_today,
            SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN o.status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            IFNULL(AVG(o.total_amount), 0) as avg_order_value
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE c.assigned_lineman_id = $lid
            AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        $result = mysqli_query($conn, $sql_orders);
        $response['orders'] = mysqli_fetch_assoc($result) ?: [];
        
        // 4. Customer Statistics
        $sql_customers = "SELECT 
            COUNT(*) as total_customers,
            COUNT(CASE WHEN last_purchase_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as active_30days,
            COUNT(CASE WHEN last_purchase_date IS NULL OR last_purchase_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN 1 END) as inactive_customers,
            AVG(total_purchases) as avg_customer_value
            FROM customers 
            WHERE assigned_lineman_id = $lid 
            AND status = 'active'";
        
        $result = mysqli_query($conn, $sql_customers);
        $response['customers'] = mysqli_fetch_assoc($result) ?: [];
        
        // 5. Monthly Performance
        $sql_monthly = "SELECT 
            DATE_FORMAT(t.created_at, '%Y-%m') as month,
            COUNT(DISTINCT t.id) as txn_count,
            IFNULL(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0) as collections,
            COUNT(DISTINCT t.customer_id) as unique_customers
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE c.assigned_lineman_id = $lid
            AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
            ORDER BY month DESC";
        
        $result = mysqli_query($conn, $sql_monthly);
        $response['monthly'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $response['monthly'][] = $row;
        }
        
        // 6. Top Customers (last 30 days)
        $sql_top_customers = "SELECT 
            c.id, c.shop_name, c.customer_name,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE c.assigned_lineman_id = $lid
            AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY c.id
            ORDER BY total_spent DESC
            LIMIT 5";
        
        $result = mysqli_query($conn, $sql_top_customers);
        $response['top_customers'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $response['top_customers'][] = $row;
        }
        
        // 7. Payment Methods Distribution
        $sql_payment_methods = "SELECT 
            payment_method,
            COUNT(*) as count,
            SUM(amount) as total
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE c.assigned_lineman_id = $lid
            AND t.type = 'payment'
            AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY payment_method";
        
        $result = mysqli_query($conn, $sql_payment_methods);
        $response['payment_methods'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $response['payment_methods'][] = $row;
        }
        
        // 8. Daily Performance (last 7 days)
        $sql_daily = "SELECT 
            DATE(t.created_at) as date,
            DATE_FORMAT(t.created_at, '%a') as day_name,
            COUNT(DISTINCT t.id) as txn_count,
            IFNULL(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END), 0) as collections,
            COUNT(DISTINCT t.customer_id) as customers_served
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE c.assigned_lineman_id = $lid
            AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(t.created_at)
            ORDER BY date";
        
        $result = mysqli_query($conn, $sql_daily);
        $response['daily'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $response['daily'][] = $row;
        }
        
        // 9. Recent Activity
        $sql_recent = "SELECT 
            t.*, o.order_number, c.shop_name, c.customer_contact,
            CASE 
                WHEN t.type = 'payment' THEN 'success'
                WHEN t.type = 'refund' THEN 'danger'
                ELSE 'secondary'
            END as badge_class
            FROM transactions t
            LEFT JOIN orders o ON t.order_id = o.id
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE c.assigned_lineman_id = $lid
            ORDER BY t.created_at DESC
            LIMIT 10";
        
        $result = mysqli_query($conn, $sql_recent);
        $response['recent'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $response['recent'][] = $row;
        }
        
        // 10. Performance Metrics
        $response['metrics'] = [
            'collection_efficiency' => $response['today']['collected'] > 0 ? 
                round(($response['today']['collected'] / ($response['today']['collected'] + $response['today']['refunds'])) * 100, 2) : 0,
            'avg_daily_customers' => $response['today']['customers_served'] ?: 0,
            'txn_per_customer' => $response['today']['customers_served'] > 0 ? 
                round($response['today']['txn_count'] / $response['today']['customers_served'], 2) : 0
        ];
        
        echo json_encode(['status' => 'success', 'data' => $response]);
        exit;
        
    } elseif ($action === 'customers') {
        // Detailed customers list
        $sql = "SELECT 
            c.*,
            (SELECT COUNT(*) FROM orders WHERE customer_id = c.id AND DATE(created_at) = CURDATE()) as today_orders,
            (SELECT SUM(total_amount) FROM orders WHERE customer_id = c.id AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_spent,
            (SELECT MAX(created_at) FROM orders WHERE customer_id = c.id) as last_order_date
            FROM customers c
            WHERE c.assigned_lineman_id = $lid
            AND c.status = 'active'
            ORDER BY c.last_purchase_date DESC NULLS LAST
            LIMIT 5";
        
        $result = mysqli_query($conn, $sql);
        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $customers[] = $row;
        }
        
        echo json_encode(['status' => 'success', 'data' => $customers]);
        exit;
        
    } elseif ($action === 'orders') {
        // Recent orders with details
        $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
        $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
        
        $where = "c.assigned_lineman_id = $lid";
        if ($status !== 'all') {
            $where .= " AND o.status = '$status'";
        }
        $where .= " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        
        $sql = "SELECT 
            o.*, c.shop_name, c.customer_contact,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            WHERE $where
            ORDER BY o.created_at DESC
            LIMIT 20";
        
        $result = mysqli_query($conn, $sql);
        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }
        
        echo json_encode(['status' => 'success', 'data' => $orders]);
        exit;
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include('includes/head.php'); ?>
<style>
    :root {
        --primary-600: #556ee6;
        --success-600: #28a745;
        --warning-600: #ffc107;
        --danger-600: #dc3545;
        --info-600: #17a2b8;
        --purple-600: #6f42c1;
        --pink-600: #e83e8c;
        --muted-700: #495057;
    }
    
    .dashboard-card {
        border: 1px solid #e9ecef;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.03);
        background: #fff;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .dashboard-card:hover {
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        transform: translateY(-2px);
    }
    
    .stat-number {
        font-size: 32px;
        font-weight: 800;
        color: var(--muted-700);
        line-height: 1.2;
    }
    
    .stat-label {
        color: #6c757d;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    
    .stat-change {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }
    
    .stat-change.positive {
        background: rgba(40, 167, 69, 0.15);
        color: var(--success-600);
    }
    
    .stat-change.negative {
        background: rgba(220, 53, 69, 0.15);
        color: var(--danger-600);
    }
    
    .live-pulse {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        background: var(--success-600);
        margin-right: 8px;
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 1);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
    }
    
    .badge-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    
    .customer-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 16px;
    }
    
    .progress-thin {
        height: 6px;
        border-radius: 3px;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    .metric-card {
        border-left: 4px solid;
        padding-left: 15px;
    }
    
    .metric-card.primary { border-left-color: var(--primary-600); }
    .metric-card.success { border-left-color: var(--success-600); }
    .metric-card.warning { border-left-color: var(--warning-600); }
    .metric-card.danger { border-left-color: var(--danger-600); }
    
    .quick-action-btn {
        padding: 10px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
        border: 1px solid #dee2e6;
        background: #f8f9fa;
    }
    
    .quick-action-btn:hover {
        background: #e9ecef;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .tab-pane {
        padding: 20px 0;
    }
    
    .data-table th {
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        color: #6c757d;
        border-top: none;
    }
    
    .data-table td {
        vertical-align: middle;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-badge.pending { background: #fff3cd; color: #856404; }
    .status-badge.delivered { background: #d4edda; color: #155724; }
    .status-badge.processing { background: #cce5ff; color: #004085; }
    .status-badge.cancelled { background: #f8d7da; color: #721c24; }
    
    /* Customer list styles */
    .customer-list-item {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
        transition: all 0.2s;
    }
    
    .customer-list-item:hover {
        background-color: #f8f9fa;
    }
    
    .customer-list-item:last-child {
        border-bottom: none;
    }
    
    .customer-initial {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(45deg, var(--primary-600), #6f42c1);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 16px;
        flex-shrink: 0;
    }
    
    .customer-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    
    .customer-status-dot.active {
        background-color: #28a745;
    }
    
    .customer-status-dot.inactive {
        background-color: #6c757d;
    }
    
    .small-muted { color:#6c757d; font-size:0.85rem; }
    
    @media (max-width: 768px) {
        .stat-number { font-size: 24px; }
        .chart-container { height: 250px; }
    }
</style>
<body data-sidebar="dark">

    <!-- Loader -->
    <?php include('includes/pre-loader.php') ?>

    <!-- Begin page -->
    <div id="layout-wrapper">
        <?php include('includes/topbar.php') ?>

        <!-- Left Sidebar -->
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <?php $current_page = 'lineman-dashboard'; include('includes/sidebar.php'); ?>
            </div>
        </div>

        <!-- Main content -->
        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Header with Quick Actions -->
                    <div class="row align-items-center mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="customer-avatar me-3">
                                    <?php echo substr($lineman_info['full_name'] ?? 'LM', 0, 1); ?>
                                </div>
                                <div>
                                    <h4 class="card-title mb-0">Lineman Dashboard</h4>
                                    <p class="card-title-desc mb-0">
                                        <strong><?php echo htmlspecialchars($lineman_info['full_name'] ?? 'Lineman'); ?></strong> 
                                        | <?php echo htmlspecialchars($lineman_info['employee_id'] ?? 'N/A'); ?>
                                        | Assigned Area: <?php echo htmlspecialchars($lineman_info['assigned_area'] ?? 'Not Assigned'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <span class="live-pulse"></span>
                                <small class="text-muted me-3" id="live-status">Live • Refreshing every 10s</small>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshDashboard()">
                                        <i class="mdi mdi-reload"></i> Refresh
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" onclick="exportData()">
                                        <i class="mdi mdi-download"></i> Export
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="printDashboard()">
                                        <i class="mdi mdi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="dashboard-card p-3">
                                <div class="row g-2">
                                    <div class="col-md-2 col-6">
                                        <a href="add-order.php" class="quick-action-btn d-block text-center">
                                            <i class="mdi mdi-plus-circle-outline text-primary fs-4 mb-2"></i>
                                            <div class="small fw-bold">New Order</div>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <a href="daily-collection.php" class="quick-action-btn d-block text-center">
                                            <i class="mdi mdi-cash-multiple text-success fs-4 mb-2"></i>
                                            <div class="small fw-bold">Collections</div>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <a href="customers.php" class="quick-action-btn d-block text-center">
                                            <i class="mdi mdi-account-group text-info fs-4 mb-2"></i>
                                            <div class="small fw-bold">Customers</div>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <a href="orders.php" class="quick-action-btn d-block text-center">
                                            <i class="mdi mdi-clipboard-list text-warning fs-4 mb-2"></i>
                                            <div class="small fw-bold">Orders</div>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <a href="stock-requests.php" class="quick-action-btn d-block text-center">
                                            <i class="mdi mdi-package-variant text-danger fs-4 mb-2"></i>
                                            <div class="small fw-bold">Stock</div>
                                        </a>
                                    </div>
                                    <div class="col-md-2 col-6">
                                        <a href="reports.php" class="quick-action-btn d-block text-center">
                                            <i class="mdi mdi-chart-bar text-purple fs-4 mb-2"></i>
                                            <div class="small fw-bold">Reports</div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Statistics -->
                    <div class="row mb-4" id="main-stats">
                        <!-- Today's Collection -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="dashboard-card p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">TODAY'S COLLECTION</div>
                                        <div class="stat-number" id="today-collection">₹0.00</div>
                                        <div class="mt-2">
                                            <span class="badge bg-success bg-opacity-10 text-success" id="today-txn">0 transactions</span>
                                            <span class="badge bg-info bg-opacity-10 text-info ms-2" id="today-customers">0 customers</span>
                                        </div>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="mdi mdi-cash text-primary fs-2"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between small text-muted">
                                        <span>Yesterday: <span id="yesterday-collection">₹0.00</span></span>
                                        <span id="collection-change" class="stat-change">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Orders -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="dashboard-card p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">TODAY'S ORDERS</div>
                                        <div class="stat-number" id="today-orders">0</div>
                                        <div class="mt-2">
                                            <span class="badge bg-success bg-opacity-10 text-success" id="delivered-orders">0 delivered</span>
                                            <span class="badge bg-warning bg-opacity-10 text-warning ms-2" id="pending-orders">0 pending</span>
                                        </div>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="mdi mdi-clipboard-check text-success fs-2"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="progress progress-thin">
                                        <div id="delivery-progress" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div class="small text-muted mt-1">Delivery Rate: <span id="delivery-rate">0%</span></div>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Customers -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="dashboard-card p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">ASSIGNED CUSTOMERS</div>
                                        <div class="stat-number" id="total-customers">0</div>
                                        <div class="mt-2">
                                            <span class="badge bg-success bg-opacity-10 text-success" id="active-customers">0 active</span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2" id="inactive-customers">0 inactive</span>
                                        </div>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-3 rounded">
                                        <i class="mdi mdi-account-group text-info fs-2"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="small text-muted">
                                        Avg. Monthly Value: <span id="avg-customer-value">₹0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Metrics -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="dashboard-card p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="stat-label">PERFORMANCE SCORE</div>
                                        <div class="stat-number" id="performance-score">0%</div>
                                        <div class="mt-2">
                                            <span class="badge bg-primary bg-opacity-10 text-primary" id="efficiency-rate">0% efficiency</span>
                                            <span class="badge bg-purple bg-opacity-10 text-purple ms-2" id="avg-txn">0 txn/cust</span>
                                        </div>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                                        <i class="mdi mdi-trending-up text-warning fs-2"></i>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="small text-muted">
                                        Last Updated: <span id="last-updated">--:--:--</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts & Detailed Analytics -->
                    <div class="row mb-4">
                        <!-- Collection Trend Chart -->
                        <div class="col-lg-8 mb-4">
                            <div class="dashboard-card h-100 p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Collection Trend (Last 7 Days)</h5>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary active" onclick="setChartPeriod('7d')">7D</button>
                                        <button class="btn btn-outline-secondary" onclick="setChartPeriod('30d')">30D</button>
                                        <button class="btn btn-outline-secondary" onclick="setChartPeriod('90d')">90D</button>
                                    </div>
                                </div>
                                <div class="chart-container">
                                    <canvas id="collectionChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Methods Distribution -->
                        <div class="col-lg-4 mb-4">
                            <div class="dashboard-card h-100 p-3">
                                <h5 class="card-title mb-3">Payment Methods</h5>
                                <div class="chart-container">
                                    <canvas id="paymentChart"></canvas>
                                </div>
                                <div id="payment-summary" class="mt-3 small text-muted">
                                    Loading payment data...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs for Detailed Views -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="dashboard-card">
                                <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#recent-activity">
                                            <i class="mdi mdi-history me-1"></i> Recent Activity
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#top-customers">
                                            <i class="mdi mdi-star-circle me-1"></i> Top Customers
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#pending-orders-tab">
                                            <i class="mdi mdi-clock-outline me-1"></i> Pending Orders
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#monthly-performance">
                                            <i class="mdi mdi-chart-line me-1"></i> Monthly Performance
                                        </a>
                                    </li>
                                </ul>
                                
                                <div class="tab-content p-3">
                                    <!-- Recent Activity Tab -->
                                    <div class="tab-pane fade show active" id="recent-activity">
                                        <div class="table-responsive">
                                            <table class="table data-table table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>Type</th>
                                                        <th>Customer</th>
                                                        <th>Amount</th>
                                                        <th>Payment Method</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="recent-activity-body">
                                                    <tr><td colspan="6" class="text-center">Loading recent activity...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Top Customers Tab -->
                                    <div class="tab-pane fade" id="top-customers">
                                        <div class="table-responsive">
                                            <table class="table data-table table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Customer</th>
                                                        <th>Orders (30D)</th>
                                                        <th>Total Spent</th>
                                                        <th>Avg. Order</th>
                                                        <th>Last Order</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="top-customers-body">
                                                    <tr><td colspan="6" class="text-center">Loading top customers...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Pending Orders Tab -->
                                    <div class="tab-pane fade" id="pending-orders-tab">
                                        <div class="table-responsive">
                                            <table class="table data-table table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Order #</th>
                                                        <th>Customer</th>
                                                        <th>Items</th>
                                                        <th>Amount</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="pending-orders-body">
                                                    <tr><td colspan="7" class="text-center">Loading pending orders...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Monthly Performance Tab -->
                                    <div class="tab-pane fade" id="monthly-performance">
                                        <div class="table-responsive">
                                            <table class="table data-table table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Collections</th>
                                                        <th>Transactions</th>
                                                        <th>Customers</th>
                                                        <th>Avg. Collection</th>
                                                        <th>Growth</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="monthly-performance-body">
                                                    <tr><td colspan="6" class="text-center">Loading monthly performance...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Distribution -->
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="dashboard-card h-100 p-3">
                                <h5 class="card-title mb-3">Customer Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="customerDistributionChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="metric-card primary">
                                                <div class="small text-muted">Total Customers</div>
                                                <div class="h4 mb-0" id="total-customers-count">0</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="metric-card success">
                                                <div class="small text-muted">Active (30D)</div>
                                                <div class="h4 mb-0" id="active-customers-count">0</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="metric-card warning">
                                                <div class="small text-muted">Avg. Order Value</div>
                                                <div class="h4 mb-0" id="avg-order-val">₹0.00</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="dashboard-card h-100 p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">Recent Customers</h5>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshCustomers()">
                                            <i class="mdi mdi-refresh"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary ms-1" onclick="loadAllCustomers()">
                                            View All
                                        </button>
                                    </div>
                                </div>
                                <div id="customers-list" style="max-height: 320px; overflow-y: auto;">
                                    <div class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <div class="mt-2 small text-muted">Loading customers...</div>
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted">
                                    <span class="customer-status-dot active"></span> Active 
                                    <span class="customer-status-dot inactive ms-3"></span> Inactive
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- container-fluid -->
            </div> <!-- page-content -->

            <?php include('includes/footer.php') ?>
        </div> <!-- main-content -->
    </div> <!-- layout-wrapper -->

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>

    <?php include('includes/scripts.php') ?>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <script>
        // Configuration
        const LINEMAN_ID = <?php echo intval($lineman_id); ?>;
        const POLL_INTERVAL = 10000; // 10 seconds
        let refreshInterval;
        let collectionChart, paymentChart, customerChart;

        // Initialize charts
        function initCharts() {
            // Collection Trend Chart
            const ctx1 = document.getElementById('collectionChart').getContext('2d');
            collectionChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Collections (₹)',
                        data: [],
                        borderColor: 'rgb(85, 110, 230)',
                        backgroundColor: 'rgba(85, 110, 230, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Customers Served',
                        data: [],
                        borderColor: 'rgb(40, 167, 69)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Collections (₹)' },
                            grid: { drawBorder: false }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            title: { display: true, text: 'Customers' },
                            grid: { drawOnChartArea: false }
                        },
                        x: {
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Collections')) {
                                        return `₹${context.parsed.y.toLocaleString('en-IN')}`;
                                    } else {
                                        return `${context.parsed.y} customers`;
                                    }
                                }
                            }
                        }
                    }
                }
            });

            // Payment Methods Chart
            const ctx2 = document.getElementById('paymentChart').getContext('2d');
            paymentChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            'rgb(85, 110, 230)',   // Primary
                            'rgb(40, 167, 69)',    // Success
                            'rgb(255, 193, 7)',    // Warning
                            'rgb(111, 66, 193)',   // Purple
                            'rgb(23, 162, 184)'    // Info
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ₹${context.parsed.toLocaleString('en-IN')}`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Customer Distribution Chart
            const ctx3 = document.getElementById('customerDistributionChart').getContext('2d');
            customerChart = new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: ['Retail', 'Wholesale', 'Hotel', 'Office', 'Residential', 'Other'],
                    datasets: [{
                        label: 'Customers',
                        data: [0, 0, 0, 0, 0, 0],
                        backgroundColor: 'rgba(23, 162, 184, 0.7)',
                        borderColor: 'rgb(23, 162, 184)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Fetch and update dashboard data
        async function fetchDashboardData() {
            try {
                const url = `?ajax=1&action=stats&lineman_id=${LINEMAN_ID}&_=${Date.now()}`;
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.status === 'success') {
                    updateDashboard(data.data);
                    updateLastUpdated();
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                document.getElementById('live-status').innerHTML = 
                    '<span class="text-danger">Connection Error</span>';
            }
        }

        // Load recent customers
        async function loadCustomers() {
            try {
                const url = `?ajax=1&action=customers&lineman_id=${LINEMAN_ID}&_=${Date.now()}`;
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.status === 'success') {
                    updateRecentCustomers(data.data);
                } else {
                    showNoCustomers();
                }
            } catch (error) {
                console.error('Error loading customers:', error);
                showNoCustomers();
            }
        }

        // Update recent customers list
        function updateRecentCustomers(customers) {
            const customersList = document.getElementById('customers-list');
            
            if (!customers || customers.length === 0) {
                showNoCustomers();
                return;
            }
            
            // Build HTML
            let html = '';
            customers.forEach(customer => {
                const initials = customer.customer_name ? 
                    customer.customer_name.charAt(0).toUpperCase() : 
                    customer.shop_name ? customer.shop_name.charAt(0).toUpperCase() : 'C';
                
                const daysSinceLastOrder = customer.last_order_date ? 
                    Math.floor((new Date() - new Date(customer.last_order_date)) / (1000 * 60 * 60 * 24)) : 999;
                
                const isActive = daysSinceLastOrder <= 30 || customer.today_orders > 0;
                const statusClass = isActive ? 'active' : 'inactive';
                const statusText = isActive ? 'Active' : 'Inactive';
                const lastOrderText = customer.last_order_date ? 
                    new Date(customer.last_order_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : 
                    'Never';
                
                const todayOrders = customer.today_orders || 0;
                const monthlySpent = parseFloat(customer.monthly_spent || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                
                html += `
                    <div class="customer-list-item">
                        <div class="d-flex align-items-center">
                            <div class="customer-initial me-3">
                                ${escapeHtml(initials)}
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold small">${escapeHtml(customer.shop_name || 'Unknown Shop')}</div>
                                        <div class="small text-muted">
                                            ${escapeHtml(customer.customer_name || '')} • 
                                            ${escapeHtml(customer.customer_contact || 'No contact')}
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="customer-status-dot ${statusClass}"></span>
                                        <span class="small text-muted">${statusText}</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div>
                                        ${todayOrders > 0 ? `
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                ${todayOrders} order${todayOrders !== 1 ? 's' : ''} today
                                            </span>
                                        ` : ''}
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Last: ${lastOrderText}</div>
                                        ${monthlySpent > 0 ? `
                                            <div class="small fw-bold">₹${monthlySpent}</div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            customersList.innerHTML = html;
        }

        function showNoCustomers() {
            const customersList = document.getElementById('customers-list');
            customersList.innerHTML = `
                <div class="text-center py-4">
                    <i class="mdi mdi-account-off-outline text-muted fs-1"></i>
                    <div class="mt-2 text-muted">No customers assigned</div>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="refreshCustomers()">
                        <i class="mdi mdi-refresh"></i> Try Again
                    </button>
                </div>
            `;
        }

        function refreshCustomers() {
            const customersList = document.getElementById('customers-list');
            customersList.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2 small text-muted">Refreshing...</div>
                </div>
            `;
            loadCustomers();
        }

        // Update all dashboard elements
        function updateDashboard(data) {
            // Update main statistics
            const today = data.today || {};
            const yesterday = data.yesterday || {};
            const orders = data.orders || {};
            const customers = data.customers || {};
            const metrics = data.metrics || {};

            // Today's collection with comparison
            const todayCollection = parseFloat(today.collected || 0);
            const yesterdayCollection = parseFloat(yesterday.collected_yesterday || 0);
            const collectionChange = yesterdayCollection > 0 ? 
                ((todayCollection - yesterdayCollection) / yesterdayCollection * 100).toFixed(1) : 0;
            
            document.getElementById('today-collection').textContent = 
                `₹${todayCollection.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
            document.getElementById('yesterday-collection').textContent = 
                `₹${yesterdayCollection.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
            document.getElementById('today-txn').textContent = 
                `${today.txn_count || 0} transactions`;
            document.getElementById('today-customers').textContent = 
                `${today.customers_served || 0} customers`;
            
            const changeElement = document.getElementById('collection-change');
            changeElement.textContent = `${collectionChange > 0 ? '+' : ''}${collectionChange}%`;
            changeElement.className = `stat-change ${collectionChange >= 0 ? 'positive' : 'negative'}`;

            // Today's orders
            const delivered = parseInt(orders.delivered || 0);
            const pending = parseInt(orders.pending || 0);
            const totalOrdersToday = delivered + pending;
            const deliveryRate = totalOrdersToday > 0 ? (delivered / totalOrdersToday * 100).toFixed(0) : 0;
            
            document.getElementById('today-orders').textContent = totalOrdersToday;
            document.getElementById('delivered-orders').textContent = `${delivered} delivered`;
            document.getElementById('pending-orders').textContent = `${pending} pending`;
            document.getElementById('delivery-progress').style.width = `${deliveryRate}%`;
            document.getElementById('delivery-rate').textContent = `${deliveryRate}%`;

            // Customer statistics
            const totalCustomers = parseInt(customers.total_customers || 0);
            const activeCustomers = parseInt(customers.active_30days || 0);
            const inactiveCustomers = parseInt(customers.inactive_customers || 0);
            const avgCustomerValue = parseFloat(customers.avg_customer_value || 0);
            
            document.getElementById('total-customers').textContent = totalCustomers;
            document.getElementById('active-customers').textContent = `${activeCustomers} active`;
            document.getElementById('inactive-customers').textContent = `${inactiveCustomers} inactive`;
            document.getElementById('avg-customer-value').textContent = 
                `₹${avgCustomerValue.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;

            // Performance metrics
            const efficiency = parseFloat(metrics.collection_efficiency || 0);
            const txnPerCustomer = parseFloat(metrics.txn_per_customer || 0);
            const performanceScore = Math.min(100, Math.round((efficiency + 100 - txnPerCustomer) / 2));
            
            document.getElementById('performance-score').textContent = `${performanceScore}%`;
            document.getElementById('efficiency-rate').textContent = `${efficiency.toFixed(1)}% efficiency`;
            document.getElementById('avg-txn').textContent = `${txnPerCustomer.toFixed(1)} txn/cust`;

            // Update charts
            updateCharts(data);

            // Update tables
            updateTables(data);

            // Update live status
            document.getElementById('live-status').innerHTML = 
                `Live • Updated: ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
                
            // Load customers after dashboard data
            loadCustomers();
        }

        // Update charts with new data
        function updateCharts(data) {
            // Daily performance chart
            const daily = data.daily || [];
            const labels = daily.map(d => d.day_name || '');
            const collections = daily.map(d => parseFloat(d.collections || 0));
            const customersServed = daily.map(d => parseInt(d.customers_served || 0));
            
            collectionChart.data.labels = labels;
            collectionChart.data.datasets[0].data = collections;
            collectionChart.data.datasets[1].data = customersServed;
            collectionChart.update();

            // Payment methods chart
            const paymentMethods = data.payment_methods || [];
            const paymentLabels = paymentMethods.map(p => p.payment_method || 'Unknown');
            const paymentData = paymentMethods.map(p => parseFloat(p.total || 0));
            
            paymentChart.data.labels = paymentLabels;
            paymentChart.data.datasets[0].data = paymentData;
            paymentChart.update();

            // Update payment summary
            const totalPayments = paymentData.reduce((a, b) => a + b, 0);
            document.getElementById('payment-summary').innerHTML = `
                Total: <strong>₹${totalPayments.toLocaleString('en-IN')}</strong> | 
                Methods: <strong>${paymentMethods.length}</strong> | 
                Most Used: <strong>${paymentLabels[0] || 'None'}</strong>
            `;
        }

        // Update data tables
        function updateTables(data) {
            // Recent activity
            const recentBody = document.getElementById('recent-activity-body');
            const recent = data.recent || [];
            
            if (recent.length === 0) {
                recentBody.innerHTML = '<tr><td colspan="6" class="text-center">No recent activity</td></tr>';
            } else {
                recentBody.innerHTML = recent.map(item => `
                    <tr>
                        <td>${new Date(item.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                        <td>
                            <span class="badge bg-${item.badge_class || 'secondary'} bg-opacity-10 text-${item.badge_class || 'secondary'}">
                                ${(item.type || '').toUpperCase()}
                            </span>
                        </td>
                        <td>${escapeHtml(item.shop_name || 'Unknown')}</td>
                        <td class="fw-bold ${item.type === 'refund' ? 'text-danger' : 'text-success'}">
                            ₹${parseFloat(item.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                        </td>
                        <td>${escapeHtml(item.payment_method || 'N/A')}</td>
                        <td>${getStatusBadge(item.type)}</td>
                    </tr>
                `).join('');
            }

            // Top customers
            const topCustomersBody = document.getElementById('top-customers-body');
            const topCustomers = data.top_customers || [];
            
            if (topCustomers.length === 0) {
                topCustomersBody.innerHTML = '<tr><td colspan="6" class="text-center">No customer data</td></tr>';
            } else {
                topCustomersBody.innerHTML = topCustomers.map(customer => `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="customer-avatar me-2">
                                    ${customer.customer_name ? customer.customer_name.charAt(0).toUpperCase() : 'C'}
                                </div>
                                <div>
                                    <div class="fw-bold">${escapeHtml(customer.shop_name || 'Unknown Shop')}</div>
                                    <div class="small text-muted">${escapeHtml(customer.customer_name || '')}</div>
                                </div>
                            </div>
                        </td>
                        <td>${parseInt(customer.order_count || 0)}</td>
                        <td class="fw-bold">
                            ₹${parseFloat(customer.total_spent || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                        </td>
                        <td>
                            ₹${parseFloat(customer.total_spent / (customer.order_count || 1)).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                        </td>
                        <td>${customer.last_order_date ? new Date(customer.last_order_date).toLocaleDateString('en-IN') : 'Never'}</td>
                        <td><span class="status-badge delivered">Active</span></td>
                    </tr>
                `).join('');
            }

            // Monthly performance
            const monthlyBody = document.getElementById('monthly-performance-body');
            const monthly = data.monthly || [];
            
            if (monthly.length === 0) {
                monthlyBody.innerHTML = '<tr><td colspan="6" class="text-center">No monthly data</td></tr>';
            } else {
                monthlyBody.innerHTML = monthly.map((month, index) => {
                    const prevMonth = index < monthly.length - 1 ? monthly[index + 1] : null;
                    const growth = prevMonth && prevMonth.collections > 0 ? 
                        ((month.collections - prevMonth.collections) / prevMonth.collections * 100).toFixed(1) : null;
                    
                    return `
                        <tr>
                            <td class="fw-bold">${month.month}</td>
                            <td class="text-primary">
                                ₹${parseFloat(month.collections || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                            </td>
                            <td>${parseInt(month.txn_count || 0)}</td>
                            <td>${parseInt(month.unique_customers || 0)}</td>
                            <td>
                                ₹${parseFloat(month.collections / (month.txn_count || 1)).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                            </td>
                            <td>
                                ${growth !== null ? `
                                    <span class="stat-change ${growth >= 0 ? 'positive' : 'negative'}">
                                        ${growth > 0 ? '+' : ''}${growth}%
                                    </span>
                                ` : '--'}
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Update customer metrics
            const customersData = data.customers || {};
            document.getElementById('total-customers-count').textContent = customersData.total_customers || 0;
            document.getElementById('active-customers-count').textContent = customersData.active_30days || 0;
            document.getElementById('avg-order-val').textContent = 
                `₹${parseFloat(data.orders?.avg_order_value || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
        }

        // Utility functions
        function getStatusBadge(type) {
            const badges = {
                'payment': '<span class="badge bg-success">Payment</span>',
                'refund': '<span class="badge bg-danger">Refund</span>',
                'purchase': '<span class="badge bg-info">Purchase</span>',
                'adjustment': '<span class="badge bg-warning">Adjustment</span>'
            };
            return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function updateLastUpdated() {
            document.getElementById('last-updated').textContent = 
                new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        }

        function setChartPeriod(period) {
            // Update chart based on selected period
            const buttons = document.querySelectorAll('.btn-group .btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // In a real implementation, you would fetch data for the selected period
            console.log(`Switching to ${period} view`);
        }

        function refreshDashboard() {
            fetchDashboardData();
            loadCustomers();
            showNotification('Dashboard refreshed successfully', 'success');
        }

        function exportData() {
            // Export functionality
            showNotification('Export feature coming soon!', 'info');
        }

        function printDashboard() {
            window.print();
        }

        function loadAllCustomers() {
            window.location.href = 'customers.php';
        }

        function showNotification(message, type = 'info') {
            // Simple notification
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 3000);
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            fetchDashboardData();
            
            // Set up auto-refresh
            refreshInterval = setInterval(() => {
                fetchDashboardData();
            }, POLL_INTERVAL);
            
            // Refresh when tab becomes visible
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    fetchDashboardData();
                }
            });
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(refreshInterval);
        });
    </script>

</body>
</html>
<?php
if (isset($conn)) mysqli_close($conn);
?>