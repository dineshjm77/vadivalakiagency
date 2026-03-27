<?php
session_start();
include('config/config.php');

// Ensure only admin can access this page
if ($_SESSION['user_role'] != 'admin') {
    header('Location: index.php');
    exit;
}

$admin_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_message = '';
$success_message = '';

if ($order_id <= 0) {
    header('Location: orders-list.php');
    exit;
}

// Fetch order details
$order_sql = "SELECT o.*, c.customer_name, c.shop_name, c.customer_contact, 
                     c.shop_location, c.customer_code, c.current_balance,
                     c.assigned_lineman_id, l.full_name as lineman_name
              FROM orders o 
              JOIN customers c ON o.customer_id = c.id
              LEFT JOIN linemen l ON c.assigned_lineman_id = l.id
              WHERE o.id = $order_id";
$order_result = mysqli_query($conn, $order_sql);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    die("Order not found.");
}

$order = mysqli_fetch_assoc($order_result);

// Fetch order items
$items_sql = "SELECT oi.*, p.product_name, p.product_code, p.quantity as available_quantity 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = $order_id";
$items_result = mysqli_query($conn, $items_sql);

// Store original items for comparison
$original_items = [];
$original_total = $order['total_amount'];
$original_paid = $order['paid_amount'];
$original_pending = $order['pending_amount'];

while ($item = mysqli_fetch_assoc($items_result)) {
    $original_items[] = $item;
}

// Fetch all customers for selection
$customers_sql = "SELECT c.*, l.full_name as lineman_name 
                  FROM customers c 
                  LEFT JOIN linemen l ON c.assigned_lineman_id = l.id 
                  WHERE c.status = 'active'
                  ORDER BY c.customer_name";
$customers_result = mysqli_query($conn, $customers_sql);

// Fetch active products
$products_sql = "SELECT id, product_code, product_name, customer_price, quantity 
                 FROM products 
                 WHERE status = 'active'
                 ORDER BY product_name";
$products_result = mysqli_query($conn, $products_sql);

// Store products in array for JavaScript
$products_array = [];
while ($product = mysqli_fetch_assoc($products_result)) {
    $products_array[] = $product;
}

