<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

$po_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($po_id == 0) {
    header('Location: purchase-orders.php');
    exit;
}

// Fetch purchase order details
$sql = "SELECT po.*, c.vendor_name, c.company_name, c.address, c.phone, c.gstin, c.email
        FROM purchase_orders po
        LEFT JOIN creditors c ON po.creditor_id = c.id
        WHERE po.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $po_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$po = mysqli_fetch_assoc($result);

if (!$po) {
    header('Location: purchase-orders.php');
    exit;
}

// Fetch items
$items = [];
$sql = "SELECT * FROM purchase_order_items WHERE po_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $po_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Fetch payments
$payments = [];
$sql = "SELECT * FROM purchase_payments WHERE po_id = ? ORDER BY payment_date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $po_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $payments[] = $row;
}

$balance = $po['total_amount'] - $po['paid_amount'];
?>

<body data-sidebar="dark">
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu">
        <div data-simplebar class="h-100">
            <?php include('includes/sidebar.php'); ?>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h4 class="mb-0">Purchase Order Details</h4>
                            <div>
                                <a href="print-purchase-order.php?id=<?php echo $po_id; ?>" class="btn btn-info" target="_blank">
                                    <i class="fas fa-print me-1"></i> Print
                                </a>
                                <a href="edit-purchase-order.php?id=<?php echo $po_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <a href="purchase-orders.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PO Header -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Purchase Order Information</h5>
                                <hr>
                                <p><strong>PO Number:</strong> <?php echo htmlspecialchars($po['po_number']); ?></p>
                                <p><strong>Order Date:</strong> <?php echo date('d M Y', strtotime($po['order_date'])); ?></p>
                                <p><strong>Expected Delivery:</strong> <?php echo $po['expected_delivery_date'] ? date('d M Y', strtotime($po['expected_delivery_date'])) : '-'; ?></p>
                                <p><strong>Order Status:</strong> 
                                    <span class="badge <?php 
                                        echo $po['order_status'] == 'delivered' ? 'bg-success' : 
                                            ($po['order_status'] == 'cancelled' ? 'bg-danger' : 
                                            ($po['order_status'] == 'shipped' ? 'bg-info' : 
                                            ($po['order_status'] == 'confirmed' ? 'bg-primary' : 'bg-secondary')));
                                    ?>">
                                        <?php echo ucfirst($po['order_status']); ?>
                                    </span>
                                </p>
                                <p><strong>Payment Status:</strong> 
                                    <span class="badge <?php 
                                        echo $po['payment_status'] == 'paid' ? 'bg-success' : 
                                            ($po['payment_status'] == 'partial' ? 'bg-warning' : 'bg-danger');
                                    ?>">
                                        <?php echo ucfirst($po['payment_status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Vendor Information</h5>
                                <hr>
                                <p><strong>Vendor Name:</strong> <?php echo htmlspecialchars($po['vendor_name']); ?></p>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($po['company_name'] ?: '-'); ?></p>
                                <p><strong>Phone:</strong> <?php echo $po['phone']; ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($po['email'] ?: '-'); ?></p>
                                <p><strong>GSTIN:</strong> <?php echo $po['gstin'] ?: '-'; ?></p>
                                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($po['address'] ?: '-')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Order Items</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Unit Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $index => $item): ?>
                                             <tr>
                                                 <td><?php echo $index + 1; ?></td>
                                                 <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                 <td><?php echo $item['quantity']; ?></td>
                                                 <td><?php echo '₹' . number_format($item['unit_price'], 2); ?></td>
                                                 <td><?php echo '₹' . number_format($item['total'], 2); ?></td>
                                             </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                                <td><strong><?php echo '₹' . number_format($po['subtotal'], 2); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end"><strong>GST (18%):</strong></td>
                                                <td><strong><?php echo '₹' . number_format($po['tax_amount'], 2); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                                <td><strong class="text-info"><?php echo '₹' . number_format($po['total_amount'], 2); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end"><strong>Paid Amount:</strong></td>
                                                <td><strong class="text-success"><?php echo '₹' . number_format($po['paid_amount'], 2); ?></strong></td>
                                            </tr>
                                            <tr class="table-light">
                                                <td colspan="4" class="text-end"><strong>Balance:</strong></td>
                                                <td><strong class="<?php echo $balance > 0 ? 'text-warning' : 'text-success'; ?>">
                                                    <?php echo '₹' . number_format($balance, 2); ?>
                                                </strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <?php if (!empty($payments)): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Payment History</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Notes</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                                <td class="text-success"><?php echo '₹' . number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['reference_no'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['notes'] ?: '-'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notes -->
                <?php if ($po['notes']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Notes</h5>
                                <p><?php echo nl2br(htmlspecialchars($po['notes'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
</body>
</html>