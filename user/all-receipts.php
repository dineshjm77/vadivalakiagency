<?php

include('config/config.php');
include('includes/auth-check.php');

// Ensure only authorized users can access this page
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin', 'lineman'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle search and filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$payment_method = isset($_GET['payment_method']) ? mysqli_real_escape_string($conn, $_GET['payment_method']) : '';
$payment_status = isset($_GET['payment_status']) ? mysqli_real_escape_string($conn, $_GET['payment_status']) : '';
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';
$lineman_id = isset($_GET['lineman_id']) ? intval($_GET['lineman_id']) : 0;

// Handle receipt actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Generate new receipt
    if (isset($_POST['generate_receipt'])) {
        $customer_id = intval($_POST['customer_id']);
        $order_id = intval($_POST['order_id']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $amount = floatval($_POST['amount']);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $reference_no = mysqli_real_escape_string($conn, $_POST['reference_no']);
        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
        
        // Generate receipt number
        $receipt_no = 'RC' . date('Ymd') . rand(1000, 9999);
        
        $insert_sql = "INSERT INTO transactions (customer_id, order_id, type, amount, 
                       payment_method, reference_no, notes, created_by, created_at) 
                       VALUES ($customer_id, $order_id, 'payment', $amount, 
                       '$payment_method', '$reference_no', '$notes', $user_id, '$payment_date')";
        
        if (mysqli_query($conn, $insert_sql)) {
            // Update customer balance
            $update_customer_sql = "UPDATE customers SET 
                                    current_balance = current_balance - $amount,
                                    updated_at = NOW()
                                    WHERE id = $customer_id";
            mysqli_query($conn, $update_customer_sql);
            
            // Update order payment status if applicable
            if ($order_id > 0) {
                // Get order details
                $order_sql = "SELECT total_amount, paid_amount, pending_amount 
                              FROM orders WHERE id = $order_id";
                $order_result = mysqli_query($conn, $order_sql);
                $order_data = mysqli_fetch_assoc($order_result);
                
                if ($order_data) {
                    $new_paid_amount = $order_data['paid_amount'] + $amount;
                    $new_pending_amount = $order_data['total_amount'] - $new_paid_amount;
                    $new_payment_status = $new_pending_amount <= 0 ? 'paid' : ($new_paid_amount > 0 ? 'partial' : 'pending');
                    
                    $update_order_sql = "UPDATE orders SET 
                                         paid_amount = $new_paid_amount,
                                         pending_amount = $new_pending_amount,
                                         payment_status = '$new_payment_status',
                                         updated_at = NOW()
                                         WHERE id = $order_id";
                    mysqli_query($conn, $update_order_sql);
                }
            }
            
            $success_message = "Receipt generated successfully! Receipt No: $receipt_no";
            header("Location: all-receipts.php?success=1");
            exit;
        } else {
            $error_message = "Failed to generate receipt: " . mysqli_error($conn);
        }
    }
    
    // Delete receipt
    if (isset($_POST['delete_receipt'])) {
        $transaction_id = intval($_POST['transaction_id']);
        
        // Get transaction details
        $get_sql = "SELECT * FROM transactions WHERE id = $transaction_id";
        $get_result = mysqli_query($conn, $get_sql);
        $transaction = mysqli_fetch_assoc($get_result);
        
        if ($transaction) {
            // Reverse customer balance
            $update_customer_sql = "UPDATE customers SET 
                                    current_balance = current_balance + $transaction[amount],
                                    updated_at = NOW()
                                    WHERE id = $transaction[customer_id]";
            mysqli_query($conn, $update_customer_sql);
            
            // Reverse order payment if applicable
            if ($transaction['order_id']) {
                $order_sql = "SELECT * FROM orders WHERE id = $transaction[order_id]";
                $order_result = mysqli_query($conn, $order_sql);
                $order_data = mysqli_fetch_assoc($order_result);
                
                if ($order_data) {
                    $new_paid_amount = $order_data['paid_amount'] - $transaction['amount'];
                    $new_pending_amount = $order_data['total_amount'] - $new_paid_amount;
                    $new_payment_status = $new_pending_amount <= 0 ? 'paid' : ($new_paid_amount > 0 ? 'partial' : 'pending');
                    
                    $update_order_sql = "UPDATE orders SET 
                                         paid_amount = $new_paid_amount,
                                         pending_amount = $new_pending_amount,
                                         payment_status = '$new_payment_status',
                                         updated_at = NOW()
                                         WHERE id = $transaction[order_id]";
                    mysqli_query($conn, $update_order_sql);
                }
            }
            
            // Delete transaction
            $delete_sql = "DELETE FROM transactions WHERE id = $transaction_id";
            if (mysqli_query($conn, $delete_sql)) {
                $success_message = "Receipt deleted successfully!";
                header("Location: all-receipts.php?success=1");
                exit;
            } else {
                $error_message = "Failed to delete receipt: " . mysqli_error($conn);
            }
        }
    }
    
    // Export to PDF
    if (isset($_POST['export_pdf'])) {
        require_once('includes/tcpdf/tcpdf.php');
        
        // Create new PDF document
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('APR Water Agencies');
        $pdf->SetAuthor('APR Water Agencies');
        $pdf->SetTitle('All Receipts Report');
        $pdf->SetSubject('Receipts Report');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Build query for receipts data
        $sql = "SELECT t.*, 
                       c.shop_name, 
                       c.customer_name, 
                       c.customer_contact,
                       o.order_number,
                       u.name as created_by_name
                FROM transactions t
                LEFT JOIN customers c ON t.customer_id = c.id
                LEFT JOIN orders o ON t.order_id = o.id
                LEFT JOIN admin_users u ON t.created_by = u.id
                WHERE t.type = 'payment'";
        
        $conditions = [];
        
        // Add search condition
        if (!empty($search)) {
            $conditions[] = "(c.customer_name LIKE '%$search%' OR 
                             c.shop_name LIKE '%$search%' OR 
                             t.reference_no LIKE '%$search%')";
        }
        
        // Add customer filter
        if ($customer_id > 0) {
            $conditions[] = "t.customer_id = $customer_id";
        }
        
        // Add order filter
        if ($order_id > 0) {
            $conditions[] = "t.order_id = $order_id";
        }
        
        // Add payment method filter
        if (!empty($payment_method)) {
            $conditions[] = "t.payment_method = '$payment_method'";
        }
        
        // Add date filters
        if (!empty($start_date)) {
            $conditions[] = "DATE(t.created_at) >= '$start_date'";
        }
        if (!empty($end_date)) {
            $conditions[] = "DATE(t.created_at) <= '$end_date'";
        }
        
        // Add lineman filter
        if ($lineman_id > 0) {
            $conditions[] = "c.assigned_lineman_id = $lineman_id";
        }
        
        // Add conditions to query
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // Order by
        $sql .= " ORDER BY t.created_at DESC";
        
        // Execute query
        $result = mysqli_query($conn, $sql);
        
        // Calculate totals
        $total_amount = 0;
        $receipt_count = 0;
        
        // Header
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'ALL RECEIPTS REPORT', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated on: ' . date('d M, Y h:i A'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated by: ' . $_SESSION['name'], 0, 1, 'C');
        
        if (!empty($search) || $customer_id > 0 || !empty($start_date)) {
            $pdf->Cell(0, 5, 'Filter Criteria Applied', 0, 1, 'C');
        }
        
        $pdf->Ln(10);
        
        // Table header
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(15, 8, 'S.No', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Date', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Receipt ID', 1, 0, 'C', 1);
        $pdf->Cell(40, 8, 'Customer', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Order', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Method', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Amount', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'By', 1, 1, 'C', 1);
        
        // Table content
        $pdf->SetFont('helvetica', '', 9);
        $counter = 1;
        while ($row = mysqli_fetch_assoc($result)) {
            if ($pdf->GetY() > 260) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(15, 8, 'S.No', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Date', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Receipt ID', 1, 0, 'C', 1);
                $pdf->Cell(40, 8, 'Customer', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Order', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Method', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Amount', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'By', 1, 1, 'C', 1);
                $pdf->SetFont('helvetica', '', 9);
            }
            
            $pdf->Cell(15, 7, $counter++, 1, 0, 'C');
            $pdf->Cell(25, 7, date('d-m-Y', strtotime($row['created_at'])), 1, 0, 'C');
            $pdf->Cell(25, 7, substr($row['id'], 0, 8), 1, 0, 'C');
            $pdf->Cell(40, 7, substr($row['customer_name'], 0, 15), 1, 0, 'L');
            $pdf->Cell(20, 7, $row['order_number'] ? substr($row['order_number'], -4) : '-', 1, 0, 'C');
            $pdf->Cell(25, 7, ucfirst($row['payment_method']), 1, 0, 'C');
            $pdf->Cell(25, 7, '₹' . number_format($row['amount'], 2), 1, 0, 'R');
            $pdf->Cell(25, 7, substr($row['created_by_name'], 0, 10), 1, 1, 'C');
            
            $total_amount += $row['amount'];
            $receipt_count++;
        }
        
        // Summary
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Ln(10);
        $pdf->Cell(0, 8, 'SUMMARY', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(100, 7, 'Total Receipts:', 0, 0, 'R');
        $pdf->Cell(30, 7, $receipt_count, 0, 1, 'L');
        $pdf->Cell(100, 7, 'Total Amount Collected:', 0, 0, 'R');
        $pdf->Cell(30, 7, '₹' . number_format($total_amount, 2), 0, 1, 'L');
        
        // Output PDF
        $pdf->Output('all-receipts-' . date('Y-m-d') . '.pdf', 'D');
        exit;
    }
}

