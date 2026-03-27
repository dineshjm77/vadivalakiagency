<?php
session_start();
include('config/config.php');

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d M, Y', strtotime($date));
}

// Sanitize inputs (basic)
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : date('Y-m-d');
$lineman_id = isset($_GET['lineman_id']) ? intval($_GET['lineman_id']) : 0;
$today = date('Y-m-d');

// Handle AJAX: order items
if (isset($_GET['action']) && $_GET['action'] === 'order_items' && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $qi = "SELECT oi.id, p.product_name, oi.quantity, oi.price, oi.total FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id";
    $res = mysqli_query($conn, $qi);
    $items = [];
    while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

// Fetch active linemen
$linemen = [];
$linemen_sql = "SELECT id, full_name, employee_id, phone FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_result = mysqli_query($conn, $linemen_sql);
while ($row = mysqli_fetch_assoc($linemen_result)) {
    $linemen[$row['id']] = $row['full_name'] . ' (' . $row['employee_id'] . ') - ' . $row['phone'];
}

// === Core aggregates for selected period ===
$total_deliveries = 0;
$total_revenue = 0.00;
$summary = [];
$perf_sql = "
    SELECT 
        l.id AS lineman_id,
        l.full_name,
        l.employee_id,
        l.phone,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(o.total_amount),0) AS total_revenue,
        COALESCE(AVG(o.total_amount),0) AS avg_order_value
    FROM linemen l
    LEFT JOIN customers c ON c.assigned_lineman_id = l.id
    LEFT JOIN orders o ON o.customer_id = c.id 
        AND o.status = 'delivered'
        AND o.order_date BETWEEN '$start_date' AND '$end_date'
    WHERE l.status = 'active'
";
if ($lineman_id > 0) $perf_sql .= " AND l.id = $lineman_id";
$perf_sql .= " GROUP BY l.id ORDER BY total_orders DESC";
$perf_result = mysqli_query($conn, $perf_sql);
while ($row = mysqli_fetch_assoc($perf_result)) {
    $summary[] = $row;
    $total_deliveries += intval($row['total_orders']);
    $total_revenue += floatval($row['total_revenue']);
}

// === Today's aggregates ===
$today_total_orders = 0;
$today_total_revenue = 0.00;
$today_summary = [];
$today_sql = "
    SELECT 
        l.id AS lineman_id,
        l.full_name,
        l.employee_id,
        l.phone,
        COUNT(o.id) AS total_orders,
        COALESCE(SUM(o.total_amount),0) AS total_revenue
    FROM linemen l
    LEFT JOIN customers c ON c.assigned_lineman_id = l.id
    LEFT JOIN orders o ON o.customer_id = c.id
        AND o.status = 'delivered'
        AND o.order_date = '$today'
    WHERE l.status = 'active'
";
if ($lineman_id > 0) $today_sql .= " AND l.id = $lineman_id";
$today_sql .= " GROUP BY l.id ORDER BY total_orders DESC";
$tres = mysqli_query($conn, $today_sql);
while ($row = mysqli_fetch_assoc($tres)) {
    $today_summary[] = $row;
    $today_total_orders += intval($row['total_orders']);
    $today_total_revenue += floatval($row['total_revenue']);
}

// === Time series: orders & revenue per day (for chart) ===
$timeseries = [];
$ts_sql = "SELECT o.order_date, COUNT(o.id) AS orders, COALESCE(SUM(o.total_amount),0) AS revenue
           FROM orders o
           WHERE o.status = 'delivered' AND o.order_date BETWEEN '$start_date' AND '$end_date'";
if ($lineman_id > 0) {
    $ts_sql .= " AND o.customer_id IN (SELECT id FROM customers WHERE assigned_lineman_id = $lineman_id)";
}
$ts_sql .= " GROUP BY o.order_date ORDER BY o.order_date";
$ts_res = mysqli_query($conn, $ts_sql);
while ($r = mysqli_fetch_assoc($ts_res)) {
    $timeseries[] = $r;
}

// === Hourly breakdown for today ===
$hours = array_fill(0,24, ['orders'=>0,'revenue'=>0]);
$hr_sql = "SELECT HOUR(o.created_at) AS hr, COUNT(o.id) AS orders, COALESCE(SUM(o.total_amount),0) AS revenue
           FROM orders o
           WHERE o.status = 'delivered' AND DATE(o.created_at) = '$today'
           GROUP BY hr ORDER BY hr";
