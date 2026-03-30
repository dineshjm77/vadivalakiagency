<?php
session_start();
include('../config/config.php');

// Role-based access check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'collection') {
    header('Location: ../login.php');
    exit;
}

$collection_user_id = $_SESSION['user_id'];
$collection_name = $_SESSION['user_name'];

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d M, Y', strtotime($date));
}

// Get today's date
$today = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : $today;
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'today';

// Handle AJAX: mark payment as collected
if (isset($_GET['action']) && $_GET['action'] === 'mark_collected' && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $payment_method = isset($_GET['payment_method']) ? mysqli_real_escape_string($conn, $_GET['payment_method']) : 'cash';
    $reference_no = isset($_GET['reference_no']) ? mysqli_real_escape_string($conn, $_GET['reference_no']) : '';
    $notes = isset($_GET['notes']) ? mysqli_real_escape_string($conn, $_GET['notes']) : '';
    
    // Get order details
    $order_query = "SELECT o.*, c.current_balance, c.id as customer_id FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id 
                    WHERE o.id = $order_id AND o.payment_status != 'paid'";
    $order_result = mysqli_query($conn, $order_query);
    
    if ($order_data = mysqli_fetch_assoc($order_result)) {
        $pending_amount = $order_data['pending_amount'];
        $amount_paid = $pending_amount;
        
        // Update order payment status
        $update_order = "UPDATE orders SET 
                         payment_status = 'paid', 
                         paid_amount = total_amount, 
                         pending_amount = 0,
                         payment_date = NOW(),
                         payment_method = '$payment_method'
                         WHERE id = $order_id";
        mysqli_query($conn, $update_order);
        
        // Update customer balance
        $new_balance = $order_data['current_balance'] - $pending_amount;
        $update_customer = "UPDATE customers SET current_balance = $new_balance WHERE id = " . $order_data['customer_id'];
        mysqli_query($conn, $update_customer);
        
        // Insert payment history
        $transaction_id = "PAY" . date('YmdHis') . rand(100, 999);
        $insert_payment = "INSERT INTO payment_history (order_id, amount_paid, payment_method, reference_no, notes, created_by) 
                           VALUES ($order_id, $pending_amount, '$payment_method', '$reference_no', '$notes', $collection_user_id)";
        mysqli_query($conn, $insert_payment);
        
        // Insert transaction record
        $insert_transaction = "INSERT INTO transactions (customer_id, order_id, payment_id, type, amount, payment_method, reference_no, notes, created_by) 
                               VALUES (" . $order_data['customer_id'] . ", $order_id, '$transaction_id', 'payment', $pending_amount, '$payment_method', '$reference_no', '$notes', $collection_user_id)";
        mysqli_query($conn, $insert_transaction);
        
        echo json_encode(['success' => true, 'message' => 'Payment collected successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found or already paid']);
    }
    exit;
}

// Handle AJAX: get order items
if (isset($_GET['action']) && $_GET['action'] === 'order_items' && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $qi = "SELECT oi.id, p.product_name, oi.quantity, oi.price, oi.total FROM order_items oi 
           LEFT JOIN products p ON oi.product_id = p.id 
           WHERE oi.order_id = $order_id";
    $res = mysqli_query($conn, $qi);
    $items = [];
    while ($r = mysqli_fetch_assoc($res)) $items[] = $r;
    header('Content-Type: application/json');
    echo json_encode($items);
    exit;
}

// === Today's Collection Summary ===
$today_collected = 0;
$today_orders_collected = 0;
$today_pending = 0;
$today_orders_pending = 0;

$today_summary_sql = "SELECT SUM(pending_amount) as total_pending, COUNT(id) as pending_count 
                      FROM orders 
                      WHERE payment_status != 'paid' AND order_date = '$today'";
$today_res = mysqli_query($conn, $today_summary_sql);
if ($today_data = mysqli_fetch_assoc($today_res)) {
    $today_pending = floatval($today_data['total_pending']);
    $today_orders_pending = intval($today_data['pending_count']);
}

$today_collected_sql = "SELECT SUM(amount_paid) as total_collected, COUNT(*) as collected_count 
                        FROM payment_history 
                        WHERE DATE(created_at) = '$today' AND created_by = $collection_user_id";
$today_col_res = mysqli_query($conn, $today_collected_sql);
if ($today_col_data = mysqli_fetch_assoc($today_col_res)) {
    $today_collected = floatval($today_col_data['total_collected']);
    $today_orders_collected = intval($today_col_data['collected_count']);
}

