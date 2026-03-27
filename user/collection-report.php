<?php
// collection-report.php
// Complete file: Collections / Payments received report
// Place this file in the same project where includes (config, auth-check, head, etc.) exist.

include('config/config.php');
include('includes/auth-check.php');

// Authorization: only admin, super_admin, lineman can view
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin', 'lineman'])) {
    header('Location: index.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Default filters (today)
$start_date  = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date    = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d');
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$lineman_id  = isset($_GET['lineman_id'])  ? intval($_GET['lineman_id'])  : 0;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'collections';

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-d');
    $end_date   = date('Y-m-d');
}
if (strtotime($end_date) < strtotime($start_date)) {
    $end_date = $start_date;
}

// Limit to 1 year for performance
$date_diff = (strtotime($end_date) - strtotime($start_date)) / (60*60*24);
if ($date_diff > 365) {
    $end_date = date('Y-m-d', strtotime($start_date . ' + 365 days'));
}

// Safe escaped values
$start_date_esc = mysqli_real_escape_string($conn, $start_date);
$end_date_esc   = mysqli_real_escape_string($conn, $end_date);

// --- SUMMARY: total collections, refunds, adjustments, outstanding (pending orders) ---
$summary_sql = "SELECT 
    IFNULL(SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END),0) AS total_collections,
    IFNULL(SUM(CASE WHEN t.type = 'refund' THEN t.amount ELSE 0 END),0) AS total_refunds,
    IFNULL(SUM(CASE WHEN t.type = 'adjustment' THEN t.amount ELSE 0 END),0) AS total_adjustments
FROM transactions t
LEFT JOIN orders o ON t.order_id = o.id
LEFT JOIN customers c ON o.customer_id = c.id
WHERE DATE(t.created_at) BETWEEN '$start_date_esc' AND '$end_date_esc'";

if ($customer_id > 0) {
    $summary_sql .= " AND (o.customer_id = " . intval($customer_id) . " OR t.customer_id = " . intval($customer_id) . ")";
}
if ($lineman_id > 0) {
    // filter by customers assigned to lineman
    $summary_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);
}

$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result) ?: ['total_collections' => 0, 'total_refunds' => 0, 'total_adjustments' => 0];

// Outstanding (pending amounts from orders)
$pending_sql = "SELECT IFNULL(SUM(o.pending_amount),0) AS total_pending
FROM orders o
JOIN customers c ON o.customer_id = c.id
WHERE DATE(o.order_date) BETWEEN '$start_date_esc' AND '$end_date_esc'";
if ($customer_id > 0) $pending_sql .= " AND o.customer_id = " . intval($customer_id);
if ($lineman_id > 0) $pending_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);
$pending_result = mysqli_query($conn, $pending_sql);
$pending_row = mysqli_fetch_assoc($pending_result) ?: ['total_pending' => 0];

// --- DAILY COLLECTIONS (group by date) ---
$daily_sql = "SELECT 
    DATE(t.created_at) AS day,
    COUNT(t.id) AS txn_count,
    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS collections,
    SUM(CASE WHEN t.type = 'refund' THEN t.amount ELSE 0 END) AS refunds,
    SUM(CASE WHEN t.type = 'adjustment' THEN t.amount ELSE 0 END) AS adjustments
FROM transactions t
LEFT JOIN orders o ON t.order_id = o.id
LEFT JOIN customers c ON o.customer_id = c.id
WHERE DATE(t.created_at) BETWEEN '$start_date_esc' AND '$end_date_esc'";

if ($customer_id > 0) $daily_sql .= " AND (o.customer_id = " . intval($customer_id) . " OR t.customer_id = " . intval($customer_id) . ")";
if ($lineman_id > 0) $daily_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);

$daily_sql .= " GROUP BY DATE(t.created_at) ORDER BY day DESC";
$daily_result = mysqli_query($conn, $daily_sql);

// --- PAYMENT METHOD SUMMARY ---
$method_sql = "SELECT 
    IFNULL(t.payment_method, 'Unknown') AS payment_method,
    COUNT(t.id) AS txn_count,
    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS total_collections
FROM transactions t
LEFT JOIN orders o ON t.order_id = o.id
LEFT JOIN customers c ON o.customer_id = c.id
WHERE DATE(t.created_at) BETWEEN '$start_date_esc' AND '$end_date_esc'";

if ($customer_id > 0) $method_sql .= " AND (o.customer_id = " . intval($customer_id) . " OR t.customer_id = " . intval($customer_id) . ")";
if ($lineman_id > 0) $method_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);

