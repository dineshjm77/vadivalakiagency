<?php
session_start();
include('config/config.php');

if (!function_exists('esc_customer_page')) {
    function esc_customer_page($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
function customerColumnExists($conn, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = mysqli_query($conn, $sql);
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $cache[$key];
}
function formatMoneyCustomer($amount) { return '₹' . number_format((float)$amount, 2); }
function appendFilterParams($extra = []) { $params = $_GET; foreach ($extra as $k => $v) { $params[$k] = $v; } return http_build_query($params); }
function customer_norm_header($value): string {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/\*/', '', $value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}
function customer_read_csv_rows(string $filePath): array {
    $rows = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        while (($data = fgetcsv($handle, 0, ',')) !== false) $rows[] = $data;
        fclose($handle);
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import_customers'])) {
    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        header('Location: customers-list.php?msg=import_error');
        exit;
    }
    $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        header('Location: customers-list.php?msg=import_csv_only');
        exit;
    }

    try {
        $rows = customer_read_csv_rows($_FILES['import_file']['tmp_name']);
        if (count($rows) < 2) throw new Exception('Empty file');

        $headers = [];
        foreach ($rows[0] as $idx => $value) $headers[$idx] = customer_norm_header($value);

        $hasGstNumber = customerColumnExists($conn, 'customers', 'gst_number');
        $hasGstNo = customerColumnExists($conn, 'customers', 'gst_no');

        $inserted = 0; $updated = 0; $skipped = 0;
        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $data = [];
            foreach ($headers as $idx => $name) {
                if ($name !== '') $data[$name] = trim((string)($row[$idx] ?? ''));
            }
            if (count(array_filter($data, fn($v) => $v !== '')) === 0) continue;

            $shop = $data['shop_name'] ?? '';
            $name = $data['customer_name'] ?? '';
            $phone = $data['customer_contact'] ?? '';
            if ($shop === '' || $name === '' || $phone === '') { $skipped++; continue; }

            $customerCode = $data['customer_code'] ?? '';
            if ($customerCode === '') $customerCode = 'CUST' . date('ymd') . rand(100, 999);

            $record = [
                'customer_code' => $customerCode,
                'shop_name' => $shop,
                'customer_name' => $name,
                'customer_contact' => $phone,
                'alternate_contact' => $data['alternate_contact'] ?? '',
                'shop_location' => $data['shop_location'] ?? '',
                'customer_type' => strtolower($data['customer_type'] ?? 'retail'),
                'assigned_area' => $data['assigned_area'] ?? '',
                'payment_terms' => $data['payment_terms'] ?? '',
                'credit_limit' => is_numeric($data['credit_limit'] ?? '') ? (float)$data['credit_limit'] : 0,
                'current_balance' => is_numeric($data['current_balance'] ?? '') ? (float)$data['current_balance'] : 0,
                'status' => strtolower($data['status'] ?? 'active'),
            ];
            if (!in_array($record['status'], ['active','inactive','blocked'], true)) $record['status'] = 'active';
            if (!in_array($record['customer_type'], ['retail','wholesale','hotel','office','residential','other'], true)) $record['customer_type'] = 'retail';
            $gstValue = $data['gst_number'] ?? ($data['gst_no'] ?? '');

            $checkSql = "SELECT id FROM customers WHERE customer_code = ? OR (shop_name = ? AND customer_contact = ?) LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, 'sss', $record['customer_code'], $record['shop_name'], $record['customer_contact']);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);
            $existing = $checkRes ? mysqli_fetch_assoc($checkRes) : null;
            mysqli_stmt_close($checkStmt);

            if ($existing) {
                $set = [
                    'customer_code = ?', 'shop_name = ?', 'customer_name = ?', 'customer_contact = ?',
                    'alternate_contact = ?', 'shop_location = ?', 'customer_type = ?', 'assigned_area = ?',
                    'payment_terms = ?', 'credit_limit = ?', 'current_balance = ?', 'status = ?'
                ];
                $types = 'sssssssssdds';
                $values = [
                    $record['customer_code'], $record['shop_name'], $record['customer_name'], $record['customer_contact'],
                    $record['alternate_contact'], $record['shop_location'], $record['customer_type'], $record['assigned_area'],
                    $record['payment_terms'], $record['credit_limit'], $record['current_balance'], $record['status']
                ];
                if ($hasGstNumber) { $set[] = 'gst_number = ?'; $types .= 's'; $values[] = $gstValue; }
                elseif ($hasGstNo) { $set[] = 'gst_no = ?'; $types .= 's'; $values[] = $gstValue; }

                $sql = "UPDATE customers SET " . implode(', ', $set) . " WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                $types .= 'i';
                $values[] = (int)$existing['id'];
                mysqli_stmt_bind_param($stmt, $types, ...$values);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $updated++;
            } else {
                $cols = ['customer_code','shop_name','customer_name','customer_contact','alternate_contact','shop_location','customer_type','assigned_area','payment_terms','credit_limit','current_balance','status'];
                $placeholders = array_fill(0, count($cols), '?');
                $types = 'sssssssssdds';
                $values = [
                    $record['customer_code'], $record['shop_name'], $record['customer_name'], $record['customer_contact'],
                    $record['alternate_contact'], $record['shop_location'], $record['customer_type'], $record['assigned_area'],
                    $record['payment_terms'], $record['credit_limit'], $record['current_balance'], $record['status']
                ];
                if ($hasGstNumber) { $cols[] = 'gst_number'; $placeholders[] = '?'; $types .= 's'; $values[] = $gstValue; }
                elseif ($hasGstNo) { $cols[] = 'gst_no'; $placeholders[] = '?'; $types .= 's'; $values[] = $gstValue; }

                $sql = "INSERT INTO customers (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$values);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $inserted++;
            }
        }

        header('Location: customers-list.php?msg=import_success&inserted=' . $inserted . '&updated=' . $updated . '&skipped=' . $skipped);
        exit;
    } catch (Throwable $e) {
        header('Location: customers-list.php?msg=import_failed');
        exit;
    }
}

