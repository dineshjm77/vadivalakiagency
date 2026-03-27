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



                <?php
                // Database connection
                include('config/config.php');
                
                // Initialize variables
                $order = null;
                $customer = null;
                $order_items = [];
                $lineman = null;
                $message = '';
                $message_type = '';
                $customers = [];
                $products = [];
                
                // Get the ID from URL
                $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                
                if ($id <= 0) {
                    echo '<div class="alert alert-danger">Invalid Order ID</div>';
                    exit;
                }
                
                // Fetch order data
                $sql = "SELECT o.*, 
                       c.*,
                       l.full_name as lineman_name,
                       l.employee_id as lineman_id,
                       COUNT(oi.id) as total_items
                       FROM orders o
                       LEFT JOIN customers c ON o.customer_id = c.id
                       LEFT JOIN linemen l ON o.created_by = l.id
                       LEFT JOIN order_items oi ON o.id = oi.order_id
                       WHERE o.id = ?
                       GROUP BY o.id";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $order = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);
                    
                    // Fetch order items
                    $items_sql = "SELECT oi.*, 
                                p.product_name, 
                                p.product_code,
                                p.quantity as available_stock,
                                p.stock_price,
                                p.customer_price
                                FROM order_items oi
                                LEFT JOIN products p ON oi.product_id = p.id
                                WHERE oi.order_id = ?";
                    $items_stmt = mysqli_prepare($conn, $items_sql);
                    mysqli_stmt_bind_param($items_stmt, "i", $id);
                    mysqli_stmt_execute($items_stmt);
                    $items_result = mysqli_stmt_get_result($items_stmt);
                    
                    while ($item = mysqli_fetch_assoc($items_result)) {
                        $order_items[] = $item;
                    }
                    mysqli_stmt_close($items_stmt);
                    
                } else {
                    echo '<div class="alert alert-danger">Order not found</div>';
                    exit;
                }
                
                // Fetch all customers for dropdown
                $customers_sql = "SELECT id, customer_code, shop_name, customer_name, customer_contact 
                                 FROM customers WHERE status = 'active' 
                                 ORDER BY shop_name";
                $customers_result = mysqli_query($conn, $customers_sql);
                while ($cust = mysqli_fetch_assoc($customers_result)) {
                    $customers[] = $cust;
                }
                
                // Fetch all active products for dropdown
                $products_sql = "SELECT id, product_code, product_name, quantity, customer_price 
                                FROM products 
                                WHERE status = 'active' 
                                ORDER BY product_name";
                $products_result = mysqli_query($conn, $products_sql);
                while ($prod = mysqli_fetch_assoc($products_result)) {
                    $products[] = $prod;
                }
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    // Check which action was performed
                    if (isset($_POST['update_order'])) {
                        // Update order basic information
                        $customer_id = intval($_POST['customer_id']);
                        $order_date = mysqli_real_escape_string($conn, $_POST['order_date']);
                        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
                        $delivery_date = !empty($_POST['delivery_date']) ? mysqli_real_escape_string($conn, $_POST['delivery_date']) : NULL;
                        $notes = mysqli_real_escape_string($conn, $_POST['notes']);
                        
                        $update_sql = "UPDATE orders SET 
                                      customer_id = ?,
                                      order_date = ?,
                                      payment_method = ?,
                                      delivery_date = ?,
                                      notes = ?
                                      WHERE id = ?";
                        $update_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($update_stmt, "issssi", 
                            $customer_id, $order_date, $payment_method, $delivery_date, $notes, $id);
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            $message = 'Order information updated successfully!';
                            $message_type = 'success';
                            
                            // Refresh order data
                            $refresh_sql = "SELECT o.*, c.* FROM orders o 
                                          LEFT JOIN customers c ON o.customer_id = c.id 
                                          WHERE o.id = ?";
                            $refresh_stmt = mysqli_prepare($conn, $refresh_sql);
                            mysqli_stmt_bind_param($refresh_stmt, "i", $id);
                            mysqli_stmt_execute($refresh_stmt);
                            $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                            $order = mysqli_fetch_assoc($refresh_result);
                            mysqli_stmt_close($refresh_stmt);
                            
                        } else {
                            $message = 'Error updating order: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                        mysqli_stmt_close($update_stmt);
                        
                    } elseif (isset($_POST['add_item'])) {
                        // Add new item to order
                        $product_id = intval($_POST['product_id']);
                        $quantity = intval($_POST['quantity']);
                        
                        // Check if product already exists in order
                        $check_sql = "SELECT id, quantity FROM order_items 
                                     WHERE order_id = ? AND product_id = ?";
                        $check_stmt = mysqli_prepare($conn, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "ii", $id, $product_id);
                        mysqli_stmt_execute($check_stmt);
                        $check_result = mysqli_stmt_get_result($check_stmt);
                        
                        if (mysqli_num_rows($check_result) > 0) {
                            // Update existing item
                            $existing_item = mysqli_fetch_assoc($check_result);
                            $new_quantity = $existing_item['quantity'] + $quantity;
                            
                            // Get product price
                            $price_sql = "SELECT customer_price FROM products WHERE id = ?";
                            $price_stmt = mysqli_prepare($conn, $price_sql);
                            mysqli_stmt_bind_param($price_stmt, "i", $product_id);
                            mysqli_stmt_execute($price_stmt);
                            $price_result = mysqli_stmt_get_result($price_stmt);
                            $product = mysqli_fetch_assoc($price_result);
                            
                            $update_item_sql = "UPDATE order_items SET 
                                               quantity = ?,
                                               total = ? * ?
                                               WHERE id = ?";
                            $update_item_stmt = mysqli_prepare($conn, $update_item_sql);
                            $total = $new_quantity * $product['customer_price'];
                            mysqli_stmt_bind_param($update_item_stmt, "iddi", 
                                $new_quantity, $new_quantity, $product['customer_price'], $existing_item['id']);
                            
                            if (mysqli_stmt_execute($update_item_stmt)) {
                                // Update product stock
                                $update_stock_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                                $update_stock_stmt = mysqli_prepare($conn, $update_stock_sql);
                                mysqli_stmt_bind_param($update_stock_stmt, "ii", $quantity, $product_id);
                                mysqli_stmt_execute($update_stock_stmt);
                                
                                $message = 'Product quantity updated successfully!';
                                $message_type = 'success';
                            } else {
                                $message = 'Error updating product quantity: ' . mysqli_error($conn);
                                $message_type = 'danger';
                            }
                            mysqli_stmt_close($update_item_stmt);
                            mysqli_stmt_close($update_stock_stmt);
                            
                        } else {
                            // Insert new item
                            // Get product price
                            $price_sql = "SELECT customer_price FROM products WHERE id = ?";
                            $price_stmt = mysqli_prepare($conn, $price_sql);
                            mysqli_stmt_bind_param($price_stmt, "i", $product_id);
                            mysqli_stmt_execute($price_stmt);
                            $price_result = mysqli_stmt_get_result($price_stmt);
                            $product = mysqli_fetch_assoc($price_result);
                            
                            $insert_sql = "INSERT INTO order_items 
                                         (order_id, product_id, quantity, price, total) 
                                         VALUES (?, ?, ?, ?, ?)";
                            $insert_stmt = mysqli_prepare($conn, $insert_sql);
                            $total = $quantity * $product['customer_price'];
                            mysqli_stmt_bind_param($insert_stmt, "iiidd", 
                                $id, $product_id, $quantity, $product['customer_price'], $total);
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                // Update product stock
                                $update_stock_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                                $update_stock_stmt = mysqli_prepare($conn, $update_stock_sql);
                                mysqli_stmt_bind_param($update_stock_stmt, "ii", $quantity, $product_id);
                                mysqli_stmt_execute($update_stock_stmt);
                                
                                $message = 'Product added to order successfully!';
                                $message_type = 'success';
                            } else {
                                $message = 'Error adding product to order: ' . mysqli_error($conn);
                                $message_type = 'danger';
                            }
                            mysqli_stmt_close($insert_stmt);
                            mysqli_stmt_close($update_stock_stmt);
                        }
                        mysqli_stmt_close($check_stmt);
                        mysqli_stmt_close($price_stmt);
                        
                        // Refresh order items
                        $refresh_items_stmt = mysqli_prepare($conn, $items_sql);
                        mysqli_stmt_bind_param($refresh_items_stmt, "i", $id);
                        mysqli_stmt_execute($refresh_items_stmt);
                        $refresh_items_result = mysqli_stmt_get_result($refresh_items_stmt);
                        $order_items = [];
                        while ($item = mysqli_fetch_assoc($refresh_items_result)) {
                            $order_items[] = $item;
                        }
                        mysqli_stmt_close($refresh_items_stmt);
                        
                    } elseif (isset($_POST['update_items'])) {
                        // Update item quantities
                        $item_updated = false;
                        
                        foreach ($_POST['item_quantity'] as $item_id => $quantity) {
                            $item_id = intval($item_id);
                            $quantity = intval($quantity);
                            
                            if ($quantity <= 0) continue;
                            
                            // Get current item details
                            $item_sql = "SELECT oi.*, p.customer_price 
                                        FROM order_items oi
                                        LEFT JOIN products p ON oi.product_id = p.id
                                        WHERE oi.id = ?";
                            $item_stmt = mysqli_prepare($conn, $item_sql);
                            mysqli_stmt_bind_param($item_stmt, "i", $item_id);
                            mysqli_stmt_execute($item_stmt);
                            $item_result = mysqli_stmt_get_result($item_stmt);
                            $item_data = mysqli_fetch_assoc($item_result);
                            
                            if ($item_data['quantity'] != $quantity) {
                                $quantity_diff = $quantity - $item_data['quantity'];
                                
                                // Update item quantity and total
                                $update_sql = "UPDATE order_items SET 
                                              quantity = ?,
                                              total = ? * ?
                                              WHERE id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_sql);
                                $total = $quantity * $item_data['customer_price'];
                                mysqli_stmt_bind_param($update_stmt, "iddi", 
                                    $quantity, $quantity, $item_data['customer_price'], $item_id);
                                
                                if (mysqli_stmt_execute($update_stmt)) {
                                    // Update product stock
                                    $update_stock_sql = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
                                    $update_stock_stmt = mysqli_prepare($conn, $update_stock_sql);
                                    mysqli_stmt_bind_param($update_stock_stmt, "ii", $quantity_diff, $item_data['product_id']);
                                    mysqli_stmt_execute($update_stock_stmt);
                                    
                                    $item_updated = true;
                                }
                                mysqli_stmt_close($update_stmt);
                                mysqli_stmt_close($update_stock_stmt);
                            }
                            mysqli_stmt_close($item_stmt);
                        }
                        
                        if ($item_updated) {
                            $message = 'Order items updated successfully!';
                            $message_type = 'success';
                            
                            // Refresh order items
                            $refresh_items_stmt = mysqli_prepare($conn, $items_sql);
                            mysqli_stmt_bind_param($refresh_items_stmt, "i", $id);
                            mysqli_stmt_execute($refresh_items_stmt);
                            $refresh_items_result = mysqli_stmt_get_result($refresh_items_stmt);
                            $order_items = [];
                            while ($item = mysqli_fetch_assoc($refresh_items_result)) {
                                $order_items[] = $item;
                            }
                            mysqli_stmt_close($refresh_items_stmt);
                        }
                        
                    } elseif (isset($_POST['delete_item'])) {
                        // Delete specific item
                        $item_id = intval($_POST['delete_item']);
                        
                        // Get item details for stock restoration
                        $item_sql = "SELECT * FROM order_items WHERE id = ?";
                        $item_stmt = mysqli_prepare($conn, $item_sql);
                        mysqli_stmt_bind_param($item_stmt, "i", $item_id);
                        mysqli_stmt_execute($item_stmt);
                        $item_result = mysqli_stmt_get_result($item_stmt);
                        $item_data = mysqli_fetch_assoc($item_result);
                        
                        // Begin transaction
                        mysqli_begin_transaction($conn);
                        
                        try {
                            // Restore stock
                            $restore_sql = "UPDATE products SET quantity = quantity + ? WHERE id = ?";
                            $restore_stmt = mysqli_prepare($conn, $restore_sql);
                            mysqli_stmt_bind_param($restore_stmt, "ii", $item_data['quantity'], $item_data['product_id']);
                            mysqli_stmt_execute($restore_stmt);
                            
                            // Delete item
                            $delete_sql = "DELETE FROM order_items WHERE id = ?";
                            $delete_stmt = mysqli_prepare($conn, $delete_sql);
                            mysqli_stmt_bind_param($delete_stmt, "i", $item_id);
                            mysqli_stmt_execute($delete_stmt);
                            
                            // Commit transaction
                            mysqli_commit($conn);
                            
                            $message = 'Item removed from order successfully!';
                            $message_type = 'success';
                            
                            // Refresh order items
                            $refresh_items_stmt = mysqli_prepare($conn, $items_sql);
                            mysqli_stmt_bind_param($refresh_items_stmt, "i", $id);
                            mysqli_stmt_execute($refresh_items_stmt);
                            $refresh_items_result = mysqli_stmt_get_result($refresh_items_stmt);
                            $order_items = [];
                            while ($item = mysqli_fetch_assoc($refresh_items_result)) {
                                $order_items[] = $item;
                            }
                            mysqli_stmt_close($refresh_items_stmt);
                            
                        } catch (Exception $e) {
                            mysqli_rollback($conn);
                            $message = 'Error removing item: ' . $e->getMessage();
                            $message_type = 'danger';
                        }
                        mysqli_stmt_close($item_stmt);
                        if (isset($restore_stmt)) mysqli_stmt_close($restore_stmt);
                        if (isset($delete_stmt)) mysqli_stmt_close($delete_stmt);
                    }
                    
                    // Always recalculate order total after any changes
                    $recalc_sql = "UPDATE orders SET 
                                  total_amount = (SELECT SUM(total) FROM order_items WHERE order_id = ?),
                                  total_items = (SELECT COUNT(id) FROM order_items WHERE order_id = ?)
                                  WHERE id = ?";
                    $recalc_stmt = mysqli_prepare($conn, $recalc_sql);
                    mysqli_stmt_bind_param($recalc_stmt, "iii", $id, $id, $id);
                    mysqli_stmt_execute($recalc_stmt);
                    mysqli_stmt_close($recalc_stmt);
                }
                
                // Close connection
                mysqli_close($conn);
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h4 class="card-title mb-0">Edit Order #<?php echo $order['order_number']; ?></h4>
                                    <div class="d-flex gap-2">
                                        <a href="order-view.php?id=<?php echo $id; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="mdi mdi-eye me-1"></i> View Order
                                        </a>
                                        <a href="orders-list.php" 
                                           class="btn btn-light btn-sm">
                                            <i class="mdi mdi-arrow-left me-1"></i> Back to List
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Order Information Form -->
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">Order Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="order-edit.php?id=<?php echo $id; ?>" id="orderForm">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Order Number</label>
                                                                <input type="text" class="form-control" 
                                                                       value="<?php echo $order['order_number']; ?>" readonly>
                                                                <small class="text-muted">Order number cannot be changed</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Customer <span class="text-danger">*</span></label>
                                                                <select class="form-select" name="customer_id" required>
                                                                    <option value="">Select Customer</option>
                                                                    <?php foreach ($customers as $cust): ?>
                                                                    <option value="<?php echo $cust['id']; ?>" 
                                                                            <?php echo ($order['customer_id'] == $cust['id']) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($cust['shop_name']); ?> - 
                                                                        <?php echo htmlspecialchars($cust['customer_name']); ?>
                                                                        (<?php echo $cust['customer_contact']; ?>)
                                                                    </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Order Date <span class="text-danger">*</span></label>
                                                                <input type="date" class="form-control" name="order_date" 
                                                                       value="<?php echo $order['order_date']; ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                                                <select class="form-select" name="payment_method" required>
                                                                    <option value="cash" <?php echo ($order['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                                                    <option value="bank_transfer" <?php echo ($order['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                                                    <option value="cheque" <?php echo ($order['payment_method'] == 'cheque') ? 'selected' : ''; ?>>Cheque</option>
                                                                    <option value="upi" <?php echo ($order['payment_method'] == 'upi') ? 'selected' : ''; ?>>UPI</option>
                                                                    <option value="credit_card" <?php echo ($order['payment_method'] == 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                                                                    <option value="debit_card" <?php echo ($order['payment_method'] == 'debit_card') ? 'selected' : ''; ?>>Debit Card</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Delivery Date</label>
                                                                <input type="date" class="form-control" name="delivery_date" 
                                                                       value="<?php echo $order['delivery_date']; ?>">
                                                                <small class="text-muted">Leave empty if not scheduled</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Order Status</label>
                                                                <input type="text" class="form-control" 
                                                                       value="<?php echo ucfirst($order['status']); ?>" readonly>
                                                                <small class="text-muted">Change status from Order View page</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="mb-3">
                                                                <label class="form-label">Order Notes</label>
                                                                <textarea class="form-control" name="notes" rows="3" 
                                                                          placeholder="Add notes about this order..."><?php echo htmlspecialchars($order['notes']); ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3">
                                                        <button type="submit" name="update_order" value="1" 
                                                                class="btn btn-primary">
                                                            <i class="mdi mdi-content-save me-1"></i> Update Order Information
                                                        </button>
                                                        <button type="reset" class="btn btn-light ms-2">
                                                            <i class="mdi mdi-refresh me-1"></i> Reset Changes
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Add Products to Order -->
                                <div class="row mt-4">
                                    <div class="col-lg-12">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <h5 class="card-title mb-0">Add Products to Order</h5>
                                                    <span class="badge bg-primary">
                                                        <?php echo count($order_items); ?> Items in Order
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <form method="POST" action="order-edit.php?id=<?php echo $id; ?>" id="addItemForm">
                                                    <div class="row">
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">Select Product <span class="text-danger">*</span></label>
                                                                <select class="form-select" name="product_id" id="productSelect" required>
                                                                    <option value="">Choose a product...</option>
                                                                    <?php foreach ($products as $product): ?>
                                                                    <option value="<?php echo $product['id']; ?>" 
                                                                            data-price="<?php echo $product['customer_price']; ?>"
                                                                            data-stock="<?php echo $product['quantity']; ?>"
                                                                            data-code="<?php echo $product['product_code']; ?>">
                                                                        <?php echo htmlspecialchars($product['product_name']); ?> 
                                                                        (<?php echo $product['product_code']; ?>)
                                                                        - Stock: <?php echo $product['quantity']; ?> 
                                                                        - Price: ₹<?php echo number_format($product['customer_price'], 2); ?>
                                                                    </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                                                <input type="number" class="form-control" name="quantity" 
                                                                       id="quantityInput" value="1" min="1" required>
                                                                <small class="text-muted" id="stockInfo">Select a product first</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="mb-3">
                                                                <label class="form-label">Price</label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text">₹</span>
                                                                    <input type="text" class="form-control" id="priceDisplay" 
                                                                           value="0.00" readonly>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="mb-3">
                                                                <label class="form-label">Total</label>
                                                                <div class="input-group">
                                                                    <span class="input-group-text">₹</span>
                                                                    <input type="text" class="form-control" id="totalDisplay" 
                                                                           value="0.00" readonly>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="d-flex justify-content-end">
                                                                <button type="submit" name="add_item" value="1" 
                                                                        class="btn btn-success" id="addItemBtn" disabled>
                                                                    <i class="mdi mdi-plus-circle me-1"></i> Add to Order
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Current Order Items -->
                                <div class="row mt-4">
                                    <div class="col-lg-12">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">Current Order Items</h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (count($order_items) > 0): ?>
                                                <form method="POST" action="order-edit.php?id=<?php echo $id; ?>" id="itemsForm">
                                                    <div class="table-responsive">
                                                        <table class="table table-centered mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>Product</th>
                                                                    <th>Product Code</th>
                                                                    <th>Price</th>
                                                                    <th>Quantity</th>
                                                                    <th>Total</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                $subtotal = 0;
                                                                $counter = 1;
                                                                foreach ($order_items as $item):
                                                                    $subtotal += $item['total'];
                                                                ?>
                                                                <tr>
                                                                    <td><?php echo $counter++; ?></td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                                                            <small class="text-muted">Available: <?php echo $item['available_stock']; ?> units</small>
                                                                        </div>
                                                                    </td>
                                                                    <td><?php echo $item['product_code']; ?></td>
                                                                    <td>
                                                                        <div class="input-group input-group-sm">
                                                                            <span class="input-group-text">₹</span>
                                                                            <input type="text" class="form-control" 
                                                                                   value="<?php echo number_format($item['price'], 2); ?>" readonly>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <input type="number" name="item_quantity[<?php echo $item['id']; ?>]" 
                                                                               class="form-control form-control-sm" 
                                                                               value="<?php echo $item['quantity']; ?>" 
                                                                               min="1" 
                                                                               style="width: 80px;">
                                                                    </td>
                                                                    <td>
                                                                        <span class="fw-bold">₹<?php echo number_format($item['total'], 2); ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <button type="submit" name="delete_item" value="<?php echo $item['id']; ?>" 
                                                                                class="btn btn-danger btn-sm"
                                                                                onclick="return confirm('Are you sure you want to remove this item from the order?');">
                                                                            <i class="mdi mdi-delete"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                            <tfoot>
                                                                <tr>
                                                                    <td colspan="5" class="text-end fw-bold">Subtotal:</td>
                                                                    <td colspan="2" class="fw-bold">₹<?php echo number_format($subtotal, 2); ?></td>
                                                                </tr>
                                                            </tfoot>
                                                        </table>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-md-12">
                                                            <div class="d-flex justify-content-between">
                                                                <div>
                                                                    <button type="submit" name="update_items" value="1" 
                                                                            class="btn btn-primary">
                                                                        <i class="mdi mdi-update me-1"></i> Update Quantities
                                                                    </button>
                                                                </div>
                                                                <div>
                                                                    <span class="fw-bold text-primary">
                                                                        Total Items: <?php echo count($order_items); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                                <?php else: ?>
                                                <div class="text-center py-4">
                                                    <i class="mdi mdi-cart-off display-4 text-muted"></i>
                                                    <h5 class="mt-2">No items in this order</h5>
                                                    <p class="text-muted">Add products using the form above</p>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order Summary -->
                                <div class="row mt-4">
                                    <div class="col-lg-6">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">Order Summary</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="50%">Order Number:</th>
                                                        <td><?php echo $order['order_number']; ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Customer:</th>
                                                        <td><?php echo htmlspecialchars($order['shop_name']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Order Date:</th>
                                                        <td><?php echo date('d M, Y', strtotime($order['order_date'])); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Payment Method:</th>
                                                        <td><?php echo ucfirst($order['payment_method']); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Payment Status:</th>
                                                        <td>
                                                            <span class="badge <?php 
                                                                echo ($order['payment_status'] == 'paid') ? 'bg-success' : 
                                                                (($order['payment_status'] == 'partial') ? 'bg-warning' : 'bg-danger'); 
                                                            ?>">
                                                                <?php echo ucfirst($order['payment_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Created By:</th>
                                                        <td>
                                                            <?php if ($order['lineman_name']): ?>
                                                            <?php echo htmlspecialchars($order['lineman_name']); ?> 
                                                            (ID: <?php echo $order['lineman_id']; ?>)
                                                            <?php else: ?>
                                                            <span class="text-muted">Admin</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-6">
                                        <div class="card border">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0">Payment Summary</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="50%">Order Total:</th>
                                                        <td class="fw-bold">₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Paid Amount:</th>
                                                        <td class="text-success fw-bold">
                                                            ₹<?php echo number_format($order['paid_amount'], 2); ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Pending Amount:</th>
                                                        <td class="text-danger fw-bold">
                                                            ₹<?php echo number_format($order['pending_amount'], 2); ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Items in Order:</th>
                                                        <td>
                                                            <span class="badge bg-primary">
                                                                <?php echo $order['total_items']; ?> items
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Order Status:</th>
                                                        <td>
                                                            <span class="badge <?php 
                                                                echo ($order['status'] == 'pending') ? 'bg-warning' : 
                                                                (($order['status'] == 'processing') ? 'bg-primary' : 
                                                                (($order['status'] == 'delivered') ? 'bg-success' : 'bg-danger')); 
                                                            ?>">
                                                                <?php echo ucfirst($order['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="row mt-4">
                                    <div class="col-lg-12">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="order-view.php?id=<?php echo $id; ?>" class="btn btn-info">
                                                    <i class="mdi mdi-eye me-1"></i> View Order
                                                </a>
                                                <a href="order-invoice.php?id=<?php echo $id; ?>" 
                                                   class="btn btn-success ms-2" target="_blank">
                                                    <i class="mdi mdi-receipt me-1"></i> View Invoice
                                                </a>
                                            </div>
                                            <div>
                                                <a href="orders-list.php" class="btn btn-light">
                                                    <i class="mdi mdi-arrow-left me-1"></i> Back to Orders
                                                </a>
                                                <a href="order-edit.php?id=<?php echo $id; ?>" class="btn btn-primary ms-2">
                                                    <i class="mdi mdi-refresh me-1"></i> Refresh Page
                                                </a>
                                            </div>
                                        </div>
                                    </div>
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
// Product selection change handler
document.getElementById('productSelect').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    var stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
    var quantityInput = document.getElementById('quantityInput');
    var priceDisplay = document.getElementById('priceDisplay');
    var totalDisplay = document.getElementById('totalDisplay');
    var stockInfo = document.getElementById('stockInfo');
    var addItemBtn = document.getElementById('addItemBtn');
    
    // Update displays
    priceDisplay.value = price.toFixed(2);
    
    // If selected product has no stock, disable add button
    if (stock <= 0) {
        quantityInput.disabled = true;
        addItemBtn.disabled = true;
        stockInfo.className = 'text-danger';
        stockInfo.textContent = 'Out of stock!';
        totalDisplay.value = '0.00';
    } else {
        quantityInput.disabled = false;
        addItemBtn.disabled = false;
        stockInfo.className = 'text-muted';
        stockInfo.textContent = 'Available: ' + stock + ' units';
        
        // Calculate total
        calculateTotal();
    }
});

// Quantity input change handler
document.getElementById('quantityInput').addEventListener('input', calculateTotal);

// Calculate total function
function calculateTotal() {
    var quantity = parseInt(document.getElementById('quantityInput').value) || 0;
    var price = parseFloat(document.getElementById('priceDisplay').value) || 0;
    var total = quantity * price;
    
    document.getElementById('totalDisplay').value = total.toFixed(2);
}

// Form validation for add item
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    var productSelect = document.getElementById('productSelect');
    var quantityInput = document.getElementById('quantityInput');
    
    if (productSelect.value === '') {
        alert('Please select a product');
        e.preventDefault();
        return false;
    }
    
    if (quantityInput.value <= 0) {
        alert('Please enter a valid quantity');
        e.preventDefault();
        return false;
    }
    
    var stock = parseInt(productSelect.options[productSelect.selectedIndex].getAttribute('data-stock')) || 0;
    if (parseInt(quantityInput.value) > stock) {
        alert('Quantity cannot exceed available stock of ' + stock + ' units');
        e.preventDefault();
        return false;
    }
    
    return true;
});

// Confirm before deleting item
document.querySelectorAll('button[name="delete_item"]').forEach(button => {
    button.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to remove this item from the order?')) {
            e.preventDefault();
        }
    });
});

// Real-time validation for item quantities
document.addEventListener('input', function(e) {
    if (e.target.name && e.target.name.startsWith('item_quantity[')) {
        var value = parseInt(e.target.value) || 0;
        
        if (value < 1) {
            e.target.value = 1;
        }
    }
});

// Calculate order total on the fly
function calculateOrderTotal() {
    var total = 0;
    var itemRows = document.querySelectorAll('#itemsForm tbody tr');
    
    itemRows.forEach(function(row) {
        var quantity = parseInt(row.querySelector('input[name^="item_quantity"]').value) || 0;
        var priceText = row.querySelector('td:nth-child(4) input').value;
        var price = parseFloat(priceText.replace('₹', '').replace(',', '')) || 0;
        var rowTotal = quantity * price;
        
        // Update total cell
        var totalCell = row.querySelector('td:nth-child(6) span');
        if (totalCell) {
            totalCell.textContent = '₹' + rowTotal.toFixed(2);
        }
        
        total += rowTotal;
    });
    
    // Update subtotal display
    var subtotalCell = document.querySelector('#itemsForm tfoot tr:first-child td:last-child');
    if (subtotalCell) {
        subtotalCell.textContent = '₹' + total.toFixed(2);
    }
    
    return total;
}

// Attach event listeners to quantity inputs for real-time calculation
document.addEventListener('DOMContentLoaded', function() {
    var quantityInputs = document.querySelectorAll('input[name^="item_quantity"]');
    quantityInputs.forEach(function(input) {
        input.addEventListener('input', calculateOrderTotal);
    });
    
    // Initialize calculation
    calculateOrderTotal();
});

// Confirm before leaving page if form has unsaved changes
window.addEventListener('beforeunload', function(e) {
    var forms = document.querySelectorAll('form');
    var hasChanges = false;
    
    forms.forEach(function(form) {
        if (form.checkValidity && !form.checkValidity()) {
            hasChanges = true;
        }
    });
    
    if (hasChanges) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
    }
});

// Initialize product select on page load
document.addEventListener('DOMContentLoaded', function() {
    var productSelect = document.getElementById('productSelect');
    if (productSelect.value) {
        productSelect.dispatchEvent(new Event('change'));
    }
});

// Auto-focus on first input field
document.addEventListener('DOMContentLoaded', function() {
    var firstInput = document.querySelector('form input, form select, form textarea');
    if (firstInput) {
        firstInput.focus();
    }
});

// Show loading state on form submission
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        var submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
            submitBtn.disabled = true;
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + S to save order info
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.querySelector('button[name="update_order"]').click();
    }
    
    // Esc to go to view page
    if (e.key === 'Escape') {
        window.location.href = 'order-view.php?id=<?php echo $id; ?>';
    }
});
</script>

</body>

</html>