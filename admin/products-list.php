<?php
session_start();
$currentPage = 'products-list';
include('config/config.php');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available. Please check config/config.php');
}

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function getColumnExists(mysqli $conn, string $table, string $column): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if (!$res) {
        return false;
    }
    $exists = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);
    return $exists;
}

$hasHsnCode = getColumnExists($conn, 'products', 'hsn_code');

$low_stock_threshold = 10;
$thresholdRes = mysqli_query($conn, "SELECT low_stock_threshold FROM business_settings ORDER BY id ASC LIMIT 1");
if ($thresholdRes && ($thresholdRow = mysqli_fetch_assoc($thresholdRes))) {
    $low_stock_threshold = max(1, (int)($thresholdRow['low_stock_threshold'] ?? 10));
    mysqli_free_result($thresholdRes);
}

$message = '';
$error = '';

if (isset($_GET['success']) && $_GET['success'] !== '') {
    $message = trim((string)$_GET['success']);
}
if (isset($_GET['error']) && $_GET['error'] !== '') {
    $error = trim((string)$_GET['error']);
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$brandFilter = (int)($_GET['brand_id'] ?? 0);

$categories = [];
$catRes = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($catRes) {
    while ($row = mysqli_fetch_assoc($catRes)) {
        $categories[] = $row;
    }
    mysqli_free_result($catRes);
}

$brands = [];
$brandRes = mysqli_query($conn, "SELECT id, brand_name FROM brands ORDER BY brand_name ASC");
if ($brandRes) {
    while ($row = mysqli_fetch_assoc($brandRes)) {
        $brands[] = $row;
    }
    mysqli_free_result($brandRes);
}

$whereParts = [];
$params = [];
$types = '';

if ($search !== '') {
    $whereParts[] = $hasHsnCode
        ? "(p.product_code LIKE ? OR p.product_name LIKE ? OR p.hsn_code LIKE ? OR c.category_name LIKE ? OR b.brand_name LIKE ? OR p.description LIKE ?)"
        : "(p.product_code LIKE ? OR p.product_name LIKE ? OR c.category_name LIKE ? OR b.brand_name LIKE ? OR p.description LIKE ?)";
    $like = '%' . $search . '%';
    if ($hasHsnCode) {
        array_push($params, $like, $like, $like, $like, $like, $like);
        $types .= 'ssssss';
    } else {
        array_push($params, $like, $like, $like, $like, $like);
        $types .= 'sssss';
    }
}

if (in_array($statusFilter, ['active', 'inactive', 'out_of_stock'], true)) {
    $whereParts[] = 'p.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($categoryFilter > 0) {
    $whereParts[] = 'p.category_id = ?';
    $params[] = $categoryFilter;
    $types .= 'i';
}

if ($brandFilter > 0) {
    $whereParts[] = 'p.brand_id = ?';
    $params[] = $brandFilter;
    $types .= 'i';
}

$whereSql = '';
if (!empty($whereParts)) {
    $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
}

$selectHsn = $hasHsnCode ? ', p.hsn_code' : '';
$sql = "SELECT p.*, c.category_name, b.brand_name{$selectHsn}
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        {$whereSql}
        ORDER BY p.created_at DESC, p.id DESC";

$products = [];
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        mysqli_free_result($result);
    }
    mysqli_stmt_close($stmt);
} else {
    $error = 'Unable to load products: ' . mysqli_error($conn);
}

$statsSql = "SELECT
                COUNT(*) AS total_products,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_products,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_products,
                SUM(CASE WHEN status = 'out_of_stock' OR quantity = 0 THEN 1 ELSE 0 END) AS outofstock_products,
                SUM(CASE WHEN quantity < {$low_stock_threshold} THEN 1 ELSE 0 END) AS low_stock_products,
                COALESCE(SUM(quantity), 0) AS total_quantity,
                COALESCE(SUM(stock_price * quantity), 0) AS total_stock_value,
                COALESCE(SUM(customer_price * quantity), 0) AS total_selling_value,
                COALESCE(SUM(profit * quantity), 0) AS total_profit
             FROM products";
