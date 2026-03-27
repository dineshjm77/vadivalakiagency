<?php
session_start();
include('config/config.php');

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Fetch order details with security check
if ($user_role == 'admin') {
    // Admin can view any order
    $order_sql = "SELECT o.*, c.customer_name, c.shop_name, c.customer_contact, 
                         c.shop_location, c.customer_code, c.current_balance,
                         l.full_name as lineman_name,
                         a.name as created_by_name
                  FROM orders o 
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
                  LEFT JOIN admin_users a ON o.created_by = a.id
                  WHERE o.id = $order_id";
} else {
    // Lineman can only view their own orders or orders of their assigned customers
    $order_sql = "SELECT o.*, c.customer_name, c.shop_name, c.customer_contact, 
                         c.shop_location, c.customer_code, c.current_balance,
                         l.full_name as lineman_name,
                         a.name as created_by_name
                  FROM orders o 
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
                  LEFT JOIN admin_users a ON o.created_by = a.id
                  WHERE o.id = $order_id 
                  AND (o.created_by = $user_id OR c.assigned_lineman_id = $user_id)";
}

$order_result = mysqli_query($conn, $order_sql);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    die("Order not found or you don't have permission to view it.");
}

$order = mysqli_fetch_assoc($order_result);

// Fetch order items
$items_sql = "SELECT oi.*, p.product_name, p.product_code 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = $order_id 
              ORDER BY oi.id";
$items_result = mysqli_query($conn, $items_sql);

// Fetch business settings for invoice
$business_sql = "SELECT * FROM business_settings LIMIT 1";
$business_result = mysqli_query($conn, $business_sql);
$business_settings = mysqli_fetch_assoc($business_result);

// If no business settings, use defaults
if (!$business_settings) {
    $business_settings = [
        'business_name' => 'APR Water Agencies',
        'contact_person' => 'Owner',
        'mobile' => '9876543210',
        'address' => '123 Main Street',
        'city' => 'Bangalore',
        'state' => 'Karnataka',
        'pincode' => '560001',
        'gstin' => '',
        'business_logo' => '',
        'invoice_footer' => '',
        'terms_conditions' => ''
    ];
}

// Fetch transaction history for this order
$transactions_sql = "SELECT * FROM transactions 
                     WHERE order_id = $order_id 
                     ORDER BY created_at DESC";