$hr_res = mysqli_query($conn, $hr_sql);
while ($r = mysqli_fetch_assoc($hr_res)) {
    $h = intval($r['hr']);
    $hours[$h] = ['orders'=>intval($r['orders']), 'revenue'=>floatval($r['revenue'])];
}

// === Top linemen by revenue (period) ===
$top_linemen = [];
$tl_sql = "SELECT l.id, l.full_name, COALESCE(SUM(o.total_amount),0) AS revenue, COUNT(o.id) AS orders
           FROM linemen l
           LEFT JOIN customers c ON c.assigned_lineman_id = l.id
           LEFT JOIN orders o ON o.customer_id = c.id AND o.status='delivered' AND o.order_date BETWEEN '$start_date' AND '$end_date'
           WHERE l.status='active'
           GROUP BY l.id ORDER BY revenue DESC LIMIT 8";
$tl_res = mysqli_query($conn, $tl_sql);
while ($r = mysqli_fetch_assoc($tl_res)) $top_linemen[] = $r;

// === Payment status distribution ===
$payment_stats = [];
$ps_sql = "SELECT payment_status, COUNT(id) AS cnt FROM orders WHERE order_date BETWEEN '$start_date' AND '$end_date' GROUP BY payment_status";
$ps_res = mysqli_query($conn, $ps_sql);
while ($r = mysqli_fetch_assoc($ps_res)) $payment_stats[$r['payment_status']] = intval($r['cnt']);

// === Low stock products (threshold from settings) ===
$low_stock_threshold = 10;
$st_res = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key='low_stock_threshold' LIMIT 1");
if ($st_row = mysqli_fetch_assoc($st_res)) $low_stock_threshold = intval($st_row['setting_value']);
$low_stock = [];
$ls_sql = "SELECT id, product_name, quantity, customer_price FROM products WHERE quantity <= $low_stock_threshold ORDER BY quantity ASC";
$ls_res = mysqli_query($conn, $ls_sql);
while ($r = mysqli_fetch_assoc($ls_res)) $low_stock[] = $r;

// === Recent orders and today's orders list ===
$recent_orders = [];
$ro_sql = "SELECT o.id, o.order_number, o.order_date, o.total_amount, o.paid_amount, c.customer_name, c.shop_name, l.full_name AS lineman FROM orders o LEFT JOIN customers c ON o.customer_id=c.id LEFT JOIN linemen l ON c.assigned_lineman_id=l.id ORDER BY o.created_at DESC LIMIT 10";
$ro_res = mysqli_query($conn, $ro_sql);
while ($r = mysqli_fetch_assoc($ro_res)) $recent_orders[] = $r;

$today_orders_list = [];
$today_orders_sql = "SELECT o.id, o.order_number, o.order_date, o.total_amount, o.paid_amount, c.customer_name, c.shop_name, l.full_name AS lineman_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
    WHERE o.status = 'delivered' AND o.order_date = '$today'";
if ($lineman_id > 0) $today_orders_sql .= " AND c.assigned_lineman_id = $lineman_id";
$today_orders_sql .= " ORDER BY o.created_at DESC";
$to_res = mysqli_query($conn, $today_orders_sql);
while ($r = mysqli_fetch_assoc($to_res)) $today_orders_list[] = $r;

// === Customers with outstanding balances (top debtors) ===
$debtors = [];
$deb_sql = "SELECT id, shop_name, customer_name, customer_contact, current_balance FROM customers WHERE current_balance > 0 ORDER BY current_balance DESC LIMIT 10";
$deb_res = mysqli_query($conn, $deb_sql);
while ($r = mysqli_fetch_assoc($deb_res)) $debtors[] = $r;

// Prepare JSON-encoded data for JS charts
$ts_labels = [];$ts_orders = []; $ts_revenue = [];
foreach ($timeseries as $t) { $ts_labels[] = $t['order_date']; $ts_orders[] = intval($t['orders']); $ts_revenue[] = floatval($t['revenue']); }
$hour_labels = range(0,23); $hour_orders = []; $hour_revenue = [];
foreach ($hours as $h) { $hour_orders[] = intval($h['orders']); $hour_revenue[] = floatval($h['revenue']); }
$top_lin_names = []; $top_lin_revs = []; $top_lin_orders = [];
foreach ($top_linemen as $l) { $top_lin_names[] = $l['full_name']; $top_lin_revs[] = floatval($l['revenue']); $top_lin_orders[] = intval($l['orders']); }
$payment_labels = array_keys($payment_stats); $payment_values = array_values($payment_stats);

