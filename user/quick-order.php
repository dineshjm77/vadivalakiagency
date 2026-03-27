<?php
include('config/config.php');
include('includes/auth-check.php');

// Ensure only linemen can access this page
if ($_SESSION['user_role'] != 'lineman') {
    header('Location: index.php');
    exit;
}

$lineman_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Check for customer_id from URL
$preselected_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Check for product_id from URL
$preselected_product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$preselected_product_quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1; // Optional quantity parameter

// Fetch assigned shops
$shops_sql = "SELECT id, customer_code, shop_name, customer_name, customer_contact 
              FROM customers 
              WHERE assigned_lineman_id = $lineman_id AND status = 'active'
              ORDER BY shop_name";
$shops_result = mysqli_query($conn, $shops_sql);

// Check if preselected customer belongs to this lineman
$preselected_customer = null;
if ($preselected_customer_id > 0) {
    $check_sql = "SELECT id, customer_code, shop_name, customer_name, customer_contact 
                  FROM customers 
                  WHERE id = $preselected_customer_id 
                  AND assigned_lineman_id = $lineman_id 
                  AND status = 'active'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $preselected_customer = mysqli_fetch_assoc($check_result);
    } else {
        $error_message = "Invalid customer or customer not assigned to you";
        $preselected_customer_id = 0; // Reset if invalid
    }
}

// Check if preselected product exists and is available
$preselected_product = null;
if ($preselected_product_id > 0) {
    $product_sql = "SELECT id, product_code, product_name, customer_price, quantity 
                    FROM products 
                    WHERE id = $preselected_product_id 
                    AND status = 'active' 
                    AND quantity > 0";
    $product_result = mysqli_query($conn, $product_sql);
    
    if ($product_result && mysqli_num_rows($product_result) > 0) {
        $preselected_product = mysqli_fetch_assoc($product_result);
        // Validate quantity
        if ($preselected_product_quantity > $preselected_product['quantity']) {
            $error_message .= " Only " . $preselected_product['quantity'] . " units available for " . $preselected_product['product_name'];
            $preselected_product_quantity = $preselected_product['quantity'];
        }
        if ($preselected_product_quantity < 1) {
            $preselected_product_quantity = 1;
        }
    } else {
        if (empty($error_message)) {
            $error_message = "Invalid product or product out of stock";
        } else {
            $error_message .= " | Invalid product or product out of stock";
        }
        $preselected_product_id = 0; // Reset if invalid
    }
}

// Fetch active products
$products_sql = "SELECT id, product_code, product_name, customer_price, quantity 
                 FROM products 
                 WHERE status = 'active' AND quantity > 0
                 ORDER BY product_name";
$products_result = mysqli_query($conn, $products_sql);

// Store products in array for JavaScript
$products_array = [];
while ($product = mysqli_fetch_assoc($products_result)) {
    $products_array[] = $product;
}

