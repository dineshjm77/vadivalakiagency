<?php

include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get order ID or customer ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// If we have an order ID, fetch order details
if ($order_id > 0) {
    // Verify order belongs to lineman
    $order_sql = "SELECT o.*, c.shop_name, c.customer_name, c.customer_contact, 
                         c.current_balance, c.customer_code
                  FROM orders o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE o.id = $order_id AND o.created_by = $lineman_id";
    $order_result = mysqli_query($conn, $order_sql);
    
    if (!$order_result || mysqli_num_rows($order_result) == 0) {
        header('Location: orders.php');
        exit;
    }
    
    $order = mysqli_fetch_assoc($order_result);
    $customer_id = $order['customer_id'];
    $order_pending_amount = $order['pending_amount'];
    
    // Get customer's other pending orders
    $pending_orders_sql = "SELECT id, order_number, total_amount, pending_amount, order_date
                          FROM orders 
                          WHERE customer_id = $customer_id 
                          AND created_by = $lineman_id 
                          AND payment_status IN ('pending', 'partial')
                          AND id != $order_id
                          ORDER BY order_date ASC";
    $pending_orders_result = mysqli_query($conn, $pending_orders_sql);
    
} elseif ($customer_id > 0) {
    // Verify customer belongs to lineman
    $customer_sql = "SELECT * FROM customers 
                     WHERE id = $customer_id AND assigned_lineman_id = $lineman_id";
    $customer_result = mysqli_query($conn, $customer_sql);
    
    if (!$customer_result || mysqli_num_rows($customer_result) == 0) {
        header('Location: my-shops.php');
        exit;
    }
    
    $customer = mysqli_fetch_assoc($customer_result);
    $order_pending_amount = $customer['current_balance'];
    
    // Get all pending orders for this customer
    $pending_orders_sql = "SELECT id, order_number, total_amount, pending_amount, order_date
                          FROM orders 
                          WHERE customer_id = $customer_id 
                          AND created_by = $lineman_id 
                          AND payment_status IN ('pending', 'partial')
                          ORDER BY order_date ASC";
    $pending_orders_result = mysqli_query($conn, $pending_orders_sql);
    
} else {
    header('Location: orders.php');
    exit;
}

// ============================
// CALCULATE STATISTICS
// ============================

// 1. Customer Statistics
$customer_stats_sql = "SELECT 
    COUNT(*) as total_orders,
    SUM(total_amount) as total_sales,
    SUM(paid_amount) as total_paid,
    SUM(pending_amount) as total_pending,
    AVG(DATEDIFF(CURDATE(), order_date)) as avg_days_pending,
    MAX(order_date) as last_order_date,
    MIN(order_date) as first_order_date
    FROM orders 
    WHERE customer_id = $customer_id 
    AND created_by = $lineman_id";

$customer_stats_result = mysqli_query($conn, $customer_stats_sql);
$customer_stats = mysqli_fetch_assoc($customer_stats_result);

// 2. Payment History Statistics
$payment_stats_sql = "SELECT 
    COUNT(*) as total_payments,
    SUM(amount) as total_collected,
    MIN(created_at) as first_payment_date,
    MAX(created_at) as last_payment_date,
    AVG(amount) as avg_payment_amount,
    payment_method,
    COUNT(*) as method_count
    FROM transactions 
    WHERE customer_id = $customer_id 
    AND created_by = $lineman_id
    AND type = 'payment'
    GROUP BY payment_method
    ORDER BY method_count DESC";

$payment_stats_result = mysqli_query($conn, $payment_stats_sql);
$payment_methods_stats = [];
$total_payments_count = 0;
$total_collected_all = 0;

while ($row = mysqli_fetch_assoc($payment_stats_result)) {
    $payment_methods_stats[] = $row;
    $total_payments_count += $row['total_payments'];
    $total_collected_all += $row['total_collected'];
}

// 3. Calculate payment efficiency
$payment_efficiency = 0;
if ($customer_stats['total_sales'] > 0) {
    $payment_efficiency = ($customer_stats['total_paid'] / $customer_stats['total_sales']) * 100;
}

// 4. Calculate average payment delay
$avg_payment_delay = 0;
if ($customer_stats['avg_days_pending'] !== null) {
    $avg_payment_delay = round($customer_stats['avg_days_pending']);
}

