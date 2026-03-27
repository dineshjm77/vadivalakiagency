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
                
                // Get filter parameters
                $filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
                $filter_product = isset($_GET['product_id']) ? mysqli_real_escape_string($conn, $_GET['product_id']) : '';
                $filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
                $filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';
                
                // Build WHERE clause for filters
                $where_conditions = [];
                
                if ($filter_type != 'all' && $filter_type != '') {
                    $where_conditions[] = "st.transaction_type = '$filter_type'";
                }
                
                if ($filter_product != '') {
                    $where_conditions[] = "st.product_id = '$filter_product'";
                }
                
                if ($filter_date_from != '') {
                    $where_conditions[] = "DATE(st.created_at) >= '$filter_date_from'";
                }
                
                if ($filter_date_to != '') {
                    $where_conditions[] = "DATE(st.created_at) <= '$filter_date_to'";
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                }
                
                // Get total counts for summary
                $counts_sql = "SELECT 
                    SUM(CASE WHEN transaction_type = 'purchase' THEN 1 ELSE 0 END) as total_purchases,
                    SUM(CASE WHEN transaction_type = 'sale' THEN 1 ELSE 0 END) as total_sales,
                    SUM(CASE WHEN transaction_type = 'adjustment' THEN 1 ELSE 0 END) as total_adjustments,
                    SUM(CASE WHEN transaction_type = 'return' THEN 1 ELSE 0 END) as total_returns,
                    COUNT(*) as total_transactions
                    FROM stock_transactions";
                
                $counts_result = mysqli_query($conn, $counts_sql);
                $counts = mysqli_fetch_assoc($counts_result);
                
                // Get total quantity changes
                $quantity_sql = "SELECT 
                    SUM(CASE WHEN transaction_type = 'purchase' THEN quantity ELSE 0 END) as total_purchased_qty,
                    SUM(CASE WHEN transaction_type = 'sale' THEN quantity ELSE 0 END) as total_sold_qty,
                    SUM(CASE WHEN transaction_type = 'adjustment' AND previous_quantity < new_quantity THEN quantity ELSE 0 END) as total_increased_qty,
                    SUM(CASE WHEN transaction_type = 'adjustment' AND previous_quantity > new_quantity THEN quantity ELSE 0 END) as total_decreased_qty
                    FROM stock_transactions";
                
                $quantity_result = mysqli_query($conn, $quantity_sql);
                $quantities = mysqli_fetch_assoc($quantity_result);
                
                // Fetch all products for filter dropdown
                $products_sql = "SELECT id, product_code, product_name FROM products ORDER BY product_name";
                $products_result = mysqli_query($conn, $products_sql);
                
                // Get transaction history with pagination
                $limit = 20; // Transactions per page
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($page - 1) * $limit;
                
                $history_sql = "SELECT 
                    st.*,
                    p.product_name,
                    p.product_code,
                    c.category_name,
                    b.brand_name
                    FROM stock_transactions st
                    JOIN products p ON st.product_id = p.id
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN brands b ON p.brand_id = b.id
                    $where_clause
                    ORDER BY st.created_at DESC
                    LIMIT $limit OFFSET $offset";
                
                $history_result = mysqli_query($conn, $history_sql);
                
                // Get total count for pagination
                $count_sql = "SELECT COUNT(*) as total FROM stock_transactions st $where_clause";
                $count_result = mysqli_query($conn, $count_sql);
                $total_count = mysqli_fetch_assoc($count_result)['total'];
                $total_pages = ceil($total_count / $limit);
                
                // Get monthly summary for chart
                $monthly_sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(CASE WHEN transaction_type = 'purchase' THEN quantity ELSE 0 END) as purchased,
                    SUM(CASE WHEN transaction_type = 'sale' THEN quantity ELSE 0 END) as sold,
                    SUM(CASE WHEN transaction_type = 'adjustment' AND previous_quantity < new_quantity THEN quantity ELSE 0 END) as increased,
                    SUM(CASE WHEN transaction_type = 'adjustment' AND previous_quantity > new_quantity THEN quantity ELSE 0 END) as decreased
                    FROM stock_transactions
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month";
                
                $monthly_result = mysqli_query($conn, $monthly_sql);
                ?>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-history"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Transactions</p>
                                        <h4 class="mb-0"><?php echo number_format($counts['total_transactions']); ?></h4>
                                        <small class="text-muted">All inventory activities</small>
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
                                            <i class="mdi mdi-plus-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Stock Purchases</p>
                                        <h4 class="mb-0"><?php echo number_format($counts['total_purchases']); ?></h4>
                                        <small class="text-muted"><?php echo number_format($quantities['total_purchased_qty']); ?> units</small>
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
                                            <i class="mdi mdi-swap-horizontal"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Adjustments</p>
                                        <h4 class="mb-0"><?php echo number_format($counts['total_adjustments']); ?></h4>
                                        <small class="text-muted"><?php echo number_format($quantities['total_increased_qty']); ?>↑ <?php echo number_format($quantities['total_decreased_qty']); ?>↓</small>
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
                                            <i class="mdi mdi-chart-line"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Net Change</p>
                                        <h4 class="mb-0 <?php echo ($quantities['total_purchased_qty'] - $quantities['total_sold_qty'] + $quantities['total_increased_qty'] - $quantities['total_decreased_qty']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php 
                                            $net_change = $quantities['total_purchased_qty'] - $quantities['total_sold_qty'] + $quantities['total_increased_qty'] - $quantities['total_decreased_qty'];
                                            echo ($net_change >= 0 ? '+' : '') . number_format($net_change); 
                                            ?>
                                        </h4>
                                        <small class="text-muted">Overall stock movement</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <!-- Filter Panel -->
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-filter me-1"></i> Filter History
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="inventory-history.php" id="filterForm">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Transaction Type</label>
                                            <select class="form-select" name="type" id="typeFilter">
                                                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                                                <option value="purchase" <?php echo $filter_type == 'purchase' ? 'selected' : ''; ?>>Purchases</option>
                                                <option value="sale" <?php echo $filter_type == 'sale' ? 'selected' : ''; ?>>Sales</option>
                                                <option value="adjustment" <?php echo $filter_type == 'adjustment' ? 'selected' : ''; ?>>Adjustments</option>
                                                <option value="return" <?php echo $filter_type == 'return' ? 'selected' : ''; ?>>Returns</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Product</label>
                                            <select class="form-select" name="product_id" id="productFilter">
                                                <option value="">All Products</option>
                                                <?php
                                                mysqli_data_seek($products_result, 0);
                                                while ($prod = mysqli_fetch_assoc($products_result)) {
                                                    $selected = ($filter_product == $prod['id']) ? 'selected' : '';
                                                    echo '<option value="' . $prod['id'] . '" ' . $selected . '>' . $prod['product_name'] . ' (' . $prod['product_code'] . ')</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Date From</label>
                                            <input type="date" class="form-control" name="date_from" value="<?php echo $filter_date_from; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Date To</label>
                                            <input type="date" class="form-control" name="date_to" value="<?php echo $filter_date_to; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="mdi mdi-filter me-1"></i> Apply
                                                </button>
                                                <a href="inventory-history.php" class="btn btn-light">
                                                    <i class="mdi mdi-refresh"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($filter_type != 'all' || $filter_product != '' || $filter_date_from != '' || $filter_date_to != ''): ?>
                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="alert alert-info py-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <i class="mdi mdi-information-outline me-2"></i>
                                                        <small>
                                                            Active Filters: 
                                                            <?php if ($filter_type != 'all'): ?><span class="badge bg-primary ms-2">Type: <?php echo ucfirst($filter_type); ?></span><?php endif; ?>
                                                            <?php if ($filter_product != ''): 
                                                                $product_name = '';
                                                                mysqli_data_seek($products_result, 0);
                                                                while ($prod = mysqli_fetch_assoc($products_result)) {
                                                                    if ($prod['id'] == $filter_product) {
                                                                        $product_name = $prod['product_name'];
                                                                        break;
                                                                    }
                                                                }
                                                                ?>
                                                                <span class="badge bg-info ms-2">Product: <?php echo $product_name; ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($filter_date_from != ''): ?><span class="badge bg-success ms-2">From: <?php echo $filter_date_from; ?></span><?php endif; ?>
                                                            <?php if ($filter_date_to != ''): ?><span class="badge bg-warning ms-2">To: <?php echo $filter_date_to; ?></span><?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <small>
                                                            Showing <?php echo number_format(min($limit, $total_count)); ?> of <?php echo number_format($total_count); ?> transactions
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction History -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">Transaction History</h4>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-success" onclick="exportHistory()">
                                            <i class="mdi mdi-file-export me-1"></i> Export
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="printHistory()">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($history_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="historyTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Date & Time</th>
                                                <th>Product</th>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Previous</th>
                                                <th>New</th>
                                                <th>Change</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $counter = $offset + 1;
                                            while ($transaction = mysqli_fetch_assoc($history_result)): 
                                                // Determine transaction details
                                                $date = date('d M Y', strtotime($transaction['created_at']));
                                                $time = date('h:i A', strtotime($transaction['created_at']));
                                                
                                                // Type styling
                                                $type_class = '';
                                                $type_icon = '';
                                                $type_text = ucfirst($transaction['transaction_type']);
                                                
                                                switch($transaction['transaction_type']) {
                                                    case 'purchase':
                                                        $type_class = 'bg-success-subtle text-success';
                                                        $type_icon = 'mdi-plus-circle';
                                                        break;
                                                    case 'sale':
                                                        $type_class = 'bg-danger-subtle text-danger';
                                                        $type_icon = 'mdi-minus-circle';
                                                        break;
                                                    case 'adjustment':
                                                        // Check if increase or decrease
                                                        if ($transaction['previous_quantity'] < $transaction['new_quantity']) {
                                                            $type_class = 'bg-info-subtle text-info';
                                                            $type_icon = 'mdi-arrow-up';
                                                            $type_text = 'Increase';
                                                        } else {
                                                            $type_class = 'bg-warning-subtle text-warning';
                                                            $type_icon = 'mdi-arrow-down';
                                                            $type_text = 'Decrease';
                                                        }
                                                        break;
                                                    case 'return':
                                                        $type_class = 'bg-primary-subtle text-primary';
                                                        $type_icon = 'mdi-undo';
                                                        break;
                                                }
                                                
                                                // Calculate change
                                                $change = $transaction['new_quantity'] - $transaction['previous_quantity'];
                                                $change_class = $change >= 0 ? 'text-success' : 'text-danger';
                                                $change_icon = $change >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down';
                                                
                                                // Determine adjustment direction
                                                $is_increase = $change > 0;
                                            ?>
                                            <tr>
                                                <td><?php echo $counter; ?></td>
                                                <td>
                                                    <div>
                                                        <small class="text-muted"><?php echo $date; ?></small><br>
                                                        <small class="text-muted"><?php echo $time; ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <div class="avatar-xs">
                                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                    <?php echo strtoupper(substr($transaction['product_name'], 0, 1)); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="font-size-14 mb-1"><?php echo htmlspecialchars($transaction['product_name']); ?></h6>
                                                            <p class="text-muted mb-0 small"><?php echo $transaction['product_code']; ?></p>
                                                            <?php if (!empty($transaction['category_name'])): ?>
                                                            <small class="text-muted">
                                                                <i class="mdi mdi-tag-outline"></i> <?php echo $transaction['category_name']; ?>
                                                            </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $type_class; ?>">
                                                        <i class="mdi <?php echo $type_icon; ?> me-1"></i> <?php echo $type_text; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-medium"><?php echo number_format($transaction['quantity']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($transaction['stock_price'] > 0): ?>
                                                    <span class="text-warning">₹<?php echo number_format($transaction['stock_price'], 2); ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $transaction['previous_quantity'] < 10 ? 'text-warning' : 'text-success'; ?>">
                                                        <?php echo number_format($transaction['previous_quantity']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $transaction['new_quantity'] < 10 ? 'text-warning' : 'text-success'; ?>">
                                                        <?php echo number_format($transaction['new_quantity']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $change_class; ?>">
                                                        <i class="mdi <?php echo $change_icon; ?> me-1"></i>
                                                        <?php echo ($change >= 0 ? '+' : '') . number_format($change); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($transaction['notes'])): ?>
                                                    <span class="text-muted small" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($transaction['notes']); ?>">
                                                        <?php echo htmlspecialchars(substr($transaction['notes'], 0, 30)); ?>
                                                        <?php if (strlen($transaction['notes']) > 30): ?>...<?php endif; ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="mdi mdi-dots-horizontal"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item" href="product-view.php?id=<?php echo $transaction['product_id']; ?>">
                                                                    <i class="mdi mdi-eye-outline me-1"></i> View Product
                                                                </a>
                                                            </li>
                                                            <?php if ($transaction['transaction_type'] == 'adjustment'): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="#" onclick="undoAdjustment(<?php echo $transaction['id']; ?>)">
                                                                    <i class="mdi mdi-undo me-1"></i> Undo Adjustment
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="#" onclick="deleteTransaction(<?php echo $transaction['id']; ?>)">
                                                                    <i class="mdi mdi-delete-outline me-1"></i> Delete
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php 
                                            $counter++;
                                            endwhile; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <div class="row mt-3">
                                    <div class="col-sm-12 col-md-5">
                                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                            Showing <?php echo number_format(min($limit, $total_count)); ?> of <?php echo number_format($total_count); ?> transactions
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-7">
                                        <div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                                            <ul class="pagination justify-content-end">
                                                <?php if ($page > 1): ?>
                                                <li class="paginate_button page-item previous">
                                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">Previous</a>
                                                </li>
                                                <?php else: ?>
                                                <li class="paginate_button page-item previous disabled">
                                                    <a href="#" class="page-link">Previous</a>
                                                </li>
                                                <?php endif; ?>
                                                
                                                <?php
                                                $start_page = max(1, $page - 2);
                                                $end_page = min($total_pages, $start_page + 4);
                                                
                                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="paginate_button page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-link"><?php echo $i; ?></a>
                                                </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($page < $total_pages): ?>
                                                <li class="paginate_button page-item next">
                                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">Next</a>
                                                </li>
                                                <?php else: ?>
                                                <li class="paginate_button page-item next disabled">
                                                    <a href="#" class="page-link">Next</a>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="mdi mdi-history display-4"></i>
                                        <h4 class="mt-3">No Transaction History Found</h4>
                                        <p class="mb-0">No inventory transactions recorded yet.</p>
                                        <div class="mt-3">
                                            <a href="add-stock.php" class="btn btn-primary me-2">
                                                <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                            </a>
                                            <a href="stock-adjustment.php" class="btn btn-success">
                                                <i class="mdi mdi-swap-horizontal me-1"></i> Make Adjustment
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Summary Chart -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-chart-bar me-1"></i> Monthly Inventory Movement (Last 6 Months)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($monthly_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Month</th>
                                                <th>Purchased</th>
                                                <th>Sold</th>
                                                <th>Increased</th>
                                                <th>Decreased</th>
                                                <th>Net Change</th>
                                                <th>Trend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            while ($month = mysqli_fetch_assoc($monthly_result)): 
                                                $net_change = $month['purchased'] - $month['sold'] + $month['increased'] - $month['decreased'];
                                                $trend_class = $net_change >= 0 ? 'text-success' : 'text-danger';
                                                $trend_icon = $net_change >= 0 ? 'mdi-trending-up' : 'mdi-trending-down';
                                                
                                                // Format month name
                                                $month_name = date('M Y', strtotime($month['month'] . '-01'));
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $month_name; ?></strong></td>
                                                <td class="text-success">+<?php echo number_format($month['purchased']); ?></td>
                                                <td class="text-danger">-<?php echo number_format($month['sold']); ?></td>
                                                <td class="text-info">+<?php echo number_format($month['increased']); ?></td>
                                                <td class="text-warning">-<?php echo number_format($month['decreased']); ?></td>
                                                <td class="<?php echo $trend_class; ?>">
                                                    <?php echo ($net_change >= 0 ? '+' : '') . number_format($net_change); ?>
                                                </td>
                                                <td class="<?php echo $trend_class; ?>">
                                                    <i class="mdi <?php echo $trend_icon; ?>"></i>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-chart-line display-4"></i>
                                        <h5 class="mt-2">No Monthly Data</h5>
                                        <p>No transaction data available for chart</p>
                                    </div>
                                </div>
                                <?php endif; ?>
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
                <p>Are you sure you want to delete this transaction?</p>
                <p class="text-danger"><small>Warning: This will also reverse the stock quantity change. This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete Transaction</button>
            </div>
        </div>
    </div>
</div>

<!-- Undo Adjustment Modal -->
<div class="modal fade" id="undoModal" tabindex="-1" aria-labelledby="undoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="undoModalLabel">Undo Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to undo this adjustment?</p>
                <p>This will reverse the stock quantity to its previous value.</p>
                <div class="alert alert-info">
                    <i class="mdi mdi-information-outline me-2"></i>
                    A new adjustment record will be created to track this change.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmUndo">Undo Adjustment</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Variables for modals
let deleteTransactionId = null;
let undoTransactionId = null;

// Function to export history
function exportHistory() {
    // Get filter parameters
    const params = new URLSearchParams(window.location.search);
    params.append('export', '1');
    
    // Show loading
    const exportBtn = document.querySelector('button[onclick="exportHistory()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Exporting...';
    exportBtn.disabled = true;
    
    // Create download link
    const url = 'export-history.php?' + params.toString();
    const link = document.createElement('a');
    link.href = url;
    link.download = 'inventory-history-' + new Date().toISOString().split('T')[0] + '.xlsx';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }, 2000);
}

// Function to print history
function printHistory() {
    // Get current filters for print title
    const typeFilter = document.getElementById('typeFilter').value;
    const productFilter = document.getElementById('productFilter').value;
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    const dateTo = document.querySelector('input[name="date_to"]').value;
    
    // Create print window
    const printWindow = window.open('', '_blank');
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Inventory History Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
                .report-title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px; }
                .report-subtitle { font-size: 14px; color: #666; }
                .filters { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
                .filters h6 { margin-bottom: 10px; color: #495057; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; text-align: left; font-weight: bold; }
                .table td { border: 1px solid #dee2e6; padding: 8px; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                .badge-success { background-color: #d4edda; color: #155724; }
                .badge-danger { background-color: #f8d7da; color: #721c24; }
                .badge-info { background-color: #d1ecf1; color: #0c5460; }
                .badge-warning { background-color: #fff3cd; color: #856404; }
                .text-success { color: #28a745; }
                .text-danger { color: #dc3545; }
                .text-warning { color: #ffc107; }
                .text-info { color: #17a2b8; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
                @media print {
                    .no-print { display: none; }
                    .table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="report-title">Inventory Transaction History</div>
                <div class="report-subtitle">APR Water Agencies</div>
                <div class="report-subtitle">Printed on: ${new Date().toLocaleString()}</div>
            </div>
            
            <div class="filters">
                <h6>Active Filters:</h6>
                <div>
                    <strong>Type:</strong> ${typeFilter === 'all' ? 'All Types' : typeFilter.charAt(0).toUpperCase() + typeFilter.slice(1)} |
                    <strong>Date Range:</strong> ${dateFrom ? dateFrom : 'All'} to ${dateTo ? dateTo : 'All'}
                </div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Previous</th>
                        <th>New</th>
                        <th>Change</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    ${Array.from(document.querySelectorAll('#historyTable tbody tr')).map(row => {
                        const cells = row.cells;
                        return `
                        <tr>
                            <td>${cells[1].querySelector('div').innerText.replace(/\n/g, ', ')}</td>
                            <td>${cells[2].querySelector('.font-size-14').innerText}</td>
                            <td>${cells[3].innerText.trim()}</td>
                            <td>${cells[4].innerText.trim()}</td>
                            <td>${cells[6].innerText.trim()}</td>
                            <td>${cells[7].innerText.trim()}</td>
                            <td>${cells[8].innerText.trim()}</td>
                            <td>${cells[9].innerText.trim()}</td>
                        </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
            
            <div class="footer">
                APR Water Agencies - Inventory Management System<br>
                Generated on: ${new Date().toLocaleString()}<br>
                Page ${window.location.href}
            </div>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Now</button>
                <button onclick="window.close()" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
}

// Function to delete transaction
function deleteTransaction(transactionId) {
    deleteTransactionId = transactionId;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

// Function to undo adjustment
function undoAdjustment(transactionId) {
    undoTransactionId = transactionId;
    const undoModal = new bootstrap.Modal(document.getElementById('undoModal'));
    undoModal.show();
}

// Confirm delete
document.getElementById('confirmDelete').addEventListener('click', function() {
    if (deleteTransactionId) {
        fetch('delete-transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + deleteTransactionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Transaction deleted successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        })
        .catch(error => {
            alert('Network error: ' + error);
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        });
    }
});

// Confirm undo
document.getElementById('confirmUndo').addEventListener('click', function() {
    if (undoTransactionId) {
        fetch('undo-adjustment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + undoTransactionId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Adjustment undone successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
            bootstrap.Modal.getInstance(document.getElementById('undoModal')).hide();
        })
        .catch(error => {
            alert('Network error: ' + error);
            bootstrap.Modal.getInstance(document.getElementById('undoModal')).hide();
        });
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Set date fields to today if empty
    const dateToField = document.querySelector('input[name="date_to"]');
    if (dateToField && !dateToField.value) {
        dateToField.value = new Date().toISOString().split('T')[0];
    }
    
    // Set date from field to 30 days ago if empty
    const dateFromField = document.querySelector('input[name="date_from"]');
    if (dateFromField && !dateFromField.value) {
        const date = new Date();
        date.setDate(date.getDate() - 30);
        dateFromField.value = date.toISOString().split('T')[0];
    }
    
    // Quick filter buttons
    const quickFilters = document.querySelectorAll('.quick-filter');
    quickFilters.forEach(button => {
        button.addEventListener('click', function() {
            const days = this.getAttribute('data-days');
            const date = new Date();
            date.setDate(date.getDate() - days);
            dateFromField.value = date.toISOString().split('T')[0];
            document.getElementById('filterForm').submit();
        });
    });
    
    // Auto-submit form when certain filters change
    const autoSubmitFilters = ['typeFilter', 'productFilter'];
    autoSubmitFilters.forEach(filterId => {
        const filter = document.getElementById(filterId);
        if (filter) {
            filter.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        }
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+F to focus on filter form
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('typeFilter').focus();
        }
        // Ctrl+P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printHistory();
        }
        // Ctrl+E to export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportHistory();
        }
        // Escape to clear filters
        if (e.key === 'Escape') {
            window.location.href = 'inventory-history.php';
        }
    });
});

// Function to clear all filters
function clearFilters() {
    window.location.href = 'inventory-history.php';
}

// Function to apply quick date filter
function applyQuickFilter(days) {
    const dateFromField = document.querySelector('input[name="date_from"]');
    const dateToField = document.querySelector('input[name="date_to"]');
    
    if (dateFromField && dateToField) {
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - days);
        
        dateFromField.value = startDate.toISOString().split('T')[0];
        dateToField.value = endDate.toISOString().split('T')[0];
        document.getElementById('filterForm').submit();
    }
}
</script>

</body>

</html>