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
    return $date ? date('d M, Y', strtotime($date)) : '';
}

function formatDateTime($datetime) {
    return $datetime ? date('d M, Y h:i A', strtotime($datetime)) : '';
}

// Fetch customer details
$customer_sql = "SELECT * FROM customers WHERE id = $customer_id";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Get filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$transaction_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';

// Build query for transactions
$sql = "SELECT 
            t.id,
            t.type,
            t.amount,
            t.payment_method,
            t.reference_no,
            t.notes,
            t.created_at,
            o.order_number,
            o.id as order_id,
            o.order_date
        FROM transactions t
        LEFT JOIN orders o ON t.order_id = o.id
        WHERE t.customer_id = $customer_id";

// Apply date filter
$sql .= " AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'";

// Apply transaction type filter
if ($transaction_type !== 'all') {
    $sql .= " AND t.type = '$transaction_type'";
}

// Apply payment method filter
if ($payment_method !== 'all' && $transaction_type == 'payment') {
    $sql .= " AND t.payment_method = '$payment_method'";
}

$sql .= " ORDER BY t.created_at DESC";

$result = mysqli_query($conn, $sql);

// Calculate summary statistics
$summary_sql = "SELECT 
                    SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as total_payments,
                    SUM(CASE WHEN type = 'purchase' THEN amount ELSE 0 END) as total_purchases,
                    SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as total_refunds,
                    SUM(CASE WHEN type = 'adjustment' THEN amount ELSE 0 END) as total_adjustments,
                    COUNT(CASE WHEN type = 'payment' THEN 1 END) as payment_count,
                    COUNT(CASE WHEN type = 'purchase' THEN 1 END) as purchase_count,
                    COUNT(CASE WHEN type = 'refund' THEN 1 END) as refund_count,
                    COUNT(CASE WHEN type = 'adjustment' THEN 1 END) as adjustment_count
                FROM transactions 
                WHERE customer_id = $customer_id
                AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'";

$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get opening balance (sum of all transactions before start date)
$opening_sql = "SELECT 
                    COALESCE(SUM(CASE 
                        WHEN type = 'payment' THEN -amount
                        WHEN type = 'purchase' THEN amount
                        WHEN type = 'refund' THEN -amount
                        WHEN type = 'adjustment' THEN amount
                    END), 0) as opening_balance
                FROM transactions 
                WHERE customer_id = $customer_id
                AND DATE(created_at) < '$start_date'";

$opening_result = mysqli_query($conn, $opening_sql);
$opening = mysqli_fetch_assoc($opening_result);
$opening_balance = $opening['opening_balance'] ?? 0;

// Calculate running balance
$running_balance = $opening_balance;
$transactions = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Calculate effect on balance
        if ($row['type'] == 'payment' || $row['type'] == 'refund') {
            $row['balance_effect'] = -$row['amount'];
        } else {
            $row['balance_effect'] = $row['amount'];
        }
        
        $running_balance += $row['balance_effect'];
        $row['running_balance'] = $running_balance;
        $transactions[] = $row;
    }
}

// Get current balance for comparison
$current_balance = $customer['current_balance'];