// Reset pointer for later use
mysqli_data_seek($products_result, 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_order'])) {
    $customer_id = intval($_POST['customer_id']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $paid_amount = floatval($_POST['paid_amount']);
    $total_amount = floatval($_POST['total_amount']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    // Validate customer
    $check_sql = "SELECT id FROM customers WHERE id = $customer_id AND status = 'active'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        $error_message = "Invalid customer selection";
    } elseif ($total_amount <= 0) {
        $error_message = "Order must have at least one product";
    } elseif ($paid_amount > $total_amount) {
        $error_message = "Paid amount cannot exceed total amount";
    } else {
        // Calculate pending amount
        $pending_amount = $total_amount - $paid_amount;
        
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Calculate differences for customer balance adjustment
            $total_difference = $total_amount - $original_total;
            $paid_difference = $paid_amount - $original_paid;
            
            // Update order
            $update_sql = "UPDATE orders 
                          SET customer_id = ?, 
                              total_amount = ?, 
                              payment_method = ?, 
                              payment_status = ?, 
                              paid_amount = ?, 
                              pending_amount = ?, 
                              notes = ?,
                              updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?";
            
            $stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt, "idssdddi", 
                $customer_id, 
                $total_amount, 
                $payment_method, 
                $payment_status, 
                $paid_amount, 
                $pending_amount, 
                $notes, 
                $order_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update order: " . mysqli_error($conn));
            }
            
            // Get submitted items
            $submitted_product_ids = $_POST['product_id'] ?? [];
            $submitted_quantities = $_POST['quantity'] ?? [];
            $submitted_prices = $_POST['price'] ?? [];
            
            // Create arrays for easy comparison
            $submitted_items = [];
            for ($i = 0; $i < count($submitted_product_ids); $i++) {
                $product_id = intval($submitted_product_ids[$i]);
                $quantity = intval($submitted_quantities[$i]);
                $price = floatval($submitted_prices[$i]);
                
                if ($product_id > 0 && $quantity > 0) {
                    $submitted_items[$product_id] = [
                        'quantity' => $quantity,
                        'price' => $price,
                        'total' => $quantity * $price
                    ];
                }
            }
            
            // Process product stock adjustments
            foreach ($original_items as $original_item) {
                $product_id = $original_item['product_id'];
                $original_qty = $original_item['quantity'];
                
                if (isset($submitted_items[$product_id])) {
                    // Item exists in both - check quantity difference
                    $new_qty = $submitted_items[$product_id]['quantity'];
                    $qty_difference = $new_qty - $original_qty;
                    
                    if ($qty_difference != 0) {
                        // Adjust product stock
                        if ($qty_difference > 0) {
                            // More quantity ordered - reduce stock
                            $update_stock_sql = "UPDATE products 
                                               SET quantity = quantity - ? 
                                               WHERE id = ? AND quantity >= ?";
                            $stmt = mysqli_prepare($conn, $update_stock_sql);
                            mysqli_stmt_bind_param($stmt, "iii", $qty_difference, $product_id, $qty_difference);
                            
                            if (!mysqli_stmt_execute($stmt)) {
                                throw new Exception("Failed to update product stock for product ID: $product_id");
                            }
                        } else {
                            // Less quantity ordered - restore stock
                            $restore_qty = abs($qty_difference);
                            $update_stock_sql = "UPDATE products 
                                               SET quantity = quantity + ? 
                                               WHERE id = ?";
                            $stmt = mysqli_prepare($conn, $update_stock_sql);
                            mysqli_stmt_bind_param($stmt, "ii", $restore_qty, $product_id);
                            
                            if (!mysqli_stmt_execute($stmt)) {
                                throw new Exception("Failed to restore product stock for product ID: $product_id");
                            }
                        }
                        
                        // Update order item
                        $update_item_sql = "UPDATE order_items 
                                           SET quantity = ?, price = ?, total = ? 
                                           WHERE order_id = ? AND product_id = ?";
                        $stmt = mysqli_prepare($conn, $update_item_sql);
                        $new_total = $new_qty * $submitted_items[$product_id]['price'];
                        mysqli_stmt_bind_param($stmt, "iddii", $new_qty, $submitted_items[$product_id]['price'], 
                                             $new_total, $order_id, $product_id);
                        
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Failed to update order item for product ID: $product_id");
                        }
                    }
                    
                    // Remove from submitted items array
                    unset($submitted_items[$product_id]);
                    
                } else {
                    // Item removed from order - restore stock
                    $update_stock_sql = "UPDATE products 
                                       SET quantity = quantity + ? 
                                       WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_stock_sql);
                    mysqli_stmt_bind_param($stmt, "ii", $original_qty, $product_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to restore removed product stock for product ID: $product_id");
                    }
                    
                    // Delete order item
                    $delete_item_sql = "DELETE FROM order_items 
                                       WHERE order_id = ? AND product_id = ?";
                    $stmt = mysqli_prepare($conn, $delete_item_sql);
                    mysqli_stmt_bind_param($stmt, "ii", $order_id, $product_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to delete removed order item for product ID: $product_id");
                    }
                }
            }
            
            // Add new items
            foreach ($submitted_items as $product_id => $item_data) {
                // Check product availability
                $check_product_sql = "SELECT quantity FROM products WHERE id = ?";
                $stmt = mysqli_prepare($conn, $check_product_sql);
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                $product_result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($product_result);
                
                if (!$product || $product['quantity'] < $item_data['quantity']) {
                    throw new Exception("Insufficient stock for product ID: $product_id");
                }
                
                // Reduce product stock
                $update_stock_sql = "UPDATE products 
                                   SET quantity = quantity - ? 
                                   WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_stock_sql);
                mysqli_stmt_bind_param($stmt, "ii", $item_data['quantity'], $product_id);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to update stock for new product ID: $product_id");
                }
                
                // Add new order item
                $add_item_sql = "INSERT INTO order_items (order_id, product_id, quantity, price, total) 
                                VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $add_item_sql);
                mysqli_stmt_bind_param($stmt, "iiidd", $order_id, $product_id, $item_data['quantity'], 
                                     $item_data['price'], $item_data['total']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to add new order item for product ID: $product_id");
                }
            }
            
            // Update customer balance based on differences
            if ($total_difference != 0 || $paid_difference != 0) {
                // For total amount difference
                if ($total_difference != 0) {
                    $balance_sql = "UPDATE customers 
                                   SET current_balance = current_balance + ?, 
                                       total_purchases = total_purchases + ? 
                                   WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $balance_sql);
                    mysqli_stmt_bind_param($stmt, "ddi", $total_difference, $total_difference, $customer_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to update customer balance for total difference");
                    }
                }
                
                // For paid amount difference
                if ($paid_difference != 0) {
                    // Record transaction for payment difference
                    $transaction_sql = "INSERT INTO transactions (customer_id, order_id, type, amount, 
                                      payment_method, notes, created_by, created_at) 
                                      VALUES (?, ?, 'payment', ?, ?, ?, ?, NOW())";
                    $stmt = mysqli_prepare($conn, $transaction_sql);
                    $txn_notes = $paid_difference > 0 ? "Additional payment for order #{$order['order_number']}" 
                                                     : "Payment adjustment for order #{$order['order_number']}";
                    mysqli_stmt_bind_param($stmt, "iidssi", $customer_id, $order_id, $paid_difference, 
                                         $payment_method, $txn_notes, $admin_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to record transaction");
                    }
                    
                    // Adjust customer balance for payment difference
                    $balance_sql = "UPDATE customers 
                                   SET current_balance = current_balance - ? 
                                   WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $balance_sql);
                    mysqli_stmt_bind_param($stmt, "di", $paid_difference, $customer_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Failed to update customer balance for payment difference");
                    }
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Order updated successfully!";
            
            // Refresh order data
            $order_result = mysqli_query($conn, $order_sql);
            $order = mysqli_fetch_assoc($order_result);
            
            // Refresh order items
            $items_result = mysqli_query($conn, $items_sql);
            $original_items = [];
            while ($item = mysqli_fetch_assoc($items_result)) {
                $original_items[] = $item;
            }
            
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
                $current_page = 'orders';
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

                    <!-- Messages -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="mdi mdi-check-all me-2"></i>
                        <?php echo $success_message; ?>
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
                                    <h4 class="card-title mb-4">Edit Order Details</h4>
                                    
                                    <form method="POST" id="orderForm" onsubmit="return validateOrder()">
                                        <div class="row">
                                            <!-- Left Column: Customer & Products -->
                                            <div class="col-lg-8">
                                                <!-- Order Information -->
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-information-outline me-2"></i> Order Information
                                                        </h5>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Order Date</label>
                                                                    <input type="text" class="form-control" 
                                                                           value="<?php echo date('d M Y', strtotime($order['order_date'])); ?>" 
                                                                           readonly>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Order Number</label>
                                                                    <input type="text" class="form-control" 
                                                                           value="<?php echo $order['order_number']; ?>" 
                                                                           readonly>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Customer Selection -->
                                                <div class="card mb-4">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-store me-2"></i> Customer Information
                                                        </h5>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Select Customer *</label>
                                                            <select class="form-select" id="customer_id" name="customer_id" required>
                                                                <option value="">-- Select a customer --</option>
                                                                <?php 
                                                                mysqli_data_seek($customers_result, 0);
                                                                while ($customer = mysqli_fetch_assoc($customers_result)): 
                                                                    $selected = ($order['customer_id'] == $customer['id']) ? 'selected' : '';
                                                                    $lineman_info = $customer['lineman_name'] ? " | Lineman: " . $customer['lineman_name'] : "";
                                                                ?>
                                                                <option value="<?php echo $customer['id']; ?>" 
                                                                        data-contact="<?php echo $customer['customer_contact']; ?>"
                                                                        data-code="<?php echo $customer['customer_code']; ?>"
                                                                        data-address="<?php echo htmlspecialchars($customer['shop_location']); ?>"
                                                                        data-balance="<?php echo $customer['current_balance']; ?>"
                                                                        data-lineman="<?php echo $customer['lineman_name']; ?>"
                                                                        <?php echo $selected; ?>>
                                                                    <?php echo htmlspecialchars($customer['shop_name']); ?> 
                                                                    - <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                                    (<?php echo $customer['customer_code']; ?>)
                                                                    <?php echo $lineman_info; ?>
                                                                </option>
                                                                <?php endwhile; ?>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Customer Contact</label>
                                                                    <input type="text" class="form-control" id="customer_contact" readonly
                                                                           value="<?php echo htmlspecialchars($order['customer_contact']); ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Customer Code</label>
                                                                    <input type="text" class="form-control" id="customer_code" readonly
                                                                           value="<?php echo $order['customer_code']; ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Balance</label>
                                                            <input type="text" class="form-control" id="customer_balance" readonly
                                                                   value="₹<?php echo number_format($order['current_balance'], 2); ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Shop Location</label>
                                                            <textarea class="form-control" id="shop_location" rows="2" readonly><?php echo htmlspecialchars($order['shop_location']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Assigned Lineman</label>
                                                            <input type="text" class="form-control" id="assigned_lineman" readonly
                                                                   value="<?php echo $order['lineman_name']; ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Product Selection -->
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-3">
                                                            <i class="mdi mdi-cube me-2"></i> Edit Products
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
                                                                    mysqli_data_seek($products_result, 0);
                                                                    while ($product = mysqli_fetch_assoc($products_result)): 
                                                                        // Check if this product is already in the order
                                                                        $is_in_order = false;
                                                                        $order_quantity = 0;
                                                                        foreach ($original_items as $order_item) {
                                                                            if ($order_item['product_id'] == $product['id']) {
                                                                                $is_in_order = true;
                                                                                $order_quantity = $order_item['quantity'];
                                                                                break;
                                                                            }
                                                                        }
                                                                        
                                                                        // Calculate available stock considering current order
                                                                        $available_for_new = $product['quantity'];
                                                                        if ($is_in_order) {
                                                                            $available_for_new += $order_quantity; // Add back the ordered quantity
                                                                        }
                                                                    ?>
                                                                    <tr data-product-id="<?php echo $product['id']; ?>">
                                                                        <td><?php echo $product_counter++; ?></td>
                                                                        <td>
                                                                            <strong class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                                            <?php if ($is_in_order): ?>
                                                                            <span class="badge bg-info ms-2">Already in order</span>
                                                                            <?php endif; ?>
                                                                            <br>
                                                                            <small class="text-muted product-code"><?php echo $product['product_code']; ?></small>
                                                                        </td>
                                                                        <td class="product-price">₹<?php echo number_format($product['customer_price'], 2); ?></td>
                                                                        <td>
                                                                            <span class="badge bg-<?php echo $available_for_new > 10 ? 'success' : ($available_for_new > 0 ? 'warning' : 'danger'); ?>-subtle text-<?php echo $available_for_new > 10 ? 'success' : ($available_for_new > 0 ? 'warning' : 'danger'); ?> product-stock">
                                                                                <?php echo $available_for_new; ?> units
                                                                            </span>
                                                                            <?php if ($is_in_order): ?>
                                                                            <br><small class="text-muted">(<?php echo $order_quantity; ?> in this order)</small>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <input type="number" 
                                                                                   class="form-control form-control-sm product-quantity" 
                                                                                   min="1" 
                                                                                   max="<?php echo $available_for_new; ?>"
                                                                                   value="<?php echo $is_in_order ? $order_quantity : 1; ?>"
                                                                                   data-max="<?php echo $available_for_new; ?>"
                                                                                   data-original-max="<?php echo $product['quantity']; ?>"
                                                                                   id="quantity_<?php echo $product['id']; ?>">
                                                                        </td>
                                                                        <td>
                                                                            <button type="button" 
                                                                                    class="btn btn-sm btn-primary add-product"
                                                                                    data-id="<?php echo $product['id']; ?>"
                                                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                                                    data-code="<?php echo $product['product_code']; ?>"
                                                                                    data-price="<?php echo $product['customer_price']; ?>"
                                                                                    data-in-order="<?php echo $is_in_order ? 'true' : 'false'; ?>"
                                                                                    data-order-quantity="<?php echo $order_quantity; ?>">
                                                                                <?php if ($is_in_order): ?>
                                                                                <i class="mdi mdi-update"></i>
                                                                                <?php else: ?>
                                                                                <i class="mdi mdi-plus"></i>
                                                                                <?php endif; ?>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                    <?php endwhile; ?>
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
                                                            <input type="hidden" name="total_amount" id="total_amount" value="<?php echo $order['total_amount']; ?>">
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
                                                                    <option value="cash" <?php echo $order['payment_method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                                                                    <option value="upi" <?php echo $order['payment_method'] == 'upi' ? 'selected' : ''; ?>>UPI</option>
                                                                    <option value="card" <?php echo $order['payment_method'] == 'card' ? 'selected' : ''; ?>>Card</option>
                                                                    <option value="bank_transfer" <?php echo $order['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                                                    <option value="wallet" <?php echo $order['payment_method'] == 'wallet' ? 'selected' : ''; ?>>Wallet</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Payment Status -->
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Status *</label>
                                                                <select class="form-select" name="payment_status" id="payment_status" required onchange="updatePaymentFields()">
                                                                    <option value="pending" <?php echo $order['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending Payment</option>
                                                                    <option value="paid" <?php echo $order['payment_status'] == 'paid' ? 'selected' : ''; ?>>Fully Paid</option>
                                                                    <option value="partial" <?php echo $order['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial Payment</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Amount Paid -->
                                                            <div class="mb-3" id="paidAmountContainer">
                                                                <label class="form-label">Amount Paid (₹) *</label>
                                                                <input type="number" class="form-control" 
                                                                       name="paid_amount" id="paid_amount" 
                                                                       min="0" step="0.01" value="<?php echo $order['paid_amount']; ?>" required
                                                                       oninput="updatePendingAmount()">
                                                            </div>
                                                            
                                                            <!-- Pending Amount -->
                                                            <div class="mb-3" id="pendingAmountContainer">
                                                                <label class="form-label">Pending Amount (₹)</label>
                                                                <input type="text" class="form-control" 
                                                                       id="pending_amount" value="₹<?php echo number_format($order['pending_amount'], 2); ?>" readonly>
                                                            </div>
                                                        </div>

                                                        <!-- Order Notes -->
                                                        <div class="mb-4">
                                                            <label class="form-label">Order Notes</label>
                                                            <textarea class="form-control" name="notes" rows="3" 
                                                                      placeholder="Any special instructions or notes..."><?php echo htmlspecialchars($order['notes']); ?></textarea>
                                                        </div>

                                                        <!-- Submit Buttons -->
                                                        <div class="d-grid gap-2">
                                                            <button type="submit" name="update_order" class="btn btn-primary btn-lg">
                                                                <i class="mdi mdi-check-circle-outline me-2"></i>
                                                                Update Order
                                                            </button>
                                                            <a href="view-invoice.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                                                                <i class="mdi mdi-arrow-left me-2"></i>
                                                                Back to Invoice
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger" onclick="resetForm()">
                                                                <i class="mdi mdi-refresh me-2"></i>
                                                                Reset Changes
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
        
        // Initialize with current order items from PHP
        <?php foreach ($original_items as $item): ?>
        selectedProducts.push({
            id: <?php echo $item['product_id']; ?>,
            name: "<?php echo addslashes($item['product_name']); ?>",
            code: "<?php echo $item['product_code']; ?>",
            price: <?php echo $item['price']; ?>,
            quantity: <?php echo $item['quantity']; ?>,
            total: <?php echo $item['total']; ?>,
            inOrder: true
        });
        <?php endforeach; ?>

        // Function to initialize the form
        function initializeForm() {
            // Update customer details based on selected customer
            updateCustomerDetails();
            
            // Update order summary
            updateOrderSummary();
            
            // Update payment fields
            updatePaymentFields();
            
            // Initialize all product quantities with their order values
            <?php foreach ($original_items as $item): ?>
            const quantityInput = document.querySelector(`#quantity_<?php echo $item['product_id']; ?>`);
            if (quantityInput) {
                quantityInput.value = <?php echo $item['quantity']; ?>;
            }
            <?php endforeach; ?>
        }

        // Shop search functionality
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

        // Update customer details when customer is selected
        document.getElementById('customer_id').addEventListener('change', updateCustomerDetails);

        function updateCustomerDetails() {
            const selectedOption = document.getElementById('customer_id').options[document.getElementById('customer_id').selectedIndex];
            const contact = selectedOption.getAttribute('data-contact') || '';
            const code = selectedOption.getAttribute('data-code') || '';
            const address = selectedOption.getAttribute('data-address') || '';
            const balance = selectedOption.getAttribute('data-balance') || '0';
            const lineman = selectedOption.getAttribute('data-lineman') || '';
            
            document.getElementById('customer_contact').value = contact;
            document.getElementById('customer_code').value = code;
            document.getElementById('shop_location').value = address;
            document.getElementById('customer_balance').value = '₹' + parseFloat(balance).toFixed(2);
            document.getElementById('assigned_lineman').value = lineman;
        }

        // Add/Update product to order - Using event delegation
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
                const isInOrder = button.getAttribute('data-in-order') === 'true';
                const orderQuantity = parseInt(button.getAttribute('data-order-quantity')) || 0;
                
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
                    alert(`Only ${maxQuantity} units available`);
                    quantityInput.value = maxQuantity;
                    quantity = maxQuantity;
                }
                
                // Check if product already exists in selected products
                const existingIndex = selectedProducts.findIndex(p => p.id === productId);
                
                if (existingIndex > -1) {
                    // Update existing product quantity
                    selectedProducts[existingIndex].quantity = quantity;
                    selectedProducts[existingIndex].total = price * quantity;
                } else {
                    // Add new product
                    selectedProducts.push({
                        id: productId,
                        name: productName,
                        code: productCode,
                        price: price,
                        quantity: quantity,
                        total: price * quantity,
                        inOrder: isInOrder
                    });
                }
                
                // Update button state
                if (isInOrder && quantity === orderQuantity) {
                    // Quantity unchanged - show update icon
                    button.innerHTML = '<i class="mdi mdi-update"></i>';
                } else {
                    // Quantity changed or new product - show check icon
                    button.innerHTML = '<i class="mdi mdi-check"></i>';
                    button.classList.add('btn-success');
                    button.classList.remove('btn-primary');
                }
                
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
                
                // Find the product
                const productIndex = selectedProducts.findIndex(p => p.id === productId);
                if (productIndex > -1) {
                    const removedProduct = selectedProducts[productIndex];
                    
                    // Reset the product row button
                    const productRow = document.querySelector(`tr[data-product-id="${productId}"]`);
                    if (productRow) {
                        const addButton = productRow.querySelector('.add-product');
                        const isInOrder = addButton.getAttribute('data-in-order') === 'true';
                        
                        if (isInOrder) {
                            // Was in order originally - show update icon
                            addButton.innerHTML = '<i class="mdi mdi-update"></i>';
                            addButton.classList.remove('btn-success');
                            addButton.classList.add('btn-primary');
                        } else {
                            // Was not in order originally - show plus icon
                            addButton.innerHTML = '<i class="mdi mdi-plus"></i>';
                            addButton.classList.remove('btn-success');
                            addButton.classList.add('btn-primary');
                        }
                    }
                    
                    // Remove from selected products
                    selectedProducts.splice(productIndex, 1);
                    
                    // Update order summary
                    updateOrderSummary();
                }
            }
        });

        // Update order summary
        function updateOrderSummary() {
            // Calculate subtotal
            subtotal = selectedProducts.reduce((sum, product) => sum + product.total, 0);
            
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
                        ${product.inOrder ? '<br><small class="text-info">(Originally in order)</small>' : ''}
                    </td>
                    <td>${product.quantity}</td>
                    <td>₹${product.price.toFixed(2)}</td>
                    <td>₹${product.total.toFixed(2)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-danger remove-product"
                                data-id="${product.id}">
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
                alert('Please select a customer');
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

        // Reset form to original state
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes? Any modifications will be lost.')) {
                // Reload the page to reset everything
                window.location.reload();
            }
        }

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
                
                // Update the button for this product if it's in selected products
                const productId = input.closest('tr').getAttribute('data-product-id');
                const existingIndex = selectedProducts.findIndex(p => p.id == productId);
                
                if (existingIndex > -1) {
                    const productRow = input.closest('tr');
                    const addButton = productRow.querySelector('.add-product');
                    const isInOrder = addButton.getAttribute('data-in-order') === 'true';
                    const orderQuantity = parseInt(addButton.getAttribute('data-order-quantity')) || 0;
                    
                    if (isInOrder && quantity === orderQuantity) {
                        // Back to original quantity - show update icon
                        addButton.innerHTML = '<i class="mdi mdi-update"></i>';
                        addButton.classList.remove('btn-success');
                        addButton.classList.add('btn-primary');
                    } else {
                        // Different quantity - show check icon
                        addButton.innerHTML = '<i class="mdi mdi-check"></i>';
                        addButton.classList.add('btn-success');
                        addButton.classList.remove('btn-primary');
                    }
                }
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeForm();
        });
    </script>

</body>
</html>

<?php
// Close database connections
if (isset($conn)) {
    mysqli_close($conn);
}