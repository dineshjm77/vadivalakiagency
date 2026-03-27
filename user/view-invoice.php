<?php
include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id == 0) {
    header('Location: quick-order.php');
    exit;
}

// Fetch order details with customer and lineman info
$order_sql = "SELECT o.*, 
               c.shop_name, c.customer_name, c.customer_contact, c.shop_location, c.customer_code,
               l.full_name as lineman_name, l.phone as lineman_phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN linemen l ON o.created_by = l.id
        WHERE o.id = $order_id AND o.created_by = $lineman_id";

$order_result = mysqli_query($conn, $order_sql);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    // Order not found or doesn't belong to this lineman
    header('Location: quick-order.php');
    exit;
}

$order = mysqli_fetch_assoc($order_result);

// Fetch order items
$items_sql = "SELECT oi.*, p.product_name, p.product_code 
              FROM order_items oi
              LEFT JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = $order_id
              ORDER BY oi.id";
$items_result = mysqli_query($conn, $items_sql);

// Calculate totals
$subtotal = 0;
$items_count = 0;
$order_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
    $subtotal += $item['total'];
    $items_count += $item['quantity'];
}

// Get business settings for invoice
$settings_sql = "SELECT * FROM business_settings LIMIT 1";
$settings_result = mysqli_query($conn, $settings_sql);
$settings = mysqli_fetch_assoc($settings_result);

// Format dates
$order_date = date('d M, Y', strtotime($order['order_date']));
$created_at = date('d M, Y h:i A', strtotime($order['created_at']));

// Payment status badge color
$payment_status_class = '';
if ($order['payment_status'] == 'paid') $payment_status_class = 'badge-soft-success';
elseif ($order['payment_status'] == 'partial') $payment_status_class = 'badge-soft-warning';
elseif ($order['payment_status'] == 'pending') $payment_status_class = 'badge-soft-danger';