$hasGstColumn = customerColumnExists($conn, 'customers', 'gst_number');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$beat = isset($_GET['beat']) ? trim($_GET['beat']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$printMode = isset($_GET['print']) && $_GET['print'] == '1';

$where = [];
if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $searchParts = [
        "c.customer_code LIKE '%$searchEsc%'","c.shop_name LIKE '%$searchEsc%'","c.customer_name LIKE '%$searchEsc%'",
        "c.customer_contact LIKE '%$searchEsc%'","c.shop_location LIKE '%$searchEsc%'","c.assigned_area LIKE '%$searchEsc%'"
    ];
    if ($hasGstColumn) $searchParts[] = "c.gst_number LIKE '%$searchEsc%'";
    $where[] = '(' . implode(' OR ', $searchParts) . ')';
}
if ($beat !== '') { $beatEsc = mysqli_real_escape_string($conn, $beat); $where[] = "c.assigned_area = '$beatEsc'"; }
if ($status !== '') { $statusEsc = mysqli_real_escape_string($conn, $status); $where[] = "c.status = '$statusEsc'"; }
if ($type !== '') { $typeEsc = mysqli_real_escape_string($conn, $type); $where[] = "c.customer_type = '$typeEsc'"; }

$whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
$gstSelect = $hasGstColumn ? 'c.gst_number' : "'' AS gst_number";

$statsSql = "SELECT COUNT(*) AS total_customers, SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) AS active_customers, SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_customers, SUM(CASE WHEN c.status = 'blocked' THEN 1 ELSE 0 END) AS blocked_customers, COALESCE(SUM(c.current_balance), 0) AS total_balance FROM customers c $whereSql";
$statsResult = mysqli_query($conn, $statsSql);
$stats = $statsResult ? mysqli_fetch_assoc($statsResult) : ['total_customers'=>0,'active_customers'=>0,'inactive_customers'=>0,'blocked_customers'=>0,'total_balance'=>0];

$beats = [];
$beatRes = mysqli_query($conn, "SELECT DISTINCT assigned_area FROM customers WHERE assigned_area IS NOT NULL AND assigned_area <> '' ORDER BY assigned_area ASC");
if ($beatRes) while ($row = mysqli_fetch_assoc($beatRes)) $beats[] = $row['assigned_area'];

