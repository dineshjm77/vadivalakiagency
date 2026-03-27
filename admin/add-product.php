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
                // Database connection and form processing
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    include('config/config.php');
                    
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
                    
                    // Generate product code
                    $product_code = 'PROD' . date('ym') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                    
                    // Check if product already exists
                    $check_sql = "SELECT id FROM products WHERE product_name = '$product_name'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Product already exists!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Insert into database
                        $sql = "INSERT INTO products (
                            product_code, product_name, category_id, brand_id, 
                            stock_price, customer_price, quantity, profit, profit_percentage,
                            description, status, created_at
                        ) VALUES (
                            '$product_code', '$product_name', '$category_id', '$brand_id',
                            '$stock_price', '$customer_price', '$quantity', '$profit', '$profit_percentage',
                            '$description', '$status', NOW()
                        )";
                        
                        if (mysqli_query($conn, $sql)) {
                            $product_id = mysqli_insert_id($conn);
                            
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Product added successfully! Product Code: ' . $product_code . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                            
                            // Clear form after successful submission
                            echo '<script>
                                setTimeout(function() {
                                    document.querySelector("form").reset();
                                    document.getElementById("profitAmount").textContent = "₹0.00";
                                    document.getElementById("profitPercentage").textContent = "0%";
                                    document.getElementById("totalStockValue").textContent = "₹0.00";
                                }, 100);
                            </script>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                    
                    mysqli_close($conn);
                }
                ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Product Information</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="add-product.php" id="productForm">
                                    <div class="row">
                                        <!-- Product Basic Information -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="product_name" required 
                                                       placeholder="e.g., Bisleri Mineral Water 1L, Amul Milk 500ml" maxlength="150">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                                <select class="form-select" name="category_id" required id="categorySelect">
                                                    <option value="">Select Category</option>
                                                    <?php
                                                    include('config/config.php');
                                                    $cat_sql = "SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name";
                                                    $cat_result = mysqli_query($conn, $cat_sql);
                                                    while ($cat = mysqli_fetch_assoc($cat_result)) {
                                                        echo '<option value="' . $cat['id'] . '">' . $cat['category_name'] . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Brand</label>
                                                <select class="form-select" name="brand_id" id="brandSelect">
                                                    <option value="">Select Brand</option>
                                                    <?php
                                                    $brand_sql = "SELECT id, brand_name FROM brands WHERE status = 'active' ORDER BY brand_name";
                                                    $brand_result = mysqli_query($conn, $brand_sql);
                                                    while ($brand = mysqli_fetch_assoc($brand_result)) {
                                                        echo '<option value="' . $brand['id'] . '">' . $brand['brand_name'] . '</option>';
                                                    }
                                                    mysqli_close($conn);
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <!-- This column is now empty since unit type is removed -->
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
                                                           placeholder="Your purchase cost" min="0" step="0.01" id="stockPrice">
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
                                                           placeholder="Selling price to customers" min="0" step="0.01" id="customerPrice">
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
                                                       placeholder="Stock quantity" min="0" step="1" id="quantity">
                                                <small class="text-muted">Current stock available</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="active" selected>Active</option>
                                                    <option value="inactive">Inactive</option>
                                                    <option value="out_of_stock">Out of Stock</option>
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
                                                          placeholder="Product description, features, usage instructions, etc." maxlength="500"></textarea>
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
                                                                <h4 class="text-success" id="profitAmount">₹0.00</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Profit Margin</h6>
                                                                <h4 class="text-primary" id="profitPercentage">0%</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Total Stock Value</h6>
                                                                <h4 class="text-warning" id="totalStockValue">₹0.00</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Total Selling Value</h6>
                                                                <h4 class="text-info" id="totalSellingValue">₹0.00</h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Product
                                        </button>
                                        <button type="reset" class="btn btn-secondary ms-2">
                                            <i class="mdi mdi-refresh me-1"></i> Reset
                                        </button>
                                        <a href="products-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Products
                                        </a>
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

        <?php include('includes/footer.php')?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<!-- Right Sidebar -->
<?php include('includes/rightbar.php')?>
<!-- /Right-bar -->

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
});
</script>

</body>

</html>