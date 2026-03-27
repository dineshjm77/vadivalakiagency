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

                <!-- Status Summary Cards -->
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Line Men</p>
                                        <h4 class="mb-0">
                                            <span id="total-linemen">0</span>
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
                                            <i class="mdi mdi-account-check"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Active</p>
                                        <h4 class="mb-0">
                                            <span id="active-linemen">0</span>
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
                                            <i class="mdi mdi-account-clock"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">On Leave</p>
                                        <h4 class="mb-0">
                                            <span id="onleave-linemen">0</span>
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
                                            <i class="mdi mdi-account-off"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Inactive</p>
                                        <h4 class="mb-0">
                                            <span id="inactive-linemen">0</span>
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
                                        <h4 class="card-title mb-0">All Line Men</h4>
                                        <p class="card-title-desc">Manage your delivery staff</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
                                            <div class="search-box">
                                                <input type="text" class="form-control" id="searchInput" placeholder="Search line men...">
                                                <i class="ri-search-line search-icon"></i>
                                            </div>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="mdi mdi-filter me-1"></i> Filter
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="all">All Status</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="active">Active Only</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="inactive">Inactive Only</a></li>
                                                    <li><a class="dropdown-item filter-option" href="#" data-status="on_leave">On Leave</a></li>
                                                    <div class="dropdown-divider"></div>
                                                    <li><a class="dropdown-item" href="#" id="clearFilters">Clear All Filters</a></li>
                                                </ul>
                                            </div>
                                            <a href="add-lineman.php" class="btn btn-success">
                                                <i class="mdi mdi-plus-circle-outline me-1"></i> Add New
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                // Database connection
                                include('config/config.php');
                                
                                // Check if connection was successful
                                if (!$conn) {
                                    echo '<div class="alert alert-danger">Database connection failed. Please check your config.php file.</div>';
                                    $result = false;
                                    $total = 0;
                                    $active = 0;
                                    $inactive = 0;
                                    $on_leave = 0;
                                } else {
                                    // Fetch line men from database
                                    $sql = "SELECT * FROM linemen ORDER BY created_at DESC";
                                    $result = mysqli_query($conn, $sql);
                                    
                                    // Initialize counters
                                    $total = 0;
                                    $active = 0;
                                    $inactive = 0;
                                    $on_leave = 0;
                                    
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            // Update counters
                                            $total++;
                                            if ($row['status'] == 'active') $active++;
                                            if ($row['status'] == 'inactive') $inactive++;
                                            if ($row['status'] == 'on_leave') $on_leave++;
                                        }
                                        // Reset result pointer
                                        mysqli_data_seek($result, 0);
                                    }
                                }
                                ?>

                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="linemanTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Employee ID</th>
                                                <th>Line Man</th>
                                                <th>Contact</th>
                                                <th>Assigned Area</th>
                                                <th>Salary</th>
                                                <th>Commission</th>
                                                <th>Status</th>
                                                <th>Joined On</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="linemanTableBody">
                                            <?php
                                            if (isset($result) && $result && mysqli_num_rows($result) > 0) {
                                                $counter = 1;
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    // Format salary and commission
                                                    $salary = $row['salary'] > 0 ? '₹' . number_format($row['salary'], 2) : 'N/A';
                                                    $commission = $row['commission'] > 0 ? $row['commission'] . '%' : 'N/A';
                                                    
                                                    // Status badge color
                                                    $status_class = '';
                                                    if ($row['status'] == 'active') $status_class = 'badge-soft-success';
                                                    elseif ($row['status'] == 'inactive') $status_class = 'badge-soft-danger';
                                                    elseif ($row['status'] == 'on_leave') $status_class = 'badge-soft-warning';
                                                    
                                                    // Format date
                                                    $joined_date = date('d M, Y', strtotime($row['created_at']));
                                                    ?>
                                                    <tr data-status="<?php echo $row['status']; ?>">
                                                        <td><?php echo $counter; ?></td>
                                                        <td>
                                                            <span class="fw-medium"><?php echo $row['employee_id']; ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-shrink-0 me-3">
                                                                    <div class="avatar-xs">
                                                                        <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                            <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h5 class="font-size-14 mb-1">
                                                                        <a href="lineman-view.php?id=<?php echo $row['id']; ?>" class="text-dark">
                                                                            <?php echo $row['full_name']; ?>
                                                                        </a>
                                                                    </h5>
                                                                    <p class="text-muted mb-0"><?php echo $row['username']; ?></p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <i class="mdi mdi-phone me-1 text-muted"></i>
                                                                <?php echo $row['phone']; ?>
                                                                <?php if (!empty($row['email'])): ?>
                                                                <br>
                                                                <i class="mdi mdi-email me-1 text-muted"></i>
                                                                <small><?php echo $row['email']; ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info-subtle text-info">
                                                                <i class="mdi mdi-map-marker me-1"></i>
                                                                <?php echo $row['assigned_area']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $salary; ?></td>
                                                        <td><?php echo $commission; ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $status_class; ?> font-size-12">
                                                                <?php 
                                                                echo ucfirst(str_replace('_', ' ', $row['status']));
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $joined_date; ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                    <i class="mdi mdi-dots-horizontal"></i>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li>
                                                                        <a class="dropdown-item" href="lineman-view.php?id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-eye-outline me-1"></i> View
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item" href="lineman-edit.php?id=<?php echo $row['id']; ?>">
                                                                            <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a class="dropdown-item text-danger delete-lineman" href="#" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['full_name']); ?>">
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
                                                    <td colspan="10" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-account-off display-4"></i>
                                                            <h5 class="mt-2">No Line Men Found</h5>
                                                            <p>Click on "Add New" to add your first line man</p>
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
                                            Showing <?php echo $total; ?> line men
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

                <!-- Export Options Card -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Export Options</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary">
                                        <i class="mdi mdi-file-pdf-box me-1"></i> Export as PDF
                                    </button>
                                    <button type="button" class="btn btn-outline-success">
                                        <i class="mdi mdi-file-excel me-1"></i> Export as Excel
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary">
                                        <i class="mdi mdi-printer me-1"></i> Print
                                    </button>
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
                <p class="text-danger">This action cannot be undone. All associated data will be removed.</p>
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
    document.getElementById('total-linemen').textContent = '<?php echo $total; ?>';
    document.getElementById('active-linemen').textContent = '<?php echo $active; ?>';
    document.getElementById('inactive-linemen').textContent = '<?php echo $inactive; ?>';
    document.getElementById('onleave-linemen').textContent = '<?php echo $on_leave; ?>';
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#linemanTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update counters after search
    updateVisibleCounters();
});

