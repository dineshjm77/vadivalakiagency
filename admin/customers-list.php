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

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-account-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Customers</p>
                                        <h4 class="mb-0">
                                            <span id="total-customers">0</span>
                                        </h4>
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
                                            <i class="mdi mdi-check-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Active</p>
                                        <h4 class="mb-0">
                                            <span id="active-customers">0</span>
                                        </h4>
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
                                            <i class="mdi mdi-credit-card-clock"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Credit Customers</p>
                                        <h4 class="mb-0">
                                            <span id="credit-customers">0</span>
                                        </h4>
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
                                            <i class="mdi mdi-close-circle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Inactive</p>
                                        <h4 class="mb-0">
                                            <span id="inactive-customers">0</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h4 class="card-title mb-0">All Customers</h4>
                                        <p class="card-title-desc">Manage your customer database</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                            <div class="search-box">
                                                <input type="text" class="form-control" id="searchInput" placeholder="Search customers...">
                                                <i class="ri-search-line search-icon"></i>
                                            </div>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="mdi mdi-filter me-1"></i> Filter
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="all">All Customers</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="active">Active Only</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="inactive">Inactive Only</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="blocked">Blocked</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-type="retail">Retail Shops</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-type="wholesale">Wholesale Dealers</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-type="hotel">Hotels/Restaurants</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-type="credit">Credit Customers</a></li>
                                                    <div class="dropdown-divider"></div>
                                                    <li><a class="dropdown-item" href="#" id="clearFilters">Clear All Filters</a></li>
                                                </ul>
                                            </div>
                                            <a href="add-customer.php" class="btn btn-success">
                                                <i class="mdi mdi-account-plus me-1"></i> Add New
                                            </a>
                                            <button type="button" class="btn btn-info" onclick="exportCustomers()">
                                                <i class="mdi mdi-file-export me-1"></i> Export
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                // Database connection
                                include('config/config.php');
                                
                                // Initialize counters
                                $total = 0;
                                $active = 0;
                                $inactive = 0;
                                $blocked = 0;
                                $credit = 0;
                                
                                // Build query
                                $sql = "SELECT * FROM customers ORDER BY created_at DESC";
                                
                                $result = mysqli_query($conn, $sql);
                                
                                if ($result) {
                                    // First pass to count
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $total++;
                                        if ($row['status'] == 'active') $active++;
                                        if ($row['status'] == 'inactive') $inactive++;
                                        if ($row['status'] == 'blocked') $blocked++;
                                        if ($row['credit_limit'] > 0) $credit++;
                                    }
                                    
                                    // Reset pointer for displaying data
                                    mysqli_data_seek($result, 0);
                                } else {
                                    $result = false;
                                }
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="customersTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Customer Code</th>
                                                <th>Shop Name</th>
                                                <th>Customer Name</th>
                                                <th>Contact</th>
                                                <th>Type</th>
                                                <th>Credit Limit</th>
                                                <th>Balance</th>
                                                <th>Status</th>
                                                <th>Last Order</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="customersTableBody">
                                            <?php
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                $counter = 1;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    // Status badge color
                                                    $status_class = '';
                                                    if ($row['status'] == 'active') $status_class = 'badge-soft-success';
                                                    elseif ($row['status'] == 'inactive') $status_class = 'badge-soft-danger';
                                                    elseif ($row['status'] == 'blocked') $status_class = 'badge-soft-warning';
                                                    
                                                    // Customer type badge
                                                    $type_class = '';
                                                    $type_text = '';
                                                    switch($row['customer_type']) {
                                                        case 'retail': 
                                                            $type_class = 'bg-info-subtle text-info';
                                                            $type_text = 'Retail';
                                                            break;
                                                        case 'wholesale': 
                                                            $type_class = 'bg-primary-subtle text-primary';
                                                            $type_text = 'Wholesale';
                                                            break;
                                                        case 'hotel': 
                                                            $type_class = 'bg-warning-subtle text-warning';
                                                            $type_text = 'Hotel';
                                                            break;
                                                        case 'office': 
                                                            $type_class = 'bg-success-subtle text-success';
                                                            $type_text = 'Office';
                                                            break;
                                                        case 'residential': 
                                                            $type_class = 'bg-secondary-subtle text-secondary';
                                                            $type_text = 'Residential';
                                                            break;
                                                        default: 
                                                            $type_class = 'bg-light text-dark';
                                                            $type_text = 'Other';
                                                    }
                                                    
                                                    // Balance color
                                                    $balance_class = $row['current_balance'] > 0 ? 'text-danger' : ($row['current_balance'] < 0 ? 'text-success' : 'text-muted');
                                                    $balance_text = $row['current_balance'] > 0 ? 'Due: ' : ($row['current_balance'] < 0 ? 'Advance: ' : '');
                                                    $balance_display = abs($row['current_balance']);
                                                    
                                                    // Credit limit indicator
                                                    $credit_usage = $row['credit_limit'] > 0 ? ($row['current_balance'] / $row['credit_limit'] * 100) : 0;
                                                    $credit_class = $credit_usage > 90 ? 'bg-danger' : ($credit_usage > 70 ? 'bg-warning' : 'bg-success');
                                                    
                                                    // Format last purchase date
                                                    $last_purchase = !empty($row['last_purchase_date']) ? date('d M, Y', strtotime($row['last_purchase_date'])) : 'Never';
                                                    ?>
                                                    <tr data-status="<?php echo $row['status']; ?>" 
                                                        data-type="<?php echo $row['customer_type']; ?>"
                                                        data-credit="<?php echo $row['credit_limit'] > 0 ? 'credit' : 'cash'; ?>">
                                                        <td><?php echo $counter; ?></td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo $row['customer_code']; ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($row['shop_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="customer-view.php?id=<?php echo $row['id']; ?>" class="text-dark">
                                                                            <?php echo htmlspecialchars($row['shop_name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <?php if (!empty($row['shop_location'])): ?>
                                                                    <p class="text-muted mb-0 small text-truncate" style="max-width: 200px;">
                                                                        <i class="mdi mdi-map-marker-outline me-1"></i>
                                                                        <?php echo htmlspecialchars(substr($row['shop_location'], 0, 40)) . '...'; ?>
                                                                    </p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo htmlspecialchars($row['customer_name']); ?></span>
                                                            <?php if (!empty($row['email'])): ?>
                                                            <p class="text-muted mb-0 small">
                                                                <i class="mdi mdi-email-outline me-1"></i>
                                                                <?php echo $row['email']; ?>
                                                            </p>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <span class="fw-medium"><?php echo $row['customer_contact']; ?></span>
                                                                <?php if (!empty($row['alternate_contact'])): ?>
                                                                <p class="text-muted mb-0 small">
                                                                    Alt: <?php echo $row['alternate_contact']; ?>
                                                                </p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $type_class; ?>">
                                                                <?php echo $type_text; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <span class="fw-medium">₹<?php echo number_format($row['credit_limit'], 2); ?></span>
                                                                <?php if ($row['credit_limit'] > 0): ?>
                                                                <div class="progress mt-1" style="height: 4px;">
                                                                    <div class="progress-bar <?php echo $credit_class; ?>" 
                                                                         style="width: <?php echo min($credit_usage, 100); ?>%">
                                                                    </div>
                                                                </div>
                                                                <small class="text-muted"><?php echo number_format($credit_usage, 0); ?>% used</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium <?php echo $balance_class; ?>">
                                                                    <?php echo $balance_text; ?>₹<?php echo number_format($balance_display, 2); ?>
                                                                </span>
                                                                <p class="text-muted mb-0 small">
                                                                    Total: ₹<?php echo number_format($row['total_purchases'], 2); ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?> font-size-12">
                                                                <?php echo ucfirst($row['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted"><?php echo $last_purchase; ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="customer-view.php?id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-eye-outline me-1"></i> View
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="customer-edit.php?id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="create-invoice.php?customer_id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-receipt me-1"></i> Create Invoice
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="payment-receive.php?customer_id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-cash-multiple me-1"></i> Receive Payment
                                                                        </a>
                                                                    </li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <?php if ($row['status'] == 'active'): ?>
                                                                    <li>
                                                                        <a class="dropdown-item text-warning" href="#" onclick="changeStatus(<?php echo $row['id']; ?>, 'inactive')">
                                                                            <i class="mdi mdi-account-off me-1"></i> Mark Inactive
                                                                        </a>
                                                                    </li>
                                                                    <?php else: ?>
                                                                    <li>
                                                                        <a class="dropdown-item text-success" href="#" onclick="changeStatus(<?php echo $row['id']; ?>, 'active')">
                                                                            <i class="mdi mdi-account-check me-1"></i> Mark Active
                                                                        </a>
                                                                    </li>
                                                                    <?php endif; ?>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger delete-customer" href="#" 
                                                                           data-id="<?php echo $row['id']; ?>" 
                                                                           data-name="<?php echo htmlspecialchars($row['shop_name']); ?>">
                                                                            <i class="mdi mdi-delete-outline me-1"></i> Delete
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $counter++;
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-account-multiple display-4"></i>
                                                            <h5 class="mt-2">No Customers Found</h5>
                                                            <p>Click on "Add New" to add your first customer</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                            
                                            if (isset($conn) && $conn) {
                                                mysqli_close($conn);
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <div class="row mt-3">
                                    <div class="col-sm-12 col-md-5">
                                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">
                                            Showing <?php echo $total; ?> customers
                                        </div>
                                    </div>
                                    <div class="col-sm-12 col-md-7">
                                        <div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                                            <ul class="pagination justify-content-end">
                                                <li class="paginate_button page-item previous disabled" id="datatable_previous">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="0" tabindex="0" class="page-link">Previous</a>
                                                </li>
                                                <li class="paginate_button page-item active">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="1" tabindex="0" class="page-link">1</a>
                                                </li>
                                                <li class="paginate_button page-item next" id="datatable_next">
                                                    <a href="#" aria-controls="datatable" data-dt-idx="2" tabindex="0" class="page-link">Next</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Summary Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-chart-bar me-1"></i> Customer Analytics
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Reconnect to get customer summary
                                include('config/config.php');
                                $summary_sql = "SELECT 
                                    COUNT(*) as total_customers,
                                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers,
                                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_customers,
                                    SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_customers,
                                    SUM(CASE WHEN credit_limit > 0 THEN 1 ELSE 0 END) as credit_customers,
                                    SUM(total_purchases) as total_sales,
                                    SUM(current_balance) as total_balance,
                                    SUM(CASE WHEN current_balance > 0 THEN current_balance ELSE 0 END) as total_due,
                                    SUM(CASE WHEN current_balance < 0 THEN ABS(current_balance) ELSE 0 END) as total_advance
                                    FROM customers";
                                $summary_result = mysqli_query($conn, $summary_sql);
                                $summary = mysqli_fetch_assoc($summary_result);
                                mysqli_close($conn);
                                ?>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Sales</h6>
                                        <h4 class="mb-0 text-success">₹<?php echo number_format($summary['total_sales'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Due</h6>
                                        <h4 class="mb-0 text-danger">₹<?php echo number_format($summary['total_due'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Advance</h6>
                                        <h4 class="mb-0 text-info">₹<?php echo number_format($summary['total_advance'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Net Balance</h6>
                                        <h4 class="mb-0 <?php echo ($summary['total_balance'] ?? 0) >= 0 ? 'text-warning' : 'text-success'; ?>">
                                            ₹<?php echo number_format($summary['total_balance'] ?? 0, 2); ?>
                                        </h4>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h6 class="text-muted mb-3">Customer Type Distribution</h6>
                                        <?php
                                        // Get customer type distribution
                                        include('config/config.php');
                                        $type_sql = "SELECT 
                                            customer_type,
                                            COUNT(*) as count,
                                            SUM(total_purchases) as sales
                                            FROM customers 
                                            WHERE status = 'active'
                                            GROUP BY customer_type";
                                        $type_result = mysqli_query($conn, $type_sql);
                                        
                                        $total_active = $summary['active_customers'] ?? 1; // Avoid division by zero
                                        ?>
                                        <div class="row">
                                            <?php while ($type = mysqli_fetch_assoc($type_result)): 
                                                $percentage = ($type['count'] / $total_active) * 100;
                                                $type_name = ucfirst($type['customer_type']);
                                            ?>
                                            <div class="col-md-2 col-6 mb-3">
                                                <div class="text-center">
                                                    <h6 class="text-muted mb-1"><?php echo $type_name; ?></h6>
                                                    <h5 class="mb-1"><?php echo $type['count']; ?></h5>
                                                    <div class="progress" style="height: 4px;">
                                                        <div class="progress-bar bg-info" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                                </div>
                                            </div>
                                            <?php endwhile; 
                                            mysqli_close($conn);
                                            ?>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
                <p class="text-danger">This action cannot be undone. All customer data including invoices and payments will be removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalLabel">Change Customer Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="statusMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusChange">Change Status</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Update counters with actual values from PHP
document.addEventListener('DOMContentLoaded', function() {
    // Update counter values
    document.getElementById('total-customers').textContent = '<?php echo $total; ?>';
    document.getElementById('active-customers').textContent = '<?php echo $active; ?>';
    document.getElementById('inactive-customers').textContent = '<?php echo $inactive + $blocked; ?>';
    document.getElementById('credit-customers').textContent = '<?php echo $credit; ?>';
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#customersTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update visible count
    updateVisibleCount();
});

// Filter by status/type
document.querySelectorAll('.filter-option').forEach(option => {
    option.addEventListener('click', function(e) {
        e.preventDefault();
        const filterType = this.getAttribute('data-status') || this.getAttribute('data-type');
        const rows = document.querySelectorAll('#customersTableBody tr');
        
        rows.forEach(row => {
            if (filterType === 'all') {
                row.style.display = '';
            } else if (filterType === 'active' || filterType === 'inactive' || filterType === 'blocked') {
                const rowStatus = row.getAttribute('data-status');
                if (rowStatus === filterType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            } else if (filterType === 'retail' || filterType === 'wholesale' || filterType === 'hotel' || 
                       filterType === 'office' || filterType === 'residential') {
                const rowType = row.getAttribute('data-type');
                if (rowType === filterType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            } else if (filterType === 'credit') {
                const rowCredit = row.getAttribute('data-credit');
                if (rowCredit === 'credit') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
        
        // Update button text
        const filterBtn = document.querySelector('.btn-primary .mdi-filter').parentElement;
        filterBtn.innerHTML = '<i class="mdi mdi-filter me-1"></i> Filter: ' + this.textContent;
        
        // Update visible count
        updateVisibleCount();
    });
});

// Clear filters
document.getElementById('clearFilters').addEventListener('click', function(e) {
    e.preventDefault();
    const rows = document.querySelectorAll('#customersTableBody tr');
    rows.forEach(row => row.style.display = '');
    
    // Reset filter button text
    const filterBtn = document.querySelector('.btn-primary .mdi-filter').parentElement;
    filterBtn.innerHTML = '<i class="mdi mdi-filter me-1"></i> Filter';
    
    // Clear search input
    document.getElementById('searchInput').value = '';
    
    // Update visible count
    updateVisibleCount();
});

// Delete confirmation
let deleteId = null;
let deleteName = null;

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('delete-customer')) {
        e.preventDefault();
        deleteId = e.target.getAttribute('data-id');
        deleteName = e.target.getAttribute('data-name');
        document.getElementById('deleteName').textContent = deleteName;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
});

// Status change
let statusCustomerId = null;
let newStatus = null;

function changeStatus(customerId, status) {
    statusCustomerId = customerId;
    newStatus = status;
    
    const statusMessage = document.getElementById('statusMessage');
    const action = status === 'active' ? 'activate' : 'deactivate';
    statusMessage.textContent = `Are you sure you want to ${action} this customer?`;
    
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    modal.show();
}

// Confirm delete
document.getElementById('confirmDelete').addEventListener('click', function() {
    if (deleteId) {
        // Send AJAX request to delete
        fetch('delete-customer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + deleteId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove row from table
                const row = document.querySelector(`tr td .delete-customer[data-id="${deleteId}"]`)?.closest('tr');
                if (row) {
                    row.remove();
                    // Show success message
                    showAlert('Customer deleted successfully!', 'success');
                    // Update counters
                    updateCounters();
                    updateVisibleCount();
                }
            } else {
                showAlert('Error deleting customer: ' + data.message, 'danger');
            }
            
            // Hide modal
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        })
        .catch(error => {
            showAlert('Network error: ' + error, 'danger');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        });
    }
});

// Confirm status change
document.getElementById('confirmStatusChange').addEventListener('click', function() {
    if (statusCustomerId && newStatus) {
        fetch('change-customer-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + statusCustomerId + '&status=' + newStatus
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to reflect changes
                window.location.reload();
            } else {
                showAlert('Error changing status: ' + data.message, 'danger');
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
            }
        })
        .catch(error => {
            showAlert('Network error: ' + error, 'danger');
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
        });
    }
});

// Export customers
function exportCustomers() {
    // Get filter parameters
    const searchTerm = document.getElementById('searchInput').value;
    
    // Show loading
    const exportBtn = document.querySelector('button[onclick="exportCustomers()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Exporting...';
    exportBtn.disabled = true;
    
    // Create download link
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    params.append('export', '1');
    
    const url = 'export-customers.php?' + params.toString();
    const link = document.createElement('a');
    link.href = url;
    link.download = 'customers-' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button
    setTimeout(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    }, 2000);
}

// Helper function to show alerts
function showAlert(message, type) {
    // Remove any existing alerts
    const existingAlert = document.querySelector('.alert-dismissible');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Update counters based on visible rows
function updateVisibleCount() {
    const visibleRows = document.querySelectorAll('#customersTableBody tr:not([style*="display: none"])');
    const countElement = document.querySelector('.dataTables_info');
    
    if (countElement) {
        countElement.textContent = `Showing ${visibleRows.length} customers`;
    }
}

// Update counters after delete
function updateCounters() {
    const totalRows = document.querySelectorAll('#customersTableBody tr').length;
    document.getElementById('total-customers').textContent = totalRows;
    
    // Update other counters (simplified - would need to recalculate)
    const activeRows = document.querySelectorAll('#customersTableBody tr[data-status="active"]').length;
    const creditRows = document.querySelectorAll('#customersTableBody tr[data-credit="credit"]').length;
    
    document.getElementById('active-customers').textContent = activeRows;
    document.getElementById('credit-customers').textContent = creditRows;
    document.getElementById('inactive-customers').textContent = totalRows - activeRows;
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Quick contact buttons
    document.querySelectorAll('.quick-call').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const phone = this.getAttribute('data-phone');
            if (phone) {
                window.location.href = 'tel:' + phone;
            }
        });
    });
    
    // Quick SMS buttons
    document.querySelectorAll('.quick-sms').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const phone = this.getAttribute('data-phone');
            if (phone) {
                window.location.href = 'sms:' + phone;
            }
        });
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+F to focus on search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
        // Ctrl+N for new customer
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'add-customer.php';
        }
        // Ctrl+E for export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportCustomers();
        }
        // Escape to clear filters
        if (e.key === 'Escape') {
            document.getElementById('clearFilters').click();
        }
    });
});

// Print customer list
function printCustomerList() {
    const printContent = document.getElementById('customersTable').outerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Customer List - APR Water Agencies</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { color: #666; margin-top: 10px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; }
                .table td { border: 1px solid #dee2e6; padding: 8px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Customer List</div>
                <div class="subtitle">APR Water Agencies</div>
                <div class="subtitle">Printed on: ${new Date().toLocaleString()}</div>
            </div>
            ${printContent}
            <div class="footer">
                Total Customers: <?php echo $total; ?>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}
</script>

</body>

</html>