?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>
<body data-sidebar="dark">
<?php include('includes/pre-loader.php') ?>
<div id="layout-wrapper">
    <?php include('includes/topbar.php') ?>
    <div class="vertical-menu"><div data-simplebar class="h-100"><?php include('includes/sidebar.php')?></div></div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <!-- KPI Row -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Today's Deliveries</p>
                                <h3 class="mb-0"><?php echo intval($today_total_orders); ?></h3>
                                <p class="text-muted mb-0">Date: <?php echo date('d M, Y', strtotime($today)); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Today's Revenue</p>
                                <h3 class="mb-0"><?php echo formatCurrency($today_total_revenue); ?></h3>
                                <p class="text-muted mb-0">Delivered orders</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Period Deliveries</p>
                                <h3 class="mb-0"><?php echo intval($total_deliveries); ?></h3>
                                <p class="text-muted mb-0"><?php echo formatDate($start_date); ?> — <?php echo formatDate($end_date); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <p class="text-uppercase fw-medium text-muted mb-1">Period Revenue</p>
                                <h3 class="mb-0"><?php echo formatCurrency($total_revenue); ?></h3>
                                <p class="text-muted mb-0">Delivered orders</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Lineman</label>
                                        <select class="form-select" name="lineman_id">
                                            <option value="0">All Linemen</option>
                                            <?php foreach ($linemen as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo $lineman_id == $id ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-filter me-1"></i> Apply</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Row: Charts -->
                <div class="row mt-3">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Orders & Revenue (Trend)</h5>
                                <canvas id="trendChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Payment Status</h5>
                                <canvas id="paymentPie" height="220"></canvas>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-body">
                                <h6 class="mb-2">Top Linemen (by revenue)</h6>
                                <div id="topLinemenList">
                                    <?php if (!empty($top_linemen)): ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($top_linemen as $p): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo htmlspecialchars($p['full_name']); ?>
                                                    <span><?php echo formatCurrency($p['revenue']); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted">No lineman data</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Hourly heat / breakdown + low stock and debtors -->
                <div class="row mt-3">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Hourly Breakdown (Today)</h5>
                                <canvas id="hourChart" height="120"></canvas>
                                <small class="text-muted">Orders & revenue per hour for today</small>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Recent Orders</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead><tr><th>#</th><th>Order</th><th>Customer</th><th>Amount</th><th>View</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $ro): ?>
                                                <tr>
                                                    <td><?php echo $ro['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($ro['order_number']); ?><br><small class="text-muted"><?php echo formatDate($ro['order_date']); ?></small></td>
                                                    <td><?php echo htmlspecialchars($ro['customer_name']); ?></td>
                                                    <td><?php echo formatCurrency($ro['total_amount']); ?></td>
                                                    <td><button class="btn btn-sm btn-outline-primary" onclick="viewOrderItems(<?php echo $ro['id']; ?>)">Items</button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock Alerts (<= <?php echo $low_stock_threshold; ?>)</h5>
                                <?php if (!empty($low_stock)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($low_stock as $p): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($p['product_name']); ?>
                                                <span><?php echo intval($p['quantity']); ?> units</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">All products above threshold</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Top Debtors</h5>
                                <?php if (!empty($debtors)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($debtors as $d): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($d['shop_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($d['customer_name']); ?> - <?php echo htmlspecialchars($d['customer_contact']); ?></small>
                                                </div>
                                                <span><?php echo formatCurrency($d['current_balance']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No outstanding balances</p>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Today's Delivered Orders Table (drillable) -->
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Today's Delivered Orders (<?php echo date('d M, Y', strtotime($today)); ?>)</h5>
                                <div class="table-responsive">
                                    <table id="todayOrdersTable" class="table table-hover table-centered mb-0">
                                        <thead class="table-light">
                                            <tr><th>Order No</th><th>Date</th><th>Customer</th><th>Lineman</th><th>Amount</th><th>Paid</th><th>Action</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($today_orders_list as $ord): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($ord['order_number']); ?></td>
                                                    <td><?php echo formatDate($ord['order_date']); ?></td>
                                                    <td><?php echo htmlspecialchars($ord['customer_name']); ?> <br><small><?php echo htmlspecialchars($ord['shop_name']); ?></small></td>
                                                    <td><?php echo htmlspecialchars($ord['lineman_name'] ?? '-'); ?></td>
                                                    <td><?php echo formatCurrency($ord['total_amount']); ?></td>
                                                    <td><?php echo formatCurrency($ord['paid_amount']); ?></td>
                                                    <td><button class="btn btn-sm btn-primary" onclick="viewOrderItems(<?php echo $ord['id']; ?>)">View Items</button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php') ?>
    </div>
</div>

<?php include('includes/rightbar.php') ?>
<?php include('includes/scripts.php') ?>

<!-- External libs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Data for charts (from PHP)
const tsLabels = <?php echo json_encode($ts_labels); ?>;
const tsOrders = <?php echo json_encode($ts_orders); ?>;
const tsRevenue = <?php echo json_encode($ts_revenue); ?>;
const hourLabels = <?php echo json_encode($hour_labels); ?>;
const hourOrders = <?php echo json_encode($hour_orders); ?>;
const hourRevenue = <?php echo json_encode($hour_revenue); ?>;
const topLinNames = <?php echo json_encode($top_lin_names); ?>;
const topLinRevs = <?php echo json_encode($top_lin_revs); ?>;
const paymentLabels = <?php echo json_encode($payment_labels); ?>;
const paymentValues = <?php echo json_encode($payment_values); ?>;

// Trend chart
const ctxTrend = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(ctxTrend, {
    type: 'bar',
    data: {
        labels: tsLabels,
        datasets: [
            { label: 'Orders', data: tsOrders, type: 'line', tension: 0.3, yAxisID: 'y' },
            { label: 'Revenue', data: tsRevenue, type: 'bar', yAxisID: 'y1' }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            y: { type: 'linear', position: 'left', title: { display:true, text:'Orders' } },
            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, title: { display:true, text:'Revenue (₹)' } }
        }
    }
});

// Payment pie
const ctxPay = document.getElementById('paymentPie').getContext('2d');
const payChart = new Chart(ctxPay, { type: 'pie', data: { labels: paymentLabels, datasets: [{ data: paymentValues }] }, options: { responsive:true } });

// Hour chart
const ctxHour = document.getElementById('hourChart').getContext('2d');
const hourChart = new Chart(ctxHour, { type: 'bar', data: { labels: hourLabels, datasets: [{ label: 'Orders', data: hourOrders }, { label: 'Revenue', data: hourRevenue }] }, options: { responsive:true, scales: { y: { beginAtZero:true } } } });

// View Order Items (AJAX)
function viewOrderItems(orderId) {
    fetch('?action=order_items&order_id=' + orderId)
        .then(r => r.json())
        .then(items => {
            let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>';
            items.forEach(it => {
                html += `<tr><td>${escapeHtml(it.product_name)}</td><td>${it.quantity}</td><td>${it.price}</td><td>${it.total}</td></tr>`;
            });
            html += '</tbody></table></div>';
            showModal('Order Items', html);
        })
        .catch(err => { showModal('Error', '<p class="text-danger">Could not load items</p>'); });
}

function escapeHtml(text) { return (text || '').replace(/[&"'<>]/g, function(a){ return {'&':'&amp;','"':'&quot;',"'":"&#39;","<":"&lt;",">":"&gt;"}[a]; }); }

// Simple modal implementation
function showModal(title, bodyHtml) {
    let modal = document.createElement('div');
    modal.innerHTML = `
    <div class="modal fade show" tabindex="-1" style="display:block;background:rgba(0,0,0,0.4);">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">${title}</h5><button type="button" class="btn-close" aria-label="Close"></button></div>
          <div class="modal-body">${bodyHtml}</div>
          <div class="modal-footer"><button type="button" class="btn btn-secondary">Close</button></div>
        </div>
      </div>
    </div>`;
    document.body.appendChild(modal);
    modal.querySelectorAll('.btn-close, .btn-secondary').forEach(b=>b.addEventListener('click', ()=> modal.remove()));
}

// Auto-submit on date change
document.querySelectorAll('input[type="date"]').forEach(input => { input.addEventListener('change', () => input.closest('form').submit()); });

</script>

</body>
</html>

<?php mysqli_close($conn); ?>