$method_sql .= " GROUP BY t.payment_method ORDER BY total_collections DESC";
$method_result = mysqli_query($conn, $method_sql);

// --- TOP PAYING CUSTOMERS (by transactions linked to orders) ---
$top_customers_sql = "SELECT 
    c.id,
    c.shop_name,
    c.customer_name,
    c.customer_contact,
    COUNT(DISTINCT t.id) AS txn_count,
    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS total_paid,
    MAX(t.created_at) AS last_payment_date
FROM transactions t
JOIN orders o ON t.order_id = o.id
JOIN customers c ON o.customer_id = c.id
WHERE DATE(t.created_at) BETWEEN '$start_date_esc' AND '$end_date_esc'";

if ($customer_id > 0) $top_customers_sql .= " AND c.id = " . intval($customer_id);
if ($lineman_id > 0) $top_customers_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);

$top_customers_sql .= " GROUP BY c.id ORDER BY total_paid DESC LIMIT 10";
$top_customers_result = mysqli_query($conn, $top_customers_sql);

// --- LATE/UNPAID ORDERS (outstanding) ---
$outstanding_sql = "SELECT 
    o.id,
    o.order_number,
    o.order_date,
    o.total_amount,
    o.paid_amount,
    o.pending_amount,
    c.shop_name,
    c.customer_contact,
    c.assigned_lineman_id
FROM orders o
JOIN customers c ON o.customer_id = c.id
WHERE o.pending_amount > 0 AND DATE(o.order_date) BETWEEN '$start_date_esc' AND '$end_date_esc'";

if ($customer_id > 0) $outstanding_sql .= " AND o.customer_id = " . intval($customer_id);
if ($lineman_id > 0) $outstanding_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);

$outstanding_sql .= " ORDER BY o.pending_amount DESC LIMIT 100";
$outstanding_result = mysqli_query($conn, $outstanding_sql);

// --- HOURLY COLLECTIONS (by transaction created_at hour) ---
$hourly_sql = "SELECT 
    HOUR(t.created_at) AS hour_of_day,
    COUNT(t.id) AS txn_count,
    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS collections
FROM transactions t
LEFT JOIN orders o ON t.order_id = o.id
LEFT JOIN customers c ON o.customer_id = c.id
WHERE DATE(t.created_at) BETWEEN '$start_date_esc' AND '$end_date_esc'";

if ($customer_id > 0) $hourly_sql .= " AND (o.customer_id = " . intval($customer_id) . " OR t.customer_id = " . intval($customer_id) . ")";
if ($lineman_id > 0) $hourly_sql .= " AND c.assigned_lineman_id = " . intval($lineman_id);

$hourly_sql .= " GROUP BY HOUR(t.created_at) ORDER BY hour_of_day";
$hourly_result = mysqli_query($conn, $hourly_sql);

// Fetch filter dropdown data
$customers_sql = "SELECT id, shop_name, customer_name FROM customers WHERE status = 'active' ORDER BY shop_name";
$customers_result = mysqli_query($conn, $customers_sql);

$linemen_sql = "SELECT id, full_name FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_result = mysqli_query($conn, $linemen_sql);

