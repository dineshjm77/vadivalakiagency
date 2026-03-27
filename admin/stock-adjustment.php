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
                
                // Initialize variables
                $product = null;
                $product_id = isset($_GET['product_id']) ? mysqli_real_escape_string($conn, $_GET['product_id']) : '';
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
                    $adjustment_type = mysqli_real_escape_string($conn, $_POST['adjustment_type']);
                    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
                    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
                    
                    // Get current product details
                    $product_sql = "SELECT product_name, quantity FROM products WHERE id = '$product_id'";
                    $product_result = mysqli_query($conn, $product_sql);
                    
                    if (mysqli_num_rows($product_result) > 0) {
                        $product_data = mysqli_fetch_assoc($product_result);
                        $current_quantity = $product_data['quantity'];
                        $product_name = $product_data['product_name'];
                        
                        // Calculate new quantity based on adjustment type
                        if ($adjustment_type == 'increase') {
                            $new_quantity = $current_quantity + $quantity;
                        } else {
                            $new_quantity = $current_quantity - $quantity;
                            if ($new_quantity < 0) $new_quantity = 0;
                        }
                        
                        // Update product quantity
                        $update_sql = "UPDATE products SET 
                            quantity = '$new_quantity',
                            updated_at = NOW()";
                        
                        // Update status based on new quantity
                        if ($new_quantity == 0) {
                            $update_sql .= ", status = 'out_of_stock'";
                        } elseif ($current_quantity == 0 && $new_quantity > 0) {
                            $update_sql .= ", status = 'active'";
                        }
                        
                        $update_sql .= " WHERE id = '$product_id'";
                        
                        if (mysqli_query($conn, $update_sql)) {
                            // Record stock transaction
                            $transaction_sql = "INSERT INTO stock_transactions (
                                product_id, transaction_type, quantity, stock_price,
                                previous_quantity, new_quantity, notes, created_at
                            ) VALUES (
                                '$product_id', 'adjustment', '$quantity', '0.00',
                                '$current_quantity', '$new_quantity', '$reason', NOW()
                            )";
                            
                            mysqli_query($conn, $transaction_sql);
                            
                            // Success message
                            $action_text = $adjustment_type == 'increase' ? 'increased' : 'decreased';
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Stock successfully ' . $action_text . ' by ' . $quantity . ' units!<br>
                                    ' . $product_name . ' - New stock: ' . $new_quantity . ' units
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
                if (!empty($product_id)) {
                    $product_sql = "SELECT p.*, c.category_name, b.brand_name 
                                   FROM products p
                                   LEFT JOIN categories c ON p.category_id = c.id
                                   LEFT JOIN brands b ON p.brand_id = b.id
                                   WHERE p.id = '$product_id'";
                    $product_result = mysqli_query($conn, $product_sql);
                    
                    if (mysqli_num_rows($product_result) > 0) {
                        $product = mysqli_fetch_assoc($product_result);
                    }
                }
                
                // Fetch all products for dropdown
                $products_sql = "SELECT id, product_code, product_name, quantity, status 
                                FROM products 
                                ORDER BY product_name";
                $products_result = mysqli_query($conn, $products_sql);
                ?>

                <div class="row">
                    <!-- Left Column - Adjustment Form -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Adjust Stock Quantity</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="stock-adjustment.php" id="adjustmentForm">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Select Product <span class="text-danger">*</span></label>
                                                <select class="form-select" name="product_id" id="productSelect" required onchange="loadProductDetails(this.value)">
                                                    <option value="">Select a product...</option>
                                                    <?php
                                                    while ($prod = mysqli_fetch_assoc($products_result)) {
                                                        $selected = ($product && $prod['id'] == $product['id']) ? 'selected' : '';
                                                        $stock_status = $prod['quantity'] == 0 ? 'Out of Stock' : ($prod['quantity'] < 10 ? 'Low Stock' : 'In Stock');
                                                        echo '<option value="' . $prod['id'] . '" ' . $selected . ' data-current-qty="' . $prod['quantity'] . '">' . 
                                                             $prod['product_name'] . ' (' . $prod['product_code'] . ') - ' . $stock_status . ': ' . $prod['quantity'] . '</option>';
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
                                                                <h4 class="mb-0 <?php echo $product['quantity'] < 10 ? 'text-warning' : ($product['quantity'] == 0 ? 'text-danger' : 'text-success'); ?>">
                                                                    <?php echo $product['quantity']; ?> units
                                                                </h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Status</h6>
                                                                <h4 class="mb-0">
                                                                    <span class="badge <?php echo $product['status'] == 'active' ? 'bg-success' : ($product['status'] == 'inactive' ? 'bg-danger' : 'bg-warning'); ?>">
                                                                        <?php echo ucfirst(str_replace('_', ' ', $product['status'])); ?>
                                                                    </span>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Stock Value</h6>
                                                                <h4 class="mb-0 text-warning">₹<?php echo number_format($product['stock_price'] * $product['quantity'], 2); ?></h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <div class="text-center">
                                                                <h6 class="text-muted mb-2">Product Details</h6>
                                                                <p class="mb-1">
                                                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                                    <span class="badge bg-primary ms-2"><?php echo $product['product_code']; ?></span>
                                                                </p>
                                                                <?php if (!empty($product['category_name'])): ?>
                                                                <p class="text-muted mb-0">
                                                                    <i class="mdi mdi-tag-outline me-1"></i> <?php echo $product['category_name']; ?>
                                                                    <?php if (!empty($product['brand_name'])): ?>
                                                                    | <i class="mdi mdi-tag-text-outline me-1"></i> <?php echo $product['brand_name']; ?>
                                                                    <?php endif; ?>
                                                                </p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Adjustment Form Fields -->
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Adjustment Type <span class="text-danger">*</span></label>
                                                <div class="d-flex gap-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="adjustment_type" 
                                                               id="increase" value="increase" checked onchange="updateCalculation()">
                                                        <label class="form-check-label" for="increase">
                                                            <i class="mdi mdi-plus-circle text-success me-1"></i> Increase Stock
                                                        </label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="adjustment_type" 
                                                               id="decrease" value="decrease" onchange="updateCalculation()">
                                                        <label class="form-check-label" for="decrease">
                                                            <i class="mdi mdi-minus-circle text-danger me-1"></i> Decrease Stock
                                                        </label>
                                                    </div>
                                                </div>
                                                <small class="text-muted">Select whether to add or remove stock</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Adjustment Quantity <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="quantity" required 
                                                       id="adjustmentQuantity" min="1" value="1" oninput="updateCalculation()">
                                                <small class="text-muted">Number of units to adjust</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Reason for Adjustment <span class="text-danger">*</span></label>
                                                <select class="form-select" name="reason" required id="reasonSelect">
                                                    <option value="">Select a reason...</option>
                                                    <option value="Damaged stock">Damaged stock</option>
                                                    <option value="Expired stock">Expired stock</option>
                                                    <option value="Stock count error">Stock count error</option>
                                                    <option value="Theft/Loss">Theft/Loss</option>
                                                    <option value="Quality return">Quality return</option>
                                                    <option value="Promotional sample">Promotional sample</option>
                                                    <option value="Other">Other (specify in notes)</option>
                                                </select>
                                                <small class="text-muted">Select the primary reason for this adjustment</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Additional Notes</label>
                                                <textarea class="form-control" name="notes" rows="3" 
                                                          placeholder="Add any additional details about this adjustment..." 
                                                          maxlength="500" id="adjustmentNotes"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Adjustment Summary -->
                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="card border-primary">
                                                <div class="card-header bg-primary-subtle">
                                                    <h5 class="card-title mb-0 text-primary">
                                                        <i class="mdi mdi-calculator me-1"></i> Adjustment Summary
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
                                                                <h6 class="text-muted">Adjustment</h6>
                                                                <h4 class="mb-0" id="adjustmentAmount">
                                                                    <span id="adjustmentSign">+</span><span id="adjustmentQty">0</span>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">New Stock</h6>
                                                                <h4 class="mb-0" id="newStock"><?php echo $product['quantity']; ?></h4>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="text-center">
                                                                <h6 class="text-muted">Status Change</h6>
                                                                <h4 class="mb-0">
                                                                    <span class="badge" id="statusChange">No Change</span>
                                                                </h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <div class="alert alert-warning" id="warningAlert" style="display: none;">
                                                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                                                <span id="warningText"></span>
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
                                                Please select a product from the dropdown above to adjust stock.
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mt-4">
                                        <?php if ($product): ?>
                                        <button type="submit" class="btn btn-primary w-md">
                                            <i class="mdi mdi-check-circle-outline me-1"></i> Apply Adjustment
                                        </button>
                                        <?php else: ?>
                                        <button type="submit" class="btn btn-primary w-md" disabled>
                                            <i class="mdi mdi-check-circle-outline me-1"></i> Apply Adjustment
                                        </button>
                                        <?php endif; ?>
                                        <a href="inventory-dashboard.php" class="btn btn-light ms-2">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to Inventory
                                        </a>
                                        <?php if ($product): ?>
                                        <a href="add-stock.php?product_id=<?php echo $product['id']; ?>" class="btn btn-success ms-2">
                                            <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                        </a>
                                        <a href="product-view.php?id=<?php echo $product['id']; ?>" class="btn btn-info ms-2">
                                            <i class="mdi mdi-eye-outline me-1"></i> View Product
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Guidelines & Recent Adjustments -->
                    <div class="col-lg-4">
                        <!-- Adjustment Guidelines -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-lightbulb-on text-warning me-1"></i> Adjustment Guidelines
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small><strong>Increase Stock:</strong> Use for returns, corrections, or found stock</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small><strong>Decrease Stock:</strong> Use for damaged, expired, or lost items</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small><strong>Always provide a reason:</strong> Required for audit trails</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small><strong>Add notes:</strong> For additional details or explanations</small>
                                    </li>
                                    <li>
                                        <i class="mdi mdi-check-circle text-success me-1"></i>
                                        <small><strong>Review summary:</strong> Check calculations before applying</small>
                                    </li>
                                </ul>
                                
                                <div class="mt-3">
                                    <h6 class="text-primary">Common Reasons:</h6>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-light text-dark">Damaged</span>
                                        <span class="badge bg-light text-dark">Expired</span>
                                        <span class="badge bg-light text-dark">Count Error</span>
                                        <span class="badge bg-light text-dark">Theft</span>
                                        <span class="badge bg-light text-dark">Sample</span>
                                        <span class="badge bg-light text-dark">Return</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Adjust Buttons -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-flash text-primary me-1"></i> Quick Adjust
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-success w-100" onclick="quickAdjust(1, 'increase')">
                                            <i class="mdi mdi-plus me-1"></i> +1 Unit
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-danger w-100" onclick="quickAdjust(1, 'decrease')">
                                            <i class="mdi mdi-minus me-1"></i> -1 Unit
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-success w-100" onclick="quickAdjust(5, 'increase')">
                                            <i class="mdi mdi-plus me-1"></i> +5 Units
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-danger w-100" onclick="quickAdjust(5, 'decrease')">
                                            <i class="mdi mdi-minus me-1"></i> -5 Units
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <label class="form-label">Custom Adjustment</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="customAdjustment" placeholder="Units" min="1">
                                        <button class="btn btn-outline-primary" type="button" onclick="quickAdjustCustom('increase')">
                                            <i class="mdi mdi-plus"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" type="button" onclick="quickAdjustCustom('decrease')">
                                            <i class="mdi mdi-minus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Adjustments -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-history text-info me-1"></i> Recent Adjustments
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch recent adjustments
                                $recent_sql = "SELECT st.*, p.product_name, p.product_code 
                                             FROM stock_transactions st
                                             JOIN products p ON st.product_id = p.id
                                             WHERE st.transaction_type = 'adjustment'
                                             ORDER BY st.created_at DESC 
                                             LIMIT 5";
                                $recent_result = mysqli_query($conn, $recent_sql);
                                
                                if (mysqli_num_rows($recent_result) > 0) {
                                    while ($transaction = mysqli_fetch_assoc($recent_result)) {
                                        $date = date('d M', strtotime($transaction['created_at']));
                                        $adjustment_type = $transaction['previous_quantity'] < $transaction['new_quantity'] ? 'increase' : 'decrease';
                                        $type_class = $adjustment_type == 'increase' ? 'text-success' : 'text-danger';
                                        $type_icon = $adjustment_type == 'increase' ? 'mdi-plus' : 'mdi-minus';
                                        ?>
                                        <div class="d-flex align-items-center py-2 border-bottom">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-xs">
                                                    <span class="avatar-title <?php echo $type_class; ?>-subtle <?php echo $type_class; ?> rounded-circle">
                                                        <i class="mdi <?php echo $type_icon; ?>"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="font-size-14 mb-1"><?php echo $transaction['product_name']; ?></h6>
                                                <p class="text-muted mb-0 small">
                                                    <span class="<?php echo $type_class; ?>">
                                                        <?php echo $adjustment_type == 'increase' ? '+' : '-'; ?><?php echo $transaction['quantity']; ?> units
                                                    </span>
                                                    • <?php echo $transaction['notes']; ?>
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
                                                <p class="mt-2 mb-0">No recent adjustments</p>
                                            </div>
                                        </div>';
                                }
                                
                                mysqli_close($conn);
                                ?>
                                <div class="mt-2 text-center">
                                    <a href="inventory-history.php?type=adjustment" class="text-primary small">
                                        <i class="mdi mdi-arrow-right me-1"></i> View All Adjustments
                                    </a>
                                </div>
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

// Function to load product details
function loadProductDetails(productId) {
    if (!productId) {
        window.location.href = 'stock-adjustment.php';
        return;
    }
    window.location.href = 'stock-adjustment.php?product_id=' + productId;
}

// Function to update adjustment calculation
function updateCalculation() {
    if (!currentStock && currentStock !== 0) return;
    
    const adjustmentType = document.querySelector('input[name="adjustment_type"]:checked').value;
    const adjustmentQty = parseInt(document.getElementById('adjustmentQuantity').value) || 0;
    
    // Update adjustment amount display
    document.getElementById('adjustmentSign').textContent = adjustmentType === 'increase' ? '+' : '-';
    document.getElementById('adjustmentQty').textContent = adjustmentQty;
    
    // Calculate new stock
    let newStock;
    if (adjustmentType === 'increase') {
        newStock = currentStock + adjustmentQty;
    } else {
        newStock = currentStock - adjustmentQty;
        if (newStock < 0) newStock = 0;
    }
    
    // Update displays
    document.getElementById('currentStock').textContent = currentStock;
    document.getElementById('newStock').textContent = newStock;
    
    // Color coding
    const newStockElem = document.getElementById('newStock');
    const adjustmentAmountElem = document.getElementById('adjustmentAmount');
    
    if (adjustmentType === 'increase') {
        adjustmentAmountElem.className = 'mb-0 text-success';
    } else {
        adjustmentAmountElem.className = 'mb-0 text-danger';
    }
    
    if (newStock === 0) {
        newStockElem.className = 'mb-0 text-danger';
    } else if (newStock < 10) {
        newStockElem.className = 'mb-0 text-warning';
    } else {
        newStockElem.className = 'mb-0 text-success';
    }
    
    // Update status change
    const statusChangeElem = document.getElementById('statusChange');
    if (currentStock === 0 && newStock > 0) {
        statusChangeElem.textContent = 'Out of Stock → Active';
        statusChangeElem.className = 'badge bg-success';
    } else if (currentStock > 0 && newStock === 0) {
        statusChangeElem.textContent = 'Active → Out of Stock';
        statusChangeElem.className = 'badge bg-danger';
    } else if (currentStock < 10 && newStock >= 10) {
        statusChangeElem.textContent = 'Low Stock → Good';
        statusChangeElem.className = 'badge bg-success';
    } else if (currentStock >= 10 && newStock < 10 && newStock > 0) {
        statusChangeElem.textContent = 'Good → Low Stock';
        statusChangeElem.className = 'badge bg-warning';
    } else {
        statusChangeElem.textContent = 'No Change';
        statusChangeElem.className = 'badge bg-secondary';
    }
    
    // Show warnings
    const warningAlert = document.getElementById('warningAlert');
    const warningText = document.getElementById('warningText');
    
    if (adjustmentType === 'decrease' && adjustmentQty > currentStock) {
        warningText.textContent = 'Warning: Decreasing more units than available stock! Stock will be set to 0.';
        warningAlert.style.display = 'block';
    } else if (newStock === 0) {
        warningText.textContent = 'Warning: This will set stock to 0. Product status will change to "Out of Stock".';
        warningAlert.style.display = 'block';
    } else {
        warningAlert.style.display = 'none';
    }
}

// Function for quick adjust buttons
function quickAdjust(quantity, type) {
    if (!currentStock && currentStock !== 0) {
        alert('Please select a product first');
        return;
    }
    
    // Set adjustment type
    const typeRadio = document.getElementById(type);
    if (typeRadio) typeRadio.checked = true;
    
    // Set quantity
    document.getElementById('adjustmentQuantity').value = quantity;
    
    // Update calculation
    updateCalculation();
}

// Function for custom quick adjust
function quickAdjustCustom(type) {
    const customInput = document.getElementById('customAdjustment');
    const quantity = parseInt(customInput.value) || 0;
    
    if (quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    quickAdjust(quantity, type);
    customInput.value = '';
}

// Function to auto-select reason based on adjustment type
function autoSelectReason() {
    const type = document.querySelector('input[name="adjustment_type"]:checked').value;
    const reasonSelect = document.getElementById('reasonSelect');
    const notesTextarea = document.getElementById('adjustmentNotes');
    
    if (type === 'decrease') {
        // Auto-select first decrease reason if not already selected
        if (!reasonSelect.value) {
            reasonSelect.value = 'Damaged stock';
        }
    } else {
        // Auto-select first increase reason if not already selected
        if (!reasonSelect.value) {
            reasonSelect.value = 'Stock count error';
        }
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize calculation
    if (currentStock || currentStock === 0) {
        updateCalculation();
    }
    
    // Add event listeners
    const quantityInput = document.getElementById('adjustmentQuantity');
    if (quantityInput) {
        quantityInput.addEventListener('input', updateCalculation);
    }
    
    const typeRadios = document.querySelectorAll('input[name="adjustment_type"]');
    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateCalculation();
            autoSelectReason();
        });
    });
    
    // Form validation
    const form = document.getElementById('adjustmentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const quantity = parseInt(document.getElementById('adjustmentQuantity').value) || 0;
            const reason = document.getElementById('reasonSelect').value;
            const type = document.querySelector('input[name="adjustment_type"]:checked').value;
            
            if (quantity <= 0) {
                alert('Please enter a valid adjustment quantity (minimum 1)');
                e.preventDefault();
                return false;
            }
            
            if (!reason) {
                alert('Please select a reason for this adjustment');
                e.preventDefault();
                return false;
            }
            
            const confirmText = type === 'increase' 
                ? `Increase stock by ${quantity} units?` 
                : `Decrease stock by ${quantity} units?`;
            
            if (!confirm(confirmText)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Only if product is selected
        if (currentStock || currentStock === 0) {
            // Ctrl+I for increase
            if (e.ctrlKey && e.key === 'i') {
                e.preventDefault();
                const increaseRadio = document.getElementById('increase');
                if (increaseRadio) increaseRadio.checked = true;
                updateCalculation();
                autoSelectReason();
            }
            // Ctrl+D for decrease
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                const decreaseRadio = document.getElementById('decrease');
                if (decreaseRadio) decreaseRadio.checked = true;
                updateCalculation();
                autoSelectReason();
            }
            // Escape to go back
            if (e.key === 'Escape') {
                window.location.href = 'inventory-dashboard.php';
            }
        }
    });
    
    // Auto-select reason on page load
    autoSelectReason();
});
</script>

</body>

</html>