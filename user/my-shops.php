<?php
include('config/config.php');
include('includes/auth-check.php'); // Add authentication check

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

// Get logged in lineman's ID
$lineman_id = $_SESSION['user_id'];

// Handle search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';

// Build query
$sql = "SELECT * FROM customers WHERE assigned_lineman_id = $lineman_id";
$conditions = [];

// Add search condition
if (!empty($search)) {
    $conditions[] = "(shop_name LIKE '%$search%' OR 
                     customer_name LIKE '%$search%' OR 
                     customer_contact LIKE '%$search%' OR 
                     shop_location LIKE '%$search%')";
}

// Add status filter
if ($status_filter != 'all') {
    $conditions[] = "status = '$status_filter'";
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Order by
$sql .= " ORDER BY created_at DESC";

// Execute query
$result = mysqli_query($conn, $sql);

// Initialize counters
$total = 0;
$active = 0;
$inactive = 0;
$blocked = 0;

// Count customers for stats
$count_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked
    FROM customers WHERE assigned_lineman_id = $lineman_id";

$count_result = mysqli_query($conn, $count_sql);
if ($count_row = mysqli_fetch_assoc($count_result)) {
    $total = $count_row['total'];
    $active = $count_row['active'];
    $inactive = $count_row['inactive'];
    $blocked = $count_row['blocked'];
}
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>

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
                // Modify sidebar to highlight active page
                $current_page = 'my-shops';
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


                    <!-- end page title -->

                    <!-- Status Summary Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                                <i class="mdi mdi-store"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="text-uppercase fw-medium text-muted mb-0">Total Shops</p>
                                            <h4 class="mb-0"><?php echo $total; ?></h4>
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
                                                <i class="mdi mdi-store-check"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="text-uppercase fw-medium text-muted mb-0">Active</p>
                                            <h4 class="mb-0"><?php echo $active; ?></h4>
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
                                                <i class="mdi mdi-store-clock"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="text-uppercase fw-medium text-muted mb-0">Inactive</p>
                                            <h4 class="mb-0"><?php echo $inactive; ?></h4>
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
                                                <i class="mdi mdi-store-remove"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <p class="text-uppercase fw-medium text-muted mb-0">Blocked</p>
                                            <h4 class="mb-0"><?php echo $blocked; ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">My Assigned Shops</h4>
                                            <p class="card-title-desc">Manage shops assigned to you</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                                <form method="GET" class="d-flex gap-2">
                                                    <div class="search-box">
                                                        <input type="text" class="form-control" name="search" 
                                                               value="<?php echo htmlspecialchars($search); ?>" 
                                                               placeholder="Search shops...">
                                                        <i class="ri-search-line search-icon"></i>
                                                    </div>
                                                    <select class="form-select" name="status" style="width: auto;">
                                                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="blocked" <?php echo $status_filter == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="mdi mdi-filter me-1"></i> Apply
                                                    </button>
                                                    <?php if (!empty($search) || $status_filter != 'all'): ?>
                                                    <a href="my-shops.php" class="btn btn-secondary">
                                                        <i class="mdi mdi-refresh me-1"></i> Clear
                                                    </a>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="shopsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Shop Code</th>
                                                    <th>Shop Details</th>
                                                    <th>Contact</th>
                                                    <th>Location</th>
                                                    <th>Type</th>
                                                    <th>Payment Terms</th>
                                                    <th>Balance</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result && mysqli_num_rows($result) > 0) {
                                                    $counter = 1;
                                                    while ($row = mysqli_fetch_assoc($result)) {
                                                        // Status badge color
                                                        $status_class = '';
                                                        if ($row['status'] == 'active') $status_class = 'badge-soft-success';
                                                        elseif ($row['status'] == 'inactive') $status_class = 'badge-soft-warning';
                                                        elseif ($row['status'] == 'blocked') $status_class = 'badge-soft-danger';
                                                        
                                                        // Payment terms display
                                                        $payment_terms = ucfirst(str_replace('_', ' ', $row['payment_terms']));
                                                        
                                                        // Format balance
                                                        $balance = '₹' . number_format($row['current_balance'], 2);
                                                        $balance_class = $row['current_balance'] > 0 ? 'text-danger' : 'text-success';
                                                        
                                                        // Truncate location if too long
                                                        $location = strlen($row['shop_location']) > 50 ? 
                                                            substr($row['shop_location'], 0, 50) . '...' : 
                                                            $row['shop_location'];
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $counter; ?></td>
                                                            <td>
                                                                <span class="fw-medium"><?php echo $row['customer_code']; ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0 me-3">
                                                                        <div class="avatar-xs">
                                                                            <span class="avatar-title bg-info-subtle text-info rounded-circle">
                                                                                <i class="mdi mdi-store"></i>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <h5 class="font-size-14 mb-1">
                                                                            <a href="shop-view.php?id=<?php echo $row['id']; ?>" class="text-dark">
                                                                                <?php echo htmlspecialchars($row['shop_name']); ?>
                                                                            </a>
                                                                        </h5>
                                                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <i class="mdi mdi-phone me-1 text-muted"></i>
                                                                    <?php echo htmlspecialchars($row['customer_contact']); ?>
                                                                    <?php if (!empty($row['alternate_contact'])): ?>
                                                                    <br>
                                                                    <small>
                                                                        <i class="mdi mdi-phone-in-talk me-1 text-muted"></i>
                                                                        <?php echo htmlspecialchars($row['alternate_contact']); ?>
                                                                    </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="text-muted" title="<?php echo htmlspecialchars($row['shop_location']); ?>">
                                                                    <i class="mdi mdi-map-marker me-1"></i>
                                                                    <?php echo htmlspecialchars($location); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-light text-dark">
                                                                    <?php echo ucfirst($row['customer_type']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary-subtle text-primary">
                                                                    <?php echo $payment_terms; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="fw-medium <?php echo $balance_class; ?>">
                                                                    <?php echo $balance; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo $status_class; ?> font-size-12">
                                                                    <?php echo ucfirst($row['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <i class="mdi mdi-dots-horizontal"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                                        <li>
                                                                            <a class="dropdown-item" href="shop-view.php?id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-eye-outline me-1"></i> View Details
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="tel:<?php echo $row['customer_contact']; ?>">
                                                                                <i class="mdi mdi-phone me-1"></i> Call Customer
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="https://maps.google.com/?q=<?php echo urlencode($row['shop_location']); ?>" target="_blank">
                                                                                <i class="mdi mdi-map-marker me-1"></i> View on Map
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="collect-payment.php?customer_id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                        $counter++;
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-store-off display-4"></i>
                                                                <h5 class="mt-2">No Shops Assigned</h5>
                                                                <p>You don't have any shops assigned to you yet.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination (if needed) -->
                                    <div class="row mt-3">
                                        <div class="col-sm-12 col-md-5">
                                            <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                                Showing <?php echo mysqli_num_rows($result); ?> shops
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Quick Actions</h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="window.location.href='shop-orders.php'">
                                            <i class="mdi mdi-cart me-1"></i> View Today's Orders
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="window.location.href='delivery-schedule.php'">
                                            <i class="mdi mdi-truck-delivery me-1"></i> Delivery Schedule
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="window.location.href='collection-report.php'">
                                            <i class="mdi mdi-cash-multiple me-1"></i> Collection Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Search functionality
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });

        // Auto-refresh status filters
        document.querySelector('select[name="status"]').addEventListener('change', function() {
            if (this.value !== 'all') {
                this.closest('form').submit();
            }
        });

        // Quick filter buttons
        document.querySelectorAll('.quick-filter').forEach(button => {
            button.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                document.querySelector('select[name="status"]').value = status;
                document.querySelector('form').submit();
            });
        });

        // Export options
        document.querySelector('.btn-outline-primary').addEventListener('click', function() {
            const search = '<?php echo $search; ?>';
            const status = '<?php echo $status_filter; ?>';
            window.location.href = `export-shops.php?format=pdf&search=${encodeURIComponent(search)}&status=${status}`;
        });

        document.querySelector('.btn-outline-success').addEventListener('click', function() {
            const search = '<?php echo $search; ?>';
            const status = '<?php echo $status_filter; ?>';
            window.location.href = `export-shops.php?format=excel&search=${encodeURIComponent(search)}&status=${status}`;
        });

        document.querySelector('.btn-outline-secondary').addEventListener('click', function() {
            window.print();
        });
    </script>

</body>
</html>

<?php
// Close database connection
if (isset($conn)) {
    mysqli_close($conn);
}
?>