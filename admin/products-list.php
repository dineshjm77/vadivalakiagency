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
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                                        <h4 class="mb-0">
                                            <span id="total-products">0</span>
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
                                            <span id="active-products">0</span>
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
                                            <i class="mdi mdi-package-variant-closed"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Out of Stock</p>
                                        <h4 class="mb-0">
                                            <span id="outofstock-products">0</span>
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
                                            <span id="inactive-products">0</span>
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
                                        <h4 class="card-title mb-0">All Products</h4>
                                        <p class="card-title-desc">Manage your product inventory</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                            <div class="search-box">
                                                <input type="text" class="form-control" id="searchInput" placeholder="Search products...">
                                                <i class="ri-search-line search-icon"></i>
                                            </div>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="mdi mdi-filter me-1"></i> Filter
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="all">All Products</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="active">Active Only</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="inactive">Inactive Only</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="out_of_stock">Out of Stock</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="low_stock">Low Stock (< 10)</a></li>
                                                    <div class="dropdown-divider"></div>
                                                    <li><a class="dropdown-item" href="#" id="clearFilters">Clear All Filters</a></li>
                                                </ul>
                                            </div>
                                            <a href="add-product.php" class="btn btn-success">
                                                <i class="mdi mdi-plus-circle-outline me-1"></i> Add New
                                            </a>
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
                                $out_of_stock = 0;
                                $low_stock = 0;
                                
                                // Build query with joins for category and brand names
                                $sql = "SELECT 
                                    p.*,
                                    c.category_name,
                                    b.brand_name
                                    FROM products p
                                    LEFT JOIN categories c ON p.category_id = c.id
                                    LEFT JOIN brands b ON p.brand_id = b.id
                                    ORDER BY p.created_at DESC";
                                
                                $result = mysqli_query($conn, $sql);
                                
                                if ($result) {
                                    // First pass to count
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $total++;
                                        if ($row['status'] == 'active') $active++;
                                        if ($row['status'] == 'inactive') $inactive++;
                                        if ($row['status'] == 'out_of_stock') $out_of_stock++;
                                        if ($row['quantity'] < 10) $low_stock++;
                                    }
                                    
                                    // Reset pointer for displaying data
                                    mysqli_data_seek($result, 0);
                                } else {
                                    $result = false;
                                }
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="productsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Product Code</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Brand</th>
                                                <th>Stock Price</th>
                                                <th>Customer Price</th>
                                                <th>Quantity</th>
                                                <th>Profit</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productsTableBody">
                                            <?php
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                $counter = 1;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    // Stock status
                                                    $stock_status = '';
                                                    $stock_class = '';
                                                    if ($row['quantity'] == 0) {
                                                        $stock_status = 'Out of Stock';
                                                        $stock_class = 'bg-danger';
                                                    } elseif ($row['quantity'] < 10) {
                                                        $stock_status = 'Low Stock';
                                                        $stock_class = 'bg-warning';
                                                    } else {
                                                        $stock_status = 'In Stock';
                                                        $stock_class = 'bg-success';
                                                    }
                                                    
                                                    // Status badge color
                                                    $status_class = '';
                                                    if ($row['status'] == 'active') $status_class = 'badge-soft-success';
                                                    elseif ($row['status'] == 'inactive') $status_class = 'badge-soft-danger';
                                                    elseif ($row['status'] == 'out_of_stock') $status_class = 'badge-soft-warning';
                                                    
                                                    // Calculate values
                                                    $total_stock_value = $row['stock_price'] * $row['quantity'];
                                                    $total_selling_value = $row['customer_price'] * $row['quantity'];
                                                    ?>
                                                    <tr data-status="<?php echo $row['status']; ?>" data-quantity="<?php echo $row['quantity']; ?>">
                                                        <td><?php echo $counter; ?></td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo $row['product_code']; ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($row['product_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="product-view.php?id=<?php echo $row['id']; ?>" class="text-dark">
                                                                            <?php echo htmlspecialchars($row['product_name']); ?>
                                                                        </a>
                                                                    </h5>
                                                                    <?php if (!empty($row['description'])): ?>
                                                                    <p class="text-muted mb-0 small text-truncate" style="max-width: 200px;">
                                                                        <?php echo htmlspecialchars(substr($row['description'], 0, 50)) . '...'; ?>
                                                                    </p>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info-subtle text-info">
                                                                <?php echo !empty($row['category_name']) ? $row['category_name'] : 'N/A'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($row['brand_name'])): ?>
                                                            <span class="badge bg-secondary-subtle text-secondary">
                                                                <?php echo $row['brand_name']; ?>
                                                            </span>
                                                            <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium">₹<?php echo number_format($row['stock_price'], 2); ?></span>
                                                                <p class="text-muted mb-0 small">Cost</p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium text-success">₹<?php echo number_format($row['customer_price'], 2); ?></span>
                                                                <p class="text-muted mb-0 small">Selling</p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium <?php echo $row['quantity'] < 10 ? 'text-warning' : 'text-success'; ?>">
                                                                    <?php echo $row['quantity']; ?>
                                                                </span>
                                                                <p class="text-muted mb-0 small">
                                                                    <span class="badge <?php echo $stock_class; ?> font-size-10">
                                                                        <?php echo $stock_status; ?>
                                                                    </span>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="text-center">
                                                                <span class="fw-medium <?php echo $row['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                    ₹<?php echo number_format($row['profit'], 2); ?>
                                                                </span>
                                                                <p class="text-muted mb-0 small">
                                                                    <?php echo number_format($row['profit_percentage'], 1); ?>%
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?> font-size-12">
                                                                <?php 
                                                                echo ucfirst(str_replace('_', ' ', $row['status']));
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="product-view.php?id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-eye-outline me-1"></i> View
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="product-edit.php?id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="add-stock.php?product_id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger delete-product" href="#" 
                                                                           data-id="<?php echo $row['id']; ?>" 
                                                                           data-name="<?php echo htmlspecialchars($row['product_name']); ?>">
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
                                                            <i class="mdi mdi-package-variant-closed display-4"></i>
                                                            <h5 class="mt-2">No Products Found</h5>
                                                            <p>Click on "Add New" to add your first product</p>
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
                                            Showing <?php echo $total; ?> products
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

                <!-- Stock Summary Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-chart-bar me-1"></i> Inventory Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Reconnect to get stock summary
                                include('config/config.php');
                                $summary_sql = "SELECT 
                                    SUM(quantity) as total_quantity,
                                    SUM(stock_price * quantity) as total_stock_value,
                                    SUM(customer_price * quantity) as total_selling_value,
                                    SUM(profit * quantity) as total_profit
                                    FROM products WHERE status = 'active'";
                                $summary_result = mysqli_query($conn, $summary_sql);
                                $summary = mysqli_fetch_assoc($summary_result);
                                mysqli_close($conn);
                                ?>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Items</h6>
                                        <h4 class="mb-0"><?php echo number_format($summary['total_quantity'] ?? 0); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Cost Value</h6>
                                        <h4 class="mb-0 text-warning">₹<?php echo number_format($summary['total_stock_value'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Selling Value</h6>
                                        <h4 class="mb-0 text-success">₹<?php echo number_format($summary['total_selling_value'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="col-md-3">
                                        <h6 class="text-muted">Total Potential Profit</h6>
                                        <h4 class="mb-0 text-primary">₹<?php echo number_format($summary['total_profit'] ?? 0, 2); ?></h4>
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
                <p class="text-danger">This action cannot be undone. All product data will be removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
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
    document.getElementById('total-products').textContent = '<?php echo $total; ?>';
    document.getElementById('active-products').textContent = '<?php echo $active; ?>';
    document.getElementById('inactive-products').textContent = '<?php echo $inactive; ?>';
    document.getElementById('outofstock-products').textContent = '<?php echo $out_of_stock; ?>';
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#productsTableBody tr');
    
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

// Filter by status
document.querySelectorAll('.filter-option').forEach(option => {
    option.addEventListener('click', function(e) {
        e.preventDefault();
        const filterType = this.getAttribute('data-status');
        const rows = document.querySelectorAll('#productsTableBody tr');
        
        rows.forEach(row => {
            if (filterType === 'all') {
                row.style.display = '';
            } else if (filterType === 'active' || filterType === 'inactive' || filterType === 'out_of_stock') {
                const rowStatus = row.getAttribute('data-status');
                if (rowStatus === filterType) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            } else if (filterType === 'low_stock') {
                const quantity = parseInt(row.getAttribute('data-quantity'));
                if (quantity < 10) {
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
    const rows = document.querySelectorAll('#productsTableBody tr');
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
    if (e.target && e.target.classList.contains('delete-product')) {
        e.preventDefault();
        deleteId = e.target.getAttribute('data-id');
        deleteName = e.target.getAttribute('data-name');
        document.getElementById('deleteName').textContent = deleteName;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
});

document.getElementById('confirmDelete').addEventListener('click', function() {
    if (deleteId) {
        // Send AJAX request to delete
        fetch('delete-product.php', {
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
                const row = document.querySelector(`tr td .delete-product[data-id="${deleteId}"]`)?.closest('tr');
                if (row) {
                    row.remove();
                    // Show success message
                    showAlert('Product deleted successfully!', 'success');
                    // Update counters
                    updateCounters();
                    updateVisibleCount();
                }
            } else {
                showAlert('Error deleting product: ' + data.message, 'danger');
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
    const visibleRows = document.querySelectorAll('#productsTableBody tr:not([style*="display: none"])');
    const countElement = document.querySelector('.dataTables_info');
    
    if (countElement) {
        countElement.textContent = `Showing ${visibleRows.length} products`;
    }
}

// Update counters after delete
function updateCounters() {
    const totalRows = document.querySelectorAll('#productsTableBody tr').length;
    document.getElementById('total-products').textContent = totalRows;
    
    // Could implement more detailed counter updates here
    // For now just updating total count
}
</script>

</body>

</html>