<?php
include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];

// Handle search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$payment_filter = isset($_GET['payment']) ? mysqli_real_escape_string($conn, $_GET['payment']) : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query for orders
$sql = "SELECT o.*, 
               c.shop_name, c.customer_name, c.customer_contact, c.customer_code
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.created_by = $lineman_id";

$conditions = [];

// Add search condition
if (!empty($search)) {
    $conditions[] = "(o.order_number LIKE '%$search%' OR 
                     c.shop_name LIKE '%$search%' OR 
                     c.customer_name LIKE '%$search%' OR 
                     c.customer_contact LIKE '%$search%')";
}

// Add status filter
if ($status_filter != 'all') {
    $conditions[] = "o.status = '$status_filter'";
}

// Add payment status filter
if ($payment_filter != 'all') {
    $conditions[] = "o.payment_status = '$payment_filter'";
}

// Add date range filter
if (!empty($date_from)) {
    $conditions[] = "o.order_date >= '$date_from'";
}
if (!empty($date_to)) {
    $conditions[] = "o.order_date <= '$date_to'";
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Order by
$sql .= " ORDER BY o.created_at DESC";

// Execute query
$result = mysqli_query($conn, $sql);

// Calculate order statistics
$stats_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN DATE(order_date) = CURDATE() THEN 1 ELSE 0 END) as today_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
    SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_payments,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
    SUM(total_amount) as total_sales,
    SUM(paid_amount) as total_collected,
    SUM(pending_amount) as total_pending
    FROM orders WHERE created_by = $lineman_id";

$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Verify order belongs to lineman
    $check_sql = "SELECT id FROM orders WHERE id = $order_id AND created_by = $lineman_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $update_sql = "UPDATE orders SET status = '$new_status', updated_at = NOW() WHERE id = $order_id";
        if (mysqli_query($conn, $update_sql)) {
            $success_message = "Order status updated successfully!";
            // Refresh page to show updated status
            header("Location: orders.php?success=1");
            exit;
        } else {
            $error_message = "Failed to update order status: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Order not found or you don't have permission to update it.";
    }
}

