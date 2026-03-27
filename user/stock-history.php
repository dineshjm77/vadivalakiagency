<?php
// stock-history.php
// Shows stock transaction history with filters, CSV export, and pagination.

include('config/config.php');
include('includes/auth-check.php');

// Access control: allow lineman and admins to view
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'lineman';
$user_name = $_SESSION['name'] ?? 'User';

// Helper sanitize
function safe_int($v) { return intval($v); }
function esc($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// --- Handle input filters ---
$product_id = isset($_GET['product_id']) ? safe_int($_GET['product_id']) : 0;
$trx_type  = isset($_GET['transaction_type']) ? trim($_GET['transaction_type']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date   = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build where clauses
$where = ["1=1"];
if ($product_id > 0) $where[] = "st.product_id = $product_id";
if (!empty($trx_type)) {
    $trx_type_sql = mysqli_real_escape_string($conn, $trx_type);
    $where[] = "st.transaction_type = '$trx_type_sql'";
}
if (!empty($from_date)) {
    // expect YYYY-MM-DD
    $fd = mysqli_real_escape_string($conn, $from_date);
    $where[] = "st.created_at >= '$fd 00:00:00'";
}
if (!empty($to_date)) {
    $td = mysqli_real_escape_string($conn, $to_date);
    $where[] = "st.created_at <= '$td 23:59:59'";
}

$where_sql = implode(' AND ', $where);

// --- CSV export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // produce CSV of filtered transactions
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=stock_history_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#','Txn ID','Date','Type','Product','Product Code','Qty','Prev Qty','New Qty','Unit Price','Total Value','Notes','By']);
    
    $csv_sql = "SELECT st.*, p.product_name, p.product_code,
                       COALESCE(l.full_name, au.name, 'System') as actor_name
                FROM stock_transactions st
                LEFT JOIN products p ON st.product_id = p.id
                LEFT JOIN linemen l ON st.created_by = l.id
                LEFT JOIN admin_users au ON st.created_by = au.id
                WHERE $where_sql
                ORDER BY st.created_at DESC";
    $csv_res = mysqli_query($conn, $csv_sql);
    $i = 1;
    while ($r = mysqli_fetch_assoc($csv_res)) {
        $total_value = number_format(($r['quantity'] * floatval($r['stock_price'] ?? 0)), 2, '.', '');
        fputcsv($out, [
            $i++,
            $r['id'],
            $r['created_at'],
            $r['transaction_type'],
            $r['product_name'] ?? '',
            $r['product_code'] ?? '',
            $r['quantity'],
            $r['previous_quantity'],
            $r['new_quantity'],
            number_format(floatval($r['stock_price'] ?? 0), 2),
            $total_value,
            $r['notes'],
            $r['actor_name']
        ]);
    }
    fclose($out);
    exit;
}

// --- Count total for pagination ---
$count_sql = "SELECT COUNT(*) as cnt
              FROM stock_transactions st
              LEFT JOIN products p ON st.product_id = p.id
              WHERE $where_sql";
$count_res = mysqli_query($conn, $count_sql);
$count_row = mysqli_fetch_assoc($count_res);
$total_records = intval($count_row['cnt']);
$total_pages = max(1, ceil($total_records / $per_page));

// --- Summary stats for current filter ---
$stats_sql = "SELECT 
                COUNT(*) as total_trx,
                SUM(CASE WHEN st.transaction_type = 'purchase' THEN st.quantity ELSE 0 END) as total_purchased,
                SUM(CASE WHEN st.transaction_type IN ('sale','adjustment') THEN st.quantity ELSE 0 END) as total_decreased,
                SUM(st.quantity * st.stock_price) as total_value_moved
              FROM stock_transactions st
              LEFT JOIN products p ON st.product_id = p.id
              WHERE $where_sql";
$stats_res = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_res);

// --- Fetch products list for filter dropdown ---
$prod_sql = "SELECT id, product_name, product_code FROM products WHERE status = 'active' ORDER BY product_name";
$prod_res = mysqli_query($conn, $prod_sql);

// --- Fetch transactions page ---
$list_sql = "SELECT st.*, p.product_name, p.product_code,
                    COALESCE(l.full_name, au.name, 'System') as actor_name
             FROM stock_transactions st
             LEFT JOIN products p ON st.product_id = p.id
             LEFT JOIN linemen l ON st.created_by = l.id
             LEFT JOIN admin_users au ON st.created_by = au.id
             WHERE $where_sql
             ORDER BY st.created_at DESC
             LIMIT $per_page OFFSET $offset";
$list_res = mysqli_query($conn, $list_sql);