// Export functionality
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="customer-transactions-' . $customer['customer_code'] . '-' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th colspan="8">Customer Transactions - ' . htmlspecialchars($customer['customer_name']) . '</th></tr>';
    echo '<tr><th>Date</th><th>Transaction Type</th><th>Order #</th><th>Amount</th><th>Payment Method</th><th>Reference</th><th>Running Balance</th><th>Notes</th></tr>';
    
    // Export opening balance row
    echo '<tr style="background-color: #f2f2f2;">';
    echo '<td>' . date('d M, Y', strtotime($start_date)) . '</td>';
    echo '<td><strong>Opening Balance</strong></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td><strong>' . formatCurrency($opening_balance) . '</strong></td>';
    echo '<td></td>';
    echo '</tr>';
    
    foreach ($transactions as $t) {
        $type_text = ucfirst($t['type']);
        if ($t['type'] == 'purchase') $type_text = 'Sale';
        if ($t['type'] == 'refund') $type_text = 'Refund';
        if ($t['type'] == 'adjustment') $type_text = 'Adjustment';
        
        echo '<tr>';
        echo '<td>' . formatDateTime($t['created_at']) . '</td>';
        echo '<td>' . $type_text . '</td>';
        echo '<td>' . ($t['order_number'] ? $t['order_number'] : 'N/A') . '</td>';
        echo '<td>' . ($t['balance_effect'] > 0 ? '+' : '') . formatCurrency($t['amount']) . '</td>';
        echo '<td>' . ($t['payment_method'] ? ucfirst($t['payment_method']) : 'N/A') . '</td>';
        echo '<td>' . ($t['reference_no'] ? $t['reference_no'] : '') . '</td>';
        echo '<td>' . formatCurrency($t['running_balance']) . '</td>';
        echo '<td>' . htmlspecialchars($t['notes']) . '</td>';
        echo '</tr>';
    }
    
    // Export closing balance row
    echo '<tr style="background-color: #f2f2f2;">';
    echo '<td>' . date('d M, Y', strtotime($end_date)) . '</td>';
    echo '<td><strong>Closing Balance</strong></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td></td>';
    echo '<td><strong>' . formatCurrency($running_balance) . '</strong></td>';
    echo '<td></td>';
    echo '</tr>';
    
    echo '</table>';
    exit();
}
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


                <!-- Customer Info -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-1">
                                            <a href="customer-view.php?id=<?php echo $customer_id; ?>" class="text-dark">
                                                <?php echo htmlspecialchars($customer['customer_name']); ?>
                                            </a>
                                        </h5>
                                        <p class="card-title-desc mb-0">
                                            <?php echo htmlspecialchars($customer['shop_name']); ?> • 
                                            <?php echo $customer['customer_contact']; ?> • 
                                            <span class="badge bg-secondary"><?php echo $customer['customer_code']; ?></span>
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-muted me-2">Current Balance:</span>
                                        <strong class="<?php echo $current_balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo formatCurrency($current_balance); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Purchases</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_purchases'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $summary['purchase_count'] ?? 0; ?> transactions</p>
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
                                            <i class="mdi mdi-cash-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Payments</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_payments'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $summary['payment_count'] ?? 0; ?> transactions</p>
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
                                            <i class="mdi mdi-cash-refund"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Refunds</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_refunds'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $summary['refund_count'] ?? 0; ?> transactions</p>
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
                                            <i class="mdi mdi-cash-sync"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Adjustments</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($summary['total_adjustments'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $summary['adjustment_count'] ?? 0; ?> transactions</p>
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
                                <h5 class="card-title mb-3">Filter Transactions</h5>
                                <form method="GET" action="" class="row g-3">
                                    <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="start_date" 
                                               value="<?php echo htmlspecialchars($start_date); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="end_date" 
                                               value="<?php echo htmlspecialchars($end_date); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Transaction Type</label>
                                        <select class="form-select" name="type">
                                            <option value="all" <?php echo $transaction_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                                            <option value="payment" <?php echo $transaction_type == 'payment' ? 'selected' : ''; ?>>Payments</option>
                                            <option value="purchase" <?php echo $transaction_type == 'purchase' ? 'selected' : ''; ?>>Purchases</option>
                                            <option value="refund" <?php echo $transaction_type == 'refund' ? 'selected' : ''; ?>>Refunds</option>
                                            <option value="adjustment" <?php echo $transaction_type == 'adjustment' ? 'selected' : ''; ?>>Adjustments</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Payment Method</label>
                                        <select class="form-select" name="payment_method">
                                            <option value="all" <?php echo $payment_method == 'all' ? 'selected' : ''; ?>>All Methods</option>
                                            <option value="cash" <?php echo $payment_method == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                            <option value="bank_transfer" <?php echo $payment_method == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                            <option value="upi" <?php echo $payment_method == 'upi' ? 'selected' : ''; ?>>UPI</option>
                                            <option value="cheque" <?php echo $payment_method == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                                            <option value="card" <?php echo $payment_method == 'card' ? 'selected' : ''; ?>>Card</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="mdi mdi-filter me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </form>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="d-flex gap-2">
                                            <a href="?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-secondary">
                                                <i class="mdi mdi-refresh me-1"></i> Reset
                                            </a>
                                            <a href="?id=<?php echo $customer_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&type=<?php echo $transaction_type; ?>&payment_method=<?php echo $payment_method; ?>&export=excel" 
                                               class="btn btn-sm btn-success">
                                                <i class="mdi mdi-file-excel me-1"></i> Export to Excel
                                            </a>
                                            <button onclick="window.print()" class="btn btn-sm btn-info">
                                                <i class="mdi mdi-printer me-1"></i> Print
                                            </button>
                                            <a href="customer-view.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="mdi mdi-arrow-left me-1"></i> Back to Customer
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h5 class="card-title mb-0">Transaction History</h5>
                                        <p class="card-title-desc">
                                            <?php echo date('d M, Y', strtotime($start_date)); ?> to <?php echo date('d M, Y', strtotime($end_date)); ?>
                                            <?php if ($transaction_type != 'all'): ?>
                                                • <?php echo ucfirst($transaction_type); ?> only
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="text-muted me-2">Balance Range:</span>
                                        <span class="badge bg-info"><?php echo formatCurrency($opening_balance); ?></span>
                                        <i class="mdi mdi-arrow-right text-muted mx-2"></i>
                                        <span class="badge bg-info"><?php echo formatCurrency($running_balance); ?></span>
                                        <?php if (abs($running_balance - $current_balance) > 0.01): ?>
                                            <span class="badge bg-danger ms-2">Mismatch: <?php echo formatCurrency($current_balance - $running_balance); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Opening Balance -->
                                <div class="alert alert-secondary mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <i class="mdi mdi-calendar-start text-primary fs-4"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">Opening Balance as of <?php echo date('d M, Y', strtotime($start_date)); ?></h6>
                                                    <p class="mb-0 text-muted">Balance carried forward from before selected period</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <h4 class="mb-0 <?php echo $opening_balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatCurrency($opening_balance); ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Transaction Type</th>
                                                <th>Order #</th>
                                                <th>Description</th>
                                                <th class="text-end">Amount</th>
                                                <th>Payment Method</th>
                                                <th>Reference</th>
                                                <th class="text-end">Running Balance</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($transactions)): ?>
                                                <?php foreach ($transactions as $t): ?>
                                                    <?php
                                                    // Determine styling based on transaction type
                                                    $type_class = '';
                                                    $type_text = ucfirst($t['type']);
                                                    $amount_class = '';
                                                    $icon = '';
                                                    
                                                    switch ($t['type']) {
                                                        case 'payment':
                                                            $type_class = 'bg-success-subtle text-success';
                                                            $amount_class = 'text-success';
                                                            $icon = 'mdi mdi-cash-check';
                                                            $type_text = 'Payment';
                                                            break;
                                                        case 'purchase':
                                                            $type_class = 'bg-primary-subtle text-primary';
                                                            $amount_class = 'text-primary';
                                                            $icon = 'mdi mdi-cart-outline';
                                                            $type_text = 'Sale';
                                                            break;
                                                        case 'refund':
                                                            $type_class = 'bg-warning-subtle text-warning';
                                                            $amount_class = 'text-warning';
                                                            $icon = 'mdi mdi-cash-refund';
                                                            $type_text = 'Refund';
                                                            break;
                                                        case 'adjustment':
                                                            $type_class = 'bg-info-subtle text-info';
                                                            $amount_class = 'text-info';
                                                            $icon = 'mdi mdi-cash-sync';
                                                            $type_text = 'Adjustment';
                                                            break;
                                                    }
                                                    
                                                    // Determine if balance is positive or negative
                                                    $balance_class = $t['running_balance'] >= 0 ? 'text-success' : 'text-danger';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <i class="mdi mdi-calendar-clock text-muted me-1"></i>
                                                                <?php echo formatDateTime($t['created_at']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $type_class; ?>">
                                                                <i class="<?php echo $icon; ?> me-1"></i><?php echo $type_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($t['order_id']): ?>
                                                                <a href="order-view.php?id=<?php echo $t['order_id']; ?>" class="text-primary">
                                                                    <?php echo $t['order_number']; ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($t['notes']): ?>
                                                                <small><?php echo htmlspecialchars($t['notes']); ?></small>
                                                            <?php else: ?>
                                                                <small class="text-muted">No description</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="fw-bold <?php echo $amount_class; ?>">
                                                                <?php echo $t['balance_effect'] > 0 ? '+' : ''; ?><?php echo formatCurrency($t['amount']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($t['payment_method']): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary">
                                                                    <?php echo ucfirst($t['payment_method']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo $t['reference_no']; ?></small>
                                                        </td>
                                                        <td class="text-end">
                                                            <span class="fw-bold <?php echo $balance_class; ?>">
                                                                <?php echo formatCurrency($t['running_balance']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <?php if ($t['order_id']): ?>
                                                                        <li>
                                                                            <a class="dropdown-item" href="order-view.php?id=<?php echo $t['order_id']; ?>">
                                                                                <i class="mdi mdi-eye me-1"></i> View Order
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <?php if ($t['type'] == 'payment'): ?>
                                                                        <li>
                                                                            <a class="dropdown-item" href="#" onclick="voidTransaction(<?php echo $t['id']; ?>)">
                                                                                <i class="mdi mdi-cancel me-1 text-danger"></i> Void Payment
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <li>
                                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#transactionModal" onclick="viewTransaction(<?php echo $t['id']; ?>)">
                                                                            <i class="mdi mdi-information-outline me-1"></i> View Details
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-5">
                                                        <i class="mdi mdi-cash-remove display-4 text-muted"></i>
                                                        <h5 class="mt-3">No Transactions Found</h5>
                                                        <p class="text-muted">No transactions found for the selected criteria.</p>
                                                        <a href="?id=<?php echo $customer_id; ?>" class="btn btn-primary mt-2">
                                                            <i class="mdi mdi-refresh me-1"></i> View All Transactions
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Closing Balance -->
                                <div class="alert alert-info mt-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <i class="mdi mdi-calendar-end text-primary fs-4"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">Closing Balance as of <?php echo date('d M, Y', strtotime($end_date)); ?></h6>
                                                    <p class="mb-0 text-muted">
                                                        Opening Balance: <?php echo formatCurrency($opening_balance); ?> + 
                                                        Net Transactions: <?php echo formatCurrency($running_balance - $opening_balance); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <h4 class="mb-0 <?php echo $running_balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo formatCurrency($running_balance); ?>
                                            </h4>
                                            <p class="mb-0">
                                                <small class="text-muted">
                                                    Current Balance: <?php echo formatCurrency($current_balance); ?>
                                                    <?php if (abs($running_balance - $current_balance) > 0.01): ?>
                                                        <span class="text-danger">(Mismatch)</span>
                                                    <?php endif; ?>
                                                </small>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Summary -->
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Net Change</h6>
                                            <h4 class="mb-0 <?php echo ($running_balance - $opening_balance) >= 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo ($running_balance - $opening_balance) >= 0 ? '+' : ''; ?><?php echo formatCurrency($running_balance - $opening_balance); ?>
                                            </h4>
                                            <small class="text-muted">During selected period</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Total Credits</h6>
                                            <h4 class="mb-0 text-success"><?php echo formatCurrency($summary['total_payments'] + $summary['total_refunds']); ?></h4>
                                            <small class="text-muted">Payments + Refunds</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted">Total Debits</h6>
                                            <h4 class="mb-0 text-primary"><?php echo formatCurrency($summary['total_purchases'] + $summary['total_adjustments']); ?></h4>
                                            <small class="text-muted">Purchases + Adjustments</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if (!empty($transactions)): ?>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="text-muted">
                                            Showing <?php echo count($transactions); ?> transaction(s)
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end">
                                            <nav aria-label="Transaction navigation">
                                                <ul class="pagination pagination-sm">
                                                    <li class="page-item disabled">
                                                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                                                    </li>
                                                    <li class="page-item active">
                                                        <a class="page-link" href="#">1</a>
                                                    </li>
                                                    <li class="page-item">
                                                        <a class="page-link" href="#">Next</a>
                                                    </li>
                                                </ul>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php')?>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                Loading...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printTransaction()">
                    <i class="mdi mdi-printer me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/rightbar.php')?>
<?php include('includes/scripts.php')?>

<script>
// Function to view transaction details
function viewTransaction(transactionId) {
    fetch('get-transaction-details.php?id=' + transactionId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('transactionDetails').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('transactionDetails').innerHTML = '<div class="alert alert-danger">Error loading transaction details.</div>';
        });
}

// Function to void a transaction
function voidTransaction(transactionId) {
    if (confirm('Are you sure you want to void this transaction? This action cannot be undone.')) {
        fetch('void-transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'transaction_id=' + transactionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Transaction voided successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Network error: ' + error);
        });
    }
}

// Function to print transaction details
function printTransaction() {
    const details = document.getElementById('transactionDetails').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Transaction Details</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
                .transaction-details { border: 1px solid #ddd; padding: 15px; }
                .detail-row { margin-bottom: 10px; }
                .label { font-weight: bold; color: #666; }
                .value { color: #333; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .amount-positive { color: green; font-weight: bold; }
                .amount-negative { color: red; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="transaction-details">
                ${details}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 100);
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Auto-submit date filters when dates change
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    [startDateInput, endDateInput].forEach(input => {
        input.addEventListener('change', function() {
            // Ensure end date is not before start date
            if (startDateInput.value && endDateInput.value && startDateInput.value > endDateInput.value) {
                alert('End date cannot be before start date');
                this.value = '';
                return;
            }
            this.closest('form').submit();
        });
    });
    
    // Quick date range buttons
    const quickButtons = document.querySelectorAll('.quick-date');
    quickButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const range = this.getAttribute('data-range');
            const today = new Date();
            let startDate = new Date();
            let endDate = new Date();
            
            switch(range) {
                case 'today':
                    startDate = endDate = today;
                    break;
                case 'yesterday':
                    startDate = endDate = new Date(today);
                    startDate.setDate(startDate.getDate() - 1);
                    break;
                case 'week':
                    startDate = new Date(today);
                    startDate.setDate(startDate.getDate() - 7);
                    break;
                case 'month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    break;
                case 'year':
                    startDate = new Date(today.getFullYear(), 0, 1);
                    break;
            }
            
            document.querySelector('input[name="start_date"]').value = startDate.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = endDate.toISOString().split('T')[0];
            this.closest('form').submit();
        });
    });
});
</script>

<style>
.amount-positive {
    color: #28a745;
    font-weight: bold;
}
.amount-negative {
    color: #dc3545;
    font-weight: bold;
}
.quick-date {
    cursor: pointer;
    padding: 2px 8px;
    border-radius: 4px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    font-size: 12px;
}
.quick-date:hover {
    background: #e9ecef;
}
.transaction-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}
</style>

</body>
</html>
<?php mysqli_close($conn); ?>