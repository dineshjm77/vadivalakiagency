<?php
session_start();
include('../config/config.php');

// Check admin access
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin')) {
    header('Location: ../index.php');
    exit;
}

include('includes/head.php');

function creditor_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    if ($res) mysqli_free_result($res);
    return $cache[$key];
}

function creditor_norm_header($value): string {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/\*/', '', $value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}

function creditor_read_csv_rows(string $filePath): array {
    $rows = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

// Handle status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "UPDATE creditors SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    header('Location: creditors-list.php?msg=status_updated');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $check_sql = "SELECT COUNT(*) as count FROM creditor_purchases WHERE creditor_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $row = mysqli_fetch_assoc($check_result);

    if ($row['count'] > 0) {
        header('Location: creditors-list.php?msg=cannot_delete');
        exit;
    }

    $sql = "DELETE FROM creditors WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: creditors-list.php?msg=deleted');
        exit;
    }
}

// CSV Bulk Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import_creditors'])) {
    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        header('Location: creditors-list.php?msg=import_error');
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        header('Location: creditors-list.php?msg=import_csv_only');
        exit;
    }

    try {
        $rows = creditor_read_csv_rows($_FILES['import_file']['tmp_name']);
        if (count($rows) < 2) throw new Exception('Empty import file');

        $headerRow = $rows[0];
        $headers = [];
        foreach ($headerRow as $index => $headerVal) {
            $headers[$index] = creditor_norm_header($headerVal);
        }

        $availableCols = [];
        foreach (['vendor_name','company_name','contact_person','phone','gstin','email','address','current_balance','status'] as $col) {
            if (creditor_has_column($conn, 'creditors', $col)) $availableCols[] = $col;
        }

        $inserted = 0; $updated = 0; $skipped = 0;

        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $data = [];
            foreach ($headers as $idx => $headerName) {
                if ($headerName !== '') $data[$headerName] = trim((string)($row[$idx] ?? ''));
            }
            if (count(array_filter($data, fn($v) => $v !== '')) === 0) continue;

            $vendorName = $data['vendor_name'] ?? '';
            if ($vendorName === '') { $skipped++; continue; }

            $status = strtolower($data['status'] ?? 'active');
            if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

            $record = [
                'vendor_name' => $vendorName,
                'company_name' => $data['company_name'] ?? '',
                'contact_person' => $data['contact_person'] ?? '',
                'phone' => $data['phone'] ?? '',
                'gstin' => $data['gstin'] ?? '',
                'email' => $data['email'] ?? '',
                'address' => $data['address'] ?? '',
                'current_balance' => is_numeric($data['current_balance'] ?? '') ? (float)$data['current_balance'] : 0,
                'status' => $status,
            ];

            $checkSql = "SELECT id FROM creditors WHERE vendor_name = ? AND phone = ? LIMIT 1";
            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, 'ss', $record['vendor_name'], $record['phone']);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);
            $existing = $checkRes ? mysqli_fetch_assoc($checkRes) : null;
            mysqli_stmt_close($checkStmt);

            if ($existing) {
                $set = []; $types = ''; $values = [];
                foreach ($availableCols as $col) {
                    if ($col === 'vendor_name') continue;
                    $set[] = "$col = ?";
                    $types .= in_array($col, ['current_balance'], true) ? 'd' : 's';
                    $values[] = $record[$col];
                }
                if (!empty($set)) {
                    $sql = "UPDATE creditors SET " . implode(', ', $set) . " WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    $types .= 'i';
                    $values[] = (int)$existing['id'];
                    mysqli_stmt_bind_param($stmt, $types, ...$values);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
                $updated++;
            } else {
                $cols = []; $placeholders = []; $types = ''; $values = [];
                foreach ($availableCols as $col) {
                    $cols[] = $col;
                    $placeholders[] = '?';
                    $types .= in_array($col, ['current_balance'], true) ? 'd' : 's';
                    $values[] = $record[$col];
                }
                if (creditor_has_column($conn, 'creditors', 'total_purchases') && !in_array('total_purchases', $cols, true)) {
                    $cols[] = 'total_purchases';
                    $placeholders[] = '0';
                }
                $sql = "INSERT INTO creditors (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
                $stmt = mysqli_prepare($conn, $sql);
                if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$values);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $inserted++;
            }
        }

        header('Location: creditors-list.php?msg=import_success&inserted=' . $inserted . '&updated=' . $updated . '&skipped=' . $skipped);
        exit;
    } catch (Throwable $e) {
        header('Location: creditors-list.php?msg=import_failed');
        exit;
    }
}

// Fetch all creditors
$creditors = [];
$sql = "SELECT * FROM creditors ORDER BY vendor_name";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $creditors[] = $row;
}