// 5. Calculate credit risk score (0-100, lower is riskier)
$credit_risk_score = 100;
if ($customer_stats['total_orders'] > 0) {
    // Factors affecting risk score:
    // 1. Payment efficiency (40%)
    // 2. Average payment delay (30%)
    // 3. Total pending amount relative to total sales (30%)
    
    $pending_ratio = 0;
    if ($customer_stats['total_sales'] > 0) {
        $pending_ratio = ($customer_stats['total_pending'] / $customer_stats['total_sales']) * 100;
    }
    
    $risk_score = ($payment_efficiency * 0.4) + 
                  (max(0, 100 - ($avg_payment_delay * 2)) * 0.3) + 
                  (max(0, 100 - $pending_ratio) * 0.3);
    
    $credit_risk_score = round(max(0, min(100, $risk_score)));
}

// 6. Get recent payment trends (last 30 days)
$recent_trends_sql = "SELECT 
    DATE(created_at) as payment_date,
    COUNT(*) as payments_count,
    SUM(amount) as total_amount
    FROM transactions 
    WHERE customer_id = $customer_id 
    AND created_by = $lineman_id
    AND type = 'payment'
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY payment_date DESC
    LIMIT 10";

$recent_trends_result = mysqli_query($conn, $recent_trends_sql);
$recent_payments_trend = [];
while ($row = mysqli_fetch_assoc($recent_trends_result)) {
    $recent_payments_trend[] = $row;
}

// 7. Calculate total pending amount
$total_pending = isset($order_pending_amount) ? $order_pending_amount : 0;

// ============================
// END STATISTICS CALCULATION
// ============================