// Build query for receipts - FIXED: Specify table aliases
$sql = "SELECT t.id as transaction_id, 
               t.amount, 
               t.payment_method, 
               t.reference_no, 
               t.notes, 
               t.created_at,
               t.order_id,
               t.customer_id,
               c.shop_name, 
               c.customer_name, 
               c.customer_contact,
               o.order_number,
               u.name as created_by_name
        FROM transactions t
        LEFT JOIN customers c ON t.customer_id = c.id
        LEFT JOIN orders o ON t.order_id = o.id
        LEFT JOIN admin_users u ON t.created_by = u.id
        WHERE t.type = 'payment'";

$conditions = [];

// Add search condition
if (!empty($search)) {
    $conditions[] = "(c.customer_name LIKE '%$search%' OR 
                     c.shop_name LIKE '%$search%' OR 
                     t.reference_no LIKE '%$search%')";
}

// Add customer filter
if ($customer_id > 0) {
    $conditions[] = "t.customer_id = $customer_id";
}

// Add order filter
if ($order_id > 0) {
    $conditions[] = "t.order_id = $order_id";
}

// Add payment method filter
if (!empty($payment_method)) {
    $conditions[] = "t.payment_method = '$payment_method'";
}

// Add payment status filter (based on related order)
if (!empty($payment_status)) {
    $conditions[] = "o.payment_status = '$payment_status'";
}

// Add date filters
if (!empty($start_date)) {
    $conditions[] = "DATE(t.created_at) >= '$start_date'";
}
if (!empty($end_date)) {
    $conditions[] = "DATE(t.created_at) <= '$end_date'";
}

