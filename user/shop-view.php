<?php
include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

// Get shop ID from URL
$shop_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($shop_id == 0) {
    header('Location: my-shops.php');
    exit;
}

// Get logged in lineman's ID
$lineman_id = $_SESSION['user_id'];

// Fetch shop details - verify it belongs to this lineman
$sql = "SELECT c.*, 
               l.full_name as lineman_name,
               l.phone as lineman_phone
        FROM customers c
        LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
        WHERE c.id = $shop_id AND c.assigned_lineman_id = $lineman_id";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    // Shop not found or doesn't belong to this lineman
    header('Location: my-shops.php');
    exit;
}

$shop = mysqli_fetch_assoc($result);

// Format dates
$created_date = date('d M, Y', strtotime($shop['created_at']));
$last_purchase = $shop['last_purchase_date'] ? date('d M, Y', strtotime($shop['last_purchase_date'])) : 'Never';

// Calculate total purchases
$total_purchases = number_format($shop['total_purchases'], 2);

// Get recent transactions
$transactions_sql = "SELECT * FROM transactions 
                    WHERE customer_id = $shop_id 
                    ORDER BY created_at DESC 
                    LIMIT 10";
$transactions_result = mysqli_query($conn, $transactions_sql);

// Get recent orders
$orders_sql = "SELECT * FROM orders 
              WHERE customer_id = $shop_id 
              ORDER BY order_date DESC 
              LIMIT 10";
$orders_result = mysqli_query($conn, $orders_sql);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['status_notes']);
    
    $update_sql = "UPDATE customers SET status = '$new_status', updated_at = NOW() WHERE id = $shop_id";
    if (mysqli_query($conn, $update_sql)) {
        // Log the status change
        $log_sql = "INSERT INTO status_logs (customer_id, old_status, new_status, changed_by, notes, created_at) 
                    VALUES ($shop_id, '{$shop['status']}', '$new_status', $lineman_id, '$notes', NOW())";
        mysqli_query($conn, $log_sql);
        
        $success_message = "Shop status updated successfully!";
        // Refresh shop data
        $result = mysqli_query($conn, $sql);
        $shop = mysqli_fetch_assoc($result);
    } else {
        $error_message = "Failed to update status: " . mysqli_error($conn);
    }
}