?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
    <?php include('includes/pre-loader.php'); ?>
    <div id="layout-wrapper">
        <?php include('includes/topbar.php'); ?>
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <?php 
                $current_page = 'stock-history';
                include('includes/sidebar.php');
                ?>
            </div>
        </div>

        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">

                    <!-- Page header -->
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <h4 class="card-title">Stock Transactions History</h4>
                            <p class="card-title-desc">View purchases, adjustments, sales and returns. Use filters to narrow results.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="available-stock.php" class="btn btn-light"><i class="mdi mdi-arrow-left me-1"></i> Back to Stock</a>
                            <a href="?<?php 
                                // preserve current filters in export link
                                $qs = $_GET;
                                $qs['export'] = 'csv';
                                echo http_build_query($qs);
                            ?>" class="btn btn-outline-secondary"><i class="mdi mdi-download me-1"></i> Export CSV</a>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Product</label>
                                    <select name="product_id" class="form-select">
                                        <option value="0">-- All products --</option>
                                        <?php while ($p = mysqli_fetch_assoc($prod_res)): ?>
                                            <option value="<?php echo $p['id']; ?>" <?php echo $product_id == $p['id'] ? 'selected' : ''; ?>>
                                                <?php echo esc($p['product_name']) . ' [' . esc($p['product_code']) . ']'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Type</label>
                                    <select name="transaction_type" class="form-select">
                                        <option value="">All Types</option>
                                        <?php
                                        $types = ['purchase','sale','adjustment','return'];
                                        foreach ($types as $t): ?>
                                            <option value="<?php echo $t; ?>" <?php echo $trx_type === $t ? 'selected' : ''; ?>><?php echo ucfirst($t); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">From</label>
                                    <input type="date" name="from_date" class="form-control" value="<?php echo esc($from_date); ?>">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">To</label>
                                    <input type="date" name="to_date" class="form-control" value="<?php echo esc($to_date); ?>">
                                </div>

                                <div class="col-md-2 text-end">
                                    <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-filter"></i> Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Transactions</h6>
                                    <h3><?php echo intval($stats['total_trx'] ?? 0); ?></h3>
                                    <small class="text-muted">Showing <?php echo esc($total_records); ?> total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Total Purchased</h6>
                                    <h3><?php echo intval($stats['total_purchased'] ?? 0); ?></h3>
                                    <small class="text-muted">Units added</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Total Decreased</h6>
                                    <h3><?php echo intval($stats['total_decreased'] ?? 0); ?></h3>
                                    <small class="text-muted">Units removed</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Value Movement</h6>
                                    <h3>₹<?php echo number_format(floatval($stats['total_value_moved'] ?? 0), 2); ?></h3>
                                    <small class="text-muted">At recorded unit price</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Product</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-center">Prev</th>
                                            <th class="text-center">New</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Total</th>
                                            <th>Notes</th>
                                            <th>By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($list_res && mysqli_num_rows($list_res) > 0):
                                            $num = $offset + 1;
                                            while ($r = mysqli_fetch_assoc($list_res)):
                                                $unit_price = number_format(floatval($r['stock_price'] ?? 0), 2);
                                                $total_value = number_format($r['quantity'] * floatval($r['stock_price'] ?? 0), 2);
                                                // sign for display: purchases positive, sales/adjustment negative? We'll display raw quantity and use color.
                                                $qty_display = intval($r['quantity']);
                                                $type = $r['transaction_type'];
                                                $badge = 'bg-secondary';
                                                if ($type === 'purchase') $badge = 'bg-success';
                                                elseif ($type === 'sale') $badge = 'bg-danger';
                                                elseif ($type === 'adjustment') $badge = 'bg-warning';
                                                elseif ($type === 'return') $badge = 'bg-info';
                                        ?>
                                        <tr>
                                            <td><?php echo $num++; ?></td>
                                            <td><?php echo esc($r['created_at']); ?></td>
                                            <td><span class="badge <?php echo $badge; ?>"><?php echo esc(ucfirst($type)); ?></span></td>
                                            <td>
                                                <strong><?php echo esc($r['product_name'] ?? '-'); ?></strong><br>
                                                <small class="text-muted"><?php echo esc($r['product_code'] ?? ''); ?></small>
                                            </td>
                                            <td class="text-center"><?php echo number_format($qty_display); ?></td>
                                            <td class="text-center"><?php echo number_format(intval($r['previous_quantity'])); ?></td>
                                            <td class="text-center"><?php echo number_format(intval($r['new_quantity'])); ?></td>
                                            <td class="text-end">₹<?php echo $unit_price; ?></td>
                                            <td class="text-end">₹<?php echo $total_value; ?></td>
                                            <td><?php echo esc($r['notes']); ?></td>
                                            <td><?php echo esc($r['actor_name']); ?></td>
                                        </tr>
                                        <?php
                                            endwhile;
                                        else:
                                        ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="mdi mdi-history display-4"></i>
                                                    <h5 class="mt-2">No transactions found</h5>
                                                    <p>Try expanding the date range or remove filters.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p class="text-muted mb-0">Showing <?php echo min($per_page, max(0, $total_records - $offset)); ?> of <?php echo $total_records; ?> records</p>
                                </div>
                                <div class="col-md-6">
                                    <nav aria-label="Page navigation" class="float-end">
                                        <ul class="pagination pagination-sm">
                                            <?php
                                            // build base url keeping filters
                                            $base_qs = $_GET;
                                            unset($base_qs['page'], $base_qs['export']);
                                            $base_url = strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($base_qs);
                                            // previous
                                            $prev = max(1, $page - 1);
                                            $next = min($total_pages, $page + 1);
                                            ?>
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo $base_url . (strpos($base_url, '&') === false ? '&' : '&') . 'page=' . ($page-1); ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php
                                            // show a window of pages
                                            $start = max(1, $page - 3);
                                            $end = min($total_pages, $page + 3);
                                            for ($p = $start; $p <= $end; $p++): ?>
                                                <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="<?php echo $base_url . (strpos($base_url, '&') === false ? '&' : '&') . 'page=' . $p; ?>"><?php echo $p; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo $base_url . (strpos($base_url, '&') === false ? '&' : '&') . 'page=' . ($page+1); ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>

                        </div> <!-- card-body -->
                    </div> <!-- card -->

                </div> <!-- container -->
            </div> <!-- page-content -->

            <?php include('includes/footer.php'); ?>
        </div> <!-- main-content -->
    </div> <!-- layout-wrapper -->

    <?php include('includes/rightbar.php'); ?>
    <?php include('includes/scripts.php'); ?>

    <script>
    // small client-side niceties if desired
    (function(){
        // nothing required for now
    })();
    </script>

</body>
</html>

<?php
// close connection
if (isset($conn)) mysqli_close($conn);
?>