// Add lineman filter
if ($lineman_id > 0 && in_array($user_role, ['admin', 'super_admin'])) {
    $conditions[] = "c.assigned_lineman_id = $lineman_id";
} elseif ($user_role == 'lineman') {
    // Lineman can only see their assigned customers
    $conditions[] = "c.assigned_lineman_id = $user_id";
}

// Add conditions to query
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Order by
$sql .= " ORDER BY t.created_at DESC";

// Execute query
$result = mysqli_query($conn, $sql);

// Store result for later use
$receipts_data = [];
$total_amount = 0;
$receipt_count = 0;
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $receipts_data[] = $row;
        $total_amount += $row['amount'];
        $receipt_count++;
    }
    reset($receipts_data);
}

// Get customers for dropdown
$customers_sql = "SELECT id, customer_name, shop_name FROM customers WHERE status = 'active' ORDER BY customer_name";
$customers_result = mysqli_query($conn, $customers_sql);

// Get orders for dropdown
$orders_sql = "SELECT o.id, o.order_number, c.customer_name, o.total_amount, o.pending_amount 
               FROM orders o
               LEFT JOIN customers c ON o.customer_id = c.id
               WHERE o.payment_status != 'paid' AND o.status != 'cancelled' 
               ORDER BY o.created_at DESC";
$orders_result = mysqli_query($conn, $orders_sql);

// Get linemen for dropdown (admin only)
if (in_array($user_role, ['admin', 'super_admin'])) {
    $linemen_sql = "SELECT id, full_name FROM linemen WHERE status = 'active' ORDER BY full_name";
    $linemen_result = mysqli_query($conn, $linemen_sql);
}