$transactions_result = mysqli_query($conn, $transactions_sql);
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


                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <!-- Invoice Header -->
                                    <div class="row mb-4">
                                        <div class="col-sm-6">
                                            <div class="text-muted">
                                                <h5 class="font-size-16 mb-3">Invoice To:</h5>
                                                <h5 class="font-size-15 mb-2"><?php echo htmlspecialchars($order['customer_name']); ?></h5>
                                                <p class="mb-1"><?php echo htmlspecialchars($order['shop_name']); ?></p>
                                                <p class="mb-1"><?php echo htmlspecialchars($order['customer_code']); ?></p>
                                                <p class="mb-1"><?php echo htmlspecialchars($order['customer_contact']); ?></p>
                                                <p class="mb-0"><?php echo htmlspecialchars($order['shop_location']); ?></p>
                                            </div>
                                        </div>
                                        <!-- end col -->
                                        <div class="col-sm-6">
                                            <div class="text-muted text-sm-end">
                                                <div>
                                                    <h5 class="font-size-15 mb-1">Invoice No:</h5>
                                                    <p><?php echo $order['order_number']; ?></p>
                                                </div>
                                                <div class="mt-4">
                                                    <h5 class="font-size-15 mb-1">Invoice Date:</h5>
                                                    <p><?php echo date('d M Y', strtotime($order['order_date'])); ?></p>
                                                </div>
                                                <div class="mt-4">
                                                    <h5 class="font-size-15 mb-1">Order Status:</h5>
                                                    <span class="badge bg-<?php echo getStatusColor($order['status']); ?> font-size-12">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- end col -->
                                    </div>
                                    <!-- end row -->

                                    <!-- Order Items -->
                                    <div class="py-2">
                                        <h5 class="font-size-15">Order Summary</h5>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-nowrap table-centered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 70px">#</th>
                                                        <th>Item</th>
                                                        <th class="text-end">Price</th>
                                                        <th class="text-end">Quantity</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $item_counter = 1;
                                                    $total_items = 0;
                                                    while ($item = mysqli_fetch_assoc($items_result)): 
                                                        $total_items += $item['quantity'];
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $item_counter++; ?></td>
                                                        <td>
                                                            <h5 class="font-size-15 mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h5>
                                                            <p class="font-size-13 text-muted mb-0">Code: <?php echo $item['product_code']; ?></p>
                                                        </td>
                                                        <td class="text-end">₹<?php echo number_format($item['price'], 2); ?></td>
                                                        <td class="text-end"><?php echo $item['quantity']; ?></td>
                                                        <td class="text-end">₹<?php echo number_format($item['total'], 2); ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                    
                                                    <tr>
                                                        <td colspan="3" class="border-0 text-end">
                                                            <h5 class="font-size-15 mb-0">Subtotal:</h5>
                                                        </td>
                                                        <td class="border-0 text-end">
                                                            <h5 class="font-size-15 mb-0"><?php echo $total_items; ?> items</h5>
                                                        </td>
                                                        <td class="border-0 text-end">
                                                            <h5 class="font-size-15 mb-0">₹<?php echo number_format($order['total_amount'], 2); ?></h5>
                                                        </td>
                                                    </tr>
                                                    
                                                    <tr>
                                                        <th colspan="4" class="border-0 text-end">
                                                            <h5 class="font-size-15 mb-0">Total Amount:</h5>
                                                        </th>
                                                        <th class="border-0 text-end">
                                                            <h5 class="font-size-15 mb-0">₹<?php echo number_format($order['total_amount'], 2); ?></h5>
                                                        </th>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- end table-responsive -->
                                    </div>

                                    <!-- Payment Information -->
                                    <div class="row mt-4">
                                        <div class="col-sm-6">
                                            <div class="text-muted">
                                                <h5 class="font-size-14 mb-3">Payment Information:</h5>
                                                <p class="mb-1">
                                                    <strong>Method:</strong> 
                                                    <span class="text-capitalize"><?php echo $order['payment_method']; ?></span>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Status:</strong> 
                                                    <span class="badge bg-<?php echo getPaymentStatusColor($order['payment_status']); ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Paid:</strong> ₹<?php echo number_format($order['paid_amount'], 2); ?>
                                                </p>
                                                <p class="mb-0">
                                                    <strong>Pending:</strong> ₹<?php echo number_format($order['pending_amount'], 2); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="text-muted text-sm-end">
                                                <h5 class="font-size-14 mb-3">Order Information:</h5>
                                                <p class="mb-1">
                                                    <strong>Created By:</strong> 
                                                    <?php echo $order['created_by_name'] ?: ($order['lineman_name'] ?: 'System'); ?>
                                                </p>
                                                <?php if ($order['lineman_name']): ?>
                                                <p class="mb-1">
                                                    <strong>Assigned Lineman:</strong> <?php echo $order['lineman_name']; ?>
                                                </p>
                                                <?php endif; ?>
                                                <p class="mb-1">
                                                    <strong>Customer Balance:</strong> 
                                                    <span class="<?php echo $order['current_balance'] < 0 ? 'text-success' : 'text-danger'; ?>">
                                                        ₹<?php echo number_format(abs($order['current_balance']), 2); ?>
                                                        <?php echo $order['current_balance'] < 0 ? '(Credit)' : '(Due)'; ?>
                                                    </span>
                                                </p>
                                                <?php if (!empty($order['notes'])): ?>
                                                <p class="mb-0">
                                                    <strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Transaction History -->
                                    <?php if (mysqli_num_rows($transactions_result) > 0): ?>
                                    <div class="mt-4">
                                        <h5 class="font-size-15 mb-3">Payment History</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Date & Time</th>
                                                        <th>Amount</th>
                                                        <th>Method</th>
                                                        <th>Type</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $txn_counter = 1;
                                                    while ($txn = mysqli_fetch_assoc($transactions_result)):
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $txn_counter++; ?></td>
                                                        <td><?php echo date('d M Y H:i', strtotime($txn['created_at'])); ?></td>
                                                        <td>₹<?php echo number_format($txn['amount'], 2); ?></td>
                                                        <td class="text-capitalize"><?php echo $txn['payment_method']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getTransactionTypeColor($txn['type']); ?>">
                                                                <?php echo ucfirst($txn['type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($txn['notes']); ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Business Information -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="border-top pt-3">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div>
                                                            <h5 class="font-size-15 mb-2"><?php echo $business_settings['business_name']; ?></h5>
                                                            <p class="mb-1"><?php echo $business_settings['contact_person']; ?></p>
                                                            <p class="mb-1"><?php echo $business_settings['address']; ?></p>
                                                            <p class="mb-1">
                                                                <?php echo $business_settings['city']; ?>, 
                                                                <?php echo $business_settings['state']; ?> - 
                                                                <?php echo $business_settings['pincode']; ?>
                                                            </p>
                                                            <p class="mb-0">Mobile: <?php echo $business_settings['mobile']; ?></p>
                                                            <?php if (!empty($business_settings['gstin'])): ?>
                                                            <p class="mb-0">GSTIN: <?php echo $business_settings['gstin']; ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="text-md-end">
                                                            <?php if (!empty($business_settings['terms_conditions'])): ?>
                                                            <div class="mt-3 mt-md-0">
                                                                <h5 class="font-size-15">Terms & Conditions</h5>
                                                                <p class="text-muted"><?php echo nl2br($business_settings['terms_conditions']); ?></p>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($business_settings['invoice_footer'])): ?>
                                                            <div class="mt-3">
                                                                <p class="text-muted"><?php echo nl2br($business_settings['invoice_footer']); ?></p>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="row mt-4">
                                        <div class="col-sm-12">
                                            <div class="float-end">
                                                <a href="javascript:window.print()" class="btn btn-success me-2">
                                                    <i class="mdi mdi-printer me-2"></i> Print
                                                </a>
                                                <?php if ($user_role == 'admin'): ?>
                                                <a href="edit-order.php?id=<?php echo $order_id; ?>" class="btn btn-primary me-2">
                                                    <i class="mdi mdi-pencil me-2"></i> Edit Order
                                                </a>
                                                <?php endif; ?>
                                                <a href="orders.php" class="btn btn-secondary">
                                                    <i class="mdi mdi-arrow-left me-2"></i> Back to Orders
                                                </a>
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

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <style>
        @media print {
            .vertical-menu, .topbar, .page-title-box, .footer,
            .btn, .breadcrumb, .right-bar {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card-body {
                padding: 20px !important;
            }
            
            body {
                background-color: white !important;
            }
        }
        
        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
    </style>

</body>
</html>

<?php
// Helper functions for styling
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'processing': return 'info';
        case 'delivered': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

function getPaymentStatusColor($status) {
    switch ($status) {
        case 'paid': return 'success';
        case 'partial': return 'warning';
        case 'pending': return 'danger';
        default: return 'secondary';
    }
}

function getTransactionTypeColor($type) {
    switch ($type) {
        case 'payment': return 'success';
        case 'purchase': return 'info';
        case 'refund': return 'danger';
        case 'adjustment': return 'warning';
        default: return 'secondary';
    }
}

// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}