<!doctype html>
<html lang="en">

<?php include('includes/head.php')?>

<body data-sidebar="dark">

<!-- Loader -->
<?php include('includes/pre-loader.php')?>

<!-- Begin page -->
<div id="layout-wrapper">

<?php include('includes/topbar.php')?>    

    <!-- ========== Left Sidebar Start ========== -->
    <div class="vertical-menu">

        <div data-simplebar class="h-100">

            <!--- Sidemenu -->
            <?php include('includes/sidebar.php')?>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- Left Sidebar End -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
           
            <div class="container-fluid">

                <!-- end page title -->

                <?php
                // Database connection
                include('config/config.php');
                
                // Check if ID is provided
                if (!isset($_GET['id']) || empty($_GET['id'])) {
                    echo '<div class="alert alert-danger">Product ID not provided.</div>';
                    echo '<a href="products-list.php" class="btn btn-primary mt-3">Back to Products</a>';
                    exit();
                }
                
                $product_id = mysqli_real_escape_string($conn, $_GET['id']);
                
                // Handle form submission for updating product
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    // Collect form data with validation
                    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
                    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
                    $brand_id = mysqli_real_escape_string($conn, $_POST['brand_id']);
                    $stock_price = mysqli_real_escape_string($conn, $_POST['stock_price']);
                    $customer_price = mysqli_real_escape_string($conn, $_POST['customer_price']);
                    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
                    $description = mysqli_real_escape_string($conn, $_POST['description']);
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Calculate profit
                    $profit = $customer_price - $stock_price;
                    $profit_percentage = ($stock_price > 0) ? (($profit / $stock_price) * 100) : 0;
                    
                    // Check if product name already exists (excluding current product)
                    $check_sql = "SELECT id FROM products WHERE product_name = '$product_name' AND id != '$product_id'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Product name already exists!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Update product in database
                        $sql = "UPDATE products SET 
                                product_name = '$product_name',
                                category_id = '$category_id',
                                brand_id = '$brand_id',
                                stock_price = '$stock_price',
                                customer_price = '$customer_price',
                                quantity = '$quantity',
                                profit = '$profit',
                                profit_percentage = '$profit_percentage',
                                description = '$description',
                                status = '$status',
                                updated_at = NOW()
                                WHERE id = '$product_id'";
                        
                        if (mysqli_query($conn, $sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Product updated successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                
                // Fetch product details
                $sql = "SELECT p.*, c.category_name, b.brand_name 
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN brands b ON p.brand_id = b.id
                        WHERE p.id = '$product_id'";
                
                $result = mysqli_query($conn, $sql);
                
                if (!$result || mysqli_num_rows($result) == 0) {
                    echo '<div class="alert alert-danger">Product not found.</div>';
                    echo '<a href="products-list.php" class="btn btn-primary mt-3">Back to Products</a>';
                    mysqli_close($conn);
                    exit();
                }
                
                $product = mysqli_fetch_assoc($result);
                
                // Fetch categories for dropdown
                $categories_sql = "SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name";
                $categories_result = mysqli_query($conn, $categories_sql);
                
                // Fetch brands for dropdown
                $brands_sql = "SELECT id, brand_name FROM brands WHERE status = 'active' ORDER BY brand_name";
                $brands_result = mysqli_query($conn, $brands_sql);
                ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">Edit Product: <?php echo htmlspecialchars($product['product_name']); ?></h4>
                                    <div>
                                        <span class="badge bg-primary">Code: <?php echo $product['product_code']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="product-edit.php?id=<?php echo $product_id; ?>" id="productForm">
                                    <div class="row">
                                        <!-- Product Basic Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="product_name" required 
                                                       value="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                                       maxlength="150">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Product Code</label>
                                                <input type="text" class="form-control" value="<?php echo $product['product_code']; ?>" readonly disabled>
                                                <small class="text-muted">Product code cannot be changed</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                                <select class="form-select" name="category_id" required id="categorySelect">
                                                    <option value="">Select Category</option>
                                                    <?php
                                                    while ($cat = mysqli_fetch_assoc($categories_result)) {
                                                        $selected = ($cat['id'] == $product['category_id']) ? 'selected' : '';
                                                        echo '<option value="' . $cat['id'] . '" ' . $selected . '>' . $cat['category_name'] . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Brand</label>
                                                <select class="form-select" name="brand_id" id="brandSelect">
                                                    <option value="">Select Brand</option>
                                                    <?php
                                                    while ($brand = mysqli_fetch_assoc($brands_result)) {
                                                        $selected = ($brand['id'] == $product['brand_id']) ? 'selected' : '';
                                                        echo '<option value="' . $brand['id'] . '" ' . $selected . '>' . $brand['brand_name'] . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pricing Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Stock Price (Cost) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="stock_price" required 
                                                           value="<?php echo $product['stock_price']; ?>" 
                                                           min="0" step="0.01" id="stockPrice">
                                                </div>
                                                <small class="text-muted">Price you pay to purchase</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Customer Price (Selling) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="customer_price" required 
                                                           value="<?php echo $product['customer_price']; ?>" 
                                                           min="0" step="0.01" id="customerPrice">
                                                </div>
                                                <small class="text-muted">Price customers pay</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quantity Information -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="quantity" required 
                                                       value="<?php echo $product['quantity']; ?>" 
                                                       min="0" step="1" id="quantity">
                                                <small class="text-muted">Current stock available</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status" id="statusSelect">
                                                    <option value="active" <?php echo $product['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $product['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="out_of_stock" <?php echo $product['status'] == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Description -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3" 
                                                          maxlength="500"><?php echo htmlspecialchars($product['description']); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Product Information -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-info">
                                                <div class="card-header bg-info-subtle">
                                                    <h5 class="card-title mb-0 text-info">
                                                        <i class="mdi mdi-information-outline me-1"></i> Product Information
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Created Date</h6>
                                                                <p class="mb-0"><?php echo date('d M, Y', strtotime($product['created_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Last Updated</h6>
                                                                <p class="mb-0"><?php echo date('d M, Y', strtotime($product['updated_at'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Current Profit</h6>
                                                                <p class="mb-0 <?php echo $product['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                    ₹<?php echo number_format($product['profit'], 2); ?> 
                                                                    (<?php echo number_format($product['profit_percentage'], 1); ?>%)
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Stock Value</h6>
                                                                <p class="mb-0 text-warning">
                                                                    ₹<?php echo number_format($product['stock_price'] * $product['quantity'], 2); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Price Summary Card -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary-subtle">
                                                    <h5 class="card-title mb-0 text-primary">
                                                        <i class="mdi mdi-calculator me-1"></i> Profit & Stock Summary
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Profit Per Unit</h6>
                                                                <h4 class="text-success" id="profitAmount">₹<?php echo number_format($product['profit'], 2); ?></h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Profit Margin</h6>
                                                                <h4 class="text-primary" id="profitPercentage"><?php echo number_format($product['profit_percentage'], 1); ?>%</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Total Stock Value</h6>
                                                                <h4 class="text-warning" id="totalStockValue">₹<?php echo number_format($product['stock_price'] * $product['quantity'], 2); ?></h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Total Selling Value</h6>
                                                                <h4 class="text-info" id="totalSellingValue">₹<?php echo number_format($product['customer_price'] * $product['quantity'], 2); ?></h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-content-save me-1"></i> Update Product
                                        </button>
                                        <a href="products-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Products
                                        </a>
                                        <a href="product-view.php?id=<?php echo $product_id; ?>" class="btn btn-info ms-2">
                                            <i class="mdi mdi-eye-outline me-1"></i> View Product
                                        </a>
                                        <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="mdi mdi-delete-outline me-1"></i> Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php 
        mysqli_close($conn);
        include('includes/footer.php') 
        ?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>?</p>
                <p class="text-danger">This action cannot be undone. All product data will be removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete-product.php?id=<?php echo $product_id; ?>" class="btn btn-danger">Delete Product</a>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to calculate profit and stock values
function calculateSummary() {
    const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
    const customerPrice = parseFloat(document.getElementById('customerPrice').value) || 0;
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    
    // Calculate profit per unit
    const profitPerUnit = customerPrice - stockPrice;
    document.getElementById('profitAmount').textContent = '₹' + profitPerUnit.toFixed(2);
    
    // Calculate profit percentage
    const profitPercentage = stockPrice > 0 ? ((profitPerUnit / stockPrice) * 100) : 0;
    document.getElementById('profitPercentage').textContent = profitPercentage.toFixed(1) + '%';
    
    // Calculate total stock value (at cost)
    const totalStockValue = quantity * stockPrice;
    document.getElementById('totalStockValue').textContent = '₹' + totalStockValue.toFixed(2);
    
    // Calculate total selling value
    const totalSellingValue = quantity * customerPrice;
    document.getElementById('totalSellingValue').textContent = '₹' + totalSellingValue.toFixed(2);
    
    // Color coding for profit
    const profitAmountElem = document.getElementById('profitAmount');
    const profitPercentageElem = document.getElementById('profitPercentage');
    
    if (profitPerUnit < 0) {
        profitAmountElem.className = 'text-danger';
        profitPercentageElem.className = 'text-danger';
    } else if (profitPerUnit > 0) {
        profitAmountElem.className = 'text-success';
        profitPercentageElem.className = 'text-success';
    } else {
        profitAmountElem.className = 'text-muted';
        profitPercentageElem.className = 'text-muted';
    }
    
    // Auto-update status based on quantity
    const statusSelect = document.getElementById('statusSelect');
    if (quantity == 0 && statusSelect.value != 'inactive') {
        statusSelect.value = 'out_of_stock';
    }
}

// Add event listeners for real-time calculation
document.addEventListener('DOMContentLoaded', function() {
    // Listen for changes in stock price
    document.getElementById('stockPrice').addEventListener('input', calculateSummary);
    
    // Listen for changes in customer price
    document.getElementById('customerPrice').addEventListener('input', calculateSummary);
    
    // Listen for changes in quantity
    document.getElementById('quantity').addEventListener('input', calculateSummary);
    
    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const productName = document.querySelector('input[name="product_name"]').value;
        const stockPrice = parseFloat(document.getElementById('stockPrice').value) || 0;
        const customerPrice = parseFloat(document.getElementById('customerPrice').value) || 0;
        const quantity = parseFloat(document.getElementById('quantity').value) || 0;
        const category = document.getElementById('categorySelect').value;
        
        // Validate required fields
        if (productName.trim().length < 2) {
            alert('Product name must be at least 2 characters long');
            e.preventDefault();
            return false;
        }
        
        if (!category) {
            alert('Please select a category');
            e.preventDefault();
            return false;
        }
        
        if (stockPrice <= 0) {
            alert('Stock price must be greater than 0');
            e.preventDefault();
            return false;
        }
        
        if (customerPrice <= 0) {
            alert('Customer price must be greater than 0');
            e.preventDefault();
            return false;
        }
        
        if (customerPrice < stockPrice) {
            if (!confirm('Warning: Customer price is lower than stock price. You will make a loss. Continue anyway?')) {
                e.preventDefault();
                return false;
            }
        }
        
        if (quantity < 0) {
            alert('Quantity cannot be negative');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Initialize calculations
    calculateSummary();
});
</script>

</body>

</html>