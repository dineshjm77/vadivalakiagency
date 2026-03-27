<?php
session_start();
include('config/config.php');

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d M, Y', strtotime($date));
}

// Filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$lineman_id = isset($_GET['lineman_id']) ? intval($_GET['lineman_id']) : 0;

// Fetch all linemen
$linemen = [];
$linemen_sql = "SELECT id, full_name, employee_id, phone FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_result = mysqli_query($conn, $linemen_sql);
while ($row = mysqli_fetch_assoc($linemen_result)) {
    $linemen[$row['id']] = $row['full_name'] . ' (' . $row['employee_id'] . ') - ' . $row['phone'];
}

// Performance Summary
$summary = [];
$total_deliveries = 0;
$total_orders = 0;
$total_revenue = 0;

$perf_sql = "
    SELECT 
        l.id AS lineman_id,
        l.full_name,
        l.employee_id,
        l.phone,
        COUNT(o.id) AS total_orders,
        SUM(o.total_amount) AS total_revenue,
        AVG(o.total_amount) AS avg_order_value
    FROM linemen l
    LEFT JOIN customers c ON c.assigned_lineman_id = l.id
    LEFT JOIN orders o ON o.customer_id = c.id 
        AND o.status = 'delivered'
        AND o.order_date BETWEEN '$start_date' AND '$end_date'
    WHERE l.status = 'active'
";

if ($lineman_id > 0) {
    $perf_sql .= " AND l.id = $lineman_id";
}

$perf_sql .= " GROUP BY l.id ORDER BY total_orders DESC";

$perf_result = mysqli_query($conn, $perf_sql);
while ($row = mysqli_fetch_assoc($perf_result)) {
    $summary[] = $row;
    $total_deliveries += $row['total_orders'];
    $total_revenue += $row['total_revenue'] ?? 0;
}

// Detailed Orders by Lineman (for selected lineman or all)
$orders_list = [];

if ($lineman_id > 0) {
    $detail_sql = "
        SELECT 
            o.order_number,
            o.order_date,
            o.total_amount,
            o.paid_amount,
            c.customer_name,
            c.shop_name,
            c.customer_contact
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE c.assigned_lineman_id = $lineman_id
          AND o.status = 'delivered'
          AND o.order_date BETWEEN '$start_date' AND '$end_date'
        ORDER BY o.order_date DESC
    ";
    $detail_result = mysqli_query($conn, $detail_sql);
    while ($row = mysqli_fetch_assoc($detail_result)) {
        $orders_list[] = $row;
    }
}
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

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle text-success rounded-2 fs-2">
                                            <i class="mdi mdi-truck-delivery"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Deliveries</p>
                                        <h4 class="mb-0"><?php echo $total_deliveries; ?></h4>
                                        <p class="text-muted mb-0"><?php echo count($summary); ?> linemen active</p>
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
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-cash-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Revenue Generated</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($total_revenue); ?></h4>
                                        <p class="text-muted mb-0">From delivered orders</p>
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
                                            <i class="mdi mdi-account-hard-hat"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Active Linemen</p>
                                        <h4 class="mb-0"><?php echo count($linemen); ?></h4>
                                        <p class="text-muted mb-0">Assigned to customers</p>
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
                                            <i class="mdi mdi-chart-bar"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Avg Orders/Lineman</p>
                                        <h4 class="mb-0">
                                            <?php echo count($summary) > 0 ? round($total_deliveries / count($summary), 1) : 0; ?>
                                        </h4>
                                        <p class="text-muted mb-0">In selected period</p>
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
                                <h5 class="card-title mb-3">Filters</h5>
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Lineman</label>
                                        <select class="form-select" name="lineman_id">
                                            <option value="0">All Linemen</option>
                                            <?php foreach ($linemen as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo $lineman_id == $id ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="mdi mdi-filter me-1"></i> Apply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lineman Performance Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title mb-3">Lineman Performance Report</h4>
                                <p class="card-title-desc">
                                    Performance from <?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?>
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Lineman</th>
                                                <th>Employee ID</th>
                                                <th>Contact</th>
                                                <th>Orders Delivered</th>
                                                <th>Total Revenue</th>
                                                <th>Avg Order Value</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($summary)): ?>
                                                <?php $counter = 1; foreach ($summary as $lm): ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-xs me-3">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($lm['full_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($lm['full_name']); ?></h5>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($lm['employee_id']); ?></td>
                                                        <td><?php echo $lm['phone']; ?></td>
                                                        <td>
                                                            <span class="badge bg-success-subtle text-success fs-6">
                                                                <?php echo $lm['total_orders']; ?> orders
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatCurrency($lm['total_revenue'] ?? 0); ?></td>
                                                        <td><?php echo formatCurrency($lm['avg_order_value'] ?? 0); ?></td>
                                                        <td>
                                                            <?php if ($lineman_id == 0): ?>
                                                                <a href="?lineman_id=<?php echo $lm['lineman_id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
                                                                   class="btn btn-sm btn-info">
                                                                    <i class="mdi mdi-eye"></i> View Details
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-5">
                                                        <i class="mdi mdi-account-hard-hat display-4 text-muted"></i>
                                                        <h5 class="mt-3">No Data Available</h5>
                                                        <p>No deliveries recorded in selected period</p>
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

                <!-- Detailed Orders (Only when a specific lineman is selected) -->
                <?php if ($lineman_id > 0 && !empty($orders_list)): ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Delivered Orders by Selected Lineman</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order No.</th>
                                                <th>Date</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders_list as $ord): ?>
                                                <tr>
                                                    <td><strong><?php echo $ord['order_number']; ?></strong></td>
                                                    <td><?php echo formatDate($ord['order_date']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($ord['customer_name']); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($ord['shop_name']); ?></small>
                                                    </td>
                                                    <td><?php echo formatCurrency($ord['total_amount']); ?></td>
                                                    <td class="text-success"><?php echo formatCurrency($ord['paid_amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include('includes/footer.php')?>
    </div>
</div>

<?php include('includes/rightbar.php')?>
<?php include('includes/scripts.php')?>

<script>
// Auto-submit on date change
document.querySelectorAll('input[type="date"]').forEach(input => {
    input.addEventListener('change', () => input.closest('form').submit());
});
</script>

</body>
</html>
<?php mysqli_close($conn); ?>