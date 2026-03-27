<?php
session_start();
include('config/config.php');

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// Filters
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$brand_id    = isset($_GET['brand_id']) ? intval($_GET['brand_id']) : 0;
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : 'all'; // all, low, out

// Fetch Categories & Brands for filters
$categories = mysqli_query($conn, "SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name");
$brands = mysqli_query($conn, "SELECT id, brand_name FROM brands WHERE status = 'active' ORDER BY brand_name");

// Main Inventory Query
$sql = "
    SELECT 
        p.id,
        p.product_code,
        p.product_name,
        c.category_name,
        b.brand_name,
        p.stock_price,
        p.customer_price,
        p.quantity,
        p.status,
        p.updated_at,
        (p.quantity * p.stock_price) AS stock_value
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.status != 'inactive'
";

if ($category_id > 0) {
    $sql .= " AND p.category_id = $category_id";
}
if ($brand_id > 0) {
    $sql .= " AND p.brand_id = $brand_id";
}
if ($stock_status === 'low') {
    $low_threshold = 10; // Can be made dynamic from settings
    $sql .= " AND p.quantity > 0 AND p.quantity <= $low_threshold";
} elseif ($stock_status === 'out') {
    $sql .= " AND p.quantity <= 0";
}

$sql .= " ORDER BY p.quantity ASC, p.product_name ASC";

$result = mysqli_query($conn, $sql);

// Summary Calculations
$total_stock_value = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$total_products = mysqli_num_rows($result);

while ($row = mysqli_fetch_assoc($result)) {
    $total_stock_value += $row['stock_value'];
    if ($row['quantity'] <= 0) {
        $out_of_stock_count++;
    } elseif ($row['quantity'] <= 10) { // Low stock threshold
        $low_stock_count++;
    }
}
// Reset result pointer for table display
mysqli_data_seek($result, 0);
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

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-success-subtle text-success rounded-2 fs-2">
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Stock Value</p>
                                        <h4 class="mb-0"><?php echo formatCurrency($total_stock_value); ?></h4>
                                        <p class="text-muted mb-0"><?php echo $total_products; ?> products</p>
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
                                            <i class="mdi mdi-alert-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Low Stock</p>
                                        <h4 class="mb-0"><?php echo $low_stock_count; ?></h4>
                                        <p class="text-muted mb-0">Items need restock</p>
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
                                            <i class="mdi mdi-cancel"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p>
                                        <h4 class="mb-0"><?php echo $out_of_stock_count; ?></h4>
                                        <p class="text-muted mb-0">No inventory</p>
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
                                            <i class="mdi mdi-chart-bar"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                                        <h4 class="mb-0"><?php echo $total_products; ?></h4>
                                        <p class="text-muted mb-0">Active items</p>
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
                                <h5 class="card-title mb-3">Filters & Options</h5>
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category_id">
                                            <option value="0">All Categories</option>
                                            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Brand</label>
                                        <select class="form-select" name="brand_id">
                                            <option value="0">All Brands</option>
                                            <?php while ($brand = mysqli_fetch_assoc($brands)): ?>
                                                <option value="<?php echo $brand['id']; ?>" <?php echo $brand_id == $brand['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($brand['brand_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Stock Status</label>
                                        <select class="form-select" name="stock_status">
                                            <option value="all" <?php echo $stock_status == 'all' ? 'selected' : ''; ?>>All Stock</option>
                                            <option value="low" <?php echo $stock_status == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                            <option value="out" <?php echo $stock_status == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="mdi mdi-filter"></i> Apply
                                        </button>
                                        <a href="inventory-reports.php" class="btn btn-secondary">
                                            <i class="mdi mdi-refresh"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">Inventory Report</h4>
                                        <p class="card-title-desc">Current stock status</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <span class="text-muted me-3">Total Stock Value:</span>
                                        <strong><?php echo formatCurrency($total_stock_value); ?></strong>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Product Code</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Brand</th>
                                                <th>Stock Price</th>
                                                <th>Current Stock</th>
                                                <th>Stock Value</th>
                                                <th>Last Updated</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (mysqli_num_rows($result) > 0): ?>
                                                <?php $counter = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                                    <?php
                                                    $status_class = '';
                                                    if ($row['quantity'] <= 0) {
                                                        $status_class = 'badge-soft-danger';
                                                        $status_text = 'Out of Stock';
                                                    } elseif ($row['quantity'] <= 10) {
                                                        $status_class = 'badge-soft-warning';
                                                        $status_text = 'Low Stock';
                                                    } else {
                                                        $status_class = 'badge-soft-success';
                                                        $status_text = 'In Stock';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($row['product_code']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($row['category_name'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($row['brand_name'] ?? '-'); ?></td>
                                                        <td><?php echo formatCurrency($row['stock_price']); ?></td>
                                                        <td>
                                                            <span class="fw-bold <?php echo $row['quantity'] <= 0 ? 'text-danger' : ($row['quantity'] <= 10 ? 'text-warning' : 'text-success'); ?>">
                                                                <?php echo $row['quantity']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatCurrency($row['stock_value']); ?></td>
                                                        <td><?php echo date('d M, Y h:i A', strtotime($row['updated_at'])); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-5">
                                                        <i class="mdi mdi-package-variant display-4 text-muted"></i>
                                                        <h5 class="mt-3">No Products Found</h5>
                                                        <p>No inventory matches the selected filters</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
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
<?php include('includes/scripts.php')?>

<script>
// Auto-submit on filter change
document.querySelectorAll('select').forEach(select => {
    select.addEventListener('change', () => select.closest('form').submit());
});
</script>

</body>
</html>
<?php mysqli_close($conn); ?>