// === Pending Payments List ===
$pending_orders = [];
$pending_sql = "SELECT o.id, o.order_number, o.order_date, o.total_amount, o.paid_amount, o.pending_amount,
                c.shop_name, c.customer_name, c.customer_contact, c.id as customer_id, l.full_name as lineman_name
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
                WHERE o.payment_status != 'paid'";
                
if ($filter_type == 'today') {
    $pending_sql .= " AND o.order_date = '$today'";
} elseif ($filter_type == 'period' && !empty($start_date) && !empty($end_date)) {
    $pending_sql .= " AND o.order_date BETWEEN '$start_date' AND '$end_date'";
}
$pending_sql .= " ORDER BY o.order_date DESC";
$pending_res = mysqli_query($conn, $pending_sql);
while ($row = mysqli_fetch_assoc($pending_res)) {
    $pending_orders[] = $row;
}

// === Recent Collections (Last 7 days) ===
$recent_collections = [];
$rc_sql = "SELECT ph.id, ph.order_id, ph.amount_paid, ph.payment_method, ph.reference_no, ph.created_at,
           o.order_number, c.shop_name, c.customer_name
           FROM payment_history ph
           LEFT JOIN orders o ON ph.order_id = o.id
           LEFT JOIN customers c ON o.customer_id = c.id
           WHERE ph.created_by = $collection_user_id
           ORDER BY ph.created_at DESC LIMIT 20";
$rc_res = mysqli_query($conn, $rc_sql);
while ($row = mysqli_fetch_assoc($rc_res)) {
    $recent_collections[] = $row;
}

// === Collection Statistics by Month ===
$monthly_stats = [];
$ms_sql = "SELECT DATE_FORMAT(ph.created_at, '%Y-%m') as month, 
           SUM(ph.amount_paid) as total_collected, COUNT(*) as collections_count
           FROM payment_history ph
           WHERE ph.created_by = $collection_user_id
           GROUP BY DATE_FORMAT(ph.created_at, '%Y-%m')
           ORDER BY month DESC LIMIT 12";
$ms_res = mysqli_query($conn, $ms_sql);
while ($row = mysqli_fetch_assoc($ms_res)) {
    $monthly_stats[] = $row;
}

// === Total Collections Overall ===
$total_collected_overall = 0;
$total_collected_sql = "SELECT SUM(amount_paid) as total FROM payment_history WHERE created_by = $collection_user_id";
$tc_res = mysqli_query($conn, $total_collected_sql);
if ($tc_data = mysqli_fetch_assoc($tc_res)) {
    $total_collected_overall = floatval($tc_data['total_collected']);
}

// Prepare JSON data for charts
$month_labels = [];
$month_values = [];
foreach (array_reverse($monthly_stats) as $ms) {
    $month_labels[] = date('M Y', strtotime($ms['month'] . '-01'));
    $month_values[] = floatval($ms['total_collected']);
}

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collection Dashboard | APR Water Agencies</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/icons.min.css" rel="stylesheet">
    <link href="../assets/css/app.min.css" rel="stylesheet">
    <style>
        .collection-card {
            transition: transform 0.2s;
        }
        .collection-card:hover {
            transform: translateY(-5px);
        }
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        .badge-collected {
            background-color: #28a745;
        }
    </style>