// Handle delete order
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Verify order belongs to lineman
    $check_sql = "SELECT id FROM orders WHERE id = $delete_id AND created_by = $lineman_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $delete_sql = "DELETE FROM orders WHERE id = $delete_id";
        if (mysqli_query($conn, $delete_sql)) {
            $success_message = "Order deleted successfully!";
            header("Location: orders.php?success=1");
            exit;
        } else {
            $error_message = "Failed to delete order: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Order not found or you don't have permission to delete it.";
    }
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
                $current_page = 'orders';
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



                    <!-- Messages -->
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        Operation completed successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Orders">Total Orders</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['total_orders'] ?? 0; ?></h3>
                                            <a href="orders.php" class="text-muted">View All</a>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-cart"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Today's Orders">Today's Orders</h5>
                                            <h3 class="my-2 py-1"><?php echo $stats['today_orders'] ?? 0; ?></h3>
                                            <a href="orders.php?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="text-muted">View Today</a>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                    <i class="mdi mdi-calendar-today"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Sales">Total Sales</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-arrow-up-bold"></i>
                                                </span>
                                                <span>Lifetime Value</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-currency-inr"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Pending Payments">Pending Payments</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($stats['total_pending'] ?? 0, 2); ?></h3>
                                            <a href="orders.php?payment=pending" class="text-muted">View Pending</a>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-warning bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-warning text-warning">
                                                    <i class="mdi mdi-cash-clock"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Order Status Summary -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Order Status Overview</h5>
                                    <div class="row">
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-warning text-warning font-size-18">
                                                            <i class="mdi mdi-clock-outline"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Pending</h5>
                                                    <p class="text-muted mb-0"><?php echo $stats['pending_orders'] ?? 0; ?> Orders</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-info text-info font-size-18">
                                                            <i class="mdi mdi-loading"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Processing</h5>
                                                    <p class="text-muted mb-0"><?php echo $stats['processing_orders'] ?? 0; ?> Orders</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-success text-success font-size-18">
                                                            <i class="mdi mdi-check-circle"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Delivered</h5>
                                                    <p class="text-muted mb-0"><?php echo $stats['delivered_orders'] ?? 0; ?> Orders</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
                                                    <div class="avatar-sm mx-auto mb-3">
                                                        <span class="avatar-title rounded-circle bg-soft-danger text-danger font-size-18">
                                                            <i class="mdi mdi-close-circle"></i>
                                                        </span>
                                                    </div>
                                                    <h5 class="font-size-16 mb-1">Cancelled</h5>
                                                    <p class="text-muted mb-0"><?php echo $stats['cancelled_orders'] ?? 0; ?> Orders</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Orders Table -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">All Orders</h4>
                                            <p class="card-title-desc">Manage and track your orders</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                                <a href="quick-order.php" class="btn btn-success">
                                                    <i class="mdi mdi-plus-circle-outline me-1"></i> Create New Order
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Search & Filter Form -->
                                    <form method="GET" class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="search" 
                                                   value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search orders...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="status">
                                                <option value="all">All Status</option>
                                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="payment">
                                                <option value="all">All Payments</option>
                                                <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending Payment</option>
                                                <option value="partial" <?php echo $payment_filter == 'partial' ? 'selected' : ''; ?>>Partial Payment</option>
                                                <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" class="form-control" name="date_from" 
                                                   value="<?php echo htmlspecialchars($date_from); ?>"
                                                   placeholder="From Date">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" class="form-control" name="date_to" 
                                                   value="<?php echo htmlspecialchars($date_to); ?>"
                                                   placeholder="To Date">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="mdi mdi-filter"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <?php if (!empty($search) || $status_filter != 'all' || $payment_filter != 'all' || !empty($date_from) || !empty($date_to)): ?>
                                    <div class="mb-3">
                                        <a href="orders.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Customer</th>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Payment Status</th>
                                                    <th>Order Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result && mysqli_num_rows($result) > 0) {
                                                    while ($row = mysqli_fetch_assoc($result)) {
                                                        // Format date
                                                        $order_date = date('d M, Y', strtotime($row['order_date']));
                                                        
                                                        // Status badge colors
                                                        $order_status_class = '';
                                                        if ($row['status'] == 'pending') $order_status_class = 'badge-soft-warning';
                                                        elseif ($row['status'] == 'processing') $order_status_class = 'badge-soft-info';
                                                        elseif ($row['status'] == 'delivered') $order_status_class = 'badge-soft-success';
                                                        elseif ($row['status'] == 'cancelled') $order_status_class = 'badge-soft-danger';
                                                        
                                                        $payment_status_class = '';
                                                        if ($row['payment_status'] == 'pending') $payment_status_class = 'badge-soft-danger';
                                                        elseif ($row['payment_status'] == 'partial') $payment_status_class = 'badge-soft-warning';
                                                        elseif ($row['payment_status'] == 'paid') $payment_status_class = 'badge-soft-success';
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <a href="view-invoice.php?id=<?php echo $row['id']; ?>" class="text-dark fw-medium">
                                                                    <?php echo $row['order_number']; ?>
                                                                </a>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($row['shop_name']); ?></h5>
                                                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($row['customer_name']); ?></p>
                                                                    <small class="text-muted"><?php echo $row['customer_contact']; ?></small>
                                                                </div>
                                                            </td>
                                                            <td><?php echo $order_date; ?></td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">₹<?php echo number_format($row['total_amount'], 2); ?></h5>
                                                                    <?php if ($row['paid_amount'] > 0): ?>
                                                                    <small class="text-success">Paid: ₹<?php echo number_format($row['paid_amount'], 2); ?></small>
                                                                    <?php endif; ?>
                                                                    <?php if ($row['pending_amount'] > 0): ?>
                                                                    <br>
                                                                    <small class="text-danger">Pending: ₹<?php echo number_format($row['pending_amount'], 2); ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo $payment_status_class; ?> font-size-12">
                                                                    <?php echo ucfirst($row['payment_status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo $order_status_class; ?> font-size-12">
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
                                                                            <a class="dropdown-item" href="view-invoice.php?id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-eye-outline me-1"></i> View Invoice
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="print-invoice.php?id=<?php echo $row['id']; ?>" target="_blank">
                                                                                <i class="mdi mdi-printer me-1"></i> Print Invoice
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <button class="dropdown-item" type="button" 
                                                                                    data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                                                    data-order-id="<?php echo $row['id']; ?>"
                                                                                    data-current-status="<?php echo $row['status']; ?>">
                                                                                <i class="mdi mdi-update me-1"></i> Update Status
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="collect-payment.php?order_id=<?php echo $row['id']; ?>">
                                                                                <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                                            </a>
                                                                        </li>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li>
                                                                            <a class="dropdown-item text-danger" 
                                                                               href="orders.php?delete=<?php echo $row['id']; ?>" 
                                                                               onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                                                                                <i class="mdi mdi-delete-outline me-1"></i> Delete Order
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="text-muted">
                                                                <i class="mdi mdi-cart-off display-4"></i>
                                                                <h5 class="mt-2">No Orders Found</h5>
                                                                <p>Create your first order using the "Create New Order" button</p>
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
                                                Showing <?php echo mysqli_num_rows($result); ?> orders
                                            </div>
                                        </div>
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

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="updateStatusForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">Update Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="modal_order_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-select" id="status1" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Update Status Modal
        document.addEventListener('DOMContentLoaded', function() {
            const updateStatusModal = document.getElementById('updateStatusModal');
            
            if (updateStatusModal) {
                updateStatusModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const orderId = button.getAttribute('data-order-id');
                    const currentStatus = button.getAttribute('data-current-status');
                    
                    document.getElementById('modal_order_id').value = orderId;
                    document.getElementById('status').value = currentStatus;
                });
            }
        });

        // Quick filters
        function filterToday() {
            const today = new Date().toISOString().split('T')[0];
            window.location.href = `orders.php?date_from=${today}&date_to=${today}`;
        }

        function filterPending() {
            window.location.href = 'orders.php?status=pending';
        }

        function filterPaymentPending() {
            window.location.href = 'orders.php?payment=pending';
        }

        // Auto-submit date filters
        document.querySelector('input[name="date_from"]').addEventListener('change', function() {
            if (this.value) {
                this.form.submit();
            }
        });

        document.querySelector('input[name="date_to"]').addEventListener('change', function() {
            if (this.value) {
                this.form.submit();
            }
        });

        // Print invoice function
        function printInvoice(orderId) {
            window.open(`print-invoice.php?id=${orderId}`, '_blank');
        }

        // Export orders
        function exportOrders(format) {
            const search = '<?php echo $search; ?>';
            const status = '<?php echo $status_filter; ?>';
            const payment = '<?php echo $payment_filter; ?>';
            const date_from = '<?php echo $date_from; ?>';
            const date_to = '<?php echo $date_to; ?>';
            
            window.location.href = `export-orders.php?format=${format}&search=${encodeURIComponent(search)}&status=${status}&payment=${payment}&date_from=${date_from}&date_to=${date_to}`;
        }

        // Search on enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}