// Get statistics
$total_creditors = count($creditors);
$total_balance = 0;
$total_purchases = 0;
foreach ($creditors as $c) {
    $total_balance += $c['current_balance'];
    $total_purchases += $c['total_purchases'];
}
$active_creditors = 0;
foreach ($creditors as $c) {
    if ($c['status'] == 'active') $active_creditors++;
}
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

                <div class="row">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                            <h4 class="mb-0">Creditors List</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="creditor-import-template.csv" class="btn btn-outline-info">
                                    <i class="fas fa-file-csv me-1"></i> Download CSV Template
                                </a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                                    <i class="fas fa-upload me-1"></i> Bulk Import CSV
                                </button>
                                <a href="add-creditor.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Add New Creditor
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] == 'status_updated'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">Creditor status updated successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">Creditor deleted successfully!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] == 'cannot_delete'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">Cannot delete creditor with existing transactions!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] == 'import_success'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">CSV import completed. Inserted: <?php echo (int)($_GET['inserted'] ?? 0); ?>, Updated: <?php echo (int)($_GET['updated'] ?? 0); ?>, Skipped: <?php echo (int)($_GET['skipped'] ?? 0); ?>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] == 'import_csv_only'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">Please upload CSV file only.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php elseif ($_GET['msg'] == 'import_error' || $_GET['msg'] == 'import_failed'): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">Unable to import creditor CSV file. Please use the template and try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex justify-content-between"><div><p class="text-muted mb-1">Total Creditors</p><h3 class="mb-0"><?php echo $total_creditors; ?></h3></div><div class="avatar-sm"><div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-4"><i class="fas fa-building"></i></div></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex justify-content-between"><div><p class="text-muted mb-1">Active Creditors</p><h3 class="mb-0 text-success"><?php echo $active_creditors; ?></h3></div><div class="avatar-sm"><div class="avatar-title bg-success-subtle text-success rounded-circle fs-4"><i class="fas fa-check-circle"></i></div></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex justify-content-between"><div><p class="text-muted mb-1">Total Balance</p><h3 class="mb-0 text-warning"><?php echo '₹' . number_format($total_balance, 2); ?></h3></div><div class="avatar-sm"><div class="avatar-title bg-warning-subtle text-warning rounded-circle fs-4"><i class="fas fa-rupee-sign"></i></div></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex justify-content-between"><div><p class="text-muted mb-1">Total Purchases</p><h3 class="mb-0 text-info"><?php echo '₹' . number_format($total_purchases, 2); ?></h3></div><div class="avatar-sm"><div class="avatar-title bg-info-subtle text-info rounded-circle fs-4"><i class="fas fa-shopping-cart"></i></div></div></div></div></div></div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card"><div class="card-body"><div class="table-responsive">
                            <table class="table table-hover table-centered mb-0" id="creditorsTable">
                                <thead class="table-light">
                                    <tr><th>#</th><th>Vendor Name</th><th>Company</th><th>Phone</th><th>GSTIN</th><th>Total Purchases</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($creditors as $index => $creditor): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($creditor['vendor_name']); ?></strong><?php if (!empty($creditor['contact_person'])): ?><br><small class="text-muted">Contact: <?php echo htmlspecialchars($creditor['contact_person']); ?></small><?php endif; ?></td>
                                        <td><?php echo htmlspecialchars($creditor['company_name'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($creditor['phone']); ?></td>
                                        <td><small><?php echo htmlspecialchars($creditor['gstin'] ?: '-'); ?></small></td>
                                        <td><?php echo '₹' . number_format($creditor['total_purchases'], 2); ?></td>
                                        <td class="<?php echo $creditor['current_balance'] > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo '₹' . number_format($creditor['current_balance'], 2); ?></td>
                                        <td><span class="badge <?php echo $creditor['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($creditor['status']); ?></span></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view-creditor.php?id=<?php echo $creditor['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                                <a href="edit-creditor.php?id=<?php echo $creditor['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                                <a href="?toggle_status=1&id=<?php echo $creditor['id']; ?>" class="btn btn-sm <?php echo $creditor['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>" onclick="return confirm('Change status?')"><i class="fas <?php echo $creditor['status'] == 'active' ? 'fa-ban' : 'fa-check-circle'; ?>"></i></a>
                                                <?php if ($creditor['total_purchases'] == 0): ?><a href="?delete=1&id=<?php echo $creditor['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this creditor? This action cannot be undone.')"><i class="fas fa-trash"></i></a><?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($creditors)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-building fs-1 d-block mb-2"></i>No creditors found. Click "Add New Creditor" to add one.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div></div></div>
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
                    <h5 class="modal-title">Bulk Import Creditors (CSV)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Download the CSV template, fill creditor rows, and upload the .csv file here.</p>
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
                    </div>
                    <div class="small text-muted">Required column: <strong>vendor_name</strong>. Existing creditors are matched by vendor name + phone.</div>
                </div>
                <div class="modal-footer">
                    <a href="creditor-import-template.csv" class="btn btn-light border">Download Template</a>
                    <button type="submit" class="btn btn-success" name="bulk_import_creditors" value="1">Import Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php'); ?>
<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#creditorsTable').DataTable({
            pageLength: 25,
            order: [[1, "asc"]],
            language: { search: "Search:", lengthMenu: "Show _MENU_ entries", info: "Showing _START_ to _END_ of _TOTAL_ creditors" }
        });
    }
});
</script>
</body>
</html>