// Order status badge color
$order_status_class = '';
if ($order['status'] == 'delivered') $order_status_class = 'badge-soft-success';
elseif ($order['status'] == 'processing') $order_status_class = 'badge-soft-primary';
elseif ($order['status'] == 'pending') $order_status_class = 'badge-soft-warning';
elseif ($order['status'] == 'cancelled') $order_status_class = 'badge-soft-danger';
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
                $current_page = 'quick-order';
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
                            <!-- Invoice Card -->
                            <div class="card" id="invoice">
                                <div class="card-body">
                                    <!-- Invoice Header -->
                                    <div class="row mb-4">
                                        <div class="col-sm-6">
                                            <div class="text-center text-sm-start">
                                                <div class="mb-3">
                                                    <h2 class="mb-0 text-primary">INVOICE</h2>
                                                    <p class="text-muted mb-0">Order #<?php echo $order['order_number']; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="text-center text-sm-end">
                                                <h4 class="text-primary mb-0"><?php echo $settings['business_name'] ?? 'APR Water Agencies'; ?></h4>
                                                <p class="text-muted mb-0"><?php echo $settings['address'] ?? ''; ?></p>
                                                <p class="text-muted mb-0"><?php echo $settings['mobile'] ?? ''; ?></p>
                                                <?php if (!empty($settings['email'])): ?>
                                                <p class="text-muted mb-0"><?php echo $settings['email']; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Invoice Details -->
                                    <div class="row mb-4">
                                        <div class="col-sm-6">
                                            <div class="mb-3">
                                                <h5 class="font-size-14 mb-2">Bill To:</h5>
                                                <p class="mb-1">
                                                    <strong><?php echo htmlspecialchars($order['shop_name']); ?></strong>
                                                </p>
                                                <p class="mb-1">
                                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <i class="mdi mdi-phone me-1"></i>
                                                    <?php echo $order['customer_contact']; ?>
                                                </p>
                                                <p class="mb-0">
                                                    <i class="mdi mdi-map-marker me-1"></i>
                                                    <?php echo htmlspecialchars($order['shop_location']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="text-sm-end">
                                                <div class="mb-3">
                                                    <h5 class="font-size-14 mb-1">Invoice Date:</h5>
                                                    <p class="mb-0"><?php echo $order_date; ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <h5 class="font-size-14 mb-1">Invoice No:</h5>
                                                    <p class="mb-0"><?php echo $order['order_number']; ?></p>
                                                </div>
                                                <div class="mb-3">
                                                    <h5 class="font-size-14 mb-1">Customer Code:</h5>
                                                    <p class="mb-0"><?php echo $order['customer_code']; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Status & Payment Info -->
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <h5 class="font-size-14 mb-2">Order Status:</h5>
                                                <span class="badge <?php echo $order_status_class; ?> font-size-12">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3 text-md-end">
                                                <h5 class="font-size-14 mb-2">Payment Status:</h5>
                                                <span class="badge <?php echo $payment_status_class; ?> font-size-12">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Items Table -->
                                    <div class="table-responsive mb-4">
                                        <table class="table table-centered align-middle table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Product</th>
                                                    <th>Product Code</th>
                                                    <th class="text-end">Price</th>
                                                    <th class="text-center">Quantity</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $counter = 1;
                                                foreach ($order_items as $item):
                                                ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td>
                                                        <h5 class="font-size-15 mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h5>
                                                    </td>
                                                    <td><?php echo $item['product_code']; ?></td>
                                                    <td class="text-end">₹<?php echo number_format($item['price'], 2); ?></td>
                                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                    <td class="text-end">₹<?php echo number_format($item['total'], 2); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4" class="border-0"></td>
                                                    <td class="border-0 text-end">
                                                        <h5 class="mb-0">Subtotal:</h5>
                                                    </td>
                                                    <td class="border-0 text-end">
                                                        <h5 class="mb-0">₹<?php echo number_format($subtotal, 2); ?></h5>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="border-0"></td>
                                                    <td class="border-0 text-end">
                                                        <h5 class="mb-0">Tax (0%):</h5>
                                                    </td>
                                                    <td class="border-0 text-end">
                                                        <h5 class="mb-0">₹0.00</h5>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="border-0"></td>
                                                    <td class="border-0 text-end">
                                                        <h5 class="mb-0">Total Amount:</h5>
                                                    </td>
                                                    <td class="border-0 text-end">
                                                        <h4 class="mb-0 text-primary">₹<?php echo number_format($order['total_amount'], 2); ?></h4>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- Payment Summary -->
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14 mb-3">Payment Information</h5>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <tbody>
                                                            <tr>
                                                                <td class="fw-medium">Payment Method</td>
                                                                <td><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="fw-medium">Amount Paid</td>
                                                                <td class="text-success">₹<?php echo number_format($order['paid_amount'], 2); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="fw-medium">Pending Amount</td>
                                                                <td class="text-danger">₹<?php echo number_format($order['pending_amount'], 2); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="fw-medium">Total Amount</td>
                                                                <td class="fw-bold">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <h5 class="font-size-14 mb-3">Order & Delivery Information</h5>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <tbody>
                                                            <tr>
                                                                <td class="fw-medium">Order Created</td>
                                                                <td><?php echo $created_at; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="fw-medium">Created By</td>
                                                                <td><?php echo $order['lineman_name']; ?> (<?php echo $_SESSION['name']; ?>)</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="fw-medium">Total Items</td>
                                                                <td><?php echo $items_count; ?> items</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="fw-medium">Delivery Status</td>
                                                                <td>
                                                                    <span class="badge <?php echo $order_status_class; ?>">
                                                                        <?php echo ucfirst($order['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notes -->
                                    <?php if (!empty($order['notes'])): ?>
                                    <div class="mb-4">
                                        <h5 class="font-size-14 mb-2">Order Notes:</h5>
                                        <div class="border p-3 rounded">
                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Invoice Footer -->
                                    <div class="row mt-4">
                                        <div class="col-sm-6">
                                            <div>
                                                <h5 class="font-size-14 mb-3">Terms & Conditions:</h5>
                                                <p class="text-muted mb-0">
                                                    <?php echo $settings['terms_conditions'] ?? 'Goods once sold will not be taken back. All disputes are subject to jurisdiction.'; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="text-sm-end">
                                                <h5 class="font-size-14 mb-3">Thank You!</h5>
                                                <p class="text-muted mb-0">
                                                    <?php echo $settings['invoice_footer'] ?? 'Thank you for your business. We look forward to serving you again.'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="d-print-none">
                                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                                    <button type="button" class="btn btn-primary" onclick="window.print()">
                                                        <i class="mdi mdi-printer me-1"></i> Print Invoice
                                                    </button>
                                                    <a href="download-invoice.php?id=<?php echo $order_id; ?>" class="btn btn-success">
                                                        <i class="mdi mdi-file-pdf me-1"></i> Download as PDF
                                                    </a>
                                                    <a href="whatsapp://send?text=Invoice%20<?php echo $order['order_number']; ?>%20-%20₹<?php echo $order['total_amount']; ?>%20-%20View:%20<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                                                       class="btn btn-success">
                                                        <i class="mdi mdi-whatsapp me-1"></i> Share via WhatsApp
                                                    </a>
                                                    <a href="edit-order.php?id=<?php echo $order_id; ?>" class="btn btn-info">
                                                        <i class="mdi mdi-pencil me-1"></i> Edit Order
                                                    </a>
                                                    <a href="collect-payment.php?order_id=<?php echo $order_id; ?>" class="btn btn-warning">
                                                        <i class="mdi mdi-cash me-1"></i> Collect Payment
                                                    </a>
                                                    <a href="quick-order.php" class="btn btn-secondary">
                                                        <i class="mdi mdi-plus-circle me-1"></i> New Order
                                                    </a>
                                                </div>
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
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            #invoice, #invoice * {
                visibility: visible;
            }
            #invoice {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none;
                box-shadow: none;
            }
            .d-print-none {
                display: none !important;
            }
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            .table-bordered {
                border: 1px solid #dee2e6 !important;
            }
            .table-bordered th,
            .table-bordered td {
                border: 1px solid #dee2e6 !important;
            }
            .badge {
                border: 1px solid #000 !important;
                color: #000 !important;
                background-color: transparent !important;
            }
        }
        
        /* Invoice Styling */
        #invoice .card-body {
            background-color: #fff;
        }
        .table th {
            font-weight: 600;
            border-top: 2px solid #dee2e6;
            border-bottom: 2px solid #dee2e6;
        }
        .table tfoot tr:last-child td {
            border-top: 2px solid #dee2e6;
            font-size: 1.1rem;
        }
    </style>

    <script>
        // Print invoice
        function printInvoice() {
            window.print();
        }

        // Download as PDF (requires additional library - using basic approach)
        function downloadPDF() {
            // In a real implementation, you would use a library like jsPDF
            // For now, just redirect to a PDF generation script
            window.location.href = 'generate-pdf.php?id=<?php echo $order_id; ?>';
        }

        // Share via WhatsApp
        document.querySelector('.btn-success[href*="whatsapp://"]').addEventListener('click', function(e) {
            if (!navigator.userAgent.match(/iPhone|iPad|iPod|Android/i)) {
                e.preventDefault();
                alert('Please open this page on your mobile device to share via WhatsApp.');
            }
        });

        // Auto-print if print parameter is set
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        });

        // Copy invoice details to clipboard
        function copyInvoiceDetails() {
            const invoiceDetails = `Invoice #<?php echo $order['order_number']; ?>
Customer: <?php echo htmlspecialchars($order['shop_name']); ?>
Date: <?php echo $order_date; ?>
Amount: ₹<?php echo number_format($order['total_amount'], 2); ?>
Payment Status: <?php echo ucfirst($order['payment_status']); ?>
Paid: ₹<?php echo number_format($order['paid_amount'], 2); ?>
Pending: ₹<?php echo number_format($order['pending_amount'], 2); ?>`;

            navigator.clipboard.writeText(invoiceDetails).then(() => {
                alert('Invoice details copied to clipboard!');
            });
        }

        // Add copy button functionality
        const copyButton = document.createElement('button');
        copyButton.className = 'btn btn-outline-info';
        copyButton.innerHTML = '<i class="mdi mdi-content-copy me-1"></i> Copy Details';
        copyButton.onclick = copyInvoiceDetails;
        document.querySelector('.d-flex.flex-wrap').appendChild(copyButton);
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}