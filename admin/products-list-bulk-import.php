<?php
session_start();
$currentPage = 'products-list';
include('config/config.php');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available. Please check config/config.php');
}

if (!function_exists('h')) {
    function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) { return '₹' . number_format((float)$amount, 2); }
}
function getColumnExists(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    $cache[$key] = $res && mysqli_num_rows($res) > 0;
    if ($res) mysqli_free_result($res);
    return $cache[$key];
}
function product_import_autoload(): bool {
    $paths = [__DIR__ . '/vendor/autoload.php', dirname(__DIR__) . '/vendor/autoload.php'];
    foreach ($paths as $path) { if (file_exists($path)) { require_once $path; return true; } }
    return class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory');
}
function product_norm_header($value): string {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/\*/', '', $value);
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_');
}

$hasHsnCode = getColumnExists($conn, 'products', 'hsn_code');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $status = trim($_POST['status'] ?? '');
    $allowedStatuses = ['active', 'inactive', 'out_of_stock'];

    if ($productId <= 0 || !in_array($status, $allowedStatuses, true)) {
        $error = 'Invalid product action.';
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE products SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $status, $productId);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: products-list.php?success=' . urlencode('Product status updated successfully.'));
            exit;
        }
        $error = 'Unable to update product status. ' . mysqli_error($conn);
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import_products'])) {
    if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        header('Location: products-list.php?error=' . urlencode('Please choose an Excel file to import.'));
        exit;
    }
    if (!product_import_autoload()) {
        header('Location: products-list.php?error=' . urlencode('PhpSpreadsheet library not found. Install it using Composer before bulk import.'));
        exit;
    }

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['import_file']['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        if (count($sheet) < 2) throw new Exception('Empty file');

        $headers = [];
        foreach (($sheet[2] ?? $sheet[1]) as $col => $value) $headers[$col] = product_norm_header($value);

        $inserted = 0; $updated = 0; $skipped = 0;
        for ($r = 3; $r <= count($sheet); $r++) {
            $row = $sheet[$r];
            $data = [];
            foreach ($headers as $col => $name) if ($name !== '') $data[$name] = trim((string)($row[$col] ?? ''));
            if (count(array_filter($data, fn($v) => $v !== '')) === 0) continue;

            $productName = $data['product_name'] ?? '';
            $customerPrice = $data['customer_price'] ?? '';
            if ($productName === '' || $customerPrice === '' || !is_numeric($customerPrice)) { $skipped++; continue; }

            $productCode = $data['product_code'] ?? '';
            if ($productCode === '') $productCode = 'PROD' . date('ymd') . rand(100, 999);
            $stockPrice = is_numeric($data['stock_price'] ?? '') ? (float)$data['stock_price'] : 0;
            $sellPrice = (float)$customerPrice;
            $qty = is_numeric($data['quantity'] ?? '') ? (int)$data['quantity'] : 0;
            $profit = $sellPrice - $stockPrice;
            $profitPct = $stockPrice > 0 ? round(($profit / $stockPrice) * 100, 2) : 0;
            $status = strtolower($data['status'] ?? ($qty <= 0 ? 'out_of_stock' : 'active'));
            if (!in_array($status, ['active','inactive','out_of_stock'], true)) $status = ($qty <= 0 ? 'out_of_stock' : 'active');

            $categoryId = 0;
            if (!empty($data['category_id']) && is_numeric($data['category_id'])) {
                $categoryId = (int)$data['category_id'];
            } elseif (!empty($data['category_name'])) {
                $safeName = mysqli_real_escape_string($conn, $data['category_name']);
                $catRes = mysqli_query($conn, "SELECT id FROM categories WHERE category_name = '{$safeName}' LIMIT 1");
                if ($catRes && ($cat = mysqli_fetch_assoc($catRes))) $categoryId = (int)$cat['id'];
            }

            $brandId = 0;
            if (!empty($data['brand_id']) && is_numeric($data['brand_id'])) {
                $brandId = (int)$data['brand_id'];
            } elseif (!empty($data['brand_name'])) {
                $safeName = mysqli_real_escape_string($conn, $data['brand_name']);
                $brandRes = mysqli_query($conn, "SELECT id FROM brands WHERE brand_name = '{$safeName}' LIMIT 1");
                if ($brandRes && ($brand = mysqli_fetch_assoc($brandRes))) $brandId = (int)$brand['id'];
            }

            $checkStmt = mysqli_prepare($conn, "SELECT id FROM products WHERE product_code = ? OR product_name = ? LIMIT 1");
            mysqli_stmt_bind_param($checkStmt, 'ss', $productCode, $productName);
            mysqli_stmt_execute($checkStmt);
            $checkRes = mysqli_stmt_get_result($checkStmt);
            $existing = $checkRes ? mysqli_fetch_assoc($checkRes) : null;
            mysqli_stmt_close($checkStmt);

            if ($existing) {
                if ($hasHsnCode) {
                    $sql = "UPDATE products SET product_code=?, product_name=?, category_id=?, brand_id=?, stock_price=?, customer_price=?, quantity=?, profit=?, profit_percentage=?, hsn_code=?, description=?, status=? WHERE id=?";
                    $stmt = mysqli_prepare($conn, $sql);
                    $hsn = $data['hsn_code'] ?? '';
                    $desc = $data['description'] ?? '';
                    mysqli_stmt_bind_param($stmt, 'ssiiddiddsssi', $productCode, $productName, $categoryId, $brandId, $stockPrice, $sellPrice, $qty, $profit, $profitPct, $hsn, $desc, $status, $existing['id']);
                } else {
                    $sql = "UPDATE products SET product_code=?, product_name=?, category_id=?, brand_id=?, stock_price=?, customer_price=?, quantity=?, profit=?, profit_percentage=?, description=?, status=? WHERE id=?";
                    $stmt = mysqli_prepare($conn, $sql);
                    $desc = $data['description'] ?? '';
                    mysqli_stmt_bind_param($stmt, 'ssiiddiddssi', $productCode, $productName, $categoryId, $brandId, $stockPrice, $sellPrice, $qty, $profit, $profitPct, $desc, $status, $existing['id']);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $updated++;
            } else {
                if ($hasHsnCode) {
                    $sql = "INSERT INTO products (product_code, product_name, category_id, brand_id, stock_price, customer_price, quantity, profit, profit_percentage, hsn_code, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    $hsn = $data['hsn_code'] ?? '';
                    $desc = $data['description'] ?? '';
                    mysqli_stmt_bind_param($stmt, 'ssiiddiddsss', $productCode, $productName, $categoryId, $brandId, $stockPrice, $sellPrice, $qty, $profit, $profitPct, $hsn, $desc, $status);
                } else {
                    $sql = "INSERT INTO products (product_code, product_name, category_id, brand_id, stock_price, customer_price, quantity, profit, profit_percentage, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    $desc = $data['description'] ?? '';
                    mysqli_stmt_bind_param($stmt, 'ssiiddiddss', $productCode, $productName, $categoryId, $brandId, $stockPrice, $sellPrice, $qty, $profit, $profitPct, $desc, $status);
                }
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $inserted++;
            }
        }

        header('Location: products-list.php?success=' . urlencode("Bulk import completed. Inserted: $inserted, Updated: $updated, Skipped: $skipped"));
        exit;
    } catch (Throwable $e) {
        header('Location: products-list.php?error=' . urlencode('Unable to import product Excel file. Please use the template and try again.'));
        exit;
    }
}

