<?php
session_start();
include('config/config.php');

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: customers.php');
    exit();
}

$customer_id = intval($_GET['id']);

// Function to format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    return $date ? date('d M, Y', strtotime($date)) : 'Never';
}

function formatDateTime($datetime) {
    return $datetime ? date('d M, Y h:i A', strtotime($datetime)) : 'Never';
}

// Fetch customer details
$customer_sql = "SELECT c.*, l.full_name as lineman_name 
                 FROM customers c 
                 LEFT JOIN linemen l ON c.assigned_lineman_id = l.id 
                 WHERE c.id = $customer_id";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Fetch customer orders (last 10)
$orders_sql = "SELECT o.*, 
                      (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
                      (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_quantity
               FROM orders o 
               WHERE o.customer_id = $customer_id 
               AND o.status != 'cancelled'
               ORDER BY o.order_date DESC 
               LIMIT 10";
$orders_result = mysqli_query($conn, $orders_sql);

// Fetch payment history
$payments_sql = "SELECT ph.*, o.order_number 
                 FROM payment_history ph 
                 JOIN orders o ON ph.order_id = o.id 
                 WHERE o.customer_id = $customer_id 
                 ORDER BY ph.created_at DESC 
                 LIMIT 10";
$payments_result = mysqli_query($conn, $payments_sql);

// Calculate statistics
$stats_sql = "SELECT 
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_spent,
                SUM(o.paid_amount) as total_paid,
                SUM(o.pending_amount) as total_pending,
                AVG(o.total_amount) as avg_order_value,
                MIN(o.order_date) as first_order_date,
                MAX(o.order_date) as last_order_date
              FROM orders o 
              WHERE o.customer_id = $customer_id 
              AND o.status != 'cancelled'";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch linemen for dropdown
$linemen_sql = "SELECT id, full_name FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_result = mysqli_query($conn, $linemen_sql);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_customer'])) {
        // Update customer details
        $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
        $shop_name = mysqli_real_escape_string($conn, $_POST['shop_name']);
        $customer_contact = mysqli_real_escape_string($conn, $_POST['customer_contact']);
        $alternate_contact = mysqli_real_escape_string($conn, $_POST['alternate_contact']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $shop_location = mysqli_real_escape_string($conn, $_POST['shop_location']);
        $customer_type = mysqli_real_escape_string($conn, $_POST['customer_type']);
        $assigned_lineman_id = intval($_POST['assigned_lineman_id']);
        $assigned_area = mysqli_real_escape_string($conn, $_POST['assigned_area']);
        $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
        $credit_limit = floatval($_POST['credit_limit']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        
        $update_sql = "UPDATE customers SET 
                        customer_name = '$customer_name',
                        shop_name = '$shop_name',
                        customer_contact = '$customer_contact',
                        alternate_contact = '$alternate_contact',
                        email = '$email',
                        shop_location = '$shop_location',
                        customer_type = '$customer_type',
                        assigned_lineman_id = '$assigned_lineman_id',
                        assigned_area = '$assigned_area',
                        payment_terms = '$payment_terms',
                        credit_limit = '$credit_limit',
                        notes = '$notes',
                        status = '$status'
                      WHERE id = $customer_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $success_msg = "Customer updated successfully!";
            // Refresh customer data
            $customer_result = mysqli_query($conn, $customer_sql);
            $customer = mysqli_fetch_assoc($customer_result);
        } else {
            $error_msg = "Error updating customer: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['update_balance'])) {
        // Update customer balance
        $adjustment_type = $_POST['adjustment_type'];
        $amount = floatval($_POST['amount']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        
        $new_balance = $customer['current_balance'];
        if ($adjustment_type == 'add') {
            $new_balance += $amount;
        } else {
            $new_balance -= $amount;
        }
        
        $update_balance_sql = "UPDATE customers SET current_balance = '$new_balance' WHERE id = $customer_id";
        
        if (mysqli_query($conn, $update_balance_sql)) {
            // Log the balance adjustment
            $log_sql = "INSERT INTO status_logs (customer_id, old_status, new_status, notes) 
                        VALUES ($customer_id, 'balance_adjusted', 'balance_adjusted', 
                        'Balance $adjustment_type: ₹$amount. New balance: ₹$new_balance. Reason: $reason')";
            mysqli_query($conn, $log_sql);
            
            $success_msg = "Balance updated successfully!";
            // Refresh customer data
            $customer_result = mysqli_query($conn, $customer_sql);
            $customer = mysqli_fetch_assoc($customer_result);
        } else {
            $error_msg = "Error updating balance: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['add_note'])) {
        // Add note
        $note = mysqli_real_escape_string($conn, $_POST['note']);
        $note_sql = "INSERT INTO status_logs (customer_id, notes, changed_by) 
                     VALUES ($customer_id, '$note', {$_SESSION['user_id']})";
        
        if (mysqli_query($conn, $note_sql)) {
            $success_msg = "Note added successfully!";
        } else {
            $error_msg = "Error adding note: " . mysqli_error($conn);
        }
    } elseif (isset($_POST['delete_customer'])) {
        // Soft delete customer
        $delete_sql = "UPDATE customers SET status = 'inactive' WHERE id = $customer_id";
        
        if (mysqli_query($conn, $delete_sql)) {
            // Log the deletion
            $log_sql = "INSERT INTO deletion_logs (table_name, record_id, action, data, deleted_by) 
                        VALUES ('customers', $customer_id, 'soft_delete', 
                        'Customer ID: $customer_id, Name: {$customer['customer_name']}', {$_SESSION['user_id']})";
            mysqli_query($conn, $log_sql);
            
            header('Location: customers.php?msg=deleted');
            exit();
        } else {
            $error_msg = "Error deleting customer: " . mysqli_error($conn);
        }
    }
}

// Fetch notes history
$notes_sql = "SELECT * FROM status_logs 
              WHERE customer_id = $customer_id 
              AND notes IS NOT NULL 
              AND notes != ''
              ORDER BY created_at DESC 
              LIMIT 20";
$notes_result = mysqli_query($conn, $notes_sql);
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



                <!-- Messages -->
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-block-helper me-2"></i>
                        <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Customer Header -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-4">
                                        <div class="avatar-lg">
                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                <?php echo strtoupper(substr($customer['customer_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h4 class="card-title mb-1"><?php echo htmlspecialchars($customer['customer_name']); ?></h4>
                                                <p class="text-muted mb-2"><?php echo htmlspecialchars($customer['shop_name']); ?></p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge bg-info-subtle text-info">
                                                        <?php echo ucfirst($customer['customer_type']); ?>
                                                    </span>
                                                    <span class="badge <?php echo $customer['status'] == 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                                                        <?php echo ucfirst($customer['status']); ?>
                                                    </span>
                                                    <span class="badge bg-secondary-subtle text-secondary">
                                                        <?php echo $customer['customer_code']; ?>
                                                    </span>
                                                    <?php if ($customer['lineman_name']): ?>
                                                        <span class="badge bg-warning-subtle text-warning">
                                                            <i class="mdi mdi-account-hard-hat me-1"></i><?php echo $customer['lineman_name']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="add-order.php?customer_id=<?php echo $customer_id; ?>">
                                                            <i class="mdi mdi-plus-circle me-1"></i> Create New Order
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="customer-transactions.php?id=<?php echo $customer_id; ?>">
                                                            <i class="mdi mdi-history me-1"></i> View Transactions
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#balanceModal">
                                                            <i class="mdi mdi-cash-sync me-1"></i> Adjust Balance
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                                            <i class="mdi mdi-delete-outline me-1"></i> Delete Customer
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-cart-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Orders</p>
                                        <h4 class="mb-0"><?php echo $stats['total_orders'] ?? 0; ?></h4>
                                        <p class="text-muted mb-0">All time</p>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Spent</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($stats['total_spent'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Lifetime value</p>
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
                                            <i class="mdi mdi-cash-clock"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Pending Balance</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($customer['current_balance']); ?></h4>
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
                                            <i class="mdi mdi-cash"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Avg Order Value</p>
                                        <h4 class="mb-0">
                                            <?php 
                                            $avg = ($stats['total_orders'] ?? 0) > 0 
                                                ? ($stats['total_spent'] ?? 0) / $stats['total_orders'] 
                                                : 0;
                                            echo formatCurrency($avg);
                                            ?>
                                        </h4>
                                        <p class="text-muted mb-0">Per order</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Details Tabs -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#details" role="tab">
                                            <i class="mdi mdi-account-details me-1"></i> Details
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#orders" role="tab">
                                            <i class="mdi mdi-cart-outline me-1"></i> Orders
                                            <span class="badge bg-danger ms-1"><?php echo $stats['total_orders'] ?? 0; ?></span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab">
                                            <i class="mdi mdi-cash-check me-1"></i> Payments
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#notes" role="tab">
                                            <i class="mdi mdi-note-text me-1"></i> Notes
                                        </a>
                                    </li>
                                </ul>

                                <div class="tab-content p-3">
                                    <!-- Details Tab -->
                                    <div class="tab-pane active" id="details" role="tabpanel">
                                        <form method="POST" action="">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Customer Name *</label>
                                                        <input type="text" class="form-control" name="customer_name" 
                                                               value="<?php echo htmlspecialchars($customer['customer_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Shop Name *</label>
                                                        <input type="text" class="form-control" name="shop_name" 
                                                               value="<?php echo htmlspecialchars($customer['shop_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Contact Number *</label>
                                                        <input type="tel" class="form-control" name="customer_contact" 
                                                               value="<?php echo htmlspecialchars($customer['customer_contact']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Alternate Contact</label>
                                                        <input type="tel" class="form-control" name="alternate_contact" 
                                                               value="<?php echo htmlspecialchars($customer['alternate_contact']); ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Email Address</label>
                                                        <input type="email" class="form-control" name="email" 
                                                               value="<?php echo htmlspecialchars($customer['email']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Shop Location *</label>
                                                        <textarea class="form-control" name="shop_location" rows="3" required><?php echo htmlspecialchars($customer['shop_location']); ?></textarea>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Customer Type</label>
                                                                <select class="form-select" name="customer_type">
                                                                    <option value="retail" <?php echo $customer['customer_type'] == 'retail' ? 'selected' : ''; ?>>Retail</option>
                                                                    <option value="wholesale" <?php echo $customer['customer_type'] == 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                                                    <option value="hotel" <?php echo $customer['customer_type'] == 'hotel' ? 'selected' : ''; ?>>Hotel</option>
                                                                    <option value="office" <?php echo $customer['customer_type'] == 'office' ? 'selected' : ''; ?>>Office</option>
                                                                    <option value="residential" <?php echo $customer['customer_type'] == 'residential' ? 'selected' : ''; ?>>Residential</option>
                                                                    <option value="other" <?php echo $customer['customer_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status">
                                                                    <option value="active" <?php echo $customer['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                    <option value="inactive" <?php echo $customer['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                    <option value="blocked" <?php echo $customer['status'] == 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Assigned Lineman</label>
                                                                <select class="form-select" name="assigned_lineman_id">
                                                                    <option value="">Select Lineman</option>
                                                                    <?php while ($lineman = mysqli_fetch_assoc($linemen_result)): ?>
                                                                        <option value="<?php echo $lineman['id']; ?>" 
                                                                            <?php echo $customer['assigned_lineman_id'] == $lineman['id'] ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($lineman['full_name']); ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                    <?php mysqli_data_seek($linemen_result, 0); ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Assigned Area</label>
                                                                <input type="text" class="form-control" name="assigned_area" 
                                                                       value="<?php echo htmlspecialchars($customer['assigned_area']); ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Terms</label>
                                                                <select class="form-select" name="payment_terms">
                                                                    <option value="cash" <?php echo $customer['payment_terms'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                                    <option value="credit_7" <?php echo $customer['payment_terms'] == 'credit_7' ? 'selected' : ''; ?>>7 Days Credit</option>
                                                                    <option value="credit_15" <?php echo $customer['payment_terms'] == 'credit_15' ? 'selected' : ''; ?>>15 Days Credit</option>
                                                                    <option value="credit_30" <?php echo $customer['payment_terms'] == 'credit_30' ? 'selected' : ''; ?>>30 Days Credit</option>
                                                                    <option value="prepaid" <?php echo $customer['payment_terms'] == 'prepaid' ? 'selected' : ''; ?>>Prepaid</option>
                                                                    <option value="weekly" <?php echo $customer['payment_terms'] == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                                                    <option value="monthly" <?php echo $customer['payment_terms'] == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Credit Limit</label>
                                                                <input type="number" class="form-control" name="credit_limit" step="0.01" min="0"
                                                                       value="<?php echo $customer['credit_limit']; ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">Notes</label>
                                                        <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <span class="text-muted">Created: <?php echo formatDateTime($customer['created_at']); ?></span>
                                                            <?php if ($customer['updated_at'] != $customer['created_at']): ?>
                                                                <span class="text-muted ms-3">Last updated: <?php echo formatDateTime($customer['updated_at']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <button type="submit" name="update_customer" class="btn btn-primary">
                                                                <i class="mdi mdi-content-save me-1"></i> Save Changes
                                                            </button>
                                                            <a href="customers.php" class="btn btn-secondary ms-1">
                                                                <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Orders Tab -->
                                    <div class="tab-pane" id="orders" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5>Recent Orders</h5>
                                            <a href="add-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success btn-sm">
                                                <i class="mdi mdi-plus-circle me-1"></i> New Order
                                            </a>
                                        </div>
                                        <?php if (mysqli_num_rows($orders_result) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover table-sm">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Order #</th>
                                                            <th>Date</th>
                                                            <th>Status</th>
                                                            <th>Items</th>
                                                            <th>Total Amount</th>
                                                            <th>Paid</th>
                                                            <th>Pending</th>
                                                            <th>Payment Status</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                                                            <?php
                                                            $status_class = '';
                                                            if ($order['status'] == 'delivered') $status_class = 'badge-soft-success';
                                                            elseif ($order['status'] == 'processing') $status_class = 'badge-soft-info';
                                                            elseif ($order['status'] == 'pending') $status_class = 'badge-soft-warning';
                                                            
                                                            $payment_class = '';
                                                            if ($order['payment_status'] == 'paid') $payment_class = 'badge-soft-success';
                                                            elseif ($order['payment_status'] == 'partial') $payment_class = 'badge-soft-warning';
                                                            else $payment_class = 'badge-soft-danger';
                                                            ?>
                                                            <tr>
                                                                <td><a href="order-view.php?id=<?php echo $order['id']; ?>" class="text-primary"><?php echo $order['order_number']; ?></a></td>
                                                                <td><?php echo formatDate($order['order_date']); ?></td>
                                                                <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                                                <td><?php echo $order['item_count']; ?> (<?php echo $order['total_quantity']; ?> qty)</td>
                                                                <td class="fw-bold"><?php echo formatCurrency($order['total_amount']); ?></td>
                                                                <td class="text-success"><?php echo formatCurrency($order['paid_amount']); ?></td>
                                                                <td class="text-danger"><?php echo formatCurrency($order['pending_amount']); ?></td>
                                                                <td><span class="badge <?php echo $payment_class; ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                                                                <td>
                                                                    <a href="order-view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light">
                                                                        <i class="mdi mdi-eye"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="text-center mt-3">
                                                <a href="customer-orders.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="mdi mdi-view-list me-1"></i> View All Orders
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="mdi mdi-cart-off display-4 text-muted"></i>
                                                <h5 class="mt-3">No Orders Yet</h5>
                                                <p class="text-muted">This customer hasn't placed any orders yet.</p>
                                                <a href="add-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary mt-2">
                                                    <i class="mdi mdi-plus-circle me-1"></i> Create First Order
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Payments Tab -->
                                    <div class="tab-pane" id="payments" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5>Payment History</h5>
                                            <?php if ($customer['current_balance'] > 0): ?>
                                                <a href="javascript:void(0)" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#receivePaymentModal">
                                                    <i class="mdi mdi-cash-check me-1"></i> Receive Payment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (mysqli_num_rows($payments_result) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover table-sm">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Order #</th>
                                                            <th>Amount</th>
                                                            <th>Method</th>
                                                            <th>Reference</th>
                                                            <th>Notes</th>
                                                            <th>Received By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php while ($payment = mysqli_fetch_assoc($payments_result)): ?>
                                                            <tr>
                                                                <td><?php echo formatDateTime($payment['created_at']); ?></td>
                                                                <td><a href="order-view.php?id=<?php echo $payment['order_id']; ?>" class="text-primary"><?php echo $payment['order_number']; ?></a></td>
                                                                <td class="text-success fw-bold"><?php echo formatCurrency($payment['amount_paid']); ?></td>
                                                                <td><span class="badge bg-secondary-subtle text-secondary"><?php echo ucfirst($payment['payment_method']); ?></span></td>
                                                                <td><small><?php echo $payment['reference_no']; ?></small></td>
                                                                <td><small><?php echo $payment['notes']; ?></small></td>
                                                                <td>System</td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="text-center mt-3">
                                                <a href="customer-transactions.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="mdi mdi-history me-1"></i> View All Transactions
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="mdi mdi-cash-remove display-4 text-muted"></i>
                                                <h5 class="mt-3">No Payments Yet</h5>
                                                <p class="text-muted">No payment history found for this customer.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Notes Tab -->
                                    <div class="tab-pane" id="notes" role="tabpanel">
                                        <div class="row">
                                            <div class="col-lg-8">
                                                <h5 class="mb-3">Notes History</h5>
                                                <?php if (mysqli_num_rows($notes_result) > 0): ?>
                                                    <div class="timeline">
                                                        <?php while ($note = mysqli_fetch_assoc($notes_result)): ?>
                                                            <div class="timeline-item mb-3">
                                                                <div class="card">
                                                                    <div class="card-body">
                                                                        <div class="d-flex justify-content-between align-items-start">
                                                                            <div>
                                                                                <p class="mb-1"><?php echo htmlspecialchars($note['notes']); ?></p>
                                                                                <small class="text-muted">
                                                                                    <?php echo formatDateTime($note['created_at']); ?>
                                                                                    <?php if ($note['changed_by']): ?>
                                                                                        • By User #<?php echo $note['changed_by']; ?>
                                                                                    <?php endif; ?>
                                                                                </small>
                                                                            </div>
                                                                            <small class="text-muted">
                                                                                <?php 
                                                                                if (strpos($note['notes'], 'Balance') !== false) {
                                                                                    echo '<span class="badge bg-info-subtle text-info">Balance</span>';
                                                                                } elseif (strpos($note['notes'], 'Order') !== false) {
                                                                                    echo '<span class="badge bg-success-subtle text-success">Order</span>';
                                                                                } else {
                                                                                    echo '<span class="badge bg-secondary-subtle text-secondary">Note</span>';
                                                                                }
                                                                                ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endwhile; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center py-5">
                                                        <i class="mdi mdi-note-outline display-4 text-muted"></i>
                                                        <h5 class="mt-3">No Notes Yet</h5>
                                                        <p class="text-muted">No notes found for this customer.</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h5 class="card-title">Add New Note</h5>
                                                        <form method="POST" action="">
                                                            <div class="mb-3">
                                                                <label class="form-label">Note</label>
                                                                <textarea class="form-control" name="note" rows="4" placeholder="Enter your note here..." required></textarea>
                                                            </div>
                                                            <button type="submit" name="add_note" class="btn btn-primary w-100">
                                                                <i class="mdi mdi-note-plus me-1"></i> Add Note
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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

<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceModal" tabindex="-1" aria-labelledby="balanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="balanceModalLabel">Adjust Customer Balance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" value="<?php echo formatCurrency($customer['current_balance']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select class="form-select" name="adjustment_type" required>
                            <option value="add">Add to Balance</option>
                            <option value="subtract">Subtract from Balance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="2" required placeholder="Enter reason for adjustment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_balance" class="btn btn-primary">Adjust Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Receive Payment Modal -->
<div class="modal fade" id="receivePaymentModal" tabindex="-1" aria-labelledby="receivePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="process-payment.php">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="receivePaymentModalLabel">Receive Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($customer['customer_name']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" value="<?php echo formatCurrency($customer['current_balance']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" name="amount" step="0.01" min="0.01" max="<?php echo $customer['current_balance']; ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" name="reference_no" placeholder="Transaction ID, UPI Ref, Cheque No., etc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Any additional information..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Receive Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteModalLabel">Delete Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="mdi mdi-alert-circle-outline display-4 text-danger"></i>
                    </div>
                    <h5 class="text-center">Are you sure?</h5>
                    <p class="text-center">You are about to delete customer:</p>
                    <div class="alert alert-warning text-center">
                        <strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($customer['shop_name']); ?> - <?php echo $customer['customer_code']; ?></small>
                    </div>
                    <div class="alert alert-danger">
                        <small>
                            <i class="mdi mdi-information-outline me-1"></i>
                            This action will mark the customer as inactive. The customer will no longer appear in active lists.
                            Existing orders and transactions will be preserved.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_customer" class="btn btn-danger">Delete Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php')?>
<?php include('includes/scripts.php')?>

<script>
// Initialize tab functionality
document.addEventListener('DOMContentLoaded', function() {
    // Enable tab functionality
    const tabTriggers = [].slice.call(document.querySelectorAll('[data-bs-toggle="tab"]'));
    tabTriggers.forEach(function (tabTriggerEl) {
        tabTriggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                // Hide all tab panes
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                // Remove active from all tabs
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                // Show selected tab
                this.classList.add('active');
                target.classList.add('show', 'active');
            }
        });
    });

    // Form validation for balance adjustment
    const balanceForm = document.querySelector('#balanceModal form');
    if (balanceForm) {
        balanceForm.addEventListener('submit', function(e) {
            const amount = this.querySelector('input[name="amount"]').value;
            if (!amount || parseFloat(amount) <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount');
                return false;
            }
            return true;
        });
    }
    
    // Form validation for payment
    const paymentForm = document.querySelector('#receivePaymentModal form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            const amount = this.querySelector('input[name="amount"]').value;
            const maxAmount = parseFloat(this.querySelector('input[name="amount"]').max);
            
            if (!amount || parseFloat(amount) <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount');
                return false;
            }
            
            if (parseFloat(amount) > maxAmount) {
                e.preventDefault();
                alert('Payment amount cannot exceed current balance');
                return false;
            }
            
            const paymentMethod = this.querySelector('select[name="payment_method"]').value;
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}
.timeline-item {
    position: relative;
    padding-left: 10px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 10px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #0dcaf0;
    border: 2px solid white;
}
</style>

</body>
</html>
<?php mysqli_close($conn); ?>