<?php
session_start();
$currentPage = 'add-product';
include('config/config.php');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not available. Please check config/config.php');
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateProductCode')) {
    function generateProductCode(mysqli $conn) {
        do {
            $code = 'PROD' . date('ymd') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE product_code = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $code);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
        } while ($exists);

        return $code;
    }
}

$hasHsnCode = false;
$colCheck = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'hsn_code'");
if ($colCheck && mysqli_num_rows($colCheck) > 0) {
    $hasHsnCode = true;
}
if ($colCheck) {
    mysqli_free_result($colCheck);
}

$message = '';
$error = '';
$isEdit = false;
$productId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$product = [
    'id' => 0,
    'product_code' => '',
    'product_name' => '',
    'category_id' => '',
    'brand_id' => '',
    'stock_price' => '0.00',
    'customer_price' => '0.00',
    'quantity' => '0',
    'profit' => '0.00',
    'profit_percentage' => '0.00',
    'hsn_code' => '',
    'description' => '',
    'status' => 'active',
];

if ($productId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT id, product_code, product_name, category_id, brand_id, stock_price, customer_price, quantity, profit, profit_percentage, " . ($hasHsnCode ? "hsn_code, " : "") . "description, status FROM products WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $product = $row;
        $isEdit = true;
    } else {
        $error = 'Product not found.';
    }
    mysqli_stmt_close($stmt);
}

$categories = [];
$catResult = mysqli_query($conn, "SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC");
if ($catResult) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