$listSql = "SELECT c.id,c.customer_code,c.shop_name,c.customer_name,c.customer_contact,c.alternate_contact,c.shop_location,$gstSelect,c.customer_type,c.assigned_area,c.payment_terms,c.credit_limit,c.current_balance,c.total_purchases,c.last_purchase_date,c.status,l.full_name AS lineman_name,z.zone_name FROM customers c LEFT JOIN linemen l ON c.assigned_lineman_id = l.id LEFT JOIN zones z ON c.zone_id = z.id $whereSql ORDER BY CASE WHEN c.assigned_area IS NULL OR c.assigned_area = '' THEN 1 ELSE 0 END,c.assigned_area ASC,c.shop_name ASC,c.customer_name ASC";
$listResult = mysqli_query($conn, $listSql);

$pageTitle = 'Customers List';
$currentPage = 'customers-list';
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php')?>
<body data-sidebar="dark"<?php echo $printMode ? ' class="print-page"' : ''; ?>>
<?php if (!$printMode) { include('includes/pre-loader.php'); } ?>
<div id="layout-wrapper">
    <?php if (!$printMode) { include('includes/topbar.php'); } ?>
    <?php if (!$printMode): ?>
    <div class="vertical-menu"><div data-simplebar class="h-100"><?php include('includes/sidebar.php')?></div></div>
    <?php endif; ?>

    <div class="main-content"<?php echo $printMode ? ' style="margin-left:0;"' : ''; ?>>
        <div class="page-content">
            <div class="container-fluid">

                <div class="row no-print">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Customers / Beat List</h4>
                            <div class="page-title-right d-flex gap-2 flex-wrap">
                                <a href="customer-import-template.csv" class="btn btn-outline-info">
                                    <i class="mdi mdi-file-delimited me-1"></i> Download CSV Template
                                </a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                                    <i class="mdi mdi-upload me-1"></i> Bulk Import CSV
                                </button>
                                <a href="add-customer.php" class="btn btn-success"><i class="mdi mdi-plus-circle-outline me-1"></i> Add Customer</a>
                                <button type="button" class="btn btn-primary" onclick="window.print()"><i class="mdi mdi-printer me-1"></i> Print</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] === 'import_success'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">CSV import completed. Inserted: <?php echo (int)($_GET['inserted'] ?? 0); ?>, Updated: <?php echo (int)($_GET['updated'] ?? 0); ?>, Skipped: <?php echo (int)($_GET['skipped'] ?? 0); ?>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] === 'import_csv_only'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">Please upload CSV file only.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] === 'import_error' || $_GET['msg'] === 'import_failed'): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">Unable to import customer CSV file. Please use the template and try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$printMode): ?>
                <div class="row">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2"><i class="mdi mdi-account-group"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Total Customers</p><h4 class="mb-0"><?php echo (int)($stats['total_customers'] ?? 0); ?></h4></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-success-subtle text-success rounded-2 fs-2"><i class="mdi mdi-check-circle"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Active</p><h4 class="mb-0"><?php echo (int)($stats['active_customers'] ?? 0); ?></h4></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2"><i class="mdi mdi-account-cancel"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Blocked</p><h4 class="mb-0"><?php echo (int)($stats['blocked_customers'] ?? 0); ?></h4></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2"><i class="mdi mdi-wallet"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Outstanding</p><h4 class="mb-0"><?php echo formatMoneyCustomer($stats['total_balance'] ?? 0); ?></h4></div></div></div></div></div>
                </div>
                <?php endif; ?>

                <div class="card no-print">
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <div class="col-md-4"><label class="form-label">Search</label><input type="text" name="search" class="form-control" value="<?php echo esc_customer_page($search); ?>" placeholder="Name / code / phone / address"></div>
                            <div class="col-md-3"><label class="form-label">Beat</label><select name="beat" class="form-select"><option value="">All Beats</option><?php foreach ($beats as $beatName): ?><option value="<?php echo esc_customer_page($beatName); ?>" <?php echo $beat === $beatName ? 'selected' : ''; ?>><?php echo esc_customer_page($beatName); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All Status</option><option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option><option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option></select></div>
                            <div class="col-md-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="">All Types</option><option value="retail" <?php echo $type === 'retail' ? 'selected' : ''; ?>>Retail</option><option value="wholesale" <?php echo $type === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option><option value="hotel" <?php echo $type === 'hotel' ? 'selected' : ''; ?>>Hotel</option><option value="office" <?php echo $type === 'office' ? 'selected' : ''; ?>>Office</option><option value="residential" <?php echo $type === 'residential' ? 'selected' : ''; ?>>Residential</option><option value="other" <?php echo $type === 'other' ? 'selected' : ''; ?>>Other</option></select></div>
                            <div class="col-md-1 d-grid"><button type="submit" class="btn btn-primary">Go</button></div>
                            <div class="col-md-12 mt-1"><a href="customers-list.php" class="btn btn-light btn-sm">Clear Filters</a><?php if ($beat !== ''): ?><a href="customers-list.php?<?php echo appendFilterParams(['print' => 1]); ?>" class="btn btn-outline-secondary btn-sm" target="_blank">Open Beat Print View</a><?php endif; ?></div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h4 class="card-title mb-1"><?php echo $beat !== '' ? 'BEAT: ' . esc_customer_page(strtoupper($beat)) : 'All Customers'; ?></h4>
                                <p class="card-title-desc mb-0">Customer list with name, address, GST and phone view.</p>
                            </div>
                            <?php if ($hasGstColumn === false): ?><div class="alert alert-warning py-2 px-3 mb-0 no-print">GST column is not in current customers table. GST will show blank until added.</div><?php endif; ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle table-nowrap customer-list-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:70px;">No.</th>
                                        <?php if ($beat === ''): ?><th style="width:140px;">Beat</th><?php endif; ?>
                                        <th style="min-width:220px;">Name</th>
                                        <th style="min-width:120px;">Contact Person</th>
                                        <th style="min-width:350px;">Address</th>
                                        <th style="min-width:140px;">GST No</th>
                                        <th style="min-width:130px;">Phone</th>
                                        <th class="no-print" style="min-width:110px;">Status</th>
                                        <th class="no-print" style="min-width:130px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($listResult && mysqli_num_rows($listResult) > 0): ?>
                                    <?php $i = 1; while ($row = mysqli_fetch_assoc($listResult)): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <?php if ($beat === ''): ?><td><?php echo esc_customer_page($row['assigned_area'] ?: '-'); ?></td><?php endif; ?>
                                            <td><div class="fw-bold"><?php echo esc_customer_page($row['shop_name']); ?></div><small class="text-muted"><?php echo esc_customer_page($row['customer_code']); ?></small></td>
                                            <td><?php echo esc_customer_page($row['customer_name']); ?></td>
                                            <td class="address-cell"><?php echo nl2br(esc_customer_page($row['shop_location'])); ?></td>
                                            <td><?php echo esc_customer_page($row['gst_number'] ?: ''); ?></td>
                                            <td><?php echo esc_customer_page($row['customer_contact']); ?><?php if (!empty($row['alternate_contact'])): ?><br><small class="text-muted"><?php echo esc_customer_page($row['alternate_contact']); ?></small><?php endif; ?></td>
                                            <td class="no-print">
                                                <?php $badgeClass = 'badge-soft-secondary'; if ($row['status'] === 'active') $badgeClass = 'badge-soft-success'; if ($row['status'] === 'inactive') $badgeClass = 'badge-soft-warning'; if ($row['status'] === 'blocked') $badgeClass = 'badge-soft-danger'; ?>
                                                <span class="badge <?php echo $badgeClass; ?> font-size-11"><?php echo esc_customer_page(ucfirst($row['status'])); ?></span>
                                            </td>
                                            <td class="no-print">
                                                <div class="d-flex gap-2">
                                                    <a href="customer-view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-info">View</a>
                                                    <a href="add-customer.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Import Customers (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Download the CSV template, fill customer rows, and upload the .csv file here.</p>
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                    </div>
                    <div class="small text-muted">Required columns: <strong>shop_name</strong>, <strong>customer_name</strong>, <strong>customer_contact</strong>.</div>
                </div>
                <div class="modal-footer">
                    <a href="customer-import-template.csv" class="btn btn-light border">Download Template</a>
                    <button type="submit" class="btn btn-success" name="bulk_import_customers" value="1">Import Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php')?>
</body>
</html>
