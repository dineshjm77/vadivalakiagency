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
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all'; // all, paid, partial, pending

// Revenue Summary
$total_revenue = 0;
$total_collected = 0;
$total_pending = 0;
$total_cost = 0;
$total_profit = 0;
$total_orders = 0;

// Main Query: Orders + Items + Cost Calculation
$sql = "
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.total_amount,
        o.paid_amount,
        o.pending_amount,
        o.payment_status,
        o.status,
        o.customer_id,
        c.customer_name,
        c.shop_name,
        c.customer_contact,
        SUM(oi.quantity * oi.price) AS sales_amount,
        SUM(oi.quantity * p.stock_price) AS cost_amount
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.order_date BETWEEN '$start_date' AND '$end_date'
      AND o.status != 'cancelled'
";

if ($payment_status === 'paid') {
    $sql .= " AND o.payment_status = 'paid'";
} elseif ($payment_status === 'partial') {
    $sql .= " AND o.payment_status = 'partial'";
} elseif ($payment_status === 'pending') {
    $sql .= " AND o.payment_status = 'pending'";
}

$sql .= " GROUP BY o.id ORDER BY o.order_date DESC";

$result = mysqli_query($conn, $sql);
$orders = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;

        $total_revenue += $row['total_amount'];
        $total_collected += $row['paid_amount'];
        $total_pending += $row['pending_amount'];
        $total_cost += $row['cost_amount'] ?? 0;
        $total_profit += ($row['sales_amount'] ?? 0) - ($row['cost_amount'] ?? 0);
        $total_orders++;
    }
}

// Today's Revenue
$today_sql = "SELECT SUM(total_amount) AS today_revenue, SUM(paid_amount) AS today_collected 
              FROM orders WHERE DATE(order_date) = CURDATE() AND status != 'cancelled'";
$today_result = mysqli_fetch_assoc(mysqli_query($conn, $today_sql));

// This Month
$month_sql = "SELECT SUM(total_amount) AS month_revenue 
              FROM orders 
              WHERE MONTH(order_date) = MONTH(CURDATE()) 
                AND YEAR(order_date) = YEAR(CURDATE()) 
                AND status != 'cancelled'";
$month_result = mysqli_fetch_assoc(mysqli_query($conn, $month_sql));
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
                                            <i class="mdi mdi-cash-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Revenue</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($total_revenue); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $total_orders; ?> orders</p>
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
                                            <i class="mdi mdi-cash-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Collected</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($total_collected); ?></h4>
                                        <p class="text-muted mb-0">Cash received</p>
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
                                            <i class="mdi mdi-cash-clock"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Pending Collection</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($total_pending); ?></h4>
                                        <p class="text-muted mb-0">Outstanding</p>
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
                                            <i class="mdi mdi-chart-pie"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Estimated Profit</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($total_profit); ?></h4>
                                        <p class="text-muted mb-0">After cost of goods</p>
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
                                <h5 class="card-title mb-3">Filters & Options</h5>
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Payment Status</label>
                                        <select class="form-select" name="payment_status">
                                            <option value="all" <?php echo $payment_status == 'all' ? 'selected' : ''; ?>>All Orders</option>
                                            <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>Fully Paid</option>
                                            <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>Partially Paid</option>
                                            <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Pending Payment</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="mdi mdi-filter me-1"></i> Apply
                                        </button>
                                        <a href="revenue-reports.php" class="btn btn-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">Revenue Report</h4>
                                        <p class="card-title-desc">
                                            From <?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="text-muted me-3">Total Revenue:</span>
                                        <strong><?php echo formatCurrency($total_revenue); ?></strong>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="revenueTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Order No.</th>
                                                <th>Customer</th>
                                                <th>Date</th>
                                                <th>Revenue</th>
                                                <th>Collected</th>
                                                <th>Pending</th>
                                                <th>Est. Profit</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($orders)): ?>
                                                <?php $counter = 1; foreach ($orders as $order): ?>
                                                    <?php
                                                    $profit = ($order['sales_amount'] ?? 0) - ($order['cost_amount'] ?? 0);
                                                    $status_class = $order['status'] == 'delivered' ? 'badge-soft-success' : 'badge-soft-info';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-xs me-3">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <a href="customer-view.php?id=<?php echo $order['customer_id']; ?>" class="text-dark">
                                                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                                                    </a><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($order['shop_name']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo formatDate($order['order_date']); ?></td>
                                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                                        <td class="text-success"><?php echo formatCurrency($order['paid_amount']); ?></td>
                                                        <td class="text-danger"><?php echo formatCurrency($order['pending_amount']); ?></td>
                                                        <td class="text-info"><?php echo formatCurrency($profit); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($order['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><a class="dropdown-item" href="order-view.php?id=<?php echo $order['id']; ?>"><i class="mdi mdi-eye-outline me-1"></i> View</a></li>
                                                                    <li><a class="dropdown-item" href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank"><i class="mdi mdi-receipt me-1"></i> Invoice</a></li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-5">
                                                        <i class="mdi mdi-finance display-4 text-muted"></i>
                                                        <h5 class="mt-3">No Revenue Data</h5>
                                                        <p>No orders found in selected period</p>
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

                <!-- Profit Summary -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Profit Summary</h5>
                                <div class="row text-center">
                                    <div class="col-md-4">
                                        <h4><?php echo formatCurrency($total_revenue); ?></h4>
                                        <p class="text-muted">Total Sales Revenue</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h4><?php echo formatCurrency($total_cost); ?></h4>
                                        <p class="text-muted">Cost of Goods Sold</p>
                                    </div>
                                    <div class="col-md-4">
                                        <h4 class="text-success"><?php echo formatCurrency($total_profit); ?></h4>
                                        <p class="text-muted">Estimated Gross Profit</p>
                                    </div>
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