// Filter by status
document.querySelectorAll('.filter-option').forEach(option => {
    option.addEventListener('click', function(e) {
        e.preventDefault();
        const status = this.getAttribute('data-status');
        const rows = document.querySelectorAll('#linemanTableBody tr');
        
        rows.forEach(row => {
            if (status === 'all') {
                row.style.display = '';
            } else {
                const rowStatus = row.getAttribute('data-status');
                if (rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
        
        // Update button text
        const filterBtn = document.querySelector('.btn-primary .mdi-filter').parentElement;
        filterBtn.innerHTML = '<i class="mdi mdi-filter me-1"></i> Filter: ' + this.textContent;
        
        // Update counters after filter
        updateVisibleCounters();
    });
});

// Clear filters
document.getElementById('clearFilters').addEventListener('click', function(e) {
    e.preventDefault();
    const rows = document.querySelectorAll('#linemanTableBody tr');
    rows.forEach(row => row.style.display = '');
    
    // Reset filter button text
    const filterBtn = document.querySelector('.btn-primary .mdi-filter').parentElement;
    filterBtn.innerHTML = '<i class="mdi mdi-filter me-1"></i> Filter';
    
    // Clear search input
    document.getElementById('searchInput').value = '';
    
    // Update counters
    updateVisibleCounters();
});

// Delete confirmation
let deleteId = null;
let deleteName = null;

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('delete-lineman')) {
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
        fetch('delete-lineman.php', {
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
                const row = document.querySelector(`tr td .delete-lineman[data-id="${deleteId}"]`)?.closest('tr');
                if (row) {
                    row.remove();
                    // Show success message
                    showAlert('Line man deleted successfully!', 'success');
                    // Update counters
                    updateVisibleCounters();
                }
            } else {
                showAlert('Error deleting line man: ' + data.message, 'danger');
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
function updateVisibleCounters() {
    const visibleRows = document.querySelectorAll('#linemanTableBody tr:not([style*="display: none"])');
    let active = 0, inactive = 0, onLeave = 0;
    
    visibleRows.forEach(row => {
        const status = row.getAttribute('data-status');
        if (status === 'active') active++;
        if (status === 'inactive') inactive++;
        if (status === 'on_leave') onLeave++;
    });
    
    document.getElementById('total-linemen').textContent = visibleRows.length;
    document.getElementById('active-linemen').textContent = active;
    document.getElementById('inactive-linemen').textContent = inactive;
    document.getElementById('onleave-linemen').textContent = onLeave;
}

// Export buttons functionality
document.querySelector('.btn-outline-primary').addEventListener('click', function() {
    alert('PDF export functionality would be implemented here');
});

document.querySelector('.btn-outline-success').addEventListener('click', function() {
    alert('Excel export functionality would be implemented here');
});

document.querySelector('.btn-outline-secondary').addEventListener('click', function() {
    window.print();
});
</script>

</body>

</html>