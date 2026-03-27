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
                
                // Check if product_id is provided
                $product_id = isset($_GET['product_id']) ? mysqli_real_escape_string($conn, $_GET['product_id']) : '';
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
                    $quantity_to_add = mysqli_real_escape_string($conn, $_POST['quantity']);
                    $stock_price = mysqli_real_escape_string($conn, $_POST['stock_price']);
                    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
                    
                    // Get current product details
                    $product_sql = "SELECT product_name, stock_price, quantity FROM products WHERE id = '$product_id'";
                    $product_result = mysqli_query($conn, $product_sql);
                    
                    if (mysqli_num_rows($product_result) > 0) {
                        $product = mysqli_fetch_assoc($product_result);
                        
                        // Calculate new quantity
                        $new_quantity = $product['quantity'] + $quantity_to_add;
                        
                        // Update product stock and price
                        $update_sql = "UPDATE products SET 
                            quantity = '$new_quantity',
                            stock_price = '$stock_price',
                            updated_at = NOW()";
                        
                        // Update status if quantity was 0 and now has stock
                        if ($product['quantity'] == 0 && $quantity_to_add > 0) {
                            $update_sql .= ", status = 'active'";
                        }
                        
                        $update_sql .= " WHERE id = '$product_id'";
                        
                        if (mysqli_query($conn, $update_sql)) {
                            // Record stock transaction
                            $transaction_sql = "INSERT INTO stock_transactions (
                                product_id, transaction_type, quantity, stock_price, 
                                previous_quantity, new_quantity, notes, created_at
                            ) VALUES (
                                '$product_id', 'purchase', '$quantity_to_add', '$stock_price',
                                '{$product['quantity']}', '$new_quantity', '$notes', NOW()
                            )";
                            
                            mysqli_query($conn, $transaction_sql);
                            
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Stock added successfully! ' . $quantity_to_add . ' units added to ' . $product['product_name'] . '.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                            
                            // Refresh product data
                            $product_sql = "SELECT * FROM products WHERE id = '$product_id'";
                            $product_result = mysqli_query($conn, $product_sql);
                            $product = mysqli_fetch_assoc($product_result);
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error updating stock: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-block-helper me-2"></i>
                                Product not found!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    }
                }
                
                // Fetch product details if product_id is provided
                $product = null;
                if (!empty($product_id)) {
                    $product_sql = "SELECT p.*, c.category_name, b.brand_name 
                                   FROM products p
                                   LEFT JOIN categories c ON p.category_id = c.id
                                   LEFT JOIN brands b ON p.brand_id = b.id
                                   WHERE p.id = '$product_id'";
                    $product_result = mysqli_query($conn, $product_sql);
                    
                    if (mysqli_num_rows($product_result) > 0) {
                        $product = mysqli_fetch_assoc($product_result);
                    } else {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Product not found. Please select a product from the list.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    }
                }
                
                // Fetch all active products for dropdown
                $products_sql = "SELECT id, product_code, product_name, quantity, stock_price 
                                FROM products WHERE status = 'active' 
                                ORDER BY product_name";
                $products_result = mysqli_query($conn, $products_sql);
                ?>

                <div class="row">
                    <!-- Product Selection & Form -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Add Stock to Product</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="add-stock.php" id="stockForm">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Select Product <span class="text-danger">*</span></label>
                                                <select class="form-select" name="product_id" id="productSelect" required onchange="loadProductDetails(this.value)">
                                                    <option value="">Select a product...</option>
                                                    <?php
                                                    while ($prod = mysqli_fetch_assoc($products_result)) {
                                                        $selected = ($product && $prod['id'] == $product['id']) ? 'selected' : '';
                                                        echo '<option value="' . $prod['id'] . '" ' . $selected . ' data-current-price="' . $prod['stock_price'] . '" data-current-qty="' . $prod['quantity'] . '">' . 
                                                             $prod['product_name'] . ' (' . $prod['product_code'] . ') - Stock: ' . $prod['quantity'] . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($product): ?>
                                    <!-- Product Information Card -->
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="card border-info">
                                                <div class="card-header bg-info-subtle">
                                                    <h5 class="card-title mb-0 text-info">
                                                        <i class="mdi mdi-information-outline me-1"></i> Product Information
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Current Stock</h6>
                                                                <h4 class="mb-0 <?php echo $product['quantity'] < 10 ? 'text-warning' : 'text-success'; ?>">
                                                                    <?php echo $product['quantity']; ?> units
                                                                </h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Current Price</h6>
                                                                <h4 class="mb-0 text-primary">₹<?php echo number_format($product['stock_price'], 2); ?></h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Stock Value</h6>
                                                                <h4 class="mb-0 text-warning">₹<?php echo number_format($product['stock_price'] * $product['quantity'], 2); ?></h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stock Addition Form -->
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">New Stock Price (Cost) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₹</span>
                                                    <input type="number" class="form-control" name="stock_price" required 
                                                           value="<?php echo $product['stock_price']; ?>"
                                                           placeholder="Enter new stock price" min="0" step="0.01" id="newStockPrice">
                                                </div>
                                                <small class="text-muted">This will update the product's cost price</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="quantity" required 
                                                       placeholder="Enter quantity to add" min="1" step="1" id="quantityToAdd" value="1">
                                                <small class="text-muted">Number of units to add to stock</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Notes (Optional)</label>
                                                <textarea class="form-control" name="notes" rows="3" 
                                                          placeholder="Add any notes about this stock addition (supplier, batch, etc.)" 
                                                          maxlength="500"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stock Summary -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary-subtle">
                                                    <h5 class="card-title mb-0 text-primary">
                                                        <i class="mdi mdi-calculator me-1"></i> Stock Summary
                                                    </h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Current Stock</h6>
                                                                <h4 class="mb-0" id="currentStock"><?php echo $product['quantity']; ?></h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Adding</h6>
                                                                <h4 class="mb-0 text-success" id="addingStock">0</h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">New Total</h6>
                                                                <h4 class="mb-0 text-primary" id="newTotalStock"><?php echo $product['quantity']; ?></h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Total Cost</h6>
                                                                <h4 class="mb-0 text-warning" id="totalCost">₹0.00</h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php else: ?>
                                    <!-- No product selected message -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="alert alert-info">
                                                <i class="mdi mdi-information-outline me-2"></i>
                                                Please select a product from the dropdown above to add stock.
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mt-4">
                                        <?php if ($product): ?>
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Stock
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" class="btn btn-primary w-md" disabled>
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Stock
                                        </button>
                                        <?php endif; ?>
                                        <a href="products-list.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Products
                                        </a>
                                        <?php if ($product): ?>
                                        <a href="product-view.php?id=<?php echo $product['id']; ?>" class="btn btn-info ms-2">
                                            <i class="mdi mdi-eye-outline me-1"></i> View Product
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Add & Recent Stock -->
                    <div class="col-lg-4">
                        <!-- Quick Add Section -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Add Common Quantities</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100" onclick="quickAdd(10)">
                                            <i class="mdi mdi-plus me-1"></i> Add 10
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-success w-100" onclick="quickAdd(25)">
                                            <i class="mdi mdi-plus me-1"></i> Add 25
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-info w-100" onclick="quickAdd(50)">
                                            <i class="mdi mdi-plus me-1"></i> Add 50
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-warning w-100" onclick="quickAdd(100)">
                                            <i class="mdi mdi-plus me-1"></i> Add 100
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <label class="form-label">Custom Quantity</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="customQuantity" placeholder="Enter quantity" min="1">
                                        <button class="btn btn-primary" type="button" onclick="quickAddCustom()">
                                            Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Stock Additions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-history me-1"></i> Recent Stock Additions
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch recent stock transactions
                                $recent_sql = "SELECT st.*, p.product_name, p.product_code 
                                             FROM stock_transactions st
                                             JOIN products p ON st.product_id = p.id
                                             WHERE st.transaction_type = 'purchase'
                                             ORDER BY st.created_at DESC 
                                             LIMIT 5";
                                $recent_result = mysqli_query($conn, $recent_sql);
                                
                                if (mysqli_num_rows($recent_result) > 0) {
                                    while ($transaction = mysqli_fetch_assoc($recent_result)) {
                                        $date = date('d M', strtotime($transaction['created_at']));
                                        ?>
                                        <div class="d-flex align-items-center py-2 border-bottom">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-xs">
                                                    <span class="avatar-title bg-success-subtle text-success rounded-circle">
                                                        <i class="mdi mdi-plus"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="font-size-14 mb-1"><?php echo $transaction['product_name']; ?></h6>
                                                <p class="text-muted mb-0 small">
                                                    +<?php echo $transaction['quantity']; ?> units 
                                                    • ₹<?php echo number_format($transaction['stock_price'], 2); ?> each
                                                    <br>
                                                    <small><?php echo $date; ?></small>
                                                </p>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo '<div class="text-center py-3">
                                            <div class="text-muted">
                                                <i class="mdi mdi-package-variant-closed display-5"></i>
                                                <p class="mt-2 mb-0">No recent stock additions</p>
                                            </div>
                                        </div>';
                                }
                                
                                mysqli_close($conn);
                                ?>
                                <div class="mt-2 text-center">
                                    <a href="inventory-history.php" class="text-primary small">
                                        <i class="mdi mdi-arrow-right me-1"></i> View All History
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Tips -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-lightbulb-on me-1"></i> Stock Management Tips
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small>Update stock price when new purchase costs change</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small>Add notes for supplier/batch tracking</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small>Regular stock updates prevent shortages</small>
                                    </li>
                                    <li>
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small>Monitor low stock alerts in products list</small>
                                    </li>
                                </ul>
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
// Global variable to store current product quantity
let currentStock = <?php echo $product ? $product['quantity'] : 0; ?>;
let currentPrice = <?php echo $product ? $product['stock_price'] : 0; ?>;

// Function to load product details via AJAX
function loadProductDetails(productId) {
    if (!productId) {
        // Clear form if no product selected
        window.location.href = 'add-stock.php';
        return;
    }
    
    // Redirect to same page with product_id parameter
    window.location.href = 'add-stock.php?product_id=' + productId;
}

// Function to update stock summary
function updateStockSummary() {
    const quantityToAdd = parseInt(document.getElementById('quantityToAdd').value) || 0;
    const newPrice = parseFloat(document.getElementById('newStockPrice').value) || 0;
    
    // Update current stock display
    document.getElementById('currentStock').textContent = currentStock;
    
    // Update adding stock display
    document.getElementById('addingStock').textContent = quantityToAdd;
    
    // Calculate and update new total
    const newTotal = currentStock + quantityToAdd;
    document.getElementById('newTotalStock').textContent = newTotal;
    
    // Calculate and update total cost
    const totalCost = quantityToAdd * newPrice;
    document.getElementById('totalCost').textContent = '₹' + totalCost.toFixed(2);
    
    // Color coding for stock levels
    const newTotalElem = document.getElementById('newTotalStock');
    if (newTotal < 10) {
        newTotalElem.className = 'mb-0 text-danger';
    } else if (newTotal < 50) {
        newTotalElem.className = 'mb-0 text-warning';
    } else {
        newTotalElem.className = 'mb-0 text-primary';
    }
}

// Function for quick add buttons
function quickAdd(quantity) {
    const quantityInput = document.getElementById('quantityToAdd');
    const currentValue = parseInt(quantityInput.value) || 0;
    quantityInput.value = currentValue + quantity;
    updateStockSummary();
}

// Function for custom quick add
function quickAddCustom() {
    const customInput = document.getElementById('customQuantity');
    const quantity = parseInt(customInput.value) || 0;
    
    if (quantity > 0) {
        const quantityInput = document.getElementById('quantityToAdd');
        const currentValue = parseInt(quantityInput.value) || 0;
        quantityInput.value = currentValue + quantity;
        updateStockSummary();
        
        // Clear custom input
        customInput.value = '';
    }
}

// Update stock summary when form loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize stock summary
    updateStockSummary();
    
    // Add event listeners
    if (document.getElementById('quantityToAdd')) {
        document.getElementById('quantityToAdd').addEventListener('input', updateStockSummary);
    }
    
    if (document.getElementById('newStockPrice')) {
        document.getElementById('newStockPrice').addEventListener('input', updateStockSummary);
    }
    
    // Form validation
    document.getElementById('stockForm').addEventListener('submit', function(e) {
        const quantity = parseInt(document.getElementById('quantityToAdd').value) || 0;
        const price = parseFloat(document.getElementById('newStockPrice').value) || 0;
        
        if (quantity <= 0) {
            alert('Please enter a valid quantity (minimum 1)');
            e.preventDefault();
            return false;
        }
        
        if (price <= 0) {
            alert('Please enter a valid stock price');
            e.preventDefault();
            return false;
        }
        
        if (!confirm('Are you sure you want to add ' + quantity + ' units to this product?')) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Update current stock and price when product selection changes
    document.getElementById('productSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            currentStock = parseInt(selectedOption.getAttribute('data-current-qty')) || 0;
            currentPrice = parseFloat(selectedOption.getAttribute('data-current-price')) || 0;
            
            // Update form fields
            if (document.getElementById('newStockPrice')) {
                document.getElementById('newStockPrice').value = currentPrice;
            }
            if (document.getElementById('quantityToAdd')) {
                document.getElementById('quantityToAdd').value = 1;
            }
            
            updateStockSummary();
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Number keys for quick add
        if (e.target === document.body) {
            switch(e.key) {
                case '1': quickAdd(10); e.preventDefault(); break;
                case '2': quickAdd(25); e.preventDefault(); break;
                case '3': quickAdd(50); e.preventDefault(); break;
                case '4': quickAdd(100); e.preventDefault(); break;
                case 'Escape': window.location.href = 'products-list.php'; break;
            }
        }
    });
});

// Auto-focus on quantity field when product is selected
if (document.getElementById('quantityToAdd') && currentStock > 0) {
    document.getElementById('quantityToAdd').focus();
    document.getElementById('quantityToAdd').select();
}
</script>

</body>

</html>