if (isset($_GET['success']) && $_GET['success'] !== '') $message = trim((string)$_GET['success']);
if (isset($_GET['error']) && $_GET['error'] !== '') $error = trim((string)$_GET['error']);

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$brandFilter = (int)($_GET['brand_id'] ?? 0);

$categories = [];
$catRes = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($catRes) while ($row = mysqli_fetch_assoc($catRes)) $categories[] = $row;

$brands = [];
$brandRes = mysqli_query($conn, "SELECT id, brand_name FROM brands ORDER BY brand_name ASC");
if ($brandRes) while ($row = mysqli_fetch_assoc($brandRes)) $brands[] = $row;

$stats = ['total_products'=>0,'active_products'=>0,'inactive_products'=>0,'out_of_stock_products'=>0,'total_qty'=>0,'stock_value'=>0];
$statsSql = "SELECT COUNT(*) AS total_products,SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_products,SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_products,SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) AS out_of_stock_products,COALESCE(SUM(quantity), 0) AS total_qty,COALESCE(SUM(quantity * stock_price), 0) AS stock_value FROM products";
$statsRes = mysqli_query($conn, $statsSql);
if ($statsRes && $row = mysqli_fetch_assoc($statsRes)) $stats = $row;

$sql = "SELECT p.id, p.product_code, p.product_name, p.stock_price, p.customer_price, p.quantity, p.profit, p.profit_percentage, p.status, p.updated_at," . ($hasHsnCode ? " p.hsn_code," : "") . " c.category_name, b.brand_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id WHERE 1=1";
$params = []; $types = '';