$brands = [];
$brandResult = mysqli_query($conn, "SELECT id, brand_name FROM brands WHERE status = 'active' ORDER BY brand_name ASC");
if ($brandResult) {
    while ($row = mysqli_fetch_assoc($brandResult)) {
        $brands[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $isEdit = $postedId > 0;

    $product['id'] = $postedId;
    $product['product_code'] = trim($_POST['product_code'] ?? '');
    $product['product_name'] = trim($_POST['product_name'] ?? '');
    $product['hsn_code'] = trim($_POST['hsn_code'] ?? '');
    $product['category_id'] = trim($_POST['category_id'] ?? '');
    $product['brand_id'] = trim($_POST['brand_id'] ?? '');
    $product['stock_price'] = trim($_POST['stock_price'] ?? '0');
    $product['customer_price'] = trim($_POST['customer_price'] ?? '0');
    $product['quantity'] = trim($_POST['quantity'] ?? '0');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['status'] = trim($_POST['status'] ?? 'active');

    if ($product['product_code'] === '') {
        $product['product_code'] = generateProductCode($conn);
    }

    $categoryId = ($product['category_id'] !== '') ? (int) $product['category_id'] : null;
    $brandId = ($product['brand_id'] !== '') ? (int) $product['brand_id'] : null;
    $stockPrice = (float) $product['stock_price'];
    $customerPrice = (float) $product['customer_price'];
    $quantity = (int) $product['quantity'];
    $profit = $customerPrice - $stockPrice;
    $profitPercentage = $stockPrice > 0 ? (($profit / $stockPrice) * 100) : 0;
    $product['hsn_code'] = substr(preg_replace('/[^A-Za-z0-9\-]/', '', $product['hsn_code']), 0, 20);

    $product['profit'] = number_format($profit, 2, '.', '');
    $product['profit_percentage'] = number_format($profitPercentage, 2, '.', '');

    $validStatuses = ['active', 'inactive', 'out_of_stock'];

    if ($product['product_name'] === '') {
        $error = 'Product name is required.';
    } elseif (!in_array($product['status'], $validStatuses, true)) {
        $error = 'Invalid product status selected.';
    } elseif ($stockPrice < 0 || $customerPrice < 0) {
        $error = 'Prices cannot be negative.';
    } elseif ($quantity < 0) {
        $error = 'Quantity cannot be negative.';
    } else {
        $checkSql = $isEdit
            ? "SELECT id FROM products WHERE product_code = ? AND id != ? LIMIT 1"
            : "SELECT id FROM products WHERE product_code = ? LIMIT 1";

        $checkStmt = mysqli_prepare($conn, $checkSql);
        if ($isEdit) {
            mysqli_stmt_bind_param($checkStmt, 'si', $product['product_code'], $postedId);
        } else {
            mysqli_stmt_bind_param($checkStmt, 's', $product['product_code']);
        }
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        $duplicateCode = mysqli_stmt_num_rows($checkStmt) > 0;
        mysqli_stmt_close($checkStmt);

        if ($duplicateCode) {
            $error = 'Product code already exists. Please use another code.';
        } else {
            if ($isEdit) {
                if ($hasHsnCode) {
                    $sql = "UPDATE products SET product_code = ?, product_name = ?, category_id = ?, brand_id = ?, stock_price = ?, customer_price = ?, quantity = ?, profit = ?, profit_percentage = ?, hsn_code = ?, description = ?, status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param(
                        $stmt,
                        'ssiiddiddsssi',
                        $product['product_code'],
                        $product['product_name'],
                        $categoryId,
                        $brandId,
                        $stockPrice,
                        $customerPrice,
                        $quantity,
                        $profit,
                        $profitPercentage,
                        $product['hsn_code'],
                        $product['description'],
                        $product['status'],
                        $postedId
                    );
                } else {
                    $sql = "UPDATE products SET product_code = ?, product_name = ?, category_id = ?, brand_id = ?, stock_price = ?, customer_price = ?, quantity = ?, profit = ?, profit_percentage = ?, description = ?, status = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param(
                        $stmt,
                        'ssiiddiddssi',
                        $product['product_code'],
                        $product['product_name'],
                        $categoryId,
                        $brandId,
                        $stockPrice,
                        $customerPrice,
                        $quantity,
                        $profit,
                        $profitPercentage,
                        $product['description'],
                        $product['status'],
                        $postedId
                    );
                }
            } else {
                if ($hasHsnCode) {
                    $sql = "INSERT INTO products (product_code, product_name, category_id, brand_id, stock_price, customer_price, quantity, profit, profit_percentage, hsn_code, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param(
                        $stmt,
                        'ssiiddiddsss',
                        $product['product_code'],
                        $product['product_name'],
                        $categoryId,
                        $brandId,
                        $stockPrice,
                        $customerPrice,
                        $quantity,
                        $profit,
                        $profitPercentage,
                        $product['hsn_code'],
                        $product['description'],
                        $product['status']
                    );
                } else {
                    $sql = "INSERT INTO products (product_code, product_name, category_id, brand_id, stock_price, customer_price, quantity, profit, profit_percentage, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param(
                        $stmt,
                        'ssiiddiddss',
                        $product['product_code'],
                        $product['product_name'],
                        $categoryId,
                        $brandId,
                        $stockPrice,
                        $customerPrice,
                        $quantity,
                        $profit,
                        $profitPercentage,
                        $product['description'],
                        $product['status']
                    );
                }
            }

            if ($stmt && mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $redirectMessage = $isEdit ? 'Product updated successfully.' : 'Product added successfully.';
                header('Location: products-list.php?success=' . urlencode($redirectMessage));
                exit;
            }

            $error = 'Failed to save product. ' . mysqli_error($conn);
            if ($stmt) {
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<?php include('includes/head.php'); ?>
<body data-sidebar="dark">
<?php include('includes/pre-loader.php'); ?>
<div id="layout-wrapper">
    <?php include('includes/topbar.php'); ?>
    <div class="vertical-menu"><div data-simplebar class="h-100"><?php include('includes/sidebar.php'); ?></div></div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row mb-3">
                    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1"><?php echo $isEdit ? 'Edit Product' : 'Add New Product'; ?></h4>
                            <p class="text-muted mb-0">Create and manage product master details.</p>
                        </div>
                        <a href="products-list.php" class="btn btn-outline-primary">
                            <i class="mdi mdi-format-list-bulleted me-1"></i> Manage Products
                        </a>
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo h($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo h($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$hasHsnCode): ?>
                    <div class="alert alert-warning" role="alert">
                        HSN Code field is added in the form, but your current <code>products</code> table does not yet have the <code>hsn_code</code> column. Add the column in MySQL to save HSN values permanently.
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="post" autocomplete="off">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Product Code</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="product_code" id="product_code" value="<?php echo h($product['product_code']); ?>" placeholder="Auto generated if empty">
                                                <button type="button" class="btn btn-light border" id="generateCodeBtn">Generate</button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="product_name" value="<?php echo h($product['product_name']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">HSN Code</label>
                                            <input type="text" class="form-control" name="hsn_code" maxlength="20" value="<?php echo h($product['hsn_code']); ?>" placeholder="Enter HSN code">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="category_id">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo (int) $category['id']; ?>" <?php echo ((string) $product['category_id'] === (string) $category['id']) ? 'selected' : ''; ?>>
                                                        <?php echo h($category['category_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Brand</label>
                                            <select class="form-select" name="brand_id">
                                                <option value="">Select Brand</option>
                                                <?php foreach ($brands as $brand): ?>
                                                    <option value="<?php echo (int) $brand['id']; ?>" <?php echo ((string) $product['brand_id'] === (string) $brand['id']) ? 'selected' : ''; ?>>
                                                        <?php echo h($brand['brand_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Stock Price <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" class="form-control calc-field" name="stock_price" id="stock_price" value="<?php echo h($product['stock_price']); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Customer Price <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0" class="form-control calc-field" name="customer_price" id="customer_price" value="<?php echo h($product['customer_price']); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Opening Quantity <span class="text-danger">*</span></label>
                                            <input type="number" min="0" class="form-control" name="quantity" value="<?php echo h($product['quantity']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Profit</label>
                                            <input type="text" class="form-control" id="profit" value="<?php echo h($product['profit']); ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Profit Percentage</label>
                                            <input type="text" class="form-control" id="profit_percentage" value="<?php echo h($product['profit_percentage']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Status</label>
                                            <select class="form-select" name="status">
                                                <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="out_of_stock" <?php echo $product['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3 d-flex align-items-end">
                                            <div class="w-100 p-3 rounded bg-light border">
                                                <div class="fw-semibold">Quick Note</div>
                                                <small class="text-muted">Product code is unique. Profit and margin are auto calculated from stock price and customer price. HSN code is supported on this form.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="4" placeholder="Optional product description"><?php echo h($product['description']); ?></textarea>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="mdi mdi-content-save me-1"></i>
                                            <?php echo $isEdit ? 'Update Product' : 'Save Product'; ?>
                                        </button>
                                        <a href="products-list.php" class="btn btn-secondary">Cancel</a>
                                        <?php if (!$isEdit): ?>
                                            <button type="reset" class="btn btn-light border">Reset</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Available Categories</h5>
                                <?php if (!empty($categories)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($categories as $category): ?>
                                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <?php echo h($category['category_name']); ?>
                                                <span class="badge bg-soft-primary text-primary"><?php echo (int) $category['id']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No active categories found.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Available Brands</h5>
                                <?php if (!empty($brands)): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($brands as $brand): ?>
                                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <?php echo h($brand['brand_name']); ?>
                                                <span class="badge bg-soft-success text-success"><?php echo (int) $brand['id']; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No active brands found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('includes/footer.php'); ?>
    </div>
</div>

<?php include('includes/rightbar.php'); ?>
<?php include('includes/scripts.php'); ?>
<script>
(function () {
    const stockInput = document.getElementById('stock_price');
    const customerInput = document.getElementById('customer_price');
    const profitInput = document.getElementById('profit');
    const profitPercentageInput = document.getElementById('profit_percentage');
    const codeInput = document.getElementById('product_code');
    const generateBtn = document.getElementById('generateCodeBtn');

    function calculateProfit() {
        const stock = parseFloat(stockInput.value || 0);
        const customer = parseFloat(customerInput.value || 0);
        const profit = customer - stock;
        const percentage = stock > 0 ? ((profit / stock) * 100) : 0;

        profitInput.value = profit.toFixed(2);
        profitPercentageInput.value = percentage.toFixed(2) + '%';
    }

    function generateCode() {
        const now = new Date();
        const year = String(now.getFullYear()).slice(-2);
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const random = String(Math.floor(Math.random() * 1000)).padStart(3, '0');
        codeInput.value = 'PROD' + year + month + day + random;
    }

    document.querySelectorAll('.calc-field').forEach(function (input) {
        input.addEventListener('input', calculateProfit);
    });

    if (generateBtn) {
        generateBtn.addEventListener('click', generateCode);
    }

    calculateProfit();
})();
</script>
</body>
</html>
<?php mysqli_close($conn); ?>
