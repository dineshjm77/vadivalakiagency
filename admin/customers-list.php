<?php
session_start();
include('config/config.php');

if (!function_exists('esc_customer_page')) {
    function esc_customer_page($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function customerColumnExists($conn, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = mysqli_query($conn, $sql);
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    if ($res) {
        mysqli_free_result($res);
    }
    return $cache[$key];
}

function formatMoneyCustomer($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function appendFilterParams($extra = []) {
    $params = $_GET;
    foreach ($extra as $k => $v) {
        $params[$k] = $v;
    }
    return http_build_query($params);
}

$hasGstColumn = customerColumnExists($conn, 'customers', 'gst_number');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$beat = isset($_GET['beat']) ? trim($_GET['beat']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$printMode = isset($_GET['print']) && $_GET['print'] == '1';

$where = [];

if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $searchParts = [
        "c.customer_code LIKE '%$searchEsc%'",
        "c.shop_name LIKE '%$searchEsc%'",
        "c.customer_name LIKE '%$searchEsc%'",
        "c.customer_contact LIKE '%$searchEsc%'",
        "c.shop_location LIKE '%$searchEsc%'",
        "c.assigned_area LIKE '%$searchEsc%'"
    ];
    if ($hasGstColumn) {
        $searchParts[] = "c.gst_number LIKE '%$searchEsc%'";
    }
    $where[] = '(' . implode(' OR ', $searchParts) . ')';
}

if ($beat !== '') {
    $beatEsc = mysqli_real_escape_string($conn, $beat);
    $where[] = "c.assigned_area = '$beatEsc'";
}

if ($status !== '') {
    $statusEsc = mysqli_real_escape_string($conn, $status);
    $where[] = "c.status = '$statusEsc'";
}

if ($type !== '') {
    $typeEsc = mysqli_real_escape_string($conn, $type);
    $where[] = "c.customer_type = '$typeEsc'";
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = ' WHERE ' . implode(' AND ', $where);
}

$gstSelect = $hasGstColumn ? 'c.gst_number' : "'' AS gst_number";

$statsSql = "
    SELECT 
        COUNT(*) AS total_customers,
        SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_customers,
        SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_customers,
        SUM(CASE WHEN c.status = 'blocked' THEN 1 ELSE 0 END) AS blocked_customers,
        COALESCE(SUM(c.current_balance), 0) AS total_balance
    FROM customers c
    $whereSql
";
$statsResult = mysqli_query($conn, $statsSql);
$stats = $statsResult ? mysqli_fetch_assoc($statsResult) : [
    'total_customers' => 0,
    'active_customers' => 0,
    'inactive_customers' => 0,
    'blocked_customers' => 0,
    'total_balance' => 0,
];

$beats = [];
$beatRes = mysqli_query($conn, "SELECT DISTINCT assigned_area FROM customers WHERE assigned_area IS NOT NULL AND assigned_area <> '' ORDER BY assigned_area ASC");
if ($beatRes) {
    while ($row = mysqli_fetch_assoc($beatRes)) {
        $beats[] = $row['assigned_area'];
    }
}

$listSql = "
    SELECT 
        c.id,
        c.customer_code,
        c.shop_name,
        c.customer_name,
        c.customer_contact,
        c.alternate_contact,
        c.shop_location,
        $gstSelect,
        c.customer_type,
        c.assigned_area,
        c.payment_terms,
        c.credit_limit,
        c.current_balance,
        c.total_purchases,
        c.last_purchase_date,
        c.status,
        l.full_name AS lineman_name,
        z.zone_name
    FROM customers c
    LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
    LEFT JOIN zones z ON c.zone_id = z.id
    $whereSql
    ORDER BY 
        CASE WHEN c.assigned_area IS NULL OR c.assigned_area = '' THEN 1 ELSE 0 END,
        c.assigned_area ASC,
        c.shop_name ASC,
        c.customer_name ASC
";
$listResult = mysqli_query($conn, $listSql);

$pageTitle = 'Customers List';
$currentPage = 'customers-list';
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php')?>
<body data-sidebar="dark"<?php echo $printMode ? ' class="print-page"' : ''; ?>>
<?php if (!$printMode) { include('includes/pre-loader.php'); } ?>
<div id="layout-wrapper">
    <?php if (!$printMode) { include('includes/topbar.php'); } ?>

    <?php if (!$printMode): ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php')?>
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content"<?php echo $printMode ? ' style="margin-left:0;"' : ''; ?>>
        <div class="page-content">
            <div class="container-fluid">

                <div class="row no-print">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Customers List</h4>
                            <div class="page-title-right d-flex gap-2">
                                <a href="add-customer.php" class="btn btn-success">
                                    <i class="mdi mdi-plus-circle-outline me-1"></i> Add Customer
                                </a>
                                <button type="button" class="btn btn-primary" onclick="window.print()">
                                    <i class="mdi mdi-printer me-1"></i> Print
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$printMode): ?>
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
                                        <h4 class="mb-0"><?php echo (int)($stats['total_customers'] ?? 0); ?></h4>
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
                                            <i class="mdi mdi-check-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Active</p>
                                        <h4 class="mb-0"><?php echo (int)($stats['active_customers'] ?? 0); ?></h4>
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
                                        <span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2">
                                            <i class="mdi mdi-account-cancel"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Blocked</p>
                                        <h4 class="mb-0"><?php echo (int)($stats['blocked_customers'] ?? 0); ?></h4>
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
                                            <i class="mdi mdi-wallet"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Outstanding</p>
                                        <h4 class="mb-0"><?php echo formatMoneyCustomer($stats['total_balance'] ?? 0); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card no-print">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" value="<?php echo esc_customer_page($search); ?>" placeholder="Name / code / phone / address">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Beat</label>
                                <select name="beat" class="form-select">
                                    <option value="">All Beats</option>
                                    <?php foreach ($beats as $beatName): ?>
                                        <option value="<?php echo esc_customer_page($beatName); ?>" <?php echo $beat === $beatName ? 'selected' : ''; ?>>
                                            <?php echo esc_customer_page($beatName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="retail" <?php echo $type === 'retail' ? 'selected' : ''; ?>>Retail</option>
                                    <option value="wholesale" <?php echo $type === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                    <option value="hotel" <?php echo $type === 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                    <option value="office" <?php echo $type === 'office' ? 'selected' : ''; ?>>Office</option>
                                    <option value="residential" <?php echo $type === 'residential' ? 'selected' : ''; ?>>Residential</option>
                                    <option value="other" <?php echo $type === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-grid">
                                <button type="submit" class="btn btn-primary">Go</button>
                            </div>
                            <div class="col-md-12 mt-1">
                                <a href="customers-list.php" class="btn btn-light btn-sm">Clear Filters</a>
                                <?php if ($beat !== ''): ?>
                                    <a href="customers-list.php?<?php echo appendFilterParams(['print' => 1]); ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Open Beat Print View</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h4 class="card-title mb-1">
                                    <?php echo $beat !== '' ? 'BEAT: ' . esc_customer_page(strtoupper($beat)) : 'All Customers'; ?>
                                </h4>
                                <p class="card-title-desc mb-0">
                                    Customer list with name, address, GST and phone view.
                                </p>
                            </div>
                            <?php if ($hasGstColumn === false): ?>
                                <div class="alert alert-warning py-2 px-3 mb-0 no-print">
                                    GST column is not in current customers table. GST will show blank until added.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Responsive table wrapper with horizontal scrolling for small screens -->
                        <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                            <table class="table table-bordered align-middle table-nowrap mb-0" style="min-width: 700px; width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:60px;">No.</th>
                                        <th style="width:220px;">Shop Name / Code</th>
                                        <th style="width:150px;">Contact Person</th>
                                        <th style="width:280px;">Address</th>
                                        <th style="width:140px;">GST No</th>
                                        <th style="width:150px;">Phone</th>
                                        <th class="no-print" style="width:90px;">Status</th>
                                        <th class="no-print" style="width:100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($listResult && mysqli_num_rows($listResult) > 0): ?>
                                        <?php $i = 1; while ($row = mysqli_fetch_assoc($listResult)): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo esc_customer_page($row['shop_name']); ?></div>
                                                    <small class="text-muted"><?php echo esc_customer_page($row['customer_code']); ?></small>
                                                </td>
                                                <td><?php echo esc_customer_page($row['customer_name']); ?></td>
                                                <td class="address-cell" style="white-space: normal; word-break: break-word;"><?php echo nl2br(esc_customer_page($row['shop_location'])); ?></td>
                                                <td><?php echo esc_customer_page($row['gst_number'] ?: ''); ?></td>
                                                <td>
                                                    <?php echo esc_customer_page($row['customer_contact']); ?>
                                                    <?php if (!empty($row['alternate_contact'])): ?>
                                                        <br><small class="text-muted"><?php echo esc_customer_page($row['alternate_contact']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="no-print">
                                                    <?php
                                                    $badgeClass = 'badge-soft-secondary';
                                                    if ($row['status'] === 'active') $badgeClass = 'badge-soft-success';
                                                    if ($row['status'] === 'inactive') $badgeClass = 'badge-soft-warning';
                                                    if ($row['status'] === 'blocked') $badgeClass = 'badge-soft-danger';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?> font-size-11"><?php echo esc_customer_page(ucfirst($row['status'])); ?></span>
                                                </td>
                                                <td class="no-print">
                                                    <div class="d-flex gap-2">
                                                        <a href="customer-view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="mdi mdi-eye-outline"></i>
                                                        </a>
                                                        <a href="add-customer.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-light">
                                                            <i class="mdi mdi-pencil-outline"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="mdi mdi-account-off display-6 d-block mb-2"></i>
                                                    No customers found.
                                                </div>
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
        <?php if (!$printMode) { include('includes/footer.php'); } ?>
    </div>
</div>

<?php if (!$printMode): ?>
<?php include('includes/rightbar.php')?>
<?php include('includes/scripts.php')?>
<?php endif; ?>

<!-- JavaScript to handle scroll to top when scrolling horizontally -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find the table responsive container
    var tableContainer = document.querySelector('.table-responsive');
    
    if (tableContainer) {
        // When user scrolls horizontally, also scroll the page to top
        tableContainer.addEventListener('scroll', function(e) {
            // Scroll the window to top
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});
</script>

<style>
/* Improved table styles */
.table td, .table th {
    vertical-align: top;
}
.address-cell {
    white-space: normal;
    line-height: 1.45;
    word-break: break-word;
}

/* Responsive table adjustments */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    position: relative;
}

/* Make table cells more compact on smaller screens */
@media (max-width: 768px) {
    .table td, .table th {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
    }
}

/* Print styles */
@media print {
    body {
        background: #fff !important;
    }
    .no-print,
    .vertical-menu,
    .topbar,
    .navbar-header,
    footer,
    #right-bar,
    .right-bar,
    .page-title-box {
        display: none !important;
    }
    .main-content,
    .page-content,
    .container-fluid,
    .card,
    .card-body {
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }
    .table {
        font-size: 12px;
    }
    .table td, .table th {
        padding: 6px !important;
    }
    .table-responsive {
        overflow: visible !important;
    }
    table {
        width: 100% !important;
        min-width: auto !important;
    }
}
</style>
</body>
</html> 