// Payment methods
$payment_methods = ['cash', 'bank_transfer', 'cheque', 'upi', 'card', 'online', 'other'];
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
                $current_page = 'all-receipts';
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

                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Total Receipts">Total Receipts</h5>
                                            <h3 class="my-2 py-1"><?php echo $receipt_count; ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    <i class="mdi mdi-receipt"></i>
                                                </span>
                                                <span>All time receipts</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-primary bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-primary text-primary">
                                                    <i class="mdi mdi-receipt"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="Total Collected">Total Collected</h5>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($total_amount, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-success me-2">
                                                    ₹
                                                </span>
                                                <span>Total amount</span>
                                            </p>
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

                        <div class="col-xl-3 col-md-6">
                            <div class="card card-h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1">
                                            <h5 class="text-muted fw-normal mt-0" title="Today's Collection">Today's Collection</h5>
                                            <?php
                                            $today_sql = "SELECT SUM(t.amount) as total 
                                                         FROM transactions t
                                                         WHERE t.type = 'payment' 
                                                         AND DATE(t.created_at) = CURDATE()";
                                            if ($user_role == 'lineman') {
                                                $today_sql .= " AND EXISTS (
                                                    SELECT 1 FROM customers c 
                                                    WHERE c.id = t.customer_id 
                                                    AND c.assigned_lineman_id = $user_id
                                                )";
                                            }
                                            $today_result = mysqli_query($conn, $today_sql);
                                            $today_data = mysqli_fetch_assoc($today_result);
                                            $today_total = $today_data['total'] ?? 0;
                                            ?>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($today_total, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-info me-2">
                                                    <i class="mdi mdi-calendar-today"></i>
                                                </span>
                                                <span>Today's receipts</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-info bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-info text-info">
                                                    <i class="mdi mdi-calendar"></i>
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
                                            <h5 class="text-muted fw-normal mt-0" title="This Month">This Month</h5>
                                            <?php
                                            $month_sql = "SELECT SUM(t.amount) as total 
                                                         FROM transactions t
                                                         WHERE t.type = 'payment' 
                                                         AND MONTH(t.created_at) = MONTH(CURDATE())
                                                         AND YEAR(t.created_at) = YEAR(CURDATE())";
                                            if ($user_role == 'lineman') {
                                                $month_sql .= " AND EXISTS (
                                                    SELECT 1 FROM customers c 
                                                    WHERE c.id = t.customer_id 
                                                    AND c.assigned_lineman_id = $user_id
                                                )";
                                            }
                                            $month_result = mysqli_query($conn, $month_sql);
                                            $month_data = mysqli_fetch_assoc($month_result);
                                            $month_total = $month_data['total'] ?? 0;
                                            ?>
                                            <h3 class="my-2 py-1">₹<?php echo number_format($month_total, 2); ?></h3>
                                            <p class="mb-0 text-muted">
                                                <span class="text-warning me-2">
                                                    <i class="mdi mdi-calendar-month"></i>
                                                </span>
                                                <span>Month collection</span>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <div class="avatar-sm rounded-circle bg-warning bg-soft">
                                                <span class="avatar-title font-size-22 rounded-circle bg-soft-warning text-warning">
                                                    <i class="mdi mdi-calendar-month"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- Quick Actions -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if (in_array($user_role, ['admin', 'super_admin', 'lineman'])): ?>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReceiptModal">
                                            <i class="mdi mdi-receipt me-1"></i> Generate Receipt
                                        </button>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;">
                                            <button type="submit" name="export_pdf" class="btn btn-outline-danger">
                                                <i class="mdi mdi-file-pdf me-1"></i> Export PDF
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-outline-info" onclick="printReceipts()">
                                            <i class="mdi mdi-printer me-1"></i> Print Report
                                        </button>
                                        <a href="customers.php" class="btn btn-outline-secondary">
                                            <i class="mdi mdi-account-multiple me-1"></i> View Customers
                                        </a>
                                        <a href="orders.php" class="btn btn-outline-secondary">
                                            <i class="mdi mdi-clipboard-list me-1"></i> View Orders
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end row -->

                    <!-- All Receipts -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h4 class="card-title mb-0">All Receipts</h4>
                                            <p class="card-title-desc">View and manage all payment receipts</p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex justify-content-end">
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="filterToday()">
                                                        Today
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="filterThisWeek()">
                                                        This Week
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="filterThisMonth()">
                                                        This Month
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Search & Filter Form -->
                                    <form method="GET" class="row g-3 mb-4">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" name="search" 
                                                   value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search customer, shop, reference...">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="customer_id">
                                                <option value="0">All Customers</option>
                                                <?php 
                                                while ($cust = mysqli_fetch_assoc($customers_result)): 
                                                ?>
                                                <option value="<?php echo $cust['id']; ?>" 
                                                    <?php echo $customer_id == $cust['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cust['customer_name'] . ' - ' . $cust['shop_name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="payment_method">
                                                <option value="">All Methods</option>
                                                <?php foreach ($payment_methods as $method): ?>
                                                <option value="<?php echo $method; ?>" 
                                                    <?php echo $payment_method == $method ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" name="payment_status">
                                                <option value="">All Status</option>
                                                <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                                <option value="pending" <?php echo $payment_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="start_date" 
                                                       value="<?php echo $start_date; ?>">
                                                <span class="input-group-text">to</span>
                                                <input type="date" class="form-control" name="end_date" 
                                                       value="<?php echo $end_date; ?>">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="mdi mdi-filter"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                        <div class="col-md-2">
                                            <select class="form-select" name="lineman_id">
                                                <option value="0">All Linemen</option>
                                                <?php 
                                                if (isset($linemen_result)) {
                                                    mysqli_data_seek($linemen_result, 0);
                                                    while ($lineman = mysqli_fetch_assoc($linemen_result)): 
                                                ?>
                                                <option value="<?php echo $lineman['id']; ?>" 
                                                    <?php echo $lineman_id == $lineman['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($lineman['full_name']); ?>
                                                </option>
                                                <?php 
                                                    endwhile;
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                    </form>

                                    <?php if (!empty($search) || $customer_id > 0 || !empty($payment_method) || !empty($start_date)): ?>
                                    <div class="mb-3">
                                        <a href="all-receipts.php" class="btn btn-sm btn-outline-secondary">
                                            <i class="mdi mdi-refresh me-1"></i> Clear Filters
                                        </a>
                                        <span class="ms-3 text-muted">
                                            Showing <?php echo $receipt_count; ?> receipts
                                            <?php if ($total_amount > 0): ?>
                                            • Total: ₹<?php echo number_format($total_amount, 2); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Receipt Details</th>
                                                    <th>Customer Info</th>
                                                    <th>Order Details</th>
                                                    <th>Payment Info</th>
                                                    <th class="text-end">Amount</th>
                                                    <th class="text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if (!empty($receipts_data)) {
                                                    $counter = 1;
                                                    foreach ($receipts_data as $row) {
                                                        $method_class = '';
                                                        switch ($row['payment_method']) {
                                                            case 'cash':
                                                                $method_class = 'success';
                                                                break;
                                                            case 'bank_transfer':
                                                            case 'cheque':
                                                                $method_class = 'primary';
                                                                break;
                                                            case 'upi':
                                                            case 'online':
                                                                $method_class = 'info';
                                                                break;
                                                            default:
                                                                $method_class = 'secondary';
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td><?php echo $counter++; ?></td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">
                                                                        #<?php echo $row['transaction_id']; ?>
                                                                    </h5>
                                                                    <p class="text-muted mb-0">
                                                                        <i class="mdi mdi-calendar me-1"></i>
                                                                        <?php echo date('d M, Y', strtotime($row['created_at'])); ?>
                                                                        <br>
                                                                        <small>
                                                                            <i class="mdi mdi-clock-outline me-1"></i>
                                                                            <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                                                        </small>
                                                                    </p>
                                                                    <?php if (!empty($row['reference_no'])): ?>
                                                                    <small class="text-muted">
                                                                        Ref: <?php echo $row['reference_no']; ?>
                                                                    </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <?php echo htmlspecialchars($row['customer_name']); ?>
                                                                    </h5>
                                                                    <p class="text-muted mb-0">
                                                                        <?php echo htmlspecialchars($row['shop_name']); ?>
                                                                        <br>
                                                                        <small>
                                                                            <i class="mdi mdi-phone me-1"></i>
                                                                            <?php echo $row['customer_contact']; ?>
                                                                        </small>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php if ($row['order_number']): ?>
                                                                <div>
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="order-details.php?id=<?php echo $row['order_id']; ?>" class="text-dark">
                                                                            <?php echo $row['order_number']; ?>
                                                                        </a>
                                                                    </h5>
                                                                </div>
                                                                <?php else: ?>
                                                                <span class="badge bg-secondary-subtle text-secondary">Direct Payment</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    <span class="badge bg-<?php echo $method_class; ?>-subtle text-<?php echo $method_class; ?> mb-1">
                                                                        <?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        By: <?php echo $row['created_by_name']; ?>
                                                                    </small>
                                                                    <?php if (!empty($row['notes'])): ?>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        Note: <?php echo htmlspecialchars(substr($row['notes'], 0, 50)); ?>
                                                                        <?php echo strlen($row['notes']) > 50 ? '...' : ''; ?>
                                                                    </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td class="text-end">
                                                                <h5 class="font-size-16 mb-0 text-success">
                                                                    ₹<?php echo number_format($row['amount'], 2); ?>
                                                                </h5>
                                                            </td>
                                                            <td class="text-center">
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-outline-info" 
                                                                            data-bs-toggle="modal" data-bs-target="#viewReceiptModal"
                                                                            data-receipt-id="<?php echo $row['transaction_id']; ?>"
                                                                            data-customer-name="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                                                            data-shop-name="<?php echo htmlspecialchars($row['shop_name']); ?>"
                                                                            data-phone="<?php echo $row['customer_contact']; ?>"
                                                                            data-order-number="<?php echo $row['order_number'] ?: 'Direct Payment'; ?>"
                                                                            data-payment-method="<?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?>"
                                                                            data-amount="<?php echo number_format($row['amount'], 2); ?>"
                                                                            data-reference="<?php echo $row['reference_no'] ?: 'N/A'; ?>"
                                                                            data-notes="<?php echo htmlspecialchars($row['notes']); ?>"
                                                                            data-date="<?php echo date('d M, Y h:i A', strtotime($row['created_at'])); ?>"
                                                                            data-created-by="<?php echo $row['created_by_name']; ?>"
                                                                            title="View Receipt">
                                                                        <i class="mdi mdi-eye"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-primary" 
                                                                            onclick="printSingleReceipt(<?php echo $row['transaction_id']; ?>)"
                                                                            title="Print Receipt">
                                                                        <i class="mdi mdi-printer"></i>
                                                                    </button>
                                                                    <?php if (in_array($user_role, ['admin', 'super_admin'])): ?>
                                                                    <button type="button" class="btn btn-outline-danger" 
                                                                            data-bs-toggle="modal" data-bs-target="#deleteReceiptModal"
                                                                            data-receipt-id="<?php echo $row['transaction_id']; ?>"
                                                                            data-customer-name="<?php echo htmlspecialchars($row['customer_name']); ?>"
                                                                            data-amount="<?php echo number_format($row['amount'], 2); ?>"
                                                                            data-date="<?php echo date('d M, Y', strtotime($row['created_at'])); ?>"
                                                                            title="Delete Receipt">
                                                                        <i class="mdi mdi-delete"></i>
                                                                    </button>
                                                                    <?php endif; ?>
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
                                                                <i class="mdi mdi-receipt display-4"></i>
                                                                <h5 class="mt-2">No Receipts Found</h5>
                                                                <p>No payment receipts match your search criteria</p>
                                                                <?php if (in_array($user_role, ['admin', 'super_admin', 'lineman'])): ?>
                                                                <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#generateReceiptModal">
                                                                    <i class="mdi mdi-receipt me-1"></i> Generate Your First Receipt
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                                ?>
                                            </tbody>
                                            <?php if (!empty($receipts_data)): ?>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                                    <td class="text-end"><strong>₹<?php echo number_format($total_amount, 2); ?></strong></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <div class="row mt-3">
                                        <div class="col-sm-12 col-md-5">
                                            <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                                Showing <?php echo $receipt_count; ?> receipts • 
                                                Total: ₹<?php echo number_format($total_amount, 2); ?>
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

    <!-- Generate Receipt Modal -->
    <div class="modal fade" id="generateReceiptModal" tabindex="-1" aria-labelledby="generateReceiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="generateReceiptForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="generateReceiptModalLabel">Generate Payment Receipt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="customer_id" class="form-label">Customer *</label>
                                <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerOrders()">
                                    <option value="">Select Customer</option>
                                    <?php 
                                    mysqli_data_seek($customers_result, 0);
                                    while ($cust = mysqli_fetch_assoc($customers_result)): 
                                    ?>
                                    <option value="<?php echo $cust['id']; ?>">
                                        <?php echo htmlspecialchars($cust['customer_name'] . ' - ' . $cust['shop_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="order_id" class="form-label">Order (Optional)</label>
                                <select class="form-select" id="order_id" name="order_id" onchange="loadOrderDetails()">
                                    <option value="">Select Order</option>
                                    <?php 
                                    mysqli_data_seek($orders_result, 0);
                                    while ($order = mysqli_fetch_assoc($orders_result)): 
                                    ?>
                                    <option value="<?php echo $order['id']; ?>" data-customer="<?php echo $order['customer_name']; ?>" data-total="<?php echo $order['total_amount']; ?>" data-pending="<?php echo $order['pending_amount']; ?>">
                                        <?php echo $order['order_number'] . ' - ' . htmlspecialchars($order['customer_name']) . ' (₹' . $order['total_amount'] . ')'; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="payment_method" class="form-label">Payment Method *</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($payment_methods as $method): ?>
                                    <option value="<?php echo $method; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="amount" class="form-label">Amount (₹) *</label>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="payment_date" class="form-label">Payment Date *</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="reference_no" class="form-label">Reference Number</label>
                                <input type="text" class="form-control" id="reference_no" name="reference_no" 
                                       placeholder="Cheque/Transaction/UPI ID">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <input type="text" class="form-control" id="notes" name="notes" 
                                       placeholder="Additional information">
                            </div>
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading mb-2">Customer Information</h6>
                                    <div id="customer_info" class="text-muted">Select a customer to view details</div>
                                    <div id="order_info" class="mt-2 text-muted"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_receipt" class="btn btn-primary">Generate Receipt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Receipt Modal -->
    <div class="modal fade" id="viewReceiptModal" tabindex="-1" aria-labelledby="viewReceiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewReceiptModalLabel">Receipt Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-3 text-primary">PAYMENT RECEIPT</h4>
                        </div>
                        <div class="col-md-4 text-end">
                            <h5 class="text-muted" id="receipt_number">#</h5>
                            <p class="text-muted mb-0" id="receipt_date"></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="mb-2">Customer Details</h6>
                            <p class="mb-1" id="customer_details"></p>
                            <p class="mb-1" id="shop_details"></p>
                            <p class="mb-0" id="phone_details"></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Payment Details</h6>
                            <p class="mb-1"><strong>Order:</strong> <span id="order_details"></span></p>
                            <p class="mb-1"><strong>Payment Method:</strong> <span id="method_details"></span></p>
                            <p class="mb-1"><strong>Reference:</strong> <span id="reference_details"></span></p>
                            <p class="mb-0"><strong>Processed By:</strong> <span id="created_by"></span></p>
                        </div>
                    </div>
                    
                    <div class="alert alert-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">PAID AMOUNT</h5>
                            <h2 class="mb-0" id="amount_details"></h2>
                        </div>
                    </div>
                    
                    <div class="mt-3" id="notes_section">
                        <h6>Notes</h6>
                        <p class="text-muted mb-0" id="notes_details"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printModalReceipt()">
                        <i class="mdi mdi-printer me-1"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Receipt Modal -->
    <div class="modal fade" id="deleteReceiptModal" tabindex="-1" aria-labelledby="deleteReceiptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteReceiptForm">
                    <input type="hidden" name="transaction_id" id="delete_receipt_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteReceiptModalLabel">Delete Receipt</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="mdi mdi-alert-circle-outline text-danger display-4"></i>
                        </div>
                        <h5 class="text-center mb-3">Are you sure you want to delete this receipt?</h5>
                        <p class="text-center text-muted">
                            Customer: <strong id="delete_customer_name"></strong>
                            <br>
                            Amount: <strong id="delete_amount"></strong>
                            <br>
                            Date: <strong id="delete_date"></strong>
                        </p>
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-circle-outline me-2"></i>
                            <strong>Warning:</strong> This will reverse the payment from customer's balance and order status!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_receipt" class="btn btn-danger">Delete Receipt</button>
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
        // View Receipt Modal Handler
        const viewReceiptModal = document.getElementById('viewReceiptModal');
        if (viewReceiptModal) {
            viewReceiptModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('receipt_number').textContent = '#' + button.getAttribute('data-receipt-id');
                document.getElementById('receipt_date').textContent = button.getAttribute('data-date');
                document.getElementById('customer_details').textContent = button.getAttribute('data-customer-name');
                document.getElementById('shop_details').textContent = button.getAttribute('data-shop-name');
                document.getElementById('phone_details').textContent = 'Phone: ' + button.getAttribute('data-phone');
                document.getElementById('order_details').textContent = button.getAttribute('data-order-number');
                document.getElementById('method_details').textContent = button.getAttribute('data-payment-method');
                document.getElementById('reference_details').textContent = button.getAttribute('data-reference');
                document.getElementById('amount_details').textContent = '₹' + button.getAttribute('data-amount');
                document.getElementById('notes_details').textContent = button.getAttribute('data-notes') || 'No notes';
                document.getElementById('created_by').textContent = button.getAttribute('data-created-by');
                
                // Hide notes section if no notes
                const notesSection = document.getElementById('notes_section');
                if (!button.getAttribute('data-notes') || button.getAttribute('data-notes') === 'No notes') {
                    notesSection.style.display = 'none';
                } else {
                    notesSection.style.display = 'block';
                }
            });
        }
        
        // Delete Receipt Modal Handler
        const deleteReceiptModal = document.getElementById('deleteReceiptModal');
        if (deleteReceiptModal) {
            deleteReceiptModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                document.getElementById('delete_receipt_id').value = button.getAttribute('data-receipt-id');
                document.getElementById('delete_customer_name').textContent = button.getAttribute('data-customer-name');
                document.getElementById('delete_amount').textContent = '₹' + button.getAttribute('data-amount');
                document.getElementById('delete_date').textContent = button.getAttribute('data-date');
            });
        }
        
        // Load customer orders
        function loadCustomerOrders() {
            const customerId = document.getElementById('customer_id').value;
            const orderSelect = document.getElementById('order_id');
            
            // Reset order select
            orderSelect.innerHTML = '<option value="">Select Order</option>';
            
            if (customerId) {
                // Fetch customer information
                fetch('get-customer-info.php?id=' + customerId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const infoDiv = document.getElementById('customer_info');
                            infoDiv.innerHTML = `
                                <strong>${data.customer_name}</strong><br>
                                ${data.shop_name}<br>
                                Phone: ${data.customer_contact}<br>
                                Balance: ₹${data.current_balance}
                            `;
                        }
                    });
                
                // Fetch customer's pending orders
                fetch('get-customer-orders.php?customer_id=' + customerId + '&status=pending')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.orders.length > 0) {
                            data.orders.forEach(order => {
                                const option = document.createElement('option');
                                option.value = order.id;
                                option.textContent = `${order.order_number} - ₹${order.total_amount} (Pending: ₹${order.pending_amount})`;
                                option.setAttribute('data-total', order.total_amount);
                                option.setAttribute('data-pending', order.pending_amount);
                                orderSelect.appendChild(option);
                            });
                        }
                    });
            } else {
                document.getElementById('customer_info').textContent = 'Select a customer to view details';
            }
        }
        
        // Load order details
        function loadOrderDetails() {
            const orderSelect = document.getElementById('order_id');
            const selectedOption = orderSelect.options[orderSelect.selectedIndex];
            
            if (selectedOption.value) {
                const totalAmount = selectedOption.getAttribute('data-total');
                const pendingAmount = selectedOption.getAttribute('data-pending');
                const infoDiv = document.getElementById('order_info');
                infoDiv.innerHTML = `
                    <strong>Selected Order:</strong> ${selectedOption.textContent}<br>
                    <small class="text-muted">You can enter any amount up to ₹${pendingAmount}</small>
                `;
                
                // Set amount field to pending amount if available
                document.getElementById('amount').value = pendingAmount;
                document.getElementById('amount').max = pendingAmount;
            } else {
                document.getElementById('order_info').innerHTML = '';
                document.getElementById('amount').value = '';
                document.getElementById('amount').max = '';
            }
        }
        
        // Filter functions
        function filterToday() {
            const today = new Date().toISOString().split('T')[0];
            window.location.href = `all-receipts.php?start_date=${today}&end_date=${today}`;
        }
        
        function filterThisWeek() {
            const today = new Date();
            const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
            const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 6));
            
            const startDate = firstDay.toISOString().split('T')[0];
            const endDate = lastDay.toISOString().split('T')[0];
            
            window.location.href = `all-receipts.php?start_date=${startDate}&end_date=${endDate}`;
        }
        
        function filterThisMonth() {
            const today = new Date();
            const startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            const endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
            
            window.location.href = `all-receipts.php?start_date=${startDate}&end_date=${endDate}`;
        }
        
        // Print receipts report
        function printReceipts() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>All Receipts Report - <?php echo $_SESSION['name']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; margin-bottom: 30px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; }
                        .text-right { text-align: right; }
                        .text-center { text-align: center; }
                        .summary { margin-top: 30px; padding: 15px; background-color: #f8f9fa; }
                        @media print {
                            @page { margin: 0.5in; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>ALL RECEIPTS REPORT</h1>
                    <p><strong>Generated By:</strong> <?php echo $_SESSION['name']; ?></p>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <?php if (!empty($search) || $customer_id > 0 || !empty($start_date)): ?>
                    <p><strong>Filter Criteria:</strong></p>
                    <ul>
                        <?php if (!empty($search)): ?><li>Search: <?php echo htmlspecialchars($search); ?></li><?php endif; ?>
                        <?php if ($customer_id > 0): ?><li>Customer: <?php echo $customer_id; ?></li><?php endif; ?>
                        <?php if (!empty($start_date)): ?><li>Date Range: <?php echo $start_date; ?> to <?php echo $end_date; ?></li><?php endif; ?>
                    </ul>
                    <?php endif; ?>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Receipt ID</th>
                                <th>Customer</th>
                                <th>Order</th>
                                <th>Payment Method</th>
                                <th class="text-right">Amount</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $print_counter = 1;
                            if (!empty($receipts_data)) {
                                foreach ($receipts_data as $row) {
                                    echo '<tr>';
                                    echo '<td>' . $print_counter++ . '</td>';
                                    echo '<td>' . date('d-m-Y', strtotime($row['created_at'])) . '</td>';
                                    echo '<td>#' . $row['transaction_id'] . '</td>';
                                    echo '<td>' . htmlspecialchars($row['customer_name']) . '</td>';
                                    echo '<td>' . ($row['order_number'] ?: 'Direct') . '</td>';
                                    echo '<td>' . ucfirst(str_replace('_', ' ', $row['payment_method'])) . '</td>';
                                    echo '<td class="text-right">₹' . number_format($row['amount'], 2) . '</td>';
                                    echo '<td>' . $row['created_by_name'] . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                        <?php if (!empty($receipts_data)): ?>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>₹<?php echo number_format($total_amount, 2); ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                    
                    <div class="summary">
                        <h3>SUMMARY</h3>
                        <p><strong>Total Receipts:</strong> <?php echo $receipt_count; ?></p>
                        <p><strong>Total Amount Collected:</strong> ₹<?php echo number_format($total_amount, 2); ?></p>
                        <p><strong>Today's Collection:</strong> ₹<?php 
                        $today_sql = "SELECT SUM(t.amount) as total FROM transactions t WHERE t.type = 'payment' AND DATE(t.created_at) = CURDATE()";
                        $today_result = mysqli_query($conn, $today_sql);
                        $today_data = mysqli_fetch_assoc($today_result);
                        echo number_format($today_data['total'] ?? 0, 2);
                        ?></p>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center;">
                        <p>Generated on: <?php echo date('d M, Y h:i A'); ?></p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        
        // Print single receipt
        function printSingleReceipt(receiptId) {
            const printWindow = window.open('', '_blank');
            
            // Prepare receipt data for JavaScript
            const receipts = <?php 
                $receipts_json = [];
                if (!empty($receipts_data)) {
                    foreach ($receipts_data as $row) {
                        $receipts_json[] = [
                            'id' => $row['transaction_id'],
                            'customer_name' => $row['customer_name'],
                            'shop_name' => $row['shop_name'],
                            'customer_contact' => $row['customer_contact'],
                            'order_number' => $row['order_number'],
                            'payment_method' => $row['payment_method'],
                            'reference_no' => $row['reference_no'],
                            'amount' => $row['amount'],
                            'notes' => $row['notes'],
                            'created_at' => $row['created_at'],
                            'created_by_name' => $row['created_by_name']
                        ];
                    }
                }
                echo json_encode($receipts_json);
            ?>;
            
            const receipt = receipts.find(r => r.id == receiptId);
            
            if (receipt) {
                printWindow.document.write(`
                    <html>
                    <head>
                        <title>Receipt #${receipt.id}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .receipt-container { max-width: 500px; margin: 0 auto; }
                            .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
                            .header h1 { margin: 0; color: #2c3e50; }
                            .header p { margin: 5px 0; color: #7f8c8d; }
                            .details { margin: 20px 0; }
                            .row { display: flex; justify-content: space-between; margin: 10px 0; }
                            .label { font-weight: bold; }
                            .amount-box { background-color: #27ae60; color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
                            .footer { text-align: center; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
                            @media print {
                                @page { margin: 0; }
                                body { margin: 0.5in; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="receipt-container">
                            <div class="header">
                                <h1>APR WATER AGENCIES</h1>
                                <p>Payment Receipt</p>
                            </div>
                            
                            <div class="row">
                                <div>
                                    <div class="label">Receipt No:</div>
                                    <div>#${receipt.id}</div>
                                </div>
                                <div>
                                    <div class="label">Date:</div>
                                    <div>${new Date(receipt.created_at).toLocaleDateString()}</div>
                                </div>
                            </div>
                            
                            <div class="details">
                                <div class="row">
                                    <div>
                                        <div class="label">Customer Name:</div>
                                        <div>${receipt.customer_name}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div>
                                        <div class="label">Shop Name:</div>
                                        <div>${receipt.shop_name}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div>
                                        <div class="label">Phone:</div>
                                        <div>${receipt.customer_contact}</div>
                                    </div>
                                </div>
                                ${receipt.order_number ? `
                                <div class="row">
                                    <div>
                                        <div class="label">Order No:</div>
                                        <div>${receipt.order_number}</div>
                                    </div>
                                </div>` : ''}
                                <div class="row">
                                    <div>
                                        <div class="label">Payment Method:</div>
                                        <div>${receipt.payment_method.replace('_', ' ').toUpperCase()}</div>
                                    </div>
                                </div>
                                ${receipt.reference_no ? `
                                <div class="row">
                                    <div>
                                        <div class="label">Reference:</div>
                                        <div>${receipt.reference_no}</div>
                                    </div>
                                </div>` : ''}
                            </div>
                            
                            <div class="amount-box">
                                <h2>₹${parseFloat(receipt.amount).toFixed(2)}</h2>
                                <p>Amount Received</p>
                            </div>
                            
                            ${receipt.notes ? `
                            <div class="row">
                                <div>
                                    <div class="label">Notes:</div>
                                    <div>${receipt.notes}</div>
                                </div>
                            </div>` : ''}
                            
                            <div class="footer">
                                <p>Processed by: ${receipt.created_by_name}</p>
                                <p>Thank you for your payment!</p>
                                <p>Generated on: ${new Date().toLocaleString()}</p>
                            </div>
                        </div>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            }
        }
        
        // Print modal receipt
        function printModalReceipt() {
            const modal = document.getElementById('viewReceiptModal');
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Receipt Details</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .receipt-container { max-width: 500px; margin: 0 auto; }
                        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
                        .header h1 { margin: 0; color: #2c3e50; }
                        .header p { margin: 5px 0; color: #7f8c8d; }
                        .details { margin: 20px 0; }
                        .row { display: flex; justify-content: space-between; margin: 10px 0; }
                        .label { font-weight: bold; }
                        .amount-box { background-color: #27ae60; color: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
                        .footer { text-align: center; margin-top: 30px; border-top: 1px dashed #ccc; padding-top: 10px; }
                        @media print {
                            @page { margin: 0; }
                            body { margin: 0.5in; }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-container">
                        <div class="header">
                            <h1>APR WATER AGENCIES</h1>
                            <p>Payment Receipt</p>
                        </div>
                        
                        <div class="row">
                            <div>
                                <div class="label">Receipt No:</div>
                                <div>${document.getElementById('receipt_number').textContent}</div>
                            </div>
                            <div>
                                <div class="label">Date:</div>
                                <div>${document.getElementById('receipt_date').textContent.split(' ')[0]}</div>
                            </div>
                        </div>
                        
                        <div class="details">
                            <div class="row">
                                <div>
                                    <div class="label">Customer Name:</div>
                                    <div>${document.getElementById('customer_details').textContent}</div>
                                </div>
                            </div>
                            <div class="row">
                                <div>
                                    <div class="label">Shop Name:</div>
                                    <div>${document.getElementById('shop_details').textContent}</div>
                                </div>
                            </div>
                            <div class="row">
                                <div>
                                    <div class="label">Phone:</div>
                                    <div>${document.getElementById('phone_details').textContent.replace('Phone: ', '')}</div>
                                </div>
                            </div>
                            <div class="row">
                                <div>
                                    <div class="label">Order No:</div>
                                    <div>${document.getElementById('order_details').textContent}</div>
                                </div>
                            </div>
                            <div class="row">
                                <div>
                                    <div class="label">Payment Method:</div>
                                    <div>${document.getElementById('method_details').textContent.toUpperCase()}</div>
                                </div>
                            </div>
                            <div class="row">
                                <div>
                                    <div class="label">Reference:</div>
                                    <div>${document.getElementById('reference_details').textContent}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="amount-box">
                            <h2>${document.getElementById('amount_details').textContent}</h2>
                            <p>Amount Received</p>
                        </div>
                        
                        ${document.getElementById('notes_details').textContent !== 'No notes' ? `
                        <div class="row">
                            <div>
                                <div class="label">Notes:</div>
                                <div>${document.getElementById('notes_details').textContent}</div>
                            </div>
                        </div>` : ''}
                        
                        <div class="footer">
                            <p>Processed by: ${document.getElementById('created_by').textContent}</p>
                            <p>Thank you for your payment!</p>
                            <p>Generated on: ${new Date().toLocaleString()}</p>
                        </div>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 500);
        }
        
        // Initialize date fields
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date as default for end date in filter
            if (!document.querySelector('input[name="end_date"]').value) {
                document.querySelector('input[name="end_date"]').value = new Date().toISOString().split('T')[0];
            }
            
            // Set start date as first day of current month if empty
            if (!document.querySelector('input[name="start_date"]').value) {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                document.querySelector('input[name="start_date"]').value = firstDay;
            }
        });
        
        // Form validation
        document.getElementById('generateReceiptForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const maxAmount = parseFloat(document.getElementById('amount').max);
            
            if (amount <= 0) {
                e.preventDefault();
                alert('Amount must be greater than 0!');
                document.getElementById('amount').focus();
                return;
            }
            
            if (maxAmount && amount > maxAmount) {
                e.preventDefault();
                alert('Amount cannot exceed the pending amount!');
                document.getElementById('amount').focus();
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
?>