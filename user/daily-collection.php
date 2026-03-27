<?php
// daily-collection.php
// Daily collections view - shows all transactions for a single day with summaries and filters.
// Place this file alongside your existing project (includes/, config/, etc.)

include('config/config.php');
include('includes/auth-check.php');

// Authorization: only admin, super_admin, lineman can view
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin', 'lineman'])) {
    header('Location: index.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Filters & defaults
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // default = today
$customer_id   = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$lineman_id    = isset($_GET['lineman_id']) ? intval($_GET['lineman_id']) : 0;
$payment_method = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';

// Validate date
if (!strtotime($selected_date)) {
    $selected_date = date('Y-m-d');
}

// Limit: ensure date isn't in future (optional)
if (strtotime($selected_date) > strtotime(date('Y-m-d'))) {
    $selected_date = date('Y-m-d');
}

$date_esc = mysqli_real_escape_string($conn, $selected_date);

// Build base WHERE clause
$where = "DATE(t.created_at) = '$date_esc'";

// apply filters
if ($customer_id > 0) {
    // transactions could have customer_id directly or be linked via order -> customer
    $where .= " AND (t.customer_id = " . intval($customer_id) . " OR o.customer_id = " . intval($customer_id) . ")";
}
if ($lineman_id > 0) {
    // filter by customers assigned to lineman
    $where .= " AND c.assigned_lineman_id = " . intval($lineman_id);
}
if ($payment_method !== '') {
    $pm_esc = mysqli_real_escape_string($conn, $payment_method);
    $where .= " AND t.payment_method = '$pm_esc'";
}

// Summary values for the selected date
$summary_sql = "SELECT 
    COUNT(t.id) AS txn_count,
    SUM(CASE WHEN t.type = 'payment' THEN t.amount ELSE 0 END) AS total_collections,
    SUM(CASE WHEN t.type = 'refund' THEN t.amount ELSE 0 END) AS total_refunds,
    SUM(CASE WHEN t.type = 'adjustment' THEN t.amount ELSE 0 END) AS total_adjustments
FROM transactions t
LEFT JOIN orders o ON t.order_id = o.id
LEFT JOIN customers c ON (o.customer_id = c.id OR t.customer_id = c.id)
WHERE $where";

$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result) ?: [
    'txn_count' => 0,
    'total_collections' => 0,
    'total_refunds' => 0,
    'total_adjustments' => 0
];

// Detailed transactions list
$txns_sql = "SELECT 
    t.*,
    o.order_number,
    o.customer_id AS order_customer_id,
    c.shop_name,
    c.customer_name,
    c.customer_contact,
    l.full_name AS lineman_name
FROM transactions t
LEFT JOIN orders o ON t.order_id = o.id
LEFT JOIN customers c ON (o.customer_id = c.id OR t.customer_id = c.id)
LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
WHERE $where
ORDER BY t.created_at DESC, t.id DESC
LIMIT 1000"; // safety cap
$txns_result = mysqli_query($conn, $txns_sql);

// Dropdown data for filters
$customers_sql = "SELECT id, shop_name, customer_name FROM customers WHERE status = 'active' ORDER BY shop_name";
$customers_res = mysqli_query($conn, $customers_sql);

$linemen_sql = "SELECT id, full_name FROM linemen WHERE status = 'active' ORDER BY full_name";
$linemen_res = mysqli_query($conn, $linemen_sql);

// Payment methods available on this date (for filter dropdown)
$methods_sql = "SELECT DISTINCT IFNULL(payment_method,'Unknown') AS pm FROM transactions WHERE DATE(created_at) = '$date_esc' ORDER BY pm";
$methods_res = mysqli_query($conn, $methods_sql);