$statsRes = mysqli_query($conn, $statsSql);
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'inactive_products' => 0,
    'outofstock_products' => 0,
    'low_stock_products' => 0,
    'total_quantity' => 0,
    'total_stock_value' => 0,
    'total_selling_value' => 0,
    'total_profit' => 0,
];
if ($statsRes && ($statsRow = mysqli_fetch_assoc($statsRes))) {
    $stats = array_merge($stats, $statsRow);
    mysqli_free_result($statsRes);
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

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo h($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo h($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$hasHsnCode): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        HSN Code column is not available in the current <strong>products</strong> table, so HSN will be shown only after adding the database column.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                                        <h4 class="mb-0"><span id="total-products"><?php echo (int)$stats['total_products']; ?></span></h4>
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
                                            <i class="mdi mdi-check-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Active</p>
                                        <h4 class="mb-0"><span id="active-products"><?php echo (int)$stats['active_products']; ?></span></h4>
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
                                            <i class="mdi mdi-package-variant-closed"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p>
                                        <h4 class="mb-0"><span id="outofstock-products"><?php echo (int)$stats['outofstock_products']; ?></span></h4>
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
                                        <span class="avatar-title bg-danger-subtle text-danger rounded-2 fs-2">
                                            <i class="mdi mdi-alert-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Low Stock</p>
                                        <h4 class="mb-0"><span id="lowstock-products"><?php echo (int)$stats['low_stock_products']; ?></span></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="get" class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" id="searchInput" name="search" value="<?php echo h($search); ?>" placeholder="Search by code, name, HSN, category, brand">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status" id="statusFilterSelect">
                                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Products</option>
                                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="out_of_stock" <?php echo $statusFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category_id">
                                            <option value="0">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo (int)$category['id']; ?>" <?php echo $categoryFilter === (int)$category['id'] ? 'selected' : ''; ?>>
                                                    <?php echo h($category['category_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Brand</label>
                                        <select class="form-select" name="brand_id">
                                            <option value="0">All Brands</option>
                                            <?php foreach ($brands as $brand): ?>
                                                <option value="<?php echo (int)$brand['id']; ?>" <?php echo $brandFilter === (int)$brand['id'] ? 'selected' : ''; ?>>
                                                    <?php echo h($brand['brand_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter me-1"></i> Apply</button>
                                    </div>
                                </form>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">All Products</h4>
                                        <p class="card-title-desc mb-0">Manage your product inventory</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mt-3 mt-md-0">
                                            <button type="button" class="btn btn-light border" id="clearFiltersBtn">
                                                <i class="mdi mdi-refresh me-1"></i> Reset
                                            </button>
                                            <a href="add-product.php" class="btn btn-success">
                                                <i class="mdi mdi-plus-circle-outline me-1"></i> Add New
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="productsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Product Code</th>
                                                <?php if ($hasHsnCode): ?>
                                                    <th>HSN Code</th>
                                                <?php endif; ?>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Brand</th>
                                                <th>Stock Price</th>
                                                <th>Customer Price</th>
                                                <th>Quantity</th>
                                                <th>Profit</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productsTableBody">
                                            <?php if (!empty($products)): ?>
                                                <?php $counter = 1; ?>
                                                <?php foreach ($products as $row): ?>
                                                    <?php
                                                    $quantity = (int)($row['quantity'] ?? 0);
                                                    if ($quantity <= 0 || $row['status'] === 'out_of_stock') {
                                                        $stockStatus = 'Out of Stock';
                                                        $stockClass = 'bg-danger';
                                                    } elseif ($quantity < $low_stock_threshold) {
                                                        $stockStatus = 'Low Stock';
                                                        $stockClass = 'bg-warning';
                                                    } else {
                                                        $stockStatus = 'In Stock';
                                                        $stockClass = 'bg-success';
                                                    }

                                                    $statusClass = 'badge-soft-secondary';
                                                    if (($row['status'] ?? '') === 'active') {
                                                        $statusClass = 'badge-soft-success';
                                                    } elseif (($row['status'] ?? '') === 'inactive') {
                                                        $statusClass = 'badge-soft-danger';
                                                    } elseif (($row['status'] ?? '') === 'out_of_stock') {
                                                        $statusClass = 'badge-soft-warning';
                                                    }
                                                    ?>
                                                    <tr data-status="<?php echo h($row['status'] ?? ''); ?>" data-quantity="<?php echo $quantity; ?>">
                                                        <td><?php echo $counter; ?></td>
                                                        <td><span class="fw-medium"><?php echo h($row['product_code'] ?? ''); ?></span></td>
                                                        <?php if ($hasHsnCode): ?>
                                                            <td><?php echo h($row['hsn_code'] ?? ''); ?></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr((string)($row['product_name'] ?? 'P'), 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="add-product.php?edit=<?php echo (int)$row['id']; ?>" class="text-dark">
                                                                            <?php echo h($row['product_name'] ?? ''); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <?php if (!empty($row['description'])): ?>
                                                                        <p class="text-muted mb-0 small text-truncate" style="max-width: 220px;">
                                                                            <?php echo h(mb_strimwidth((string)$row['description'], 0, 60, '...')); ?>
                                                                        </p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info-subtle text-info">
                                                                <?php echo h($row['category_name'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($row['brand_name'])): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary"><?php echo h($row['brand_name']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium">₹<?php echo number_format((float)($row['stock_price'] ?? 0), 2); ?></span>
                                                                <p class="text-muted mb-0 small">Cost</p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium text-success">₹<?php echo number_format((float)($row['customer_price'] ?? 0), 2); ?></span>
                                                                <p class="text-muted mb-0 small">Selling</p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium <?php echo $quantity < $low_stock_threshold ? 'text-warning' : 'text-success'; ?>"><?php echo $quantity; ?></span>
                                                                <p class="text-muted mb-0 small">
                                                                    <span class="badge <?php echo $stockClass; ?> font-size-10"><?php echo $stockStatus; ?></span>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium <?php echo ((float)($row['profit'] ?? 0)) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                    ₹<?php echo number_format((float)($row['profit'] ?? 0), 2); ?>
                                                                </span>
                                                                <p class="text-muted mb-0 small"><?php echo number_format((float)($row['profit_percentage'] ?? 0), 1); ?>%</p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $statusClass; ?> font-size-12">
                                                                <?php echo ucfirst(str_replace('_', ' ', (string)($row['status'] ?? ''))); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="add-product.php?edit=<?php echo (int)$row['id']; ?>">
                                                                            <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="add-stock.php?product_id=<?php echo (int)$row['id']; ?>">
                                                                            <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger delete-product" href="#" data-id="<?php echo (int)$row['id']; ?>" data-name="<?php echo h($row['product_name'] ?? ''); ?>">
                                                                            <i class="mdi mdi-delete-outline me-1"></i> Delete
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php $counter++; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="<?php echo $hasHsnCode ? 12 : 11; ?>" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-package-variant-closed display-4"></i>
                                                            <h5 class="mt-2">No Products Found</h5>
                                                            <p>Click on "Add New" to add your first product</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-sm-12 col-md-5">
                                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                            Showing <?php echo count($products); ?> products
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="mdi mdi-chart-bar me-1"></i> Inventory Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Items</h6>
                                        <h4 class="mb-0"><?php echo number_format((float)$stats['total_quantity']); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Cost Value</h6>
                                        <h4 class="mb-0 text-warning">₹<?php echo number_format((float)$stats['total_stock_value'], 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Selling Value</h6>
                                        <h4 class="mb-0 text-success">₹<?php echo number_format((float)$stats['total_selling_value'], 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Potential Profit</h6>
                                        <h4 class="mb-0 text-primary">₹<?php echo number_format((float)$stats['total_profit'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include('includes/footer.php')?>
    </div>

</div>

<?php include('includes/rightbar.php')?>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
                <p class="text-danger mb-0">This action cannot be undone. All product data will be removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/scripts.php')?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');
    const tableBody = document.getElementById('productsTableBody');
    const infoBox = document.getElementById('datatable_info');
    const threshold = <?php echo (int)$low_stock_threshold; ?>;
    let deleteId = null;

    function getRows() {
        return Array.from(tableBody.querySelectorAll('tr'));
    }

    function updateVisibleCount() {
        const visibleRows = getRows().filter(row => row.style.display !== 'none');
        if (infoBox) {
            infoBox.textContent = `Showing ${visibleRows.length} products`;
        }
    }

    function recalculateCards() {
        const rows = getRows().filter(row => row.querySelector('.delete-product'));
        let total = 0;
        let active = 0;
        let inactive = 0;
        let outOfStock = 0;
        let lowStock = 0;

        rows.forEach(row => {
            total++;
            const status = row.getAttribute('data-status');
            const qty = parseInt(row.getAttribute('data-quantity') || '0', 10);
            if (status === 'active') active++;
            if (status === 'inactive') inactive++;
            if (status === 'out_of_stock' || qty <= 0) outOfStock++;
            if (qty < threshold) lowStock++;
        });

        document.getElementById('total-products').textContent = total;
        document.getElementById('active-products').textContent = active;
        document.getElementById('outofstock-products').textContent = outOfStock;
        document.getElementById('lowstock-products').textContent = lowStock;
    }

    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const searchTerm = this.value.toLowerCase();
            getRows().forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
            updateVisibleCount();
        });
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            window.location.href = 'products-list.php';
        });
    }

    document.addEventListener('click', function (e) {
        const deleteLink = e.target.closest('.delete-product');
        if (!deleteLink) return;

        e.preventDefault();
        deleteId = deleteLink.getAttribute('data-id');
        const deleteName = deleteLink.getAttribute('data-name') || '';
        document.getElementById('deleteName').textContent = deleteName;

        const deleteModalEl = document.getElementById('deleteModal');
        const deleteModal = new bootstrap.Modal(deleteModalEl);
        deleteModal.show();
    });

    const confirmDeleteBtn = document.getElementById('confirmDelete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (!deleteId) return;

            fetch('delete-product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + encodeURIComponent(deleteId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const deleteLink = document.querySelector('.delete-product[data-id="' + deleteId + '"]');
                    const row = deleteLink ? deleteLink.closest('tr') : null;
                    if (row) {
                        row.remove();
                    }
                    recalculateCards();
                    updateVisibleCount();
                    showAlert('Product deleted successfully!', 'success');
                } else {
                    showAlert(data.message ? data.message : 'Unable to delete product.', 'danger');
                }

                const modalEl = document.getElementById('deleteModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            })
            .catch(() => {
                showAlert('Network error while deleting product.', 'danger');
                const modalEl = document.getElementById('deleteModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            });
        });
    }

    function showAlert(message, type) {
        const existingAlert = document.querySelector('.floating-alert-message');
        if (existingAlert) {
            existingAlert.remove();
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show floating-alert-message';
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '320px';
        alertDiv.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        document.body.appendChild(alertDiv);

        setTimeout(function () {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 4000);
    }

    updateVisibleCount();
});
</script>

</body>
</html>
<?php mysqli_close($conn); ?>