</head>
<body data-sidebar="dark">
    <div id="layout-wrapper">
        <?php include('../includes/topbar.php'); ?>
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <?php include('../includes/collection-sidebar.php'); ?>
            </div>
        </div>

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Page Title -->
                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">Collection Dashboard</h4>
                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item"><a href="index.php">Collection</a></li>
                                        <li class="breadcrumb-item active">Dashboard</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome Message -->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-account-check me-2"></i> Welcome back, <strong><?php echo htmlspecialchars($collection_name); ?></strong>! 
                                You have collected <strong><?php echo formatCurrency($total_collected_overall); ?></strong> overall.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card collection-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <p class="text-muted mb-1">Today's Collections</p>
                                            <h3 class="mb-0"><?php echo formatCurrency($today_collected); ?></h3>
                                            <small class="text-success"><?php echo $today_orders_collected; ?> orders collected</small>
                                        </div>
                                        <div class="avatar-sm">
                                            <div class="avatar-title bg-success-subtle text-success rounded-circle fs-4">
                                                <i class="mdi mdi-cash-multiple"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card collection-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <p class="text-muted mb-1">Pending Collection</p>
                                            <h3 class="mb-0"><?php echo formatCurrency($today_pending); ?></h3>
                                            <small class="text-warning"><?php echo $today_orders_pending; ?> orders pending</small>
                                        </div>
                                        <div class="avatar-sm">
                                            <div class="avatar-title bg-warning-subtle text-warning rounded-circle fs-4">
                                                <i class="mdi mdi-clock-outline"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card collection-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <p class="text-muted mb-1">Total Collected</p>
                                            <h3 class="mb-0"><?php echo formatCurrency($total_collected_overall); ?></h3>
                                            <small class="text-info">Lifetime collections</small>
                                        </div>
                                        <div class="avatar-sm">
                                            <div class="avatar-title bg-info-subtle text-info rounded-circle fs-4">
                                                <i class="mdi mdi-chart-line"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card collection-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <p class="text-muted mb-1">Collection Rate</p>
                                            <?php 
                                            $collection_rate = ($today_collected + $today_pending > 0) ? 
                                                ($today_collected / ($today_collected + $today_pending) * 100) : 0;
                                            ?>
                                            <h3 class="mb-0"><?php echo number_format($collection_rate, 1); ?>%</h3>
                                            <small class="text-muted">Today's collection rate</small>
                                        </div>
                                        <div class="avatar-sm">
                                            <div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-4">
                                                <i class="mdi mdi-percent"></i>
                                            </div>
                                        </div>
                                    </div>
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
                                        <div class="col-md-2">
                                            <label class="form-label">Filter By</label>
                                            <select class="form-select" name="filter_type" id="filter_type">
                                                <option value="today" <?php echo $filter_type == 'today' ? 'selected' : ''; ?>>Today's Orders</option>
                                                <option value="period" <?php echo $filter_type == 'period' ? 'selected' : ''; ?>>Date Range</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3" id="date_range_div" style="display: <?php echo $filter_type == 'period' ? 'block' : 'none'; ?>">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                        </div>
                                        <div class="col-md-3" id="date_range_end_div" style="display: <?php echo $filter_type == 'period' ? 'block' : 'none'; ?>">
                                            <label class="form-label">End Date</label>
                                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-filter me-1"></i> Apply</button>
                                        </div>
                                        <div class="col-md-2">
                                            <a href="index.php" class="btn btn-secondary w-100"><i class="mdi mdi-refresh me-1"></i> Reset</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Collections Table -->
                    <div class="row mt-3">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">
                                            <i class="mdi mdi-cash-clock me-2 text-warning"></i> 
                                            Pending Collections 
                                            <span class="badge bg-warning ms-2"><?php echo count($pending_orders); ?> orders</span>
                                        </h5>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="refreshList()">
                                                <i class="mdi mdi-refresh"></i> Refresh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Order No</th>
                                                    <th>Date</th>
                                                    <th>Customer</th>
                                                    <th>Lineman</th>
                                                    <th>Total Amount</th>
                                                    <th>Paid</th>
                                                    <th>Pending</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="pending-orders-body">
                                                <?php if (empty($pending_orders)): ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted py-4">
                                                            <i class="mdi mdi-check-circle-outline fs-1 d-block mb-2"></i>
                                                            No pending orders found!
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($pending_orders as $order): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                            </td>
                                                            <td><?php echo formatDate($order['order_date']); ?></td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($order['shop_name']); ?></small><br>
                                                                <small><?php echo htmlspecialchars($order['customer_contact']); ?></small>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($order['lineman_name'] ?? '-'); ?></td>
                                                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                                            <td><?php echo formatCurrency($order['paid_amount']); ?></td>
                                                            <td class="text-warning fw-bold"><?php echo formatCurrency($order['pending_amount']); ?></td>
                                                            <td>
                                                                <div class="d-flex gap-1">
                                                                    <button class="btn btn-sm btn-info" onclick="viewOrderItems(<?php echo $order['id']; ?>)">
                                                                        <i class="mdi mdi-eye"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-success" onclick="collectPayment(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', <?php echo $order['pending_amount']; ?>)">
                                                                        <i class="mdi mdi-cash"></i> Collect
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Two Column Layout: Monthly Stats & Recent Collections -->
                    <div class="row mt-3">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Monthly Collection Trend</h5>
                                    <canvas id="monthlyChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Recent Collections</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Order</th>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_collections as $rc): ?>
                                                    <tr>
                                                        <td><small><?php echo formatDate($rc['created_at']); ?></small></td>
                                                        <td><?php echo htmlspecialchars($rc['order_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($rc['customer_name']); ?></td>
                                                        <td class="text-success fw-bold"><?php echo formatCurrency($rc['amount_paid']); ?></td>
                                                        <td><?php echo ucfirst($rc['payment_method']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($recent_collections)): ?>
                                                    <tr><td colspan="5" class="text-center text-muted">No collections yet</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Collection Tips -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-1 text-center">
                                            <i class="mdi mdi-lightbulb-on-outline fs-1 text-warning"></i>
                                        </div>
                                        <div class="col-md-11">
                                            <h6 class="mb-1">Collection Tips</h6>
                                            <p class="text-muted mb-0">
                                                <i class="mdi mdi-check-circle text-success me-1"></i> Always verify customer details before collecting payment.<br>
                                                <i class="mdi mdi-receipt-text text-primary me-1"></i> Provide proper receipt/invoice after collection.<br>
                                                <i class="mdi mdi-phone-message text-info me-1"></i> For any issues, contact the admin or respective lineman.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <?php include('../includes/footer.php'); ?>
        </div>
    </div>

    <!-- Payment Collection Modal -->
    <div class="modal fade" id="collectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Collect Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="collect_order_id">
                    <div class="mb-3">
                        <label class="form-label">Order Number</label>
                        <input type="text" class="form-control" id="collect_order_number" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount to Collect</label>
                        <input type="text" class="form-control" id="collect_amount" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" id="collect_payment_method">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference No (Optional)</label>
                        <input type="text" class="form-control" id="collect_reference" placeholder="Transaction ID / Cheque No">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="collect_notes" rows="2" placeholder="Any remarks..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmCollectionBtn">Confirm Collection</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Items Modal -->
    <div class="modal fade" id="orderItemsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderItemsBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
    
    <script>
    // Monthly Chart
    const monthLabels = <?php echo json_encode($month_labels); ?>;
    const monthValues = <?php echo json_encode($month_values); ?>;
    
    if (monthLabels.length > 0) {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: 'Collections (₹)',
                    data: monthValues,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Filter toggle
    $('#filter_type').change(function() {
        if ($(this).val() == 'period') {
            $('#date_range_div, #date_range_end_div').show();
        } else {
            $('#date_range_div, #date_range_end_div').hide();
        }
    });

    // Collect Payment Modal
    let currentOrderId = null;
    
    function collectPayment(orderId, orderNumber, amount) {
        currentOrderId = orderId;
        $('#collect_order_id').val(orderId);
        $('#collect_order_number').val(orderNumber);
        $('#collect_amount').val('₹' + amount.toFixed(2));
        $('#collectionModal').modal('show');
    }

    $('#confirmCollectionBtn').click(function() {
        const orderId = $('#collect_order_id').val();
        const paymentMethod = $('#collect_payment_method').val();
        const referenceNo = $('#collect_reference').val();
        const notes = $('#collect_notes').val();
        
        if (!orderId) return;
        
        $.ajax({
            url: 'index.php',
            type: 'GET',
            data: {
                action: 'mark_collected',
                order_id: orderId,
                payment_method: paymentMethod,
                reference_no: referenceNo,
                notes: notes
            },
            success: function(response) {
                const res = JSON.parse(response);
                if (res.success) {
                    $('#collectionModal').modal('hide');
                    showToast('success', res.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('error', res.message);
                }
            },
            error: function() {
                showToast('error', 'Failed to process collection');
            }
        });
    });

    // View Order Items
    function viewOrderItems(orderId) {
        $('#orderItemsBody').html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        $('#orderItemsModal').modal('show');
        
        $.ajax({
            url: 'index.php',
            type: 'GET',
            data: { action: 'order_items', order_id: orderId },
            success: function(items) {
                if (items.length === 0) {
                    $('#orderItemsBody').html('<p class="text-muted text-center">No items found</p>');
                    return;
                }
                let html = '<table class="table table-sm">\n\
                    <thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead>\n\
                    <tbody>';
                items.forEach(function(item) {
                    html += '<tr><td>' + escapeHtml(item.product_name) + '</td><td>' + item.quantity + '</td><td>₹' + item.price + '</td><td>₹' + item.total + '</td></tr>';
                });
                html += '</tbody></table>';
                $('#orderItemsBody').html(html);
            },
            error: function() {
                $('#orderItemsBody').html('<p class="text-danger text-center">Failed to load items</p>');
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function refreshList() {
        location.reload();
    }

    function showToast(type, message) {
        const toast = $(`<div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 9999">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
        $('body').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        setTimeout(() => toast.remove(), 3000);
    }
    </script>
</body>
</html>