// Prepare safe display values
$summary_total_collections = floatval($summary['total_collections']);
$summary_total_refunds     = floatval($summary['total_refunds']);
$summary_total_adjustments = floatval($summary['total_adjustments']);
$summary_total_pending     = floatval($pending_row['total_pending']);
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>
<style>
    :root {
        --primary: #2b6cb0;
        --success: #198754;
        --danger: #b02a37;
        --muted: #495057;
    }
    .card { border:1px solid #e9ecef; background:#fff; box-shadow:0 6px 18px rgba(0,0,0,0.03); }
    .summary-card { padding:14px; border-radius:8px; text-align:center; border:1px solid #e9ecef; background:#fff; }
    .summary-card h6 { margin:0; color:#6c757d; font-weight:600; }
    .summary-card h3 { margin-top:8px; font-size:20px; color:var(--muted); font-weight:700; }
    .text-muted { color:#6c757d !important; }
    .table thead th { background:#f8f9fa; }
    @media print { .no-print { display:none; } }
</style>

<body data-sidebar="dark">
    <?php include('includes/pre-loader.php') ?>

    <div id="layout-wrapper">
        <?php include('includes/topbar.php') ?>

        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <?php $current_page = 'collection-report'; include('includes/sidebar.php'); ?>
            </div>
        </div>

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Header -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h4 class="card-title mb-0">Collection Report</h4>
                            <p class="card-title-desc">Payments, refunds and adjustments received — detailed & summarized.</p>
                        </div>
                        <div class="col-md-6 text-end no-print">
                            <div class="d-inline-flex gap-2">
                                <button class="btn btn-primary" onclick="printReport()"><i class="mdi mdi-printer me-1"></i> Print</button>
                                <button class="btn btn-success" onclick="exportReport()"><i class="mdi mdi-download me-1"></i> Export</button>
                                <?php if (in_array($user_role, ['admin','super_admin'])): ?>
                                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#emailReportModal"><i class="mdi mdi-email me-1"></i> Email</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Customer</label>
                                            <select name="customer_id" class="form-select">
                                                <option value="0">All Customers</option>
                                                <?php mysqli_data_seek($customers_result, 0); while ($c = mysqli_fetch_assoc($customers_result)): ?>
                                                    <option value="<?php echo $c['id']; ?>" <?php echo $customer_id == $c['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['shop_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Lineman</label>
                                            <?php mysqli_data_seek($linemen_result, 0); ?>
                                            <select name="lineman_id" class="form-select">
                                                <option value="0">All Linemen</option>
                                                <?php while ($l = mysqli_fetch_assoc($linemen_result)): ?>
                                                    <option value="<?php echo $l['id']; ?>" <?php echo $lineman_id == $l['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($l['full_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter me-1"></i> Apply</button>
                                                <a href="collection-report.php" class="btn btn-outline-secondary"><i class="mdi mdi-refresh me-1"></i> Reset</a>
                                                <div class="btn-group ms-auto" role="group">
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('today')">Today</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('yesterday')">Yesterday</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('week')">This Week</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('month')">This Month</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('year')">This Year</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>

                                    <?php if ($customer_id>0 || $lineman_id>0 || $start_date != date('Y-m-d') || $end_date != date('Y-m-d')): ?>
                                    <div class="mt-3">
                                        <div class="alert alert-info">
                                            <i class="mdi mdi-information-outline me-2"></i>
                                            <strong>Report:</strong> <?php echo date('d M, Y', strtotime($start_date)); ?> — <?php echo date('d M, Y', strtotime($end_date)); ?>
                                            <?php if ($customer_id>0): ?>
                                                | <strong>Customer:</strong> <?php 
                                                    $q = mysqli_query($conn, "SELECT shop_name FROM customers WHERE id=".intval($customer_id)." LIMIT 1");
                                                    if ($q && $r = mysqli_fetch_assoc($q)) echo htmlspecialchars($r['shop_name']);
                                                ?>
                                            <?php endif; ?>
                                            <?php if ($lineman_id>0): ?>
                                                | <strong>Lineman:</strong> <?php 
                                                    $q = mysqli_query($conn, "SELECT full_name FROM linemen WHERE id=".intval($lineman_id)." LIMIT 1");
                                                    if ($q && $r = mysqli_fetch_assoc($q)) echo htmlspecialchars($r['full_name']);
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Total Collections</h6>
                                <h3>₹<?php echo number_format($summary_total_collections, 2); ?></h3>
                                <small class="text-muted">Payments received</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Total Refunds</h6>
                                <h3>₹<?php echo number_format($summary_total_refunds, 2); ?></h3>
                                <small class="text-muted">Amount refunded</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Adjustments</h6>
                                <h3>₹<?php echo number_format($summary_total_adjustments, 2); ?></h3>
                                <small class="text-muted">Manual adjustments</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Outstanding (Pending)</h6>
                                <h3>₹<?php echo number_format($summary_total_pending, 2); ?></h3>
                                <small class="text-muted">Orders unpaid</small>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Collections Table -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Daily Collections</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th class="text-center">Transactions</th>
                                                    <th class="text-end">Collections</th>
                                                    <th class="text-end">Refunds</th>
                                                    <th class="text-end">Adjustments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($daily_result && mysqli_num_rows($daily_result) > 0):
                                                    mysqli_data_seek($daily_result, 0);
                                                    while ($d = mysqli_fetch_assoc($daily_result)):
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo date('d M, Y', strtotime($d['day'])); ?></strong></td>
                                                    <td class="text-center"><?php echo intval($d['txn_count']); ?></td>
                                                    <td class="text-end text-success">₹<?php echo number_format($d['collections'], 2); ?></td>
                                                    <td class="text-end text-danger">₹<?php echo number_format($d['refunds'], 2); ?></td>
                                                    <td class="text-end">₹<?php echo number_format($d['adjustments'], 2); ?></td>
                                                </tr>
                                                <?php 
                                                    endwhile;
                                                else:
                                                ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-cash-multiple display-4"></i>
                                                            <h5 class="mt-2">No collection transactions found</h5>
                                                            <p class="mb-0">Adjust the date range or filters to view data.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th>Total</th>
                                                    <th class="text-center">-</th>
                                                    <th class="text-end">₹<?php echo number_format($summary_total_collections, 2); ?></th>
                                                    <th class="text-end">₹<?php echo number_format($summary_total_refunds, 2); ?></th>
                                                    <th class="text-end">₹<?php echo number_format($summary_total_adjustments, 2); ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method & Top Customers -->
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Payment Method Summary</h5>
                                    <?php if ($method_result && mysqli_num_rows($method_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Method</th>
                                                    <th class="text-center">Txns</th>
                                                    <th class="text-end">Collections</th>
                                                    <th class="text-center">Share</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $total_method = 0;
                                                mysqli_data_seek($method_result, 0);
                                                while ($m = mysqli_fetch_assoc($method_result)) $total_method += floatval($m['total_collections']);
                                                mysqli_data_seek($method_result, 0);
                                                while ($m = mysqli_fetch_assoc($method_result)):
                                                    $share = $total_method > 0 ? ($m['total_collections'] / $total_method) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($m['payment_method']); ?></td>
                                                    <td class="text-center"><?php echo intval($m['txn_count']); ?></td>
                                                    <td class="text-end">₹<?php echo number_format($m['total_collections'], 2); ?></td>
                                                    <td class="text-center"><?php echo number_format($share, 1); ?>%</td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Total</th>
                                                    <th class="text-center">-</th>
                                                    <th class="text-end">₹<?php echo number_format($total_method, 2); ?></th>
                                                    <th class="text-center">100%</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                        <div class="text-center py-3 text-muted">
                                            <i class="mdi mdi-credit-card-scan display-4"></i>
                                            <p class="mt-2">No payment method data</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Top Customers -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Top Paying Customers</h5>
                                    <?php if ($top_customers_result && mysqli_num_rows($top_customers_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th class="text-center">Txns</th>
                                                    <th class="text-end">Total Paid</th>
                                                    <th class="text-center">Last Payment</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php mysqli_data_seek($top_customers_result, 0); while ($tc = mysqli_fetch_assoc($top_customers_result)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($tc['shop_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($tc['customer_name']); ?></small></td>
                                                    <td class="text-center"><?php echo intval($tc['txn_count']); ?></td>
                                                    <td class="text-end">₹<?php echo number_format($tc['total_paid'], 2); ?></td>
                                                    <td class="text-center"><small><?php echo date('d M, Y', strtotime($tc['last_payment_date'])); ?></small></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                        <div class="text-center py-3 text-muted">
                                            <i class="mdi mdi-account-clock display-4"></i>
                                            <p class="mt-2">No customer payments found</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outstanding Orders -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Outstanding / Pending Orders</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Order Date</th>
                                                    <th>Customer</th>
                                                    <th class="text-end">Total</th>
                                                    <th class="text-end">Paid</th>
                                                    <th class="text-end">Pending</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($outstanding_result && mysqli_num_rows($outstanding_result) > 0): mysqli_data_seek($outstanding_result, 0);
                                                    while ($o = mysqli_fetch_assoc($outstanding_result)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($o['order_number']); ?></td>
                                                        <td><?php echo date('d M, Y', strtotime($o['order_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($o['shop_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($o['customer_contact']); ?></small></td>
                                                        <td class="text-end">₹<?php echo number_format($o['total_amount'], 2); ?></td>
                                                        <td class="text-end">₹<?php echo number_format($o['paid_amount'], 2); ?></td>
                                                        <td class="text-end text-danger">₹<?php echo number_format($o['pending_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endwhile; else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4 text-muted">
                                                            <i class="mdi mdi-clipboard-check display-4"></i>
                                                            <p class="mt-2">No outstanding orders in selected period</p>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hourly Collections (compact) -->
                    <?php if ($hourly_result && mysqli_num_rows($hourly_result) > 0): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Hourly Collections</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Hour</th>
                                                    <th class="text-center">Txns</th>
                                                    <th class="text-end">Collections</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $hour_map = [];
                                                mysqli_data_seek($hourly_result, 0);
                                                while ($h = mysqli_fetch_assoc($hourly_result)) $hour_map[intval($h['hour_of_day'])] = $h;
                                                for ($hr=0;$hr<24;$hr++):
                                                    $row = $hour_map[$hr] ?? ['txn_count'=>0,'collections'=>0];
                                                ?>
                                                <tr>
                                                    <td><?php echo sprintf('%02d:00 - %02d:00',$hr,$hr+1); ?></td>
                                                    <td class="text-center"><?php echo intval($row['txn_count']); ?></td>
                                                    <td class="text-end">₹<?php echo number_format($row['collections'], 2); ?></td>
                                                </tr>
                                                <?php endfor; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div> <!-- container -->
            </div> <!-- page-content -->

            <?php include('includes/footer.php') ?>
        </div>
    </div>

    <!-- Email Modal -->
    <?php if (in_array($user_role, ['admin','super_admin'])): ?>
    <div class="modal fade" id="emailReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="send-report-email.php">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <input type="hidden" name="customer_id" value="<?php echo intval($customer_id); ?>">
                    <input type="hidden" name="lineman_id" value="<?php echo intval($lineman_id); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Email Collection Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">To *</label>
                            <input type="email" name="email_to" class="form-control" required placeholder="recipient@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="email_subject" class="form-control" value="Collection Report: <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="email_message" class="form-control" rows="4">Please find attached the collection report for the period <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?>.

Key figures:
- Total Collections: ₹<?php echo number_format($summary_total_collections,2); ?>
- Total Refunds: ₹<?php echo number_format($summary_total_refunds,2); ?>
- Outstanding: ₹<?php echo number_format($summary_total_pending,2); ?>

Regards,
<?php echo $_SESSION['name']; ?></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="include_attachments" checked id="include_attachments">
                            <label class="form-check-label" for="include_attachments">Include PDF / Excel attachments</label>
                        </div>
                        <div class="alert alert-info small">
                            The report will be sent with attachments if checked.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include('includes/rightbar.php') ?>
    <?php include('includes/scripts.php') ?>

    <script>
        function setDateRange(range) {
            const today = new Date();
            let start = new Date(), end = new Date();
            switch(range) {
                case 'today': start = end = today; break;
                case 'yesterday': start = end = new Date(today.setDate(today.getDate()-1)); break;
                case 'week': start = new Date(); start.setDate(start.getDate() - start.getDay()); end = new Date(); break;
                case 'month': start = new Date(today.getFullYear(), today.getMonth(), 1); end = new Date(); break;
                case 'year': start = new Date(today.getFullYear(), 0, 1); end = new Date(); break;
            }
            document.getElementById('start_date').value = start.toISOString().split('T')[0];
            document.getElementById('end_date').value = end.toISOString().split('T')[0];
        }

        function printReport() {
            const win = window.open('', '_blank');
            const now = new Date();
            const gen = now.toLocaleString('en-IN', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
            win.document.write('<html><head><title>Collection Report</title><style>body{font-family:Arial;color:#222;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px}th{background:#f8f9fa}</style></head><body>');
            win.document.write('<h2>Collection Report</h2>');
            win.document.write('<p><strong>Period:</strong> <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?> | <strong>Generated:</strong> '+gen+'</p>');
            // Copy the daily table HTML
            const tbl = document.querySelector('.table');
            if (tbl) {
                win.document.write(tbl.outerHTML);
            } else {
                win.document.write('<p>No data to print</p>');
            }
            win.document.write('</body></html>');
            win.document.close();
            setTimeout(()=>win.print(), 300);
        }

        function exportReport() {
            const params = new URLSearchParams({
                start_date: '<?php echo $start_date; ?>',
                end_date: '<?php echo $end_date; ?>',
                customer_id: '<?php echo $customer_id; ?>',
                lineman_id: '<?php echo $lineman_id; ?>',
                export: '1',
                type: 'collections'
            });
            window.location.href = 'export-report.php?' + params.toString();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const s = document.getElementById('start_date'), e = document.getElementById('end_date');
            if (s) s.max = today;
            if (e) e.max = today;
            if (s && e) {
                s.addEventListener('change', () => { e.min = s.value; if (e.value < s.value) e.value = s.value; });
                e.addEventListener('change', () => { if (e.value < s.value) e.value = s.value; });
            }
        });
    </script>

</body>
</html>

<?php
// Close connection
if (isset($conn)) mysqli_close($conn);
?>