// Handle payment collection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['collect_payment'])) {
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $payment_type = mysqli_real_escape_string($conn, $_POST['payment_type']);
    $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
    $payment_notes = mysqli_real_escape_string($conn, $_POST['payment_notes']);
    $apply_to_orders = $_POST['apply_to_orders'] ?? [];
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        $error_message = "Please enter a valid payment amount";
    } elseif ($payment_amount > $total_pending) {
        $error_message = "Payment amount cannot exceed pending amount of ₹" . number_format($total_pending, 2);
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            $payment_id = 'PAY' . date('Ymd') . rand(1000, 9999);
            
            // Record payment transaction
            $payment_sql = "INSERT INTO transactions (customer_id, order_id, payment_id, type, amount, 
                              payment_method, reference_no, notes, created_by, created_at) 
                              VALUES (?, ?, ?, 'payment', ?, ?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($conn, $payment_sql);
            mysqli_stmt_bind_param($stmt, "iisdsssi", 
                $customer_id, $order_id, $payment_id, $payment_amount, 
                $payment_method, $reference_no, $payment_notes, $lineman_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to record payment: " . mysqli_error($conn));
            }
            
            // Update customer balance
            $balance_sql = "UPDATE customers SET current_balance = current_balance - ? WHERE id = ?";
            $balance_stmt = mysqli_prepare($conn, $balance_sql);
            mysqli_stmt_bind_param($balance_stmt, "di", $payment_amount, $customer_id);
            
            if (!mysqli_stmt_execute($balance_stmt)) {
                throw new Exception("Failed to update customer balance: " . mysqli_error($conn));
            }
            mysqli_stmt_close($balance_stmt);
            
            // If payment is for specific order(s)
            if ($payment_type == 'specific' && !empty($apply_to_orders)) {
                $remaining_amount = $payment_amount;
                
                foreach ($apply_to_orders as $order_to_pay) {
                    if ($remaining_amount <= 0) break;
                    
                    // Get order details
                    $order_sql = "SELECT pending_amount FROM orders WHERE id = ?";
                    $order_stmt = mysqli_prepare($conn, $order_sql);
                    mysqli_stmt_bind_param($order_stmt, "i", $order_to_pay);
                    mysqli_stmt_execute($order_stmt);
                    $order_result = mysqli_stmt_get_result($order_stmt);
                    $order_data = mysqli_fetch_assoc($order_result);
                    
                    $order_pending = $order_data['pending_amount'];
                    $amount_to_apply = min($remaining_amount, $order_pending);
                    
                    if ($amount_to_apply > 0) {
                        // Update order payment status
                        $new_paid = $amount_to_apply;
                        $new_pending = $order_pending - $amount_to_apply;
                        $new_payment_status = $new_pending > 0 ? 'partial' : 'paid';
                        
                        $update_order_sql = "UPDATE orders SET 
                                            paid_amount = paid_amount + ?,
                                            pending_amount = ?,
                                            payment_status = ?
                                            WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_order_sql);
                        mysqli_stmt_bind_param($update_stmt, "ddsi", 
                            $new_paid, $new_pending, $new_payment_status, $order_to_pay);
                        
                        if (!mysqli_stmt_execute($update_stmt)) {
                            throw new Exception("Failed to update order payment: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($update_stmt);
                        
                        $remaining_amount -= $amount_to_apply;
                    }
                }
            } elseif ($payment_type == 'general') {
                // General payment - reduce all pending amounts proportionally
                $total_pending_all = 0;
                $orders_list = [];
                
                // Get all pending orders
                $pending_sql = "SELECT id, pending_amount FROM orders 
                               WHERE customer_id = ? AND created_by = ? 
                               AND payment_status IN ('pending', 'partial')";
                $pending_stmt = mysqli_prepare($conn, $pending_sql);
                mysqli_stmt_bind_param($pending_stmt, "ii", $customer_id, $lineman_id);
                mysqli_stmt_execute($pending_stmt);
                $pending_result = mysqli_stmt_get_result($pending_stmt);
                
                while ($row = mysqli_fetch_assoc($pending_result)) {
                    $orders_list[] = $row;
                    $total_pending_all += $row['pending_amount'];
                }
                
                if ($total_pending_all > 0) {
                    $remaining_amount = $payment_amount;
                    
                    foreach ($orders_list as $order_data) {
                        if ($remaining_amount <= 0) break;
                        
                        $proportion = $order_data['pending_amount'] / $total_pending_all;
                        $amount_to_apply = min($remaining_amount, $order_data['pending_amount']);
                        
                        $new_pending = $order_data['pending_amount'] - $amount_to_apply;
                        $new_payment_status = $new_pending > 0 ? 'partial' : 'paid';
                        
                        $update_order_sql = "UPDATE orders SET 
                                            paid_amount = paid_amount + ?,
                                            pending_amount = ?,
                                            payment_status = ?
                                            WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_order_sql);
                        mysqli_stmt_bind_param($update_stmt, "ddsi", 
                            $amount_to_apply, $new_pending, $new_payment_status, $order_data['id']);
                        
                        if (!mysqli_stmt_execute($update_stmt)) {
                            throw new Exception("Failed to update order payment: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($update_stmt);
                        
                        $remaining_amount -= $amount_to_apply;
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Payment of ₹" . number_format($payment_amount, 2) . " collected successfully!";
            
            // Redirect to receipt
            header("Location: payment-receipt.php?payment_id=$payment_id");
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}

// Calculate total pending for display
$display_pending = 0;
if (isset($pending_orders_result) && mysqli_num_rows($pending_orders_result) > 0) {
    mysqli_data_seek($pending_orders_result, 0);
    while ($row = mysqli_fetch_assoc($pending_orders_result)) {
        $display_pending += $row['pending_amount'];
    }
    mysqli_data_seek($pending_orders_result, 0);
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
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <!-- ============================
                    CUSTOMER STATISTICS SECTION
                    ============================ -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-chart-line me-2"></i> Customer Payment Statistics
                                    </h5>
                                    
                                    <div class="row">
                                        <!-- Total Orders -->
                                        <div class="col-xl-2 col-md-4 col-sm-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-muted fw-normal mt-0" title="Total Orders">Total Orders</h5>
                                                            <h3 class="my-2 py-1"><?php echo $customer_stats['total_orders'] ?? 0; ?></h3>
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

                                        <!-- Total Sales -->
                                        <div class="col-xl-2 col-md-4 col-sm-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-muted fw-normal mt-0" title="Total Sales">Total Sales</h5>
                                                            <h3 class="my-2 py-1">₹<?php echo number_format($customer_stats['total_sales'] ?? 0, 2); ?></h3>
                                                        </div>
                                                        <div class="flex-shrink-0">
                                                            <div class="avatar-sm rounded-circle bg-success bg-soft">
                                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-success text-success">
                                                                    <i class="mdi mdi-currency-inr"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Total Paid -->
                                        <div class="col-xl-2 col-md-4 col-sm-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-muted fw-normal mt-0" title="Total Paid">Total Paid</h5>
                                                            <h3 class="my-2 py-1">₹<?php echo number_format($customer_stats['total_paid'] ?? 0, 2); ?></h3>
                                                        </div>
                                                        <div class="flex-shrink-0">
                                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                                    <i class="mdi mdi-cash-check"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Total Pending -->
                                        <div class="col-xl-2 col-md-4 col-sm-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-muted fw-normal mt-0" title="Total Pending">Total Pending</h5>
                                                            <h3 class="my-2 py-1">₹<?php echo number_format($customer_stats['total_pending'] ?? 0, 2); ?></h3>
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

                                        <!-- Payment Efficiency -->
                                        <div class="col-xl-2 col-md-4 col-sm-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-muted fw-normal mt-0" title="Payment Efficiency">Payment Efficiency</h5>
                                                            <h3 class="my-2 py-1"><?php echo round($payment_efficiency, 1); ?>%</h3>
                                                        </div>
                                                        <div class="flex-shrink-0">
                                                            <div class="avatar-sm rounded-circle bg-<?php echo $payment_efficiency >= 80 ? 'success' : ($payment_efficiency >= 60 ? 'warning' : 'danger'); ?> bg-soft">
                                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-<?php echo $payment_efficiency >= 80 ? 'success' : ($payment_efficiency >= 60 ? 'warning' : 'danger'); ?> text-<?php echo $payment_efficiency >= 80 ? 'success' : ($payment_efficiency >= 60 ? 'warning' : 'danger'); ?>">
                                                                    <i class="mdi mdi-percent"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Credit Risk Score -->
                                        <div class="col-xl-2 col-md-4 col-sm-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <h5 class="text-muted fw-normal mt-0" title="Credit Risk Score">Risk Score</h5>
                                                            <h3 class="my-2 py-1"><?php echo $credit_risk_score; ?>/100</h3>
                                                        </div>
                                                        <div class="flex-shrink-0">
                                                            <div class="avatar-sm rounded-circle bg-<?php echo $credit_risk_score >= 80 ? 'success' : ($credit_risk_score >= 60 ? 'warning' : 'danger'); ?> bg-soft">
                                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-<?php echo $credit_risk_score >= 80 ? 'success' : ($credit_risk_score >= 60 ? 'warning' : 'danger'); ?> text-<?php echo $credit_risk_score >= 80 ? 'success' : ($credit_risk_score >= 60 ? 'warning' : 'danger'); ?>">
                                                                    <i class="mdi mdi-shield-check"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detailed Statistics -->
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <h6 class="card-title mb-3">
                                                        <i class="mdi mdi-history me-2"></i> Payment History Summary
                                                    </h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>Payment Method</th>
                                                                    <th class="text-end">Payments</th>
                                                                    <th class="text-end">Total Amount</th>
                                                                    <th class="text-end">Avg. Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if (!empty($payment_methods_stats)): ?>
                                                                    <?php foreach ($payment_methods_stats as $method): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <?php 
                                                                            $method_names = [
                                                                                'cash' => 'Cash',
                                                                                'upi' => 'UPI',
                                                                                'card' => 'Card',
                                                                                'bank_transfer' => 'Bank Transfer',
                                                                                'cheque' => 'Cheque',
                                                                                'wallet' => 'Digital Wallet',
                                                                                'other' => 'Other'
                                                                            ];
                                                                            echo $method_names[$method['payment_method']] ?? ucfirst($method['payment_method']);
                                                                            ?>
                                                                        </td>
                                                                        <td class="text-end"><?php echo $method['method_count']; ?></td>
                                                                        <td class="text-end">₹<?php echo number_format($method['total_collected'], 2); ?></td>
                                                                        <td class="text-end">₹<?php echo number_format($method['avg_payment_amount'], 2); ?></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                    <tr class="table-light">
                                                                        <td class="fw-bold">Total</td>
                                                                        <td class="text-end fw-bold"><?php echo $total_payments_count; ?></td>
                                                                        <td class="text-end fw-bold">₹<?php echo number_format($total_collected_all, 2); ?></td>
                                                                        <td class="text-end fw-bold">₹<?php echo number_format($total_collected_all / max(1, $total_payments_count), 2); ?></td>
                                                                    </tr>
                                                                <?php else: ?>
                                                                    <tr>
                                                                        <td colspan="4" class="text-center">No payment history found</td>
                                                                    </tr>
                                                                <?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="card border">
                                                <div class="card-body">
                                                    <h6 class="card-title mb-3">
                                                        <i class="mdi mdi-trending-up me-2"></i> Recent Payment Trends
                                                    </h6>
                                                    <?php if (!empty($recent_payments_trend)): ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-hover mb-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Date</th>
                                                                        <th class="text-end">Payments</th>
                                                                        <th class="text-end">Amount</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($recent_payments_trend as $trend): ?>
                                                                    <tr>
                                                                        <td><?php echo date('d M', strtotime($trend['payment_date'])); ?></td>
                                                                        <td class="text-end"><?php echo $trend['payments_count']; ?></td>
                                                                        <td class="text-end text-success">₹<?php echo number_format($trend['total_amount'], 2); ?></td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-center py-4">
                                                            <i class="mdi mdi-chart-line display-4 text-muted"></i>
                                                            <p class="mt-2">No recent payment trends available</p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Key Metrics -->
                                                    <div class="row mt-3">
                                                        <div class="col-6">
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0">
                                                                    <i class="mdi mdi-calendar-clock text-primary fs-4"></i>
                                                                </div>
                                                                <div class="flex-grow-1 ms-3">
                                                                    <p class="mb-0">Avg. Payment Delay</p>
                                                                    <h5 class="mb-0"><?php echo $avg_payment_delay; ?> days</h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0">
                                                                    <i class="mdi mdi-calendar-range text-success fs-4"></i>
                                                                </div>
                                                                <div class="flex-grow-1 ms-3">
                                                                    <p class="mb-0">Customer Since</p>
                                                                    <h5 class="mb-0">
                                                                        <?php 
                                                                        if ($customer_stats['first_order_date']) {
                                                                            echo date('M Y', strtotime($customer_stats['first_order_date']));
                                                                        } else {
                                                                            echo 'N/A';
                                                                        }
                                                                        ?>
                                                                    </h5>
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
                    <!-- ============================
                    END STATISTICS SECTION
                    ============================ -->

                    <div class="row">
                        <!-- Customer/Order Information -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-account-cash me-2"></i> Payment Details
                                    </h5>
                                    
                                    <?php if (isset($order)): ?>
                                    <!-- Payment for Specific Order -->
                                    <div class="mb-4">
                                        <h6 class="mb-2">Order Details:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <tbody>
                                                    <tr>
                                                        <td class="fw-medium" width="30%">Order Number</td>
                                                        <td>
                                                            <a href="view-invoice.php?id=<?php echo $order['id']; ?>" class="text-primary">
                                                                <?php echo $order['order_number']; ?>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Customer</td>
                                                        <td><?php echo htmlspecialchars($order['shop_name']); ?> (<?php echo htmlspecialchars($order['customer_name']); ?>)</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Order Date</td>
                                                        <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Total Amount</td>
                                                        <td class="fw-bold">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Paid Amount</td>
                                                        <td class="text-success">₹<?php echo number_format($order['paid_amount'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Pending Amount</td>
                                                        <td class="text-danger fw-bold">₹<?php echo number_format($order['pending_amount'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Payment Status</td>
                                                        <td>
                                                            <?php 
                                                            $payment_status_class = '';
                                                            if ($order['payment_status'] == 'paid') $payment_status_class = 'badge-soft-success';
                                                            elseif ($order['payment_status'] == 'partial') $payment_status_class = 'badge-soft-warning';
                                                            elseif ($order['payment_status'] == 'pending') $payment_status_class = 'badge-soft-danger';
                                                            ?>
                                                            <span class="badge <?php echo $payment_status_class; ?>">
                                                                <?php echo ucfirst($order['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php elseif (isset($customer)): ?>
                                    <!-- Payment for Customer (General) -->
                                    <div class="mb-4">
                                        <h6 class="mb-2">Customer Details:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <tbody>
                                                    <tr>
                                                        <td class="fw-medium" width="30%">Shop Name</td>
                                                        <td><?php echo htmlspecialchars($customer['shop_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Customer Name</td>
                                                        <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Contact</td>
                                                        <td><?php echo $customer['customer_contact']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Customer Code</td>
                                                        <td><?php echo $customer['customer_code']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="fw-medium">Current Balance</td>
                                                        <td class="fw-bold <?php echo $customer['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                            ₹<?php echo number_format($customer['current_balance'], 2); ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Pending Orders List -->
                                    <?php if (isset($pending_orders_result) && mysqli_num_rows($pending_orders_result) > 0): ?>
                                    <div class="mb-4">
                                        <h6 class="mb-2">Pending Orders:</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th width="5%">#</th>
                                                        <th>Order #</th>
                                                        <th>Date</th>
                                                        <th>Total Amount</th>
                                                        <th>Pending Amount</th>
                                                        <th class="text-center">Age (Days)</th>
                                                        <th>Select</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $counter = 1;
                                                    $display_pending = 0;
                                                    mysqli_data_seek($pending_orders_result, 0);
                                                    while ($pending_order = mysqli_fetch_assoc($pending_orders_result)):
                                                        $display_pending += $pending_order['pending_amount'];
                                                        $order_age = floor((time() - strtotime($pending_order['order_date'])) / (60 * 60 * 24));
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td>
                                                            <a href="view-invoice.php?id=<?php echo $pending_order['id']; ?>" class="text-dark">
                                                                <?php echo $pending_order['order_number']; ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo date('d M', strtotime($pending_order['order_date'])); ?></td>
                                                        <td>₹<?php echo number_format($pending_order['total_amount'], 2); ?></td>
                                                        <td class="text-danger fw-medium">₹<?php echo number_format($pending_order['pending_amount'], 2); ?></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?php echo $order_age < 7 ? 'success' : ($order_age < 15 ? 'warning' : 'danger'); ?>-subtle text-<?php echo $order_age < 7 ? 'success' : ($order_age < 15 ? 'warning' : 'danger'); ?>">
                                                                <?php echo $order_age; ?> days
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <input type="checkbox" class="form-check-input order-checkbox" 
                                                                   name="apply_to_orders[]" value="<?php echo $pending_order['id']; ?>"
                                                                   data-amount="<?php echo $pending_order['pending_amount']; ?>"
                                                                   <?php if (isset($order) && $order['id'] == $pending_order['id']) echo 'checked'; ?>>
                                                        </td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                    <tr class="table-light">
                                                        <td colspan="4" class="text-end fw-bold">Total Pending:</td>
                                                        <td class="text-danger fw-bold" id="display-total-pending">₹<?php echo number_format($display_pending, 2); ?></td>
                                                        <td></td>
                                                        <td></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <!-- Show message if no pending orders -->
                                    <div class="alert alert-info mb-4">
                                        <i class="mdi mdi-information me-2"></i>
                                        No pending orders found for this customer.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">
                                        <i class="mdi mdi-cash-multiple me-2"></i> Payment Collection
                                    </h5>
                                    
                                    <form method="POST" id="paymentForm">
                                        <!-- Payment Type -->
                                        <div class="mb-3">
                                            <label class="form-label">Payment Type *</label>
                                            <select class="form-select" name="payment_type" id="payment_type" required onchange="toggleOrderSelection()">
                                                <option value="specific">Specific Order(s)</option>
                                                <option value="general">General Payment</option>
                                            </select>
                                        </div>

                                        <!-- Payment Amount -->
                                        <div class="mb-3">
                                            <label class="form-label">Payment Amount (₹) *</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" class="form-control" 
                                                       name="payment_amount" id="payment_amount" 
                                                       min="0.01" step="0.01" required
                                                       placeholder="Enter amount"
                                                       value=""
                                                       oninput="validatePaymentAmount()">
                                            </div>
                                            <div class="form-text">
                                                Maximum payable: ₹<span id="max_payable"><?php echo number_format($total_pending, 2); ?></span>
                                                <?php if ($total_pending > 0): ?>
                                                <br><small class="text-muted">(<?php echo ceil($total_pending / 100) * 100; ?> rounded up)</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Payment Method -->
                                        <div class="mb-3">
                                            <label class="form-label">Payment Method *</label>
                                            <select class="form-select" name="payment_method" required>
                                                <option value="cash">Cash</option>
                                                <option value="upi">UPI</option>
                                                <option value="card">Card</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="wallet">Wallet</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <!-- Reference Number -->
                                        <div class="mb-3">
                                            <label class="form-label">Reference Number</label>
                                            <input type="text" class="form-control" 
                                                   name="reference_no" 
                                                   placeholder="Transaction ID, Cheque No, etc.">
                                            <div class="form-text">Optional for cash payments</div>
                                        </div>

                                        <!-- Payment Notes -->
                                        <div class="mb-3">
                                            <label class="form-label">Payment Notes</label>
                                            <textarea class="form-control" name="payment_notes" 
                                                      rows="3" placeholder="Any notes about this payment..."></textarea>
                                        </div>

                                        <!-- Payment Insights -->
                                        <div class="alert alert-info mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-shrink-0">
                                                    <i class="mdi mdi-lightbulb-on fs-4"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="alert-heading">Payment Insight</h6>
                                                    <div class="mb-1">
                                                        <small>Avg. Payment: ₹<?php echo number_format($total_collected_all / max(1, $total_payments_count), 2); ?></small>
                                                    </div>
                                                    <div class="progress mb-2" style="height: 5px;">
                                                        <div class="progress-bar bg-<?php echo $payment_efficiency >= 80 ? 'success' : ($payment_efficiency >= 60 ? 'warning' : 'danger'); ?>" 
                                                             style="width: <?php echo $payment_efficiency; ?>%"></div>
                                                    </div>
                                                    <small class="mb-0">Payment Efficiency: <?php echo round($payment_efficiency, 1); ?>%</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="collect_payment" class="btn btn-success btn-lg">
                                                <i class="mdi mdi-cash-check me-2"></i>
                                                Collect Payment
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                                                <i class="mdi mdi-arrow-left me-2"></i>
                                                Go Back
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">Quick Actions</h6>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="fillFullPayment()">
                                            <i class="mdi mdi-cash-fast me-2"></i>
                                            Full Payment
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" onclick="fillPartialPayment()">
                                            <i class="mdi mdi-cash-100 me-2"></i>
                                            50% Payment
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="fillSuggestedPayment()">
                                            <i class="mdi mdi-cash-sync me-2"></i>
                                            Suggested (<?php echo ceil($total_pending * 0.7 / 100) * 100; ?>)
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" onclick="clearForm()">
                                            <i class="mdi mdi-refresh me-2"></i>
                                            Clear Form
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Tips -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="mdi mdi-lightbulb-outline me-2"></i> Collection Tips
                                    </h6>
                                    <ul class="list-unstyled mb-0">
                                        <?php if ($credit_risk_score < 60): ?>
                                        <li class="mb-2">
                                            <i class="mdi mdi-alert-circle text-danger me-2"></i>
                                            <small>High risk customer. Consider collecting full payment.</small>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($avg_payment_delay > 15): ?>
                                        <li class="mb-2">
                                            <i class="mdi mdi-clock-alert text-warning me-2"></i>
                                            <small>Average payment delay is <?php echo $avg_payment_delay; ?> days.</small>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($payment_efficiency < 70): ?>
                                        <li class="mb-2">
                                            <i class="mdi mdi-percent-box text-info me-2"></i>
                                            <small>Payment efficiency is low. Consider payment plan.</small>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (!empty($payment_methods_stats)): ?>
                                        <?php 
                                        $preferred_method = $payment_methods_stats[0]['payment_method'];
                                        $method_names = [
                                            'cash' => 'Cash',
                                            'upi' => 'UPI',
                                            'card' => 'Card',
                                            'bank_transfer' => 'Bank Transfer',
                                            'cheque' => 'Cheque',
                                            'wallet' => 'Digital Wallet',
                                            'other' => 'Other'
                                        ];
                                        ?>
                                        <li class="mb-2">
                                            <i class="mdi mdi-account-cash text-success me-2"></i>
                                            <small>Preferred method: <?php echo $method_names[$preferred_method] ?? ucfirst($preferred_method); ?></small>
                                        </li>
                                        <?php endif; ?>
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

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

    <script>
        // Calculate totals
        let totalPending = <?php echo $total_pending; ?>;
        let selectedOrdersTotal = 0;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateMaxPayable();
            updateSelectedTotal();
            
            // Auto-select the current order if applicable
            <?php if (isset($order)): ?>
            const orderCheckbox = document.querySelector(`.order-checkbox[value="<?php echo $order['id']; ?>"]`);
            if (orderCheckbox) {
                orderCheckbox.checked = true;
                updateSelectedTotal();
            }
            <?php endif; ?>
            
            // Show total pending in the form
            document.getElementById('max_payable').textContent = totalPending.toFixed(2);
        });
        
        // Update max payable amount
        function updateMaxPayable() {
            const paymentType = document.getElementById('payment_type').value;
            let maxAmount = 0;
            
            if (paymentType === 'specific') {
                // Sum of selected orders
                maxAmount = selectedOrdersTotal;
            } else {
                // Total pending amount
                maxAmount = totalPending;
            }
            
            document.getElementById('max_payable').textContent = maxAmount.toFixed(2);
            return maxAmount;
        }
        
        // Update selected orders total
        function updateSelectedTotal() {
            selectedOrdersTotal = 0;
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            
            checkboxes.forEach(checkbox => {
                const amount = parseFloat(checkbox.getAttribute('data-amount'));
                selectedOrdersTotal += amount;
            });
            
            updateMaxPayable();
        }
        
        // Toggle order selection based on payment type
        function toggleOrderSelection() {
            const paymentType = document.getElementById('payment_type').value;
            const checkboxes = document.querySelectorAll('.order-checkbox');
            
            if (paymentType === 'general') {
                // Disable all checkboxes for general payment
                checkboxes.forEach(checkbox => {
                    checkbox.disabled = true;
                });
            } else {
                // Enable all checkboxes for specific payment
                checkboxes.forEach(checkbox => {
                    checkbox.disabled = false;
                });
            }
            
            updateSelectedTotal();
            updateMaxPayable();
        }
        
        // Validate payment amount
        function validatePaymentAmount() {
            const amountInput = document.getElementById('payment_amount');
            const maxAmount = updateMaxPayable();
            const enteredAmount = parseFloat(amountInput.value) || 0;
            
            if (enteredAmount > maxAmount) {
                alert(`Payment amount cannot exceed ₹${maxAmount.toFixed(2)}`);
                amountInput.value = maxAmount.toFixed(2);
            }
            
            if (enteredAmount <= 0) {
                alert('Please enter a valid payment amount');
                amountInput.value = '';
            }
        }
        
        // Checkbox change event
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('order-checkbox')) {
                updateSelectedTotal();
                validatePaymentAmount();
            }
        });
        
        // Quick action: Fill full payment
        function fillFullPayment() {
            const maxAmount = updateMaxPayable();
            document.getElementById('payment_amount').value = maxAmount.toFixed(2);
            validatePaymentAmount();
        }
        
        // Quick action: Fill partial payment (50%)
        function fillPartialPayment() {
            const maxAmount = updateMaxPayable();
            const partialAmount = maxAmount * 0.5;
            document.getElementById('payment_amount').value = partialAmount.toFixed(2);
            validatePaymentAmount();
        }
        
        // Quick action: Fill suggested payment (70% rounded to nearest 100)
        function fillSuggestedPayment() {
            const maxAmount = updateMaxPayable();
            const suggestedAmount = Math.ceil(maxAmount * 0.7 / 100) * 100;
            document.getElementById('payment_amount').value = suggestedAmount.toFixed(2);
            validatePaymentAmount();
        }
        
        // Clear form
        function clearForm() {
            if (confirm('Are you sure you want to clear the form?')) {
                document.getElementById('paymentForm').reset();
                document.getElementById('payment_amount').value = '';
                updateSelectedTotal();
                toggleOrderSelection();
            }
        }
        
        // Form validation before submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('payment_amount').value) || 0;
            const maxAmount = updateMaxPayable();
            const paymentType = document.getElementById('payment_type').value;
            const riskScore = <?php echo $credit_risk_score; ?>;
            
            if (amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid payment amount');
                return false;
            }
            
            if (amount > maxAmount) {
                e.preventDefault();
                alert(`Payment amount cannot exceed ₹${maxAmount.toFixed(2)}`);
                return false;
            }
            
            if (paymentType === 'specific') {
                const checkedOrders = document.querySelectorAll('.order-checkbox:checked');
                if (checkedOrders.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one order to apply payment to');
                    return false;
                }
            }
            
            // Warning for high risk customers
            if (riskScore < 60 && amount < maxAmount) {
                if (!confirm('⚠️ High Risk Customer\nPayment efficiency is low. Consider collecting full payment.\n\nProceed with partial payment?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Confirm submission
            if (!confirm(`Collect payment of ₹${amount.toFixed(2)}?`)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}