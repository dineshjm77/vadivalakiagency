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
                $message = '';
                $message_type = '';
                
                // Handle form submission
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    if (isset($_POST['assign_customer'])) {
                        $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
                        $lineman_id = mysqli_real_escape_string($conn, $_POST['lineman_id']);
                        
                        // Update customer with assigned lineman
                        $sql = "UPDATE customers SET assigned_lineman_id = ? WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ii", $lineman_id, $customer_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = 'Customer assigned successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Error assigning customer: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    } elseif (isset($_POST['bulk_assign'])) {
                        // Bulk assignment
                        $lineman_id = mysqli_real_escape_string($conn, $_POST['bulk_lineman_id']);
                        $customer_ids = $_POST['customer_ids'] ?? [];
                        
                        if (!empty($customer_ids)) {
                            // Create placeholders for the IN clause
                            $placeholders = implode(',', array_fill(0, count($customer_ids), '?'));
                            $sql = "UPDATE customers SET assigned_lineman_id = ? WHERE id IN ($placeholders)";
                            $stmt = mysqli_prepare($conn, $sql);
                            
                            // Build parameters array
                            $params = array_merge([$lineman_id], $customer_ids);
                            $types = str_repeat('i', count($params));
                            mysqli_stmt_bind_param($stmt, $types, ...$params);
                            
                            if (mysqli_stmt_execute($stmt)) {
                                $affected_rows = mysqli_stmt_affected_rows($stmt);
                                $message = "Successfully assigned $affected_rows customer(s) to line man!";
                                $message_type = 'success';
                            } else {
                                $message = 'Error in bulk assignment: ' . mysqli_error($conn);
                                $message_type = 'danger';
                            }
                        } else {
                            $message = 'Please select at least one customer for bulk assignment.';
                            $message_type = 'warning';
                        }
                    } elseif (isset($_POST['remove_assignment'])) {
                        $customer_id = mysqli_real_escape_string($conn, $_POST['remove_customer_id']);
                        
                        // Remove lineman assignment
                        $sql = "UPDATE customers SET assigned_lineman_id = NULL WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $customer_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = 'Assignment removed successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Error removing assignment: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    }
                }
                
                // Fetch all line men
                $linemen = [];
                $lineman_sql = "SELECT id, employee_id, full_name, assigned_area FROM linemen WHERE status = 'active' ORDER BY full_name";
                $lineman_result = mysqli_query($conn, $lineman_sql);
                if ($lineman_result) {
                    while ($row = mysqli_fetch_assoc($lineman_result)) {
                        $linemen[] = $row;
                    }
                }
                
                // Fetch all customers with their assigned linemen
                $customers = [];
                $customer_sql = "SELECT c.*, l.full_name as lineman_name, l.employee_id as lineman_code 
                                 FROM customers c 
                                 LEFT JOIN linemen l ON c.assigned_lineman_id = l.id 
                                 ORDER BY c.shop_name";
                $customer_result = mysqli_query($conn, $customer_sql);
                if ($customer_result) {
                    while ($row = mysqli_fetch_assoc($customer_result)) {
                        $customers[] = $row;
                    }
                }
                
                // Get statistics
                $total_customers = count($customers);
                $assigned_count = 0;
                $unassigned_count = 0;
                foreach ($customers as $customer) {
                    if ($customer['lineman_name']) {
                        $assigned_count++;
                    } else {
                        $unassigned_count++;
                    }
                }
                
                // Close connection
                mysqli_close($conn);
                ?>

                <!-- Display message -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-account-group"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Customers</p>
                                        <h4 class="mb-0"><?php echo $total_customers; ?></h4>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Assigned</p>
                                        <h4 class="mb-0"><?php echo $assigned_count; ?></h4>
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
                                            <i class="mdi mdi-account-question"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Unassigned</p>
                                        <h4 class="mb-0"><?php echo $unassigned_count; ?></h4>
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
                                            <i class="mdi mdi-account-multiple"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Line Men</p>
                                        <h4 class="mb-0"><?php echo count($linemen); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Individual Assignment -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Assign Customer to Line Man</h4>
                                <p class="card-title-desc">Assign individual customers to line men</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Customer <span class="text-danger">*</span></label>
                                                <select class="form-select" name="customer_id" required id="customerSelect">
                                                    <option value="">Choose a customer...</option>
                                                    <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>">
                                                        <?php echo htmlspecialchars($customer['shop_name']); ?> 
                                                        (<?php echo htmlspecialchars($customer['customer_name']); ?>)
                                                        <?php if ($customer['lineman_name']): ?>
                                                        - Assigned to: <?php echo htmlspecialchars($customer['lineman_name']); ?>
                                                        <?php endif; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Assign to Line Man <span class="text-danger">*</span></label>
                                                <select class="form-select" name="lineman_id" required id="linemanSelect">
                                                    <option value="">Select line man...</option>
                                                    <?php foreach ($linemen as $lineman): ?>
                                                    <option value="<?php echo $lineman['id']; ?>">
                                                        <?php echo htmlspecialchars($lineman['full_name']); ?> 
                                                        (<?php echo $lineman['employee_id']; ?>)
                                                        - <?php echo $lineman['assigned_area']; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" name="assign_customer" class="btn btn-primary">
                                            <i class="mdi mdi-account-check me-1"></i> Assign Customer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bulk Assignment -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Bulk Assignment</h4>
                                <p class="card-title-desc">Assign multiple customers to a line man</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="bulkForm">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Select Customers <span class="text-danger">*</span></label>
                                                <select class="form-select" name="customer_ids[]" multiple required id="bulkCustomerSelect" size="4">
                                                    <?php foreach ($customers as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>">
                                                        <?php echo htmlspecialchars($customer['shop_name']); ?> 
                                                        - <?php echo htmlspecialchars($customer['customer_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Hold Ctrl/Cmd to select multiple customers</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">Assign to Line Man <span class="text-danger">*</span></label>
                                                <select class="form-select" name="bulk_lineman_id" required>
                                                    <option value="">Select line man...</option>
                                                    <?php foreach ($linemen as $lineman): ?>
                                                    <option value="<?php echo $lineman['id']; ?>">
                                                        <?php echo htmlspecialchars($lineman['full_name']); ?> 
                                                        (<?php echo $lineman['employee_id']; ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="mdi mdi-information-outline me-2"></i>
                                        <strong>Tip:</strong> You can also select customers from the table below and use bulk assignment.
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" name="bulk_assign" class="btn btn-warning" id="bulkAssignBtn">
                                            <i class="mdi mdi-account-multiple-check me-1"></i> Bulk Assign Selected
                                        </button>
                                        <button type="button" class="btn btn-light ms-2" onclick="selectAllCustomers()">
                                            <i class="mdi mdi-select-all me-1"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-light ms-2" onclick="deselectAllCustomers()">
                                            <i class="mdi mdi-select-off me-1"></i> Deselect All
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Assignments Table -->
                <div class="row mt-4">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Customer Assignments</h4>
                                <p class="card-title-desc">View and manage customer assignments to line men</p>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="search-box">
                                            <input type="text" class="form-control" id="searchCustomers" placeholder="Search customers...">
                                            <i class="ri-search-line search-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="btn-group float-end">
                                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="mdi mdi-filter me-1"></i> Filter by Status
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item filter-option" href="#" data-status="all">All Customers</a></li>
                                                <li><a class="dropdown-item filter-option" href="#" data-status="assigned">Assigned Only</a></li>
                                                <li><a class="dropdown-item filter-option" href="#" data-status="unassigned">Unassigned Only</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle mb-0" id="customersTable">
                                        <thead>
                                            <tr>
                                                <th width="50">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                                                    </div>
                                                </th>
                                                <th>#</th>
                                                <th>Customer Code</th>
                                                <th>Shop Name</th>
                                                <th>Customer Name</th>
                                                <th>Contact</th>
                                                <th>Location</th>
                                                <th>Assigned Line Man</th>
                                                <th>Area</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($customers)): ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-account-off display-4"></i>
                                                            <h5 class="mt-2">No Customers Found</h5>
                                                            <p>Add customers first to assign line men</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($customers as $index => $customer): ?>
                                                <tr data-status="<?php echo $customer['lineman_name'] ? 'assigned' : 'unassigned'; ?>">
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input customer-checkbox" type="checkbox" value="<?php echo $customer['id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark"><?php echo $customer['customer_code']; ?></span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($customer['shop_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['customer_contact']); ?></td>
                                                    <td>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($customer['shop_location'], 0, 40)); ?>...</small>
                                                    </td>
                                                    <td>
                                                        <?php if ($customer['lineman_name']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <div class="avatar-xs">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($customer['lineman_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($customer['lineman_name']); ?></h6>
                                                                <small class="text-muted"><?php echo $customer['lineman_code']; ?></small>
                                                            </div>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="badge badge-soft-warning">Not Assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (isset($customer['assigned_area']) && $customer['assigned_area']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo $customer['assigned_area']; ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $customer['status'] == 'active' ? 'badge-soft-success' : 'badge-soft-danger'; ?>">
                                                            <?php echo ucfirst($customer['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-light btn-sm dropdown-toggle" type="button" 
                                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="mdi mdi-dots-horizontal"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <?php if ($customer['lineman_name']): ?>
                                                                <li>
                                                                    <form method="POST" action="">
                                                                        <input type="hidden" name="remove_customer_id" value="<?php echo $customer['id']; ?>">
                                                                        <button type="submit" name="remove_assignment" class="dropdown-item text-danger">
                                                                            <i class="mdi mdi-account-remove me-1"></i> Remove Assignment
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                                <?php endif; ?>
                                                                <li>
                                                                    <a class="dropdown-item" href="#" onclick="quickAssign(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['shop_name']); ?>')">
                                                                        <i class="mdi mdi-account-check me-1"></i> Quick Assign
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="customer-view.php?id=<?php echo $customer['id']; ?>">
                                                                        <i class="mdi mdi-eye-outline me-1"></i> View Details
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Selected Customers Counter -->
                                <div class="mt-3" id="selectedCounter" style="display: none;">
                                    <div class="alert alert-info py-2">
                                        <i class="mdi mdi-information-outline me-2"></i>
                                        <span id="selectedCount">0</span> customer(s) selected. 
                                        <a href="#" class="alert-link" onclick="scrollToBulkForm()">Assign them now</a> or 
                                        <a href="#" class="alert-link" onclick="deselectAllCustomers()">deselect all</a>.
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
// Quick assign function
function quickAssign(customerId, shopName) {
    // Set the customer select
    const customerSelect = document.getElementById('customerSelect');
    customerSelect.value = customerId;
    
    // Scroll to the assignment form
    document.querySelector('.card-header').scrollIntoView({ behavior: 'smooth' });
    
    // Show success message
    showAlert(`Customer "${shopName}" selected. Now choose a line man and click "Assign Customer".`, 'info');
    
    // Auto focus on lineman select
    setTimeout(() => {
        document.getElementById('linemanSelect').focus();
    }, 500);
}

// Select all customers
function selectAllCustomers() {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCounter();
    
    // Also select in bulk select dropdown
    const bulkSelect = document.getElementById('bulkCustomerSelect');
    for (let i = 0; i < bulkSelect.options.length; i++) {
        bulkSelect.options[i].selected = true;
    }
}

// Deselect all customers
function deselectAllCustomers() {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCounter();
    
    // Also deselect in bulk select dropdown
    const bulkSelect = document.getElementById('bulkCustomerSelect');
    for (let i = 0; i < bulkSelect.options.length; i++) {
        bulkSelect.options[i].selected = false;
    }
}

// Update selected customers counter
function updateSelectedCounter() {
    const selectedCheckboxes = document.querySelectorAll('.customer-checkbox:checked');
    const count = selectedCheckboxes.length;
    const counter = document.getElementById('selectedCount');
    const counterDiv = document.getElementById('selectedCounter');
    
    counter.textContent = count;
    
    if (count > 0) {
        counterDiv.style.display = 'block';
        
        // Update bulk select with selected IDs
        const bulkSelect = document.getElementById('bulkCustomerSelect');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        for (let i = 0; i < bulkSelect.options.length; i++) {
            bulkSelect.options[i].selected = selectedIds.includes(bulkSelect.options[i].value);
        }
    } else {
        counterDiv.style.display = 'none';
    }
}

// Scroll to bulk form
function scrollToBulkForm() {
    document.getElementById('bulkForm').scrollIntoView({ behavior: 'smooth' });
}

// Show alert message
function showAlert(message, type) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-dismissible:not(.auth-alert)');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="mdi mdi-information-outline me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insert alert after page title
    const pageTitle = document.querySelector('.page-title-box');
    pageTitle.parentNode.insertBefore(alertDiv, pageTitle.nextSibling);
}

// Search functionality
document.getElementById('searchCustomers').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#customersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Filter by status
document.querySelectorAll('.filter-option').forEach(option => {
    option.addEventListener('click', function(e) {
        e.preventDefault();
        const status = this.getAttribute('data-status');
        const rows = document.querySelectorAll('#customersTable tbody tr');
        
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
        document.querySelector('.btn-light .mdi-filter').parentElement.textContent = 
            'Filter: ' + this.textContent;
    });
});

// Select all checkbox
document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCounter();
});

// Individual checkbox change
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('customer-checkbox')) {
        updateSelectedCounter();
    }
});

// Bulk assign button confirmation
document.getElementById('bulkAssignBtn').addEventListener('click', function(e) {
    const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
    if (selectedCount === 0) {
        e.preventDefault();
        showAlert('Please select at least one customer for bulk assignment.', 'warning');
        return false;
    }
    
    if (!confirm(`Are you sure you want to assign ${selectedCount} customer(s) to this line man?`)) {
        e.preventDefault();
        return false;
    }
});

// Auto-hide alert after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        const closeBtn = alert.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.click();
        }
    });
}, 5000);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
    
    // Update counter initially
    updateSelectedCounter();
});
</script>

</body>

</html>