if ($search !== '') {
    $term = '%' . $search . '%';
    if ($hasHsnCode) {
        $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.hsn_code LIKE ? OR c.category_name LIKE ? OR b.brand_name LIKE ?)";
        array_push($params, $term, $term, $term, $term, $term); $types .= 'sssss';
    } else {
        $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR c.category_name LIKE ? OR b.brand_name LIKE ?)";
        array_push($params, $term, $term, $term, $term); $types .= 'ssss';
    }
}
if (in_array($statusFilter, ['active', 'inactive', 'out_of_stock'], true)) { $sql .= " AND p.status = ?"; $params[] = $statusFilter; $types .= 's'; }
if ($categoryFilter > 0) { $sql .= " AND p.category_id = ?"; $params[] = $categoryFilter; $types .= 'i'; }
if ($brandFilter > 0) { $sql .= " AND p.brand_id = ?"; $params[] = $brandFilter; $types .= 'i'; }
$sql .= " ORDER BY p.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && !empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
$products = [];
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) $products[] = $row;
    mysqli_stmt_close($stmt);
} else $error = 'Unable to load product list. ' . mysqli_error($conn);
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php')?>
<body data-sidebar="dark">
<?php include('includes/pre-loader.php')?>
<div id="layout-wrapper">
<?php include('includes/topbar.php')?>
    <div class="vertical-menu"><div data-simplebar class="h-100"><?php include('includes/sidebar.php')?></div></div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

                <?php if ($message !== ''): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo h($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo h($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if (!$hasHsnCode): ?><div class="alert alert-warning alert-dismissible fade show" role="alert">HSN Code column is not available in the current <strong>products</strong> table, so HSN will be shown only after adding the database column.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <div class="row">
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2"><i class="mdi mdi-package-variant"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Total Products</p><h4 class="mb-0"><span id="total-products"><?php echo (int)$stats['total_products']; ?></span></h4></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-success-subtle text-success rounded-2 fs-2"><i class="mdi mdi-check-circle"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Active</p><h4 class="mb-0"><span id="active-products"><?php echo (int)$stats['active_products']; ?></span></h4></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-2"><i class="mdi mdi-package-variant-closed"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p><h4 class="mb-0"><span id="outofstock-products"><?php echo (int)$stats['out_of_stock_products']; ?></span></h4></div></div></div></div></div>
                    <div class="col-md-3"><div class="card"><div class="card-body"><div class="d-flex align-items-center"><div class="avatar-sm flex-shrink-0"><span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2"><i class="mdi mdi-alert-circle"></i></span></div><div class="flex-grow-1 ms-3"><p class="text-uppercase fw-medium text-muted mb-0">Total Qty</p><h4 class="mb-0"><span><?php echo (int)$stats['total_qty']; ?></span></h4></div></div></div></div></div>
                </div>

                <div class="row">
                    <div class="col-lg-12"><div class="card"><div class="card-body">
                        <form method="get" class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label">Search</label><input type="text" class="form-control" id="searchInput" name="search" value="<?php echo h($search); ?>" placeholder="Search by code, name, HSN, category, brand"></div>
                            <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status" id="statusFilterSelect"><option value="">All Products</option><option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option><option value="out_of_stock" <?php echo $statusFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option></select></div>
                            <div class="col-md-2"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="0">All Categories</option><?php foreach ($categories as $category): ?><option value="<?php echo (int)$category['id']; ?>" <?php echo $categoryFilter === (int)$category['id'] ? 'selected' : ''; ?>><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-2"><label class="form-label">Brand</label><select class="form-select" name="brand_id"><option value="0">All Brands</option><?php foreach ($brands as $brand): ?><option value="<?php echo (int)$brand['id']; ?>" <?php echo $brandFilter === (int)$brand['id'] ? 'selected' : ''; ?>><?php echo h($brand['brand_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-2 d-grid"><label class="form-label">&nbsp;</label><button type="submit" class="btn btn-primary"><i class="mdi mdi-filter me-1"></i> Apply</button></div>
                        </form>

                        <div class="row mb-3">
                            <div class="col-md-6"><h4 class="card-title mb-0">All Products</h4><p class="card-title-desc mb-0">Manage your product inventory</p></div>
                            <div class="col-md-6">
                                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mt-3 mt-md-0">
                                    <a href="product-import-template.xlsx" class="btn btn-outline-info"><i class="mdi mdi-file-excel me-1"></i> Download Template</a>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkImportModal"><i class="mdi mdi-upload me-1"></i> Bulk Import</button>
                                    <button type="button" class="btn btn-light border" id="clearFiltersBtn"><i class="mdi mdi-refresh me-1"></i> Reset</button>
                                    <a href="add-product.php" class="btn btn-success"><i class="mdi mdi-plus-circle-outline me-1"></i> Add New</a>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-centered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th><th>Code</th><?php if ($hasHsnCode): ?><th>HSN</th><?php endif; ?><th>Product</th><th>Category</th><th>Brand</th><th>Stock Price</th><th>Customer Price</th><th>Qty</th><th>Profit</th><th>Status</th><th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($products)): $counter = 1; foreach ($products as $row): 
                                    $quantity = (int)($row['quantity'] ?? 0);
                                    $stockStatus = $quantity <= 0 ? 'Out of Stock' : ($quantity < 10 ? 'Low Stock' : 'In Stock');
                                    $stockClass = $quantity <= 0 ? 'bg-danger' : ($quantity < 10 ? 'bg-warning' : 'bg-success');
                                    $statusClass = ($row['status'] === 'active') ? 'badge-soft-success' : (($row['status'] === 'inactive') ? 'badge-soft-danger' : 'badge-soft-warning');
                                ?>
                                    <tr data-status="<?php echo h($row['status']); ?>" data-quantity="<?php echo $quantity; ?>">
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo h($row['product_code']); ?></td>
                                        <?php if ($hasHsnCode): ?><td><?php echo h($row['hsn_code'] ?? ''); ?></td><?php endif; ?>
                                        <td><div class="fw-semibold"><?php echo h($row['product_name']); ?></div></td>
                                        <td><span class="badge bg-info-subtle text-info"><?php echo !empty($row['category_name']) ? h($row['category_name']) : 'N/A'; ?></span></td>
                                        <td><?php if (!empty($row['brand_name'])): ?><span class="badge bg-secondary-subtle text-secondary"><?php echo h($row['brand_name']); ?></span><?php else: ?><span class="text-muted">N/A</span><?php endif; ?></td>
                                        <td><div class="text-center"><span class="fw-medium">₹<?php echo number_format((float)($row['stock_price'] ?? 0), 2); ?></span><p class="text-muted mb-0 small">Cost</p></div></td>
                                        <td><div class="text-center"><span class="fw-medium text-success">₹<?php echo number_format((float)($row['customer_price'] ?? 0), 2); ?></span><p class="text-muted mb-0 small">Selling</p></div></td>
                                        <td><div class="text-center"><span class="fw-medium <?php echo $quantity < 10 ? 'text-warning' : 'text-success'; ?>"><?php echo $quantity; ?></span><p class="text-muted mb-0 small"><span class="badge <?php echo $stockClass; ?> font-size-10"><?php echo $stockStatus; ?></span></p></div></td>
                                        <td><div class="text-center"><span class="fw-medium <?php echo ((float)($row['profit'] ?? 0)) >= 0 ? 'text-success' : 'text-danger'; ?>">₹<?php echo number_format((float)($row['profit'] ?? 0), 2); ?></span><p class="text-muted mb-0 small"><?php echo number_format((float)($row['profit_percentage'] ?? 0), 1); ?>%</p></div></td>
                                        <td><span class="badge <?php echo $statusClass; ?> font-size-12"><?php echo ucfirst(str_replace('_', ' ', h($row['status']))); ?></span></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="add-product.php?id=<?php echo (int)$row['id']; ?>">Edit</a></li>
                                                    <li><a class="dropdown-item" href="product-view.php?id=<?php echo (int)$row['id']; ?>">View</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="<?php echo $hasHsnCode ? 12 : 11; ?>" class="text-center text-muted py-4">No products found.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div></div></div>
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
                    <h5 class="modal-title">Bulk Import Products</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Download the Excel template, fill product rows, and upload the .xlsx file here.</p>
                    <div class="mb-3">
                        <label class="form-label">Excel File</label>
                        <input type="file" class="form-control" name="import_file" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="small text-muted">Required columns: <strong>product_name</strong> and <strong>customer_price</strong>. Category and brand can be matched by name.</div>
                </div>
                <div class="modal-footer">
                    <a href="product-import-template.xlsx" class="btn btn-light border">Download Template</a>
                    <button type="submit" class="btn btn-success" name="bulk_import_products" value="1">Import Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/scripts.php')?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            window.location.href = 'products-list.php';
        });
    }
});
</script>
</body>
</html>