// Generate order number
$order_number = 'ORD' . date('Ymd') . rand(1000, 9999);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_order'])) {
    $customer_id = intval($_POST['customer_id']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $paid_amount = floatval($_POST['paid_amount']);
    $total_amount = floatval($_POST['total_amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Validate customer belongs to lineman
    $check_sql = "SELECT id FROM customers WHERE id = $customer_id AND assigned_lineman_id = $lineman_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        $error_message = "Invalid shop selection";
    } elseif ($total_amount <= 0) {
        $error_message = "Please add at least one product to the order";
    } else {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Create order
            $order_sql = "INSERT INTO orders (customer_id, order_number, order_date, total_amount, 
                          payment_method, payment_status, paid_amount, pending_amount, notes, 
                          created_by) 
                          VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)";
            
            $pending_amount = $total_amount - $paid_amount;
            
            $stmt = mysqli_prepare($conn, $order_sql);
            mysqli_stmt_bind_param($stmt, "isdssddsi", 
                $customer_id, 
                $order_number, 
                $total_amount, 
                $payment_method, 
                $payment_status, 
                $paid_amount, 
                $pending_amount, 
                $notes, 
                $lineman_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create order: " . mysqli_error($conn));
            }
            
            $order_id = mysqli_insert_id($conn);
            
            // Add order items
            $product_ids = $_POST['product_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $prices = $_POST['price'] ?? [];
            
            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = intval($product_ids[$i]);
                $quantity = intval($quantities[$i]);
                $price = floatval($prices[$i]);
                
                if ($product_id > 0 && $quantity > 0) {
                    // Add order item
                    $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price, total) 
                                VALUES (?, ?, ?, ?, ?)";
                    $item_stmt = mysqli_prepare($conn, $item_sql);
                    $total = $quantity * $price;
                    mysqli_stmt_bind_param($item_stmt, "iiidd", $order_id, $product_id, $quantity, $price, $total);
                    
                    if (!mysqli_stmt_execute($item_stmt)) {
                        throw new Exception("Failed to add order item: " . mysqli_error($conn));
                    }
                    
                    // Update product quantity
                    $update_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ii", $quantity, $product_id);
                    
                    if (!mysqli_stmt_execute($update_stmt)) {
                        throw new Exception("Failed to update product stock: " . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_close($item_stmt);
                    mysqli_stmt_close($update_stmt);
                }
            }
            
            // Record payment if paid
            if ($paid_amount > 0) {
                $payment_sql = "INSERT INTO transactions (customer_id, type, amount, payment_method, 
                              reference_no, notes, created_by, created_at) 
                              VALUES (?, 'payment', ?, ?, ?, ?, ?, NOW())";
                $payment_stmt = mysqli_prepare($conn, $payment_sql);
                $payment_notes = "Payment for order #$order_number";
                mysqli_stmt_bind_param($payment_stmt, "idsssi", $customer_id, $paid_amount, 
                                     $payment_method, $order_number, $payment_notes, $lineman_id);
                
                if (!mysqli_stmt_execute($payment_stmt)) {
                    throw new Exception("Failed to record payment: " . mysqli_error($conn));
                }
                mysqli_stmt_close($payment_stmt);
                
                // Update customer balance
                $balance_sql = "UPDATE customers SET current_balance = current_balance - ?, 
                               total_purchases = total_purchases + ?, 
                               last_purchase_date = CURDATE() 
                               WHERE id = ?";
                $balance_stmt = mysqli_prepare($conn, $balance_sql);
                mysqli_stmt_bind_param($balance_stmt, "ddi", $paid_amount, $total_amount, $customer_id);
                
                if (!mysqli_stmt_execute($balance_stmt)) {
                    throw new Exception("Failed to update customer balance: " . mysqli_error($conn));
                }
                mysqli_stmt_close($balance_stmt);
            } else {
                // Update customer balance for credit purchase
                $balance_sql = "UPDATE customers SET current_balance = current_balance + ?, 
                               total_purchases = total_purchases + ?, 
                               last_purchase_date = CURDATE() 
                               WHERE id = ?";
                $balance_stmt = mysqli_prepare($conn, $balance_sql);
                mysqli_stmt_bind_param($balance_stmt, "ddi", $total_amount, $total_amount, $customer_id);
                
                if (!mysqli_stmt_execute($balance_stmt)) {
                    throw new Exception("Failed to update customer balance: " . mysqli_error($conn));
                }
                mysqli_stmt_close($balance_stmt);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Generate new order number for next order
            $order_number = 'ORD' . date('Ymd') . rand(1000, 9999);
            
            $success_message = "Order created successfully! Order #$order_number";
            
            // Redirect with success message
            header("Location: quick-order.php?success=1&order_id=$order_id");
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="en">
<?php include('includes/head.php') ?>

<body data-sidebar="dark">

    <!-- Loader -->
    <?php include('includes/pre-loader.php') ?>

    <!-- Begin page -->
    <div id="layout-wrapper">

        <?php include('includes/topbar.php') ?>

        <!-- ========== Left Sidebar Start ========== -->
        <div class="vertical-menu">
            <div data-simplebar class="h-100">
                <!--- Sidemenu -->
                <?php 
                $current_page = 'quick-order';
                include('includes/sidebar.php');
                ?>
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

                    <!-- Messages -->
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        Order created successfully! 
                        <?php if (isset($_GET['order_id'])): ?>
                        <a href="view-invoice.php?id=<?php echo $_GET['order_id']; ?>" class="alert-link">
                            View Invoice
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title mb-4">Create New Order</h4>
                                    
                                    <?php if ($preselected_customer): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="mdi mdi-information-outline me-2"></i>
                                        Pre-selected customer: 
                                        <strong><?php echo htmlspecialchars($preselected_customer['shop_name']); ?></strong> 
                                        - <?php echo htmlspecialchars($preselected_customer['customer_name']); ?>
                                        (<?php echo $preselected_customer['customer_code']; ?>)
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($preselected_product): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="mdi mdi-information-outline me-2"></i>
                                        Pre-added product: 
                                        <strong><?php echo htmlspecialchars($preselected_product['product_name']); ?></strong> 
                                        - ₹<?php echo number_format($preselected_product['customer_price'], 2); ?>
                                        (Quantity: <?php echo $preselected_product_quantity; ?>)
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" id="orderForm" onsubmit="return validateOrder()">
                                        <div class="row">
                                            <!-- Left Column: Customer & Products -->
                                            <div class="col-lg-8">
                                                <!-- Customer Selection -->
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-store me-2"></i> Select Shop
                                                        </h5>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Search Shop</label>
                                                                    <div class="input-group">
                                                                        <input type="text" class="form-control" 
                                                                               id="shopSearch" 
                                                                               placeholder="Type to search shops...">
                                                                        <span class="input-group-text">
                                                                            <i class="mdi mdi-magnify"></i>
                                                                        </span>
                                                                    </div>
                                                                    <div class="form-text">
                                                                        Search by shop name, customer name, or phone number
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Select Shop *</label>
                                                            <select class="form-select" id="customer_id" name="customer_id" required>
                                                                <option value="">-- Select a shop --</option>
                                                                <?php 
                                                                // Reset pointer and loop through shops
                                                                mysqli_data_seek($shops_result, 0);
                                                                while ($shop = mysqli_fetch_assoc($shops_result)): 
                                                                    $selected = ($preselected_customer_id == $shop['id']) ? 'selected' : '';
                                                                ?>
                                                                <option value="<?php echo $shop['id']; ?>" 
                                                                        data-contact="<?php echo $shop['customer_contact']; ?>"
                                                                        data-code="<?php echo $shop['customer_code']; ?>"
                                                                        <?php echo $selected; ?>>
                                                                    <?php echo htmlspecialchars($shop['shop_name']); ?> 
                                                                    - <?php echo htmlspecialchars($shop['customer_name']); ?>
                                                                    (<?php echo $shop['customer_code']; ?>)
                                                                </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                            <div class="form-text">
                                                                Only your assigned active shops are shown
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Customer Contact</label>
                                                                    <input type="text" class="form-control" id="customer_contact" readonly
                                                                           value="<?php echo $preselected_customer ? htmlspecialchars($preselected_customer['customer_contact']) : ''; ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Customer Code</label>
                                                                    <input type="text" class="form-control" id="customer_code" readonly
                                                                           value="<?php echo $preselected_customer ? $preselected_customer['customer_code'] : ''; ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Product Selection -->
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-cube me-2"></i> Add Products
                                                        </h5>
                                                        <div class="row mb-3">
                                                            <div class="col-md-12">
                                                                <div class="input-group">
                                                                    <input type="text" class="form-control" 
                                                                           id="productSearch" 
                                                                           placeholder="Search products...">
                                                                    <span class="input-group-text">
                                                                        <i class="mdi mdi-magnify"></i>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Products List -->
                                                        <div class="table-responsive">
                                                            <table class="table table-hover mb-0">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th width="5%">#</th>
                                                                        <th width="40%">Product</th>
                                                                        <th width="15%">Price (₹)</th>
                                                                        <th width="15%">Available</th>
                                                                        <th width="15%">Quantity</th>
                                                                        <th width="10%">Add</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="productsTable">
                                                                    <?php 
                                                                    $product_counter = 1;
                                                                    foreach ($products_array as $product): 
                                                                        // Check if this is the pre-selected product
                                                                        $is_preselected = ($preselected_product && $product['id'] == $preselected_product_id);
                                                                    ?>
                                                                    <tr data-product-id="<?php echo $product['id']; ?>">
                                                                        <td><?php echo $product_counter++; ?></td>
                                                                        <td>
                                                                            <strong class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                                            <?php if ($is_preselected): ?>
                                                                            <span class="badge bg-info ms-2">Pre-selected</span>
                                                                            <?php endif; ?>
                                                                            <br>
                                                                            <small class="text-muted product-code"><?php echo $product['product_code']; ?></small>
                                                                        </td>
                                                                        <td class="product-price">₹<?php echo number_format($product['customer_price'], 2); ?></td>
                                                                        <td>
                                                                            <span class="badge bg-<?php echo $product['quantity'] > 10 ? 'success' : ($product['quantity'] > 0 ? 'warning' : 'danger'); ?>-subtle text-<?php echo $product['quantity'] > 10 ? 'success' : ($product['quantity'] > 0 ? 'warning' : 'danger'); ?> product-stock">
                                                                                <?php echo $product['quantity']; ?> units
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <input type="number" 
                                                                                   class="form-control form-control-sm product-quantity" 
                                                                                   min="1" 
                                                                                   max="<?php echo $product['quantity']; ?>"
                                                                                   value="<?php echo $is_preselected ? $preselected_product_quantity : 1; ?>"
                                                                                   data-max="<?php echo $product['quantity']; ?>"
                                                                                   id="quantity_<?php echo $product['id']; ?>">
                                                                        </td>
                                                                        <td>
                                                                            <button type="button" 
                                                                                    class="btn btn-sm btn-primary add-product"
                                                                                    data-id="<?php echo $product['id']; ?>"
                                                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                                                    data-code="<?php echo $product['product_code']; ?>"
                                                                                    data-price="<?php echo $product['customer_price']; ?>">
                                                                                <i class="mdi mdi-plus"></i>
                                                                            </button>
                                                                            <?php if ($is_preselected): ?>
                                                                            <div class="mt-1">
                                                                                <small class="text-info">Will be auto-added</small>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Right Column: Order Summary & Payment -->
                                            <div class="col-lg-4">
                                                <div class="card sticky-top" style="top: 20px;">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-cart me-2"></i> Order Summary
                                                        </h5>
                                                        
                                                        <!-- Selected Products -->
                                                        <div class="mb-4">
                                                            <h6 class="mb-2">Selected Products</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm mb-0" id="selectedProducts">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Product</th>
                                                                            <th>Qty</th>
                                                                            <th>Price</th>
                                                                            <th>Total</th>
                                                                            <th></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <!-- Products will be added here dynamically -->
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <div class="text-center mt-2" id="noProductsMessage">
                                                                <p class="text-muted">No products added yet</p>
                                                            </div>
                                                        </div>

                                                        <!-- Order Summary -->
                                                        <div class="mb-4">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Subtotal:</span>
                                                                <span class="fw-medium" id="subtotal">₹0.00</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Tax (Not applicable):</span>
                                                                <span class="fw-medium" id="tax">₹0.00</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <span class="text-muted">Discount:</span>
                                                                <span class="fw-medium" id="discount">₹0.00</span>
                                                            </div>
                                                            <hr>
                                                            <div class="d-flex justify-content-between mb-3">
                                                                <h5 class="mb-0">Total:</h5>
                                                                <h5 class="mb-0 text-primary" id="total">₹0.00</h5>
                                                            </div>
                                                            <input type="hidden" name="total_amount" id="total_amount" value="0">
                                                        </div>

                                                        <!-- Payment Information -->
                                                        <div class="mb-4">
                                                            <h6 class="mb-3">
                                                                <i class="mdi mdi-cash-multiple me-2"></i> Payment Information
                                                            </h6>
                                                            
                                                            <!-- Payment Method -->
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Method *</label>
                                                                <select class="form-select" name="payment_method" id="payment_method" required>
                                                                    <option value="cash">Cash</option>
                                                                    <option value="upi">UPI</option>
                                                                    <option value="card">Card</option>
                                                                    <option value="bank_transfer">Bank Transfer</option>
                                                                    <option value="wallet">Wallet</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Payment Status -->
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Status *</label>
                                                                <select class="form-select" name="payment_status" id="payment_status" required onchange="updatePaymentFields()">
                                                                    <option value="pending">Pending Payment</option>
                                                                    <option value="paid">Fully Paid</option>
                                                                    <option value="partial">Partial Payment</option>
                                                                    
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Amount Paid -->
                                                            <div class="mb-3" id="paidAmountContainer">
                                                                <label class="form-label">Amount Paid (₹) *</label>
                                                                <input type="number" class="form-control" 
                                                                       name="paid_amount" id="paid_amount" 
                                                                       min="0" step="0.01" value="0" required
                                                                       oninput="updatePendingAmount()">
                                                            </div>
                                                            
                                                            <!-- Pending Amount -->
                                                            <div class="mb-3" id="pendingAmountContainer">
                                                                <label class="form-label">Pending Amount (₹)</label>
                                                                <input type="text" class="form-control" 
                                                                       id="pending_amount" value="₹0.00" readonly>
                                                            </div>
                                                        </div>

                                                        <!-- Order Notes -->
                                                        <div class="mb-4">
                                                            <label class="form-label">Order Notes</label>
                                                            <textarea class="form-control" name="notes" rows="3" 
                                                                      placeholder="Any special instructions or notes..."></textarea>
                                                        </div>

                                                        <!-- Submit Buttons -->
                                                        <div class="d-grid gap-2">
                                                            <button type="submit" name="create_order" class="btn btn-primary btn-lg">
                                                                <i class="mdi mdi-check-circle-outline me-2"></i>
                                                                Create Order & Generate Invoice
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                                                <i class="mdi mdi-refresh me-2"></i>
                                                                Reset Form
                                                            </button>
                                                            <button type="button" class="btn btn-outline-success" onclick="saveDraft()">
                                                                <i class="mdi mdi-content-save me-2"></i>
                                                                Save as Draft
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
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

            <?php include('includes/footer.php') ?>
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    <!-- Right Sidebar -->
    <?php include('includes/rightbar.php') ?>
    <!-- /Right-bar -->

    <!-- JAVASCRIPT -->
    <?php include('includes/scripts.php') ?>

        <script>
        // Initialize variables
        let selectedProducts = [];
        let subtotal = 0;
        let taxRate = 0;
        let discount = 0;
        
        // Initialize with preselected customer (from PHP)
        let preselectedCustomerId = <?php echo $preselected_customer_id; ?>;
        
        // Initialize with preselected product (from PHP)
        let preselectedProductId = <?php echo $preselected_product_id; ?>;
        let preselectedProductQuantity = <?php echo $preselected_product_quantity; ?>;

        // Function to auto-add preselected product
        function autoAddPreselectedProduct() {
            if (preselectedProductId > 0) {
                const addButton = document.querySelector(`.add-product[data-id="${preselectedProductId}"]`);
                if (addButton) {
                    // Trigger click on the add button after a short delay
                    setTimeout(() => {
                        addButton.click();
                    }, 500);
                }
            }
        }

        // Shop search functionality
        document.getElementById('shopSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const options = document.querySelectorAll('#customer_id option');
            
            options.forEach(option => {
                if (option.value !== '') {
                    const text = option.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                }
            });
        });

        // Update customer details when shop is selected
        document.getElementById('customer_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const contact = selectedOption.getAttribute('data-contact') || '';
            const code = selectedOption.getAttribute('data-code') || '';
            
            document.getElementById('customer_contact').value = contact;
            document.getElementById('customer_code').value = code;
        });

        // Product search functionality
        document.getElementById('productSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tr');
            
            rows.forEach(row => {
                const productName = row.querySelector('.product-name').textContent.toLowerCase();
                const productCode = row.querySelector('.product-code').textContent.toLowerCase();
                
                if (productName.includes(searchTerm) || productCode.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Add product to order - Using event delegation
        document.getElementById('productsTable').addEventListener('click', function(e) {
            // Check if the clicked element is the add button or its icon
            let button = e.target;
            if (button.tagName === 'I') {
                button = button.parentElement;
            }
            
            if (button.classList.contains('add-product')) {
                const productId = button.getAttribute('data-id');
                const productName = button.getAttribute('data-name');
                const productCode = button.getAttribute('data-code');
                const price = parseFloat(button.getAttribute('data-price'));
                
                const row = button.closest('tr');
                const quantityInput = row.querySelector('.product-quantity');
                const maxQuantity = parseInt(quantityInput.getAttribute('data-max'));
                let quantity = parseInt(quantityInput.value);
                
                // Validate quantity
                if (quantity < 1 || isNaN(quantity)) {
                    alert('Please enter a valid quantity');
                    quantityInput.value = 1;
                    return;
                }
                
                if (quantity > maxQuantity) {
                    alert(`Only ${maxQuantity} units available in stock`);
                    quantityInput.value = maxQuantity;
                    quantity = maxQuantity;
                }
                
                // Check if product already exists
                const existingIndex = selectedProducts.findIndex(p => p.id === productId);
                
                if (existingIndex > -1) {
                    // Update existing product quantity
                    selectedProducts[existingIndex].quantity += quantity;
                } else {
                    // Add new product
                    selectedProducts.push({
                        id: productId,
                        name: productName,
                        code: productCode,
                        price: price,
                        quantity: quantity,
                        total: price * quantity
                    });
                }
                
                // Update stock in product list
                const newQuantity = maxQuantity - quantity;
                const stockBadge = row.querySelector('.product-stock');
                const quantityInputField = row.querySelector('.product-quantity');
                
                stockBadge.textContent = newQuantity + ' units';
                quantityInputField.setAttribute('data-max', newQuantity);
                quantityInputField.setAttribute('max', newQuantity);
                
                // Update badge color
                if (newQuantity > 10) {
                    stockBadge.className = 'badge bg-success-subtle text-success product-stock';
                } else if (newQuantity > 0) {
                    stockBadge.className = 'badge bg-warning-subtle text-warning product-stock';
                } else {
                    stockBadge.className = 'badge bg-danger-subtle text-danger product-stock';
                    button.disabled = true;
                }
                
                // Clear quantity input
                quantityInput.value = 1;
                
                // Update order summary
                updateOrderSummary();
            }
        });

        // Remove product from order - Using event delegation
        document.getElementById('selectedProducts').addEventListener('click', function(e) {
            let button = e.target;
            if (button.tagName === 'I') {
                button = button.parentElement;
            }
            
            if (button.classList.contains('remove-product')) {
                const productId = button.getAttribute('data-id');
                const quantity = parseInt(button.getAttribute('data-quantity'));
                
                // Remove from selected products
                const productIndex = selectedProducts.findIndex(p => p.id === productId);
                if (productIndex > -1) {
                    const removedProduct = selectedProducts[productIndex];
                    selectedProducts.splice(productIndex, 1);
                    
                    // Restore stock in product list
                    const productRow = document.querySelector(`tr[data-product-id="${productId}"]`);
                    if (productRow) {
                        const stockBadge = productRow.querySelector('.product-stock');
                        const currentStock = parseInt(stockBadge.textContent);
                        const newStock = currentStock + quantity;
                        
                        stockBadge.textContent = newStock + ' units';
                        
                        if (newStock > 10) {
                            stockBadge.className = 'badge bg-success-subtle text-success product-stock';
                        } else if (newStock > 0) {
                            stockBadge.className = 'badge bg-warning-subtle text-warning product-stock';
                        } else {
                            stockBadge.className = 'badge bg-danger-subtle text-danger product-stock';
                        }
                        
                        // Enable add button
                        const addButton = productRow.querySelector('.add-product');
                        addButton.disabled = false;
                        
                        // Update max quantity
                        const quantityInput = productRow.querySelector('.product-quantity');
                        quantityInput.setAttribute('data-max', newStock);
                        quantityInput.setAttribute('max', newStock);
                    }
                    
                    // Update order summary
                    updateOrderSummary();
                }
            }
        });

        // Update order summary
        function updateOrderSummary() {
            // Calculate subtotal
            subtotal = selectedProducts.reduce((sum, product) => sum + (product.price * product.quantity), 0);
            
            // Calculate tax
            const tax = subtotal * taxRate;
            
            // Calculate total
            const total = subtotal + tax - discount;
            
            // Update display
            document.getElementById('subtotal').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('tax').textContent = '₹' + tax.toFixed(2);
            document.getElementById('total').textContent = '₹' + total.toFixed(2);
            document.getElementById('total_amount').value = total.toFixed(2);
            
            // Update selected products table
            const tbody = document.getElementById('selectedProducts').querySelector('tbody');
            const noProductsMessage = document.getElementById('noProductsMessage');
            
            if (selectedProducts.length === 0) {
                tbody.innerHTML = '';
                noProductsMessage.style.display = 'block';
                return;
            }
            
            noProductsMessage.style.display = 'none';
            
            // Clear existing rows
            tbody.innerHTML = '';
            
            // Add product rows
            selectedProducts.forEach((product, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <input type="hidden" name="product_id[]" value="${product.id}">
                        <input type="hidden" name="price[]" value="${product.price}">
                        <input type="hidden" name="quantity[]" value="${product.quantity}">
                        <strong>${product.name}</strong><br>
                        <small class="text-muted">${product.code}</small>
                    </td>
                    <td>${product.quantity}</td>
                    <td>₹${product.price.toFixed(2)}</td>
                    <td>₹${(product.price * product.quantity).toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-product"
                                data-id="${product.id}" data-quantity="${product.quantity}">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Update pending amount based on payment status
            updatePendingAmount();
        }

        // Update payment fields based on payment status
        function updatePaymentFields() {
            const paymentStatus = document.getElementById('payment_status').value;
            const paidAmountInput = document.getElementById('paid_amount');
            const total = parseFloat(document.getElementById('total_amount').value) || 0;
            
            switch(paymentStatus) {
                case 'paid':
                    paidAmountInput.value = total.toFixed(2);
                    paidAmountInput.readOnly = true;
                    break;
                case 'partial':
                    paidAmountInput.value = '0';
                    paidAmountInput.readOnly = false;
                    break;
                case 'pending':
                    paidAmountInput.value = '0';
                    paidAmountInput.readOnly = true;
                    break;
            }
            
            updatePendingAmount();
        }

        // Update pending amount
        function updatePendingAmount() {
            const total = parseFloat(document.getElementById('total_amount').value) || 0;
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            const pendingAmount = total - paidAmount;
            
            document.getElementById('pending_amount').value = '₹' + pendingAmount.toFixed(2);
            
            // Validate paid amount
            if (paidAmount > total) {
                alert('Paid amount cannot exceed total amount');
                document.getElementById('paid_amount').value = total.toFixed(2);
                updatePendingAmount();
            }
        }

        // Validate order before submission
        function validateOrder() {
            const customerId = document.getElementById('customer_id').value;
            const paymentStatus = document.getElementById('payment_status').value;
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            const total = parseFloat(document.getElementById('total_amount').value) || 0;
            
            if (!customerId) {
                alert('Please select a shop');
                return false;
            }
            
            if (selectedProducts.length === 0) {
                alert('Please add at least one product to the order');
                return false;
            }
            
            if (total <= 0) {
                alert('Total amount must be greater than zero');
                return false;
            }
            
            if (paymentStatus === 'partial' && paidAmount <= 0) {
                alert('Please enter paid amount for partial payment');
                return false;
            }
            
            if (paymentStatus === 'partial' && paidAmount >= total) {
                alert('For partial payment, paid amount must be less than total amount');
                return false;
            }
            
            return true;
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                // Reset selected products array
                selectedProducts = [];
                
                // Reset form
                document.getElementById('orderForm').reset();
                document.getElementById('selectedProducts').querySelector('tbody').innerHTML = '';
                document.getElementById('noProductsMessage').style.display = 'block';
                
                // If we have a preselected customer, keep it selected
                if (preselectedCustomerId > 0) {
                    document.getElementById('customer_id').value = preselectedCustomerId;
                    // Trigger change to update customer details
                    const event = new Event('change');
                    document.getElementById('customer_id').dispatchEvent(event);
                } else {
                    document.getElementById('customer_contact').value = '';
                    document.getElementById('customer_code').value = '';
                }
                
                // Reset summary
                document.getElementById('subtotal').textContent = '₹0.00';
                document.getElementById('tax').textContent = '₹0.00';
                document.getElementById('total').textContent = '₹0.00';
                document.getElementById('total_amount').value = '0';
                document.getElementById('pending_amount').value = '₹0.00';
                
                // Reset payment fields
                document.getElementById('paid_amount').readOnly = false;
                updatePaymentFields();
                
                // Clear search
                document.getElementById('shopSearch').value = '';
                document.getElementById('productSearch').value = '';
                
                // Show all products
                document.querySelectorAll('#productsTable tr').forEach(row => {
                    row.style.display = '';
                });
                
                // Show all shop options
                document.querySelectorAll('#customer_id option').forEach(option => {
                    option.style.display = '';
                });
                
                // Reset all product quantities to original
                <?php 
                // Use a separate counter to avoid variable redeclaration issues
                $js_counter = 0;
                foreach ($products_array as $product): 
                ?>
                const productRow<?php echo $js_counter; ?> = document.querySelector(`tr[data-product-id="<?php echo $product['id']; ?>"]`);
                if (productRow<?php echo $js_counter; ?>) {
                    const stockBadge<?php echo $js_counter; ?> = productRow<?php echo $js_counter; ?>.querySelector('.product-stock');
                    const quantityInput<?php echo $js_counter; ?> = productRow<?php echo $js_counter; ?>.querySelector('.product-quantity');
                    const addButton<?php echo $js_counter; ?> = productRow<?php echo $js_counter; ?>.querySelector('.add-product');
                    
                    stockBadge<?php echo $js_counter; ?>.textContent = '<?php echo $product['quantity']; ?> units';
                    stockBadge<?php echo $js_counter; ?>.className = 'badge bg-<?php echo $product['quantity'] > 10 ? 'success' : ($product['quantity'] > 0 ? 'warning' : 'danger'); ?>-subtle text-<?php echo $product['quantity'] > 10 ? 'success' : ($product['quantity'] > 0 ? 'warning' : 'danger'); ?> product-stock';
                    quantityInput<?php echo $js_counter; ?>.setAttribute('data-max', '<?php echo $product['quantity']; ?>');
                    quantityInput<?php echo $js_counter; ?>.setAttribute('max', '<?php echo $product['quantity']; ?>');
                    quantityInput<?php echo $js_counter; ?>.value = 1;
                    addButton<?php echo $js_counter; ?>.disabled = false;
                }
                <?php 
                $js_counter++;
                endforeach; 
                ?>
            }
        }

        // Save as draft
        function saveDraft() {
            const customerId = document.getElementById('customer_id').value;
            
            if (!customerId || selectedProducts.length === 0) {
                alert('Please select a shop and add products before saving as draft');
                return;
            }
            
            // Create draft object
            const draft = {
                customer_id: customerId,
                customer_contact: document.getElementById('customer_contact').value,
                customer_code: document.getElementById('customer_code').value,
                products: selectedProducts,
                payment_method: document.getElementById('payment_method').value,
                payment_status: document.getElementById('payment_status').value,
                paid_amount: document.getElementById('paid_amount').value,
                notes: document.querySelector('textarea[name="notes"]').value,
                subtotal: subtotal,
                total: parseFloat(document.getElementById('total_amount').value)
            };
            
            // Save to localStorage
            localStorage.setItem('order_draft', JSON.stringify(draft));
            
            alert('Order saved as draft. You can continue later.');
        }

        // Load draft on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedDraft = localStorage.getItem('order_draft');
            if (savedDraft) {
                if (confirm('You have a saved draft. Do you want to load it?')) {
                    try {
                        const draft = JSON.parse(savedDraft);
                        
                        // Load customer
                        document.getElementById('customer_id').value = draft.customer_id;
                        document.getElementById('customer_contact').value = draft.customer_contact;
                        document.getElementById('customer_code').value = draft.customer_code;
                        
                        // Trigger change event
                        document.getElementById('customer_id').dispatchEvent(new Event('change'));
                        
                        // Load products
                        selectedProducts = draft.products;
                        updateOrderSummary();
                        
                        // Load payment info
                        document.getElementById('payment_method').value = draft.payment_method;
                        document.getElementById('payment_status').value = draft.payment_status;
                        document.getElementById('paid_amount').value = draft.paid_amount;
                        document.querySelector('textarea[name="notes"]').value = draft.notes;
                        
                        // Update payment fields
                        updatePaymentFields();
                        
                        // Clear draft
                        localStorage.removeItem('order_draft');
                        
                        alert('Draft loaded successfully!');
                    } catch (e) {
                        console.error('Error loading draft:', e);
                        alert('Error loading draft. Starting fresh.');
                        localStorage.removeItem('order_draft');
                    }
                }
            }
            
            // Initialize payment fields
            updatePaymentFields();
            
            // Auto-add preselected product after page is fully loaded
            if (preselectedProductId > 0) {
                // Wait for the product table to be fully rendered
                setTimeout(autoAddPreselectedProduct, 1000);
            }
        });

        // Auto-calculate product totals when quantity changes
        document.getElementById('productsTable').addEventListener('input', function(e) {
            if (e.target && e.target.classList.contains('product-quantity')) {
                const input = e.target;
                const maxQuantity = parseInt(input.getAttribute('data-max'));
                const quantity = parseInt(input.value);
                
                if (quantity > maxQuantity) {
                    alert(`Only ${maxQuantity} units available`);
                    input.value = maxQuantity;
                }
                
                if (quantity < 1) {
                    input.value = 1;
                }
            }
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}