// Handle notes update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notes'])) {
    $new_notes = mysqli_real_escape_string($conn, $_POST['shop_notes']);
    
    $update_sql = "UPDATE customers SET notes = '$new_notes', updated_at = NOW() WHERE id = $shop_id";
    if (mysqli_query($conn, $update_sql)) {
        $success_message = "Notes updated successfully!";
        // Refresh shop data
        $result = mysqli_query($conn, $sql);
        $shop = mysqli_fetch_assoc($result);
    } else {
        $error_message = "Failed to update notes: " . mysqli_error($conn);
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

                    <!-- Messages -->
                    <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_message; ?>
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

                    <div class="row">
                        <!-- Left Column: Shop Information -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-4">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="avatar-lg">
                                                <span class="avatar-title bg-info-subtle text-info rounded-circle">
                                                    <i class="mdi mdi-store font-size-24"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h4 class="card-title mb-1"><?php echo htmlspecialchars($shop['shop_name']); ?></h4>
                                            <p class="text-muted mb-0">
                                                <i class="mdi mdi-account me-1"></i>
                                                <?php echo htmlspecialchars($shop['customer_name']); ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <?php
                                            $status_class = '';
                                            if ($shop['status'] == 'active') $status_class = 'badge-soft-success';
                                            elseif ($shop['status'] == 'inactive') $status_class = 'badge-soft-warning';
                                            elseif ($shop['status'] == 'blocked') $status_class = 'badge-soft-danger';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> font-size-14">
                                                <?php echo strtoupper($shop['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Shop Code -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Shop Code</label>
                                                <p class="fw-medium mb-0"><?php echo $shop['customer_code']; ?></p>
                                            </div>
                                        </div>

                                        <!-- Customer Type -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Customer Type</label>
                                                <p class="mb-0">
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo ucfirst($shop['customer_type']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Contact Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">
                                                    <i class="mdi mdi-phone me-1"></i> Contact Number
                                                </label>
                                                <p class="fw-medium mb-0">
                                                    <a href="tel:<?php echo $shop['customer_contact']; ?>" class="text-dark">
                                                        <?php echo $shop['customer_contact']; ?>
                                                    </a>
                                                </p>
                                                <?php if (!empty($shop['alternate_contact'])): ?>
                                                <small class="text-muted">
                                                    Alternate: <?php echo $shop['alternate_contact']; ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Email -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">
                                                    <i class="mdi mdi-email me-1"></i> Email Address
                                                </label>
                                                <?php if (!empty($shop['email'])): ?>
                                                <p class="fw-medium mb-0">
                                                    <a href="mailto:<?php echo $shop['email']; ?>" class="text-dark">
                                                        <?php echo $shop['email']; ?>
                                                    </a>
                                                </p>
                                                <?php else: ?>
                                                <p class="text-muted mb-0">Not provided</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Shop Location -->
                                    <div class="mb-3">
                                        <label class="form-label text-muted">
                                            <i class="mdi mdi-map-marker me-1"></i> Shop Location
                                        </label>
                                        <p class="mb-2"><?php echo htmlspecialchars($shop['shop_location']); ?></p>
                                    </div>

                                    <!-- Financial Information -->
                                    <div class="row mt-4">
                                        <div class="col-md-4">
                                            <div class="card border">
                                                <div class="card-body text-center">
                                                    <h5 class="text-muted mb-2">Current Balance</h5>
                                                    <h3 class="mb-0 <?php echo $shop['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                        ₹<?php echo number_format($shop['current_balance'], 2); ?>
                                                    </h3>
                                                    <a href="collect-payment.php?customer_id=<?php echo $shop_id; ?>" 
                                                       class="btn btn-sm btn-success mt-2">
                                                        <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="card border">
                                                <div class="card-body text-center">
                                                    <h5 class="text-muted mb-2">Total Purchases</h5>
                                                    <h3 class="mb-0 text-primary">₹<?php echo $total_purchases; ?></h3>
                                                    <p class="text-muted mb-0">Lifetime value</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="card border">
                                                <div class="card-body text-center">
                                                    <h5 class="text-muted mb-2">Credit Limit</h5>
                                                    <h3 class="mb-0 text-info">₹<?php echo number_format($shop['credit_limit'], 2); ?></h3>
                                                    <p class="text-muted mb-0">Allowed limit</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Information -->
                                    <div class="mt-4">
                                        <h5 class="card-title mb-3">Payment Information</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Payment Terms</label>
                                                    <p class="mb-0">
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <?php echo ucfirst(str_replace('_', ' ', $shop['payment_terms'])); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted">Last Purchase</label>
                                                    <p class="fw-medium mb-0"><?php echo $last_purchase; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Additional Notes -->
                                    <div class="mt-4">
                                        <h5 class="card-title mb-3">Additional Notes</h5>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <textarea class="form-control" name="shop_notes" rows="4" 
                                                          placeholder="Add notes about this shop..."><?php echo htmlspecialchars($shop['notes'] ?? ''); ?></textarea>
                                            </div>
                                            <button type="submit" name="update_notes" class="btn btn-primary">
                                                <i class="mdi mdi-content-save me-1"></i> Save Notes
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Actions & History -->
                        <div class="col-lg-4">
                            <!-- Quick Actions Card -->
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Quick Actions</h5>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="tel:<?php echo $shop['customer_contact']; ?>" 
                                           class="btn btn-outline-primary btn-block text-start">
                                            <i class="mdi mdi-phone me-2"></i> Call Customer
                                        </a>
                                        
                                        <a href="whatsapp://send?phone=<?php echo $shop['customer_contact']; ?>&text=Hi <?php echo urlencode($shop['customer_name']); ?>, this is <?php echo $_SESSION['name']; ?> from APR Water Supply." 
                                           class="btn btn-outline-success btn-block text-start">
                                            <i class="mdi mdi-whatsapp me-2"></i> WhatsApp Message
                                        </a>
                                        
                                        <a href="quick-order.php?customer_id=<?php echo $shop_id; ?>" 
                                           class="btn btn-outline-info btn-block text-start">
                                            <i class="mdi mdi-cart-plus me-2"></i> New Order
                                        </a>
                                        
                                        <a href="collect-payment.php?customer_id=<?php echo $shop_id; ?>" 
                                           class="btn btn-outline-success btn-block text-start">
                                            <i class="mdi mdi-cash me-2"></i> Collect Payment
                                        </a>
                                        
                                        <button type="button" class="btn btn-outline-danger btn-block text-start" 
                                                data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                            <i class="mdi mdi-update me-2"></i> Update Status
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Transactions -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Recent Transactions</h5>
                                    
                                    <?php if ($transactions_result && mysqli_num_rows($transactions_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-centered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                                                <tr>
                                                    <td><?php echo date('d M', strtotime($transaction['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $transaction['type'] == 'payment' ? 'success' : 'info'; ?>-subtle text-<?php echo $transaction['type'] == 'payment' ? 'success' : 'info'; ?>">
                                                            <?php echo ucfirst($transaction['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="fw-medium">₹<?php echo number_format($transaction['amount'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-2">
                                        <a href="transactions.php?customer_id=<?php echo $shop_id; ?>" class="btn btn-sm btn-link">
                                            View All Transactions
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-cash-multiple display-4 text-muted"></i>
                                        <p class="text-muted mb-0">No transactions yet</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Orders -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Recent Orders</h5>
                                    
                                    <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-centered mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Items</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                                                <tr>
                                                    <td><?php echo date('d M', strtotime($order['order_date'])); ?></td>
                                                    <td><?php echo $order['total_items']; ?></td>
                                                    <td class="fw-medium">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-2">
                                        <a href="orders.php?customer_id=<?php echo $shop_id; ?>" class="btn btn-sm btn-link">
                                            View All Orders
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <i class="mdi mdi-cart-outline display-4 text-muted"></i>
                                        <p class="text-muted mb-0">No orders yet</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Shop Statistics -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Shop Statistics</h5>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Account Created</span>
                                                <span class="fw-medium"><?php echo $created_date; ?></span>
                                            </div>
                                        </li>
                                        <li class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Total Orders</span>
                                                <span class="fw-medium">
                                                    <?php echo mysqli_num_rows($orders_result); ?>+
                                                </span>
                                            </div>
                                        </li>
                                        <li class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Assigned Lineman</span>
                                                <span class="fw-medium">You (<?php echo $_SESSION['name']; ?>)</span>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Shop ID</span>
                                                <span class="fw-medium">#<?php echo $shop_id; ?></span>
                                            </div>
                                        </li>
                                    </ul>
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
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateStatusModalLabel">Update Shop Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">New Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $shop['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $shop['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="blocked" <?php echo $shop['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status_notes" class="form-label">Reason for Status Change</label>
                            <textarea class="form-control" id="status_notes" name="status_notes" 
                                      rows="3" placeholder="Optional: Add reason for status change..."></textarea>
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
        // Auto-refresh page on status update
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'update_status') {
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        });

        // WhatsApp message template
        document.querySelector('a[href*="whatsapp://"]').addEventListener('click', function(e) {
            if (!navigator.userAgent.match(/iPhone|iPad|iPod|Android/i)) {
                e.preventDefault();
                alert('Please open this page on your mobile device to use WhatsApp.');
            }
        });

        // Map integration
        document.querySelector('a[href*="maps.google.com"]').addEventListener('click', function(e) {
            // Add tracking for map views
            console.log('Customer location viewed on map');
        });

        // Status change confirmation
        document.querySelector('button[name="update_status"]').addEventListener('click', function(e) {
            const newStatus = document.querySelector('#status').value;
            const currentStatus = '<?php echo $shop['status']; ?>';
            
            if (newStatus === 'blocked' && currentStatus !== 'blocked') {
                if (!confirm('Are you sure you want to block this shop? This will prevent them from placing new orders.')) {
                    e.preventDefault();
                }
            }
        });

        // Quick print shop info
        document.addEventListener('keydown', function(e) {
            // Ctrl + P for quick print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Copy shop details to clipboard
        function copyShopDetails() {
            const details = `Shop: ${document.querySelector('.card-title').innerText}\n` +
                           `Contact: ${document.querySelector('a[href^="tel:"]').innerText}\n` +
                           `Location: ${document.querySelector('.mb-2').innerText}`;
            
            navigator.clipboard.writeText(details).then(() => {
                alert('Shop details copied to clipboard!');
            });
        }
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}
?>