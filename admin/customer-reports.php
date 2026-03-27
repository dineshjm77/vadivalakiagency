<?php
session_start();
include('config/config.php');

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    return $date ? date('d M, Y', strtotime($date)) : 'Never';
}

// Filters
$customer_type = isset($_GET['customer_type']) ? $_GET['customer_type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Customer Summary Stats
$summary_sql = "
    SELECT 
        COUNT(*) AS total_customers,
        SUM(total_purchases) AS total_purchases_value,
        SUM(current_balance) AS total_pending_balance
    FROM customers
";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Count by status
$active_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM customers WHERE status = 'active'"))['count'];
$inactive_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS count FROM customers WHERE status IN ('inactive', 'blocked')"))['count'];

// Main Customers Query
$sql = "
    SELECT 
        c.id,
        c.customer_code,
        c.shop_name,
        c.customer_name,
        c.customer_contact,
        c.customer_type,
        c.payment_terms,
        c.total_purchases,
        c.current_balance,
        c.last_purchase_date,
        c.status,
        COUNT(o.id) AS total_orders
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id AND o.status != 'cancelled'
    WHERE 1=1
";

if ($customer_type !== 'all') {
    $sql .= " AND c.customer_type = '$customer_type'";
}
if ($status !== 'all') {
    if ($status === 'active') {
        $sql .= " AND c.status = 'active'";
    } else {
        $sql .= " AND c.status IN ('inactive', 'blocked')";
    }
}
if ($search) {
    $sql .= " AND (c.customer_name LIKE '%$search%' 
                  OR c.shop_name LIKE '%$search%' 
                  OR c.customer_contact LIKE '%$search%' 
                  OR c.customer_code LIKE '%$search%')";
}

$sql .= " GROUP BY c.id ORDER BY c.customer_name ASC";

$result = mysqli_query($conn, $sql);
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
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-account-group"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Customers</p>
                                        <h4 class="mb-0"><?php echo $summary['total_customers']; ?></h4>
                                        <p class="text-muted mb-0"><?php echo $active_count; ?> active • <?php echo $inactive_count; ?> inactive</p>
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
                                            <i class="mdi mdi-cash-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Purchases</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_purchases_value'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Lifetime sales from customers</p>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Pending Balance</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_pending_balance'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Outstanding payments</p>
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
                                            <i class="mdi mdi-chart-line"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Avg Purchase Value</p>
                                        <h4 class="mb-0">
                                            <?php 
                                            $avg = $summary['total_customers'] > 0 
                                                ? ($summary['total_purchases_value'] ?? 0) / $summary['total_customers'] 
                                                : 0;
                                            echo formatCurrency($avg);
                                            ?>
                                        </h4>
                                        <p class="text-muted mb-0">Per customer (lifetime)</p>
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
                                        <label class="form-label">Customer Type</label>
                                        <select class="form-select" name="customer_type">
                                            <option value="all">All Types</option>
                                            <option value="retail" <?php echo $customer_type == 'retail' ? 'selected' : ''; ?>>Retail</option>
                                            <option value="wholesale" <?php echo $customer_type == 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                            <option value="hotel" <?php echo $customer_type == 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                            <option value="office" <?php echo $customer_type == 'office' ? 'selected' : ''; ?>>Office</option>
                                            <option value="residential" <?php echo $customer_type == 'residential' ? 'selected' : ''; ?>>Residential</option>
                                            <option value="other" <?php echo $customer_type == 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="all">All Status</option>
                                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive / Blocked</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Shop, Phone, Code">
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

                <!-- Customers Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">Customer Report</h4>
                                        <p class="card-title-desc">Showing <?php echo mysqli_num_rows($result); ?> customers</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="text-muted me-3">Total Pending:</span>
                                        <strong class="text-danger"><?php echo formatCurrency($summary['total_pending_balance'] ?? 0); ?></strong>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Customer</th>
                                                <th>Contact</th>
                                                <th>Type</th>
                                                <th>Total Orders</th>
                                                <th>Total Spent</th>
                                                <th>Current Balance</th>
                                                <th>Last Order</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result) > 0): ?>
                                                <?php $counter = 1; while ($cust = mysqli_fetch_assoc($result)): ?>
                                                    <?php
                                                    $balance_class = $cust['current_balance'] > 0 ? 'text-danger' : 'text-success';
                                                    $status_class = $cust['status'] == 'active' ? 'badge-soft-success' : 'badge-soft-danger';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar-xs me-3">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($cust['customer_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                                <div>
                                                                    <a href="customer-view.php?id=<?php echo $cust['id']; ?>" class="text-dark fw-medium">
                                                                        <?php echo htmlspecialchars($cust['customer_name']); ?>
                                                                    </a><br>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($cust['shop_name']); ?></small><br>
                                                                    <small class="text-muted"><?php echo $cust['customer_code']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <i class="mdi mdi-phone me-1"></i><?php echo $cust['customer_contact']; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info-subtle text-info">
                                                                <?php echo ucfirst($cust['customer_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $cust['total_orders']; ?></td>
                                                        <td><?php echo formatCurrency($cust['total_purchases']); ?></td>
                                                        <td class="<?php echo $balance_class; ?>">
                                                            <strong><?php echo formatCurrency($cust['current_balance']); ?></strong>
                                                        </td>
                                                        <td><?php echo formatDate($cust['last_purchase_date']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($cust['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="customer-view.php?id=<?php echo $cust['id']; ?>" class="btn btn-sm btn-info">
                                                                <i class="mdi mdi-eye"></i> View
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-5">
                                                        <i class="mdi mdi-account-off display-4 text-muted"></i>
                                                        <h5 class="mt-3">No Customers Found</h5>
                                                        <p>No customers match the selected filters</p>
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

            </div>
        </div>
        <?php include('includes/footer.php')?>
    </div>
</div>

<?php include('includes/rightbar.php')?>
<?php include('includes/scripts.php')?>

<script>
// Auto-submit on filter change
document.querySelectorAll('select').forEach(select => {
    select.addEventListener('change', () => select.closest('form').submit());
});
</script>

</body>
</html>
<?php mysqli_close($conn); ?>