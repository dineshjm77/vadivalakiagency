<!doctype html>
<html lang="en">

<?php include('includes/head.php')?>

<body data-sidebar="dark">

<!-- Loader -->
<?php include('includes/pre-loader.php')?>

<!-- Begin page -->
<div id="layout-wrapper">

<?php include('includes/topbar.php')?>    

    <!-- ========== Left Sidebar Start ========== -->
    <div class="vertical-menu">

        <div data-simplebar class="h-100">

            <!--- Sidemenu -->
            <?php include('includes/sidebar.php')?>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- Left Sidebar End -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
           
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Customer History</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="customers-list.php">Customers</a></li>
                                    <li class="breadcrumb-item active">Customer History</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <?php
                // Database connection
                include('config/config.php');
                
                // Initialize variables
                $customer = null;
                $customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                $message = '';
                $message_type = '';
                
                if ($customer_id <= 0) {
                    // Redirect to customers list if no ID provided
                    header('Location: customers-list.php');
                    exit;
                }
                
                // Create necessary tables if they don't exist
                $tables = [
                    'orders' => "CREATE TABLE IF NOT EXISTS orders (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        order_no VARCHAR(50) UNIQUE NOT NULL,
                        customer_id INT NOT NULL,
                        customer_name VARCHAR(100) NOT NULL,
                        customer_contact VARCHAR(15) NOT NULL,
                        order_date DATE NOT NULL,
                        delivery_date DATE,
                        total_amount DECIMAL(10,2) NOT NULL,
                        discount_amount DECIMAL(10,2) DEFAULT 0,
                        tax_amount DECIMAL(10,2) DEFAULT 0,
                        grand_total DECIMAL(10,2) NOT NULL,
                        paid_amount DECIMAL(10,2) DEFAULT 0,
                        balance_amount DECIMAL(10,2) DEFAULT 0,
                        payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
                        order_status ENUM('pending', 'processing', 'delivered', 'cancelled') DEFAULT 'pending',
                        notes TEXT,
                        created_by INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                    )",
                    
                    'order_items' => "CREATE TABLE IF NOT EXISTS order_items (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        order_id INT NOT NULL,
                        product_id INT NOT NULL,
                        product_name VARCHAR(150) NOT NULL,
                        quantity INT NOT NULL,
                        unit_price DECIMAL(10,2) NOT NULL,
                        total_price DECIMAL(10,2) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
                    )",
                    
                    'payments' => "CREATE TABLE IF NOT EXISTS payments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        payment_no VARCHAR(50) UNIQUE NOT NULL,
                        order_id INT,
                        customer_id INT NOT NULL,
                        payment_date DATE NOT NULL,
                        payment_amount DECIMAL(10,2) NOT NULL,
                        payment_mode ENUM('cash', 'cheque', 'bank_transfer', 'card', 'upi', 'credit') DEFAULT 'cash',
                        cheque_no VARCHAR(50),
                        bank_name VARCHAR(100),
                        transaction_id VARCHAR(100),
                        notes TEXT,
                        received_by INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                    )",
                    
                    'customer_activity_logs' => "CREATE TABLE IF NOT EXISTS customer_activity_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        customer_id INT NOT NULL,
                        activity_type VARCHAR(50) NOT NULL,
                        activity_details TEXT,
                        performed_by INT,
                        performed_by_type ENUM('admin', 'lineman', 'customer') DEFAULT 'admin',
                        ip_address VARCHAR(45),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
                    )"
                ];
                
                // Create tables
                foreach ($tables as $sql) {
                    mysqli_query($conn, $sql);
                }
                
                // Fetch customer details
                $sql = "SELECT c.*, l.full_name as lineman_name, l.employee_id as lineman_code 
                        FROM customers c 
                        LEFT JOIN linemen l ON c.assigned_lineman_id = l.id 
                        WHERE c.id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $customer_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $customer = mysqli_fetch_assoc($result);
                } else {
                    $message = 'Customer not found!';
                    $message_type = 'danger';
                }
                
                // Fetch customer orders
                $orders = [];
                $orders_sql = "SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC";
                $orders_stmt = mysqli_prepare($conn, $orders_sql);
                mysqli_stmt_bind_param($orders_stmt, "i", $customer_id);
                mysqli_stmt_execute($orders_stmt);
                $orders_result = mysqli_stmt_get_result($orders_stmt);
                
                if ($orders_result) {
                    while ($row = mysqli_fetch_assoc($orders_result)) {
                        $orders[] = $row;
                    }
                }
                
                // Fetch customer payments
                $payments = [];
                $payments_sql = "SELECT * FROM payments WHERE customer_id = ? ORDER BY payment_date DESC";
                $payments_stmt = mysqli_prepare($conn, $payments_sql);
                mysqli_stmt_bind_param($payments_stmt, "i", $customer_id);
                mysqli_stmt_execute($payments_stmt);
                $payments_result = mysqli_stmt_get_result($payments_stmt);
                
                if ($payments_result) {
                    while ($row = mysqli_fetch_assoc($payments_result)) {
                        $payments[] = $row;
                    }
                }
                
                // Fetch customer activity logs
                $activities = [];
                $activities_sql = "SELECT * FROM customer_activity_logs WHERE customer_id = ? ORDER BY created_at DESC";
                $activities_stmt = mysqli_prepare($conn, $activities_sql);
                mysqli_stmt_bind_param($activities_stmt, "i", $customer_id);
                mysqli_stmt_execute($activities_stmt);
                $activities_result = mysqli_stmt_get_result($activities_stmt);
                
                if ($activities_result) {
                    while ($row = mysqli_fetch_assoc($activities_result)) {
                        $activities[] = $row;
                    }
                }
                
                // Calculate statistics
                $total_orders = count($orders);
                $total_payments = count($payments);
                $total_spent = 0;
                $total_paid = 0;
                $total_pending = 0;
                
                foreach ($orders as $order) {
                    $total_spent += $order['grand_total'];
                    $total_paid += $order['paid_amount'];
                    $total_pending += $order['balance_amount'];
                }
                
                // Close connection
                mysqli_close($conn);
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($customer): ?>
                <!-- Customer Information Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-lg">
                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle display-4">
                                                        <?php echo strtoupper(substr($customer['customer_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h4 class="mb-1"><?php echo htmlspecialchars($customer['customer_name']); ?></h4>
                                                <p class="text-muted mb-0">
                                                    <i class="mdi mdi-store me-1"></i> <?php echo htmlspecialchars($customer['shop_name']); ?>
                                                </p>
                                                <p class="text-muted mb-0">
                                                    <i class="mdi mdi-phone me-1"></i> <?php echo htmlspecialchars($customer['customer_contact']); ?>
                                                    <?php if ($customer['alternate_contact']): ?>
                                                    / <?php echo htmlspecialchars($customer['alternate_contact']); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-muted mb-0">
                                                    <i class="mdi mdi-map-marker me-1"></i> <?php echo htmlspecialchars($customer['shop_location']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Customer Code</label>
                                                    <input type="text" class="form-control" value="<?php echo $customer['customer_code']; ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Customer Type</label>
                                                    <input type="text" class="form-control" value="<?php echo ucfirst($customer['customer_type']); ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Payment Terms</label>
                                                    <input type="text" class="form-control" 
                                                           value="<?php echo ucfirst(str_replace('_', ' ', $customer['payment_terms'])); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Assigned Line Man</label>
                                                    <?php if ($customer['lineman_name']): ?>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" 
                                                               value="<?php echo htmlspecialchars($customer['lineman_name']); ?>" readonly>
                                                        <span class="input-group-text"><?php echo $customer['lineman_code']; ?></span>
                                                    </div>
                                                    <?php else: ?>
                                                    <input type="text" class="form-control" value="Not Assigned" readonly>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Account Status</label>
                                                    <input type="text" class="form-control" 
                                                           value="<?php echo ucfirst($customer['status']); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <div class="avatar-lg mx-auto mb-3">
                                                <div class="avatar-title bg-success-subtle text-success rounded-circle display-4">
                                                    <i class="mdi mdi-account"></i>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2">
                                                <a href="edit-customer.php?id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                                    <i class="mdi mdi-pencil-outline me-1"></i> Edit Customer
                                                </a>
                                                <a href="new-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">
                                                    <i class="mdi mdi-cart-plus me-1"></i> New Order
                                                </a>
                                                <a href="add-payment.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-info">
                                                    <i class="mdi mdi-cash-plus me-1"></i> Add Payment
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-cart"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Orders</p>
                                        <h4 class="mb-0"><?php echo $total_orders; ?></h4>
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
                                            <i class="mdi mdi-currency-inr"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Spent</p>
                                        <h4 class="mb-0">₹<?php echo number_format($total_spent, 2); ?></h4>
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
                                            <i class="mdi mdi-cash"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Paid</p>
                                        <h4 class="mb-0">₹<?php echo number_format($total_paid, 2); ?></h4>
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
                                            <i class="mdi mdi-alert-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Balance Due</p>
                                        <h4 class="mb-0">₹<?php echo number_format($total_pending, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="row mt-4">
                    <div class="col-lg-12">
                        <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#orders" role="tab">
                                    <i class="mdi mdi-cart me-1"></i> Orders (<?php echo $total_orders; ?>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab">
                                    <i class="mdi mdi-cash me-1"></i> Payments (<?php echo $total_payments; ?>)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#activity" role="tab">
                                    <i class="mdi mdi-history me-1"></i> Activity Logs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#details" role="tab">
                                    <i class="mdi mdi-information me-1"></i> Customer Details
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content p-3 border border-top-0 rounded-bottom">
                            <!-- Orders Tab -->
                            <div class="tab-pane fade show active" id="orders" role="tabpanel">
                                <div class="table-responsive">
                                    <?php if ($total_orders > 0): ?>
                                    <table class="table table-hover table-centered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order No</th>
                                                <th>Date</th>
                                                <th>Delivery Date</th>
                                                <th>Items</th>
                                                <th>Total Amount</th>
                                                <th>Paid</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $order['order_no']; ?></strong>
                                                </td>
                                                <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                                <td>
                                                    <?php if ($order['delivery_date']): ?>
                                                    <?php echo date('d M, Y', strtotime($order['delivery_date'])); ?>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Fetch order items count (you would need to query order_items table)
                                                    // This is a placeholder - implement actual item count query
                                                    ?>
                                                    <span class="badge bg-info">N/A</span>
                                                </td>
                                                <td>₹<?php echo number_format($order['grand_total'], 2); ?></td>
                                                <td>₹<?php echo number_format($order['paid_amount'], 2); ?></td>
                                                <td>
                                                    <?php if ($order['balance_amount'] > 0): ?>
                                                    <span class="text-danger">₹<?php echo number_format($order['balance_amount'], 2); ?></span>
                                                    <?php else: ?>
                                                    <span class="text-success">₹0.00</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        if ($order['order_status'] == 'delivered') echo 'badge-soft-success';
                                                        elseif ($order['order_status'] == 'processing') echo 'badge-soft-primary';
                                                        elseif ($order['order_status'] == 'cancelled') echo 'badge-soft-danger';
                                                        else echo 'badge-soft-warning';
                                                        ?>">
                                                        <?php echo ucfirst($order['order_status']); ?>
                                                    </span>
                                                    <br>
                                                    <span class="badge 
                                                        <?php 
                                                        if ($order['payment_status'] == 'paid') echo 'badge-soft-success';
                                                        elseif ($order['payment_status'] == 'partial') echo 'badge-soft-info';
                                                        else echo 'badge-soft-danger';
                                                        ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" 
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="mdi mdi-dots-horizontal"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item" href="order-view.php?id=<?php echo $order['id']; ?>">
                                                                    <i class="mdi mdi-eye-outline me-1"></i> View Order
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="invoice.php?order_id=<?php echo $order['id']; ?>">
                                                                    <i class="mdi mdi-file-document-outline me-1"></i> View Invoice
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="add-payment.php?order_id=<?php echo $order['id']; ?>">
                                                                    <i class="mdi mdi-cash-plus me-1"></i> Add Payment
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="mdi mdi-cart-off display-4 text-muted"></i>
                                        <h5 class="mt-3">No Orders Found</h5>
                                        <p class="text-muted">This customer hasn't placed any orders yet.</p>
                                        <a href="new-order.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                            <i class="mdi mdi-cart-plus me-1"></i> Create First Order
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Payments Tab -->
                            <div class="tab-pane fade" id="payments" role="tabpanel">
                                <div class="table-responsive">
                                    <?php if ($total_payments > 0): ?>
                                    <table class="table table-hover table-centered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Payment No</th>
                                                <th>Date</th>
                                                <th>Order No</th>
                                                <th>Amount</th>
                                                <th>Payment Mode</th>
                                                <th>Reference</th>
                                                <th>Notes</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $payment['payment_no']; ?></strong>
                                                </td>
                                                <td><?php echo date('d M, Y', strtotime($payment['payment_date'])); ?></td>
                                                <td>
                                                    <?php if ($payment['order_id']): ?>
                                                    <span class="badge bg-info">#<?php echo $payment['order_id']; ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">Direct Payment</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold">
                                                        ₹<?php echo number_format($payment['payment_amount'], 2); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_mode'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($payment['cheque_no']): ?>
                                                    <small>Cheque: <?php echo $payment['cheque_no']; ?></small>
                                                    <?php elseif ($payment['transaction_id']): ?>
                                                    <small>Txn: <?php echo substr($payment['transaction_id'], 0, 10); ?>...</small>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($payment['notes'], 0, 30)); ?>...</small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-light" 
                                                            onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                        <i class="mdi mdi-eye-outline"></i>
                                                    </button>
                                                    <a href="receipt.php?payment_id=<?php echo $payment['id']; ?>" 
                                                       class="btn btn-sm btn-info" target="_blank">
                                                        <i class="mdi mdi-receipt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="mdi mdi-cash-off display-4 text-muted"></i>
                                        <h5 class="mt-3">No Payments Found</h5>
                                        <p class="text-muted">No payment records found for this customer.</p>
                                        <a href="add-payment.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-success">
                                            <i class="mdi mdi-cash-plus me-1"></i> Add Payment
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Activity Logs Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel">
                                <div class="timeline">
                                    <?php if (!empty($activities)): ?>
                                        <?php foreach ($activities as $activity): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-item-marker">
                                                <div class="timeline-item-marker-text">
                                                    <?php echo date('h:i A', strtotime($activity['created_at'])); ?>
                                                </div>
                                                <div class="timeline-item-marker-indicator 
                                                    <?php 
                                                    if (strpos($activity['activity_type'], 'payment') !== false) echo 'bg-success';
                                                    elseif (strpos($activity['activity_type'], 'order') !== false) echo 'bg-primary';
                                                    elseif (strpos($activity['activity_type'], 'created') !== false) echo 'bg-info';
                                                    else echo 'bg-secondary';
                                                    ?>">
                                                </div>
                                            </div>
                                            <div class="timeline-item-content">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h6 class="card-title mb-1">
                                                            <?php echo ucwords(str_replace('_', ' ', $activity['activity_type'])); ?>
                                                        </h6>
                                                        <p class="card-text text-muted mb-2">
                                                            <?php echo $activity['activity_details']; ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <?php echo date('d M, Y', strtotime($activity['created_at'])); ?>
                                                            <?php if ($activity['performed_by_type']): ?>
                                                            • By <?php echo $activity['performed_by_type']; ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="mdi mdi-history-off display-4 text-muted"></i>
                                        <h5 class="mt-3">No Activity Logs</h5>
                                        <p class="text-muted">No activity recorded for this customer yet.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Customer Details Tab -->
                            <div class="tab-pane fade" id="details" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Contact Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="40%">Shop Name:</th>
                                                        <td><?php echo htmlspecialchars($customer['shop_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Customer Name:</th>
                                                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Primary Contact:</th>
                                                        <td><?php echo htmlspecialchars($customer['customer_contact']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Alternate Contact:</th>
                                                        <td><?php echo $customer['alternate_contact'] ? htmlspecialchars($customer['alternate_contact']) : '-'; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Email:</th>
                                                        <td><?php echo $customer['email'] ? htmlspecialchars($customer['email']) : '-'; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Location:</th>
                                                        <td><?php echo htmlspecialchars($customer['shop_location']); ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Business Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="40%">Customer Code:</th>
                                                        <td><?php echo $customer['customer_code']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Customer Type:</th>
                                                        <td>
                                                            <span class="badge 
                                                                <?php 
                                                                if ($customer['customer_type'] == 'wholesale') echo 'bg-primary';
                                                                elseif ($customer['customer_type'] == 'retail') echo 'bg-success';
                                                                else echo 'bg-secondary';
                                                                ?>">
                                                                <?php echo ucfirst($customer['customer_type']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Payment Terms:</th>
                                                        <td><?php echo ucfirst(str_replace('_', ' ', $customer['payment_terms'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Credit Limit:</th>
                                                        <td>₹<?php echo number_format($customer['credit_limit'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Current Balance:</th>
                                                        <td>
                                                            <?php if ($customer['current_balance'] > 0): ?>
                                                            <span class="text-danger">₹<?php echo number_format($customer['current_balance'], 2); ?></span>
                                                            <?php else: ?>
                                                            <span class="text-success">₹0.00</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Status:</th>
                                                        <td>
                                                            <span class="badge 
                                                                <?php 
                                                                if ($customer['status'] == 'active') echo 'badge-soft-success';
                                                                elseif ($customer['status'] == 'inactive') echo 'badge-soft-warning';
                                                                else echo 'badge-soft-danger';
                                                                ?>">
                                                                <?php echo ucfirst($customer['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Created On:</th>
                                                        <td>
                                                            <?php 
                                                            if (isset($customer['created_at'])) {
                                                                echo date('d M, Y', strtotime($customer['created_at']));
                                                            } else {
                                                                echo '-';
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Customer Notes -->
                                <div class="row mt-4">
                                    <div class="col-lg-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="card-title mb-0">Customer Notes</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($customer['notes']): ?>
                                                <p><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></p>
                                                <?php else: ?>
                                                <p class="text-muted">No notes added for this customer.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Back Button -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center">
                            <a href="customers-list.php" class="btn btn-light">
                                <i class="mdi mdi-arrow-left me-1"></i> Back to Customers List
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Customer not found message -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="text-center py-5">
                                    <i class="mdi mdi-account-off display-4 text-danger"></i>
                                    <h3 class="mt-4">Customer Not Found</h3>
                                    <p class="text-muted">The customer you're looking for doesn't exist or has been deleted.</p>
                                    <a href="customers-list.php" class="btn btn-primary">
                                        <i class="mdi mdi-arrow-left me-1"></i> Go to Customers List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include('includes/footer.php')?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                Loading payment details...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="printReceiptBtn" target="_blank">
                    <i class="mdi mdi-printer me-1"></i> Print Receipt
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<style>
/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-item-marker {
    position: absolute;
    left: -40px;
    top: 0;
}

.timeline-item-marker-text {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.timeline-item-marker-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
}

.timeline-item-content {
    background: #fff;
    border-radius: 8px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}
</style>

<script>
// View payment details
function viewPaymentDetails(paymentId) {
    // You would typically make an AJAX call here to fetch payment details
    // For now, we'll show a simple message
    const content = `
        <div class="text-center py-3">
            <i class="mdi mdi-cash-multiple display-4 text-success mb-3"></i>
            <h5>Payment Details</h5>
            <p>Payment ID: ${paymentId}</p>
            <p>Detailed payment information would be shown here.</p>
        </div>
    `;
    
    document.getElementById('paymentDetailsContent').innerHTML = content;
    document.getElementById('printReceiptBtn').href = `receipt.php?payment_id=${paymentId}`;
    
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    modal.show();
}

// Export customer data
function exportCustomerData(format) {
    const customerId = <?php echo $customer_id; ?>;
    const customerName = "<?php echo addslashes($customer['customer_name']); ?>";
    
    if (format === 'pdf') {
        window.open(`export-customer-pdf.php?id=${customerId}`, '_blank');
    } else if (format === 'excel') {
        window.open(`export-customer-excel.php?id=${customerId}`, '_blank');
    } else if (format === 'print') {
        window.print();
    }
}

// Filter orders by date
function filterOrders(dateRange) {
    const now = new Date();
    let fromDate, toDate;
    
    switch(dateRange) {
        case 'today':
            fromDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            toDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1);
            break;
        case 'week':
            fromDate = new Date(now.setDate(now.getDate() - 7));
            toDate = new Date();
            break;
        case 'month':
            fromDate = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
            toDate = new Date();
            break;
        case 'year':
            fromDate = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate());
            toDate = new Date();
            break;
        default:
            fromDate = null;
            toDate = null;
    }
    
    // You would implement actual filtering here
    alert(`Filtering orders from ${fromDate} to ${toDate}`);
}

// Search within tabs
function searchInTab(tab, searchTerm) {
    const rows = document.querySelectorAll(`#${tab} tbody tr`);
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
    
    // Auto-refresh statistics (optional)
    setInterval(() => {
        // You could implement auto-refresh here if needed
    }, 60000); // Every 60 seconds
});
</script>

</body>

</html>