// Prepare safe display values
$txn_count = intval($summary['txn_count']);
$total_collections = floatval($summary['total_collections']);
$total_refunds = floatval($summary['total_refunds']);
$total_adjustments = floatval($summary['total_adjustments']);
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<style>
    :root{
        --primary:#556ee6;
        --success:#198754;
        --danger:#b02a37;
        --muted:#495057;
    }
    .card { border:1px solid #e9ecef; background:#fff; box-shadow:0 6px 18px rgba(0,0,0,0.03); }
    .summary-card { padding:14px; border-radius:8px; text-align:center; border:1px solid #e9ecef; background:#fff; }
    .summary-card h6 { margin:0; color:#6c757d; font-weight:600; }
    .summary-card h3 { margin-top:8px; font-size:20px; color:var(--muted); font-weight:700; }
    .table thead th { background:#f8f9fa; }
    .small-muted { color:#6c757d; font-size:0.85rem; }
    @media print { .no-print { display:none; } }
</style>
<body data-sidebar="dark">

    <?php include('includes/pre-loader.php'); ?>

    <div id="layout-wrapper">
        <?php include('includes/topbar.php'); ?>

        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <?php $current_page = 'daily-collection'; include('includes/sidebar.php'); ?>
            </div>
        </div>

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Header -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h4 class="card-title mb-0">Daily Collection</h4>
                            <p class="card-title-desc">Transactions recorded on <strong><?php echo date('d M, Y', strtotime($selected_date)); ?></strong>.</p>
                        </div>
                        <div class="col-md-6 text-end no-print">
                            <div class="d-inline-flex gap-2">
                                <button class="btn btn-primary" onclick="printReport()"><i class="mdi mdi-printer me-1"></i> Print</button>
                                <button class="btn btn-success" onclick="exportReport()"><i class="mdi mdi-download me-1"></i> Export</button>
                                <?php if (in_array($user_role,['admin','super_admin'])): ?>
                                    <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="mdi mdi-email me-1"></i> Email</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Date</label>
                                            <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Customer</label>
                                            <select name="customer_id" class="form-select">
                                                <option value="0">All Customers</option>
                                                <?php mysqli_data_seek($customers_res,0); while ($c = mysqli_fetch_assoc($customers_res)): ?>
                                                    <option value="<?php echo $c['id']; ?>" <?php echo $customer_id == $c['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($c['shop_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Lineman</label>
                                            <?php mysqli_data_seek($linemen_res,0); ?>
                                            <select name="lineman_id" class="form-select">
                                                <option value="0">All Linemen</option>
                                                <?php while ($l = mysqli_fetch_assoc($linemen_res)): ?>
                                                    <option value="<?php echo $l['id']; ?>" <?php echo $lineman_id == $l['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($l['full_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select">
                                                <option value="">All Methods</option>
                                                <?php mysqli_data_seek($methods_res, 0); while ($m = mysqli_fetch_assoc($methods_res)): ?>
                                                    <option value="<?php echo htmlspecialchars($m['pm']); ?>" <?php echo $payment_method === $m['pm'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($m['pm']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter me-1"></i> Apply Filters</button>
                                                <a href="daily-collection.php" class="btn btn-outline-secondary"><i class="mdi mdi-refresh me-1"></i> Reset</a>
                                                <div class="btn-group ms-auto" role="group">
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('today')">Today</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('yesterday')">Yesterday</button>
                                                    <button type="button" class="btn btn-outline-info" onclick="setDateRange('week')">This Week</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>

                                    <?php if ($customer_id>0 || $lineman_id>0 || $payment_method !== '' || $selected_date != date('Y-m-d')): ?>
                                    <div class="mt-3">
                                        <div class="alert alert-info">
                                            <i class="mdi mdi-information-outline me-2"></i>
                                            <strong>Showing:</strong> <?php echo date('d M, Y', strtotime($selected_date)); ?>
                                            <?php if ($customer_id>0): ?>
                                                | <strong>Customer:</strong> <?php 
                                                    $q = mysqli_query($conn, "SELECT shop_name FROM customers WHERE id=".intval($customer_id)." LIMIT 1");
                                                    if ($q && $r = mysqli_fetch_assoc($q)) echo htmlspecialchars($r['shop_name']);
                                                ?>
                                            <?php endif; ?>
                                            <?php if ($lineman_id>0): ?>
                                                | <strong>Lineman:</strong> <?php 
                                                    $q = mysqli_query($conn, "SELECT full_name FROM linemen WHERE id=".intval($lineman_id)." LIMIT 1");
                                                    if ($q && $r = mysqli_fetch_assoc($q)) echo htmlspecialchars($r['full_name']);
                                                ?>
                                            <?php endif; ?>
                                            <?php if ($payment_method !== ''): ?>
                                                | <strong>Method:</strong> <?php echo htmlspecialchars($payment_method); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Transactions</h6>
                                <h3><?php echo number_format($txn_count); ?></h3>
                                <small class="small-muted">Count of transactions</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Total Collections</h6>
                                <h3>₹<?php echo number_format($total_collections, 2); ?></h3>
                                <small class="small-muted">Payments received</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Total Refunds</h6>
                                <h3>₹<?php echo number_format($total_refunds, 2); ?></h3>
                                <small class="small-muted">Refunds issued</small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="summary-card">
                                <h6>Adjustments</h6>
                                <h3>₹<?php echo number_format($total_adjustments, 2); ?></h3>
                                <small class="small-muted">Manual adjustments</small>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Transactions (<?php echo date('d M, Y', strtotime($selected_date)); ?>)</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-centered table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Txn ID</th>
                                                    <th>Type</th>
                                                    <th class="text-end">Amount</th>
                                                    <th>Method</th>
                                                    <th>Order</th>
                                                    <th>Customer</th>
                                                    <th>Lineman</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($txns_result && mysqli_num_rows($txns_result) > 0): mysqli_data_seek($txns_result, 0);
                                                    while ($t = mysqli_fetch_assoc($txns_result)):
                                                        $time = date('h:i A', strtotime($t['created_at']));
                                                        $type = htmlspecialchars($t['type']);
                                                        $amount = floatval($t['amount']);
                                                        $method = htmlspecialchars($t['payment_method'] ?: 'N/A');
                                                        $order_no = $t['order_number'] ? htmlspecialchars($t['order_number']) : '-';
                                                        $cust_name = $t['shop_name'] ? htmlspecialchars($t['shop_name']) : ($t['customer_name'] ? htmlspecialchars($t['customer_name']) : '-');
                                                        $lineman = $t['lineman_name'] ? htmlspecialchars($t['lineman_name']) : '-';
                                                        $notes = $t['notes'] ? htmlspecialchars($t['notes']) : '';
                                                ?>
                                                <tr>
                                                    <td><?php echo $time; ?></td>
                                                    <td><?php echo htmlspecialchars($t['payment_id'] ?: 'TXN'.$t['id']); ?></td>
                                                    <td><?php echo ucfirst($type); ?></td>
                                                    <td class="text-end <?php echo $type === 'refund' ? 'text-danger' : 'text-success'; ?>">₹<?php echo number_format($amount,2); ?></td>
                                                    <td><?php echo $method; ?></td>
                                                    <td><?php echo $order_no; ?></td>
                                                    <td><?php echo $cust_name; ?><br><small class="small-muted"><?php echo htmlspecialchars($t['customer_contact'] ?? ''); ?></small></td>
                                                    <td><?php echo $lineman; ?></td>
                                                    <td><?php echo $notes; ?></td>
                                                </tr>
                                                <?php endwhile; else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-cash-multiple display-4"></i>
                                                            <h5 class="mt-2">No transactions found</h5>
                                                            <p class="mb-0">Try changing the date range or filters.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr>
                                                    <th colspan="3">Totals</th>
                                                    <th class="text-end">₹<?php echo number_format($total_collections - $total_refunds + $total_adjustments, 2); ?></th>
                                                    <th colspan="5"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="mt-3 small text-muted">
                                        Showing up to 1000 latest transactions for the selected date.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- container -->
            </div> <!-- page-content -->

            <?php include('includes/footer.php'); ?>
        </div> <!-- main-content -->
    </div> <!-- layout-wrapper -->

    <!-- Email Modal -->
    <?php if (in_array($user_role, ['admin','super_admin'])): ?>
    <div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="send-report-email.php">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                    <input type="hidden" name="type" value="daily-collection">
                    <div class="modal-header">
                        <h5 class="modal-title">Email Daily Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">To *</label>
                            <input type="email" name="email_to" class="form-control" required placeholder="recipient@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="email_subject" class="form-control" value="Daily Collection: <?php echo date('d M, Y', strtotime($selected_date)); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="email_message" class="form-control" rows="4">Please find attached the daily collection report for <?php echo date('d M, Y', strtotime($selected_date)); ?>.

Total Collections: ₹<?php echo number_format($total_collections,2); ?>
Total Refunds: ₹<?php echo number_format($total_refunds,2); ?>
Adjustments: ₹<?php echo number_format($total_adjustments,2); ?>

Regards,
<?php echo $_SESSION['name']; ?></textarea>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="include_attachments" checked id="attach">
                            <label class="form-check-label" for="attach">Include PDF/Excel</label>
                        </div>
                        <div class="alert alert-info small">Report will be generated and attached if requested.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include('includes/rightbar.php'); ?>
    <?php include('includes/scripts.php'); ?>

    <script>
        function setDateRange(range) {
            const today = new Date();
            let start = new Date(), end = new Date();
            switch(range) {
                case 'today': start = end = today; break;
                case 'yesterday': start = end = new Date(today.setDate(today.getDate() - 1)); break;
                case 'week': start = new Date(); start.setDate(start.getDate() - start.getDay()); end = new Date(); break;
            }
            document.getElementById('date').value = start.toISOString().split('T')[0];
        }

        function printReport() {
            const win = window.open('', '_blank');
            const generatedAt = new Date().toLocaleString('en-IN', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
            win.document.write('<html><head><title>Daily Collection - <?php echo htmlspecialchars($selected_date); ?></title>');
            win.document.write('<style>body{font-family:Arial;color:#222;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px}th{background:#f8f9fa}h2{margin-bottom:5px}</style>');
            win.document.write('</head><body>');
            win.document.write('<h2>Daily Collection</h2>');
            win.document.write('<p><strong>Date:</strong> <?php echo date('d M, Y', strtotime($selected_date)); ?> | <strong>Generated:</strong> ' + generatedAt + '</p>');
            // clone main transactions table
            const tbl = document.querySelector('.table');
            if (tbl) {
                win.document.write(tbl.outerHTML);
            } else {
                win.document.write('<p>No data to print</p>');
            }
            win.document.write('</body></html>');
            win.document.close();
            setTimeout(()=>win.print(), 300);
        }

        function exportReport() {
            const params = new URLSearchParams({
                date: '<?php echo $selected_date; ?>',
                customer_id: '<?php echo $customer_id; ?>',
                lineman_id: '<?php echo $lineman_id; ?>',
                payment_method: '<?php echo addslashes($payment_method); ?>',
                export: '1',
                type: 'daily-collection'
            });
            window.location.href = 'export-report.php?' + params.toString();
        }

        document.addEventListener('DOMContentLoaded', function(){
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('date');
            if (dateInput) dateInput.max = today;
        });
    </script>

</body>
</html>

<?php
// Close db connection
if (isset($conn)) mysqli_close($conn);
?>
