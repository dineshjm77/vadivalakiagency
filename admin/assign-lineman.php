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
                    if (isset($_POST['assign_lineman'])) {
                        $shop_id = mysqli_real_escape_string($conn, $_POST['shop_id']);
                        $lineman_id = mysqli_real_escape_string($conn, $_POST['lineman_id']);
                        
                        // Update shop with assigned lineman
                        $sql = "UPDATE customers SET assigned_lineman_id = ? WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ii", $lineman_id, $shop_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $message = 'Line man assigned successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Error assigning line man: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    } elseif (isset($_POST['bulk_assign'])) {
                        // Bulk assignment
                        $lineman_id = mysqli_real_escape_string($conn, $_POST['bulk_lineman_id']);
                        $area = mysqli_real_escape_string($conn, $_POST['bulk_area']);
                        
                        // Update all shops in the selected area
                        $sql = "UPDATE customers SET assigned_lineman_id = ? WHERE shop_location LIKE ? OR assigned_area = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        $search_area = "%$area%";
                        mysqli_stmt_bind_param($stmt, "iss", $lineman_id, $search_area, $area);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $affected_rows = mysqli_stmt_affected_rows($stmt);
                            $message = "Successfully assigned line man to $affected_rows shop(s) in $area area!";
                            $message_type = 'success';
                        } else {
                            $message = 'Error in bulk assignment: ' . mysqli_error($conn);
                            $message_type = 'danger';
                        }
                    } elseif (isset($_POST['remove_assignment'])) {
                        $shop_id = mysqli_real_escape_string($conn, $_POST['remove_shop_id']);
                        
                        // Remove lineman assignment
                        $sql = "UPDATE customers SET assigned_lineman_id = NULL WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $shop_id);
                        
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
                
                // Fetch all shops with their assigned linemen
                $shops = [];
                $shop_sql = "SELECT c.*, l.full_name as lineman_name, l.employee_id as lineman_code 
                             FROM customers c 
                             LEFT JOIN linemen l ON c.assigned_lineman_id = l.id 
                             ORDER BY c.shop_name";
                $shop_result = mysqli_query($conn, $shop_sql);
                if ($shop_result) {
                    while ($row = mysqli_fetch_assoc($shop_result)) {
                        $shops[] = $row;
                    }
                }
                
                // Get unique areas for bulk assignment
                $areas = [];
                $area_sql = "SELECT DISTINCT assigned_area FROM linemen WHERE assigned_area != '' AND status = 'active' ORDER BY assigned_area";
                $area_result = mysqli_query($conn, $area_sql);
                if ($area_result) {
                    while ($row = mysqli_fetch_assoc($area_result)) {
                        $areas[] = $row['assigned_area'];
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

                <div class="row">
                    <!-- Individual Assignment -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Assign Line Man to Shop</h4>
                                <p class="card-title-desc">Assign individual shops to line men</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Shop <span class="text-danger">*</span></label>
                                                <select class="form-select" name="shop_id" required>
                                                    <option value="">Choose a shop...</option>
                                                    <?php foreach ($shops as $shop): ?>
                                                    <option value="<?php echo $shop['id']; ?>">
                                                        <?php echo htmlspecialchars($shop['shop_name']); ?> 
                                                        (<?php echo htmlspecialchars($shop['customer_name']); ?>)
                                                        <?php if ($shop['lineman_name']): ?>
                                                        - Currently assigned to: <?php echo htmlspecialchars($shop['lineman_name']); ?>
                                                        <?php endif; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Assign to Line Man <span class="text-danger">*</span></label>
                                                <select class="form-select" name="lineman_id" required>
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
                                        <button type="submit" name="assign_lineman" class="btn btn-primary">
                                            <i class="mdi mdi-account-check me-1"></i> Assign Line Man
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
                                <p class="card-title-desc">Assign all shops in an area to a line man</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Select Area <span class="text-danger">*</span></label>
                                                <select class="form-select" name="bulk_area" required>
                                                    <option value="">Choose area...</option>
                                                    <?php foreach ($areas as $area): ?>
                                                    <option value="<?php echo htmlspecialchars($area); ?>">
                                                        <?php echo $area; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">All shops in this area will be assigned</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
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
                                    
                                    <div class="alert alert-warning">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        <strong>Warning:</strong> This will overwrite existing assignments for all shops in the selected area.
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" name="bulk_assign" class="btn btn-warning" 
                                                onclick="return confirm('Are you sure? This will assign ALL shops in the selected area to this line man.')">
                                            <i class="mdi mdi-account-multiple-check me-1"></i> Bulk Assign
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
                                <h4 class="card-title mb-0">Current Assignments</h4>
                                <p class="card-title-desc">View and manage existing shop assignments</p>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Shop Name</th>
                                                <th>Customer</th>
                                                <th>Contact</th>
                                                <th>Location</th>
                                                <th>Assigned Line Man</th>
                                                <th>Employee ID</th>
                                                <th>Area</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($shops)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <div class="text-muted">
                                                            <i class="mdi mdi-store-off display-4"></i>
                                                            <h5 class="mt-2">No Shops Found</h5>
                                                            <p>Add shops first to assign line men</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($shops as $index => $shop): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($shop['shop_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($shop['customer_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($shop['customer_contact']); ?></td>
                                                    <td>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($shop['shop_location'], 0, 50)); ?>...</small>
                                                    </td>
                                                    <td>
                                                        <?php if ($shop['lineman_name']): ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-2">
                                                                <div class="avatar-xs">
                                                                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                        <?php echo strtoupper(substr($shop['lineman_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0"><?php echo htmlspecialchars($shop['lineman_name']); ?></h6>
                                                            </div>
                                                        </div>
                                                        <?php else: ?>
                                                        <span class="badge badge-soft-warning">Not Assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($shop['lineman_code']): ?>
                                                        <span class="badge bg-info"><?php echo $shop['lineman_code']; ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($shop['assigned_area']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo $shop['assigned_area']; ?></span>
                                                        <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($shop['lineman_name']): ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <input type="hidden" name="remove_shop_id" value="<?php echo $shop['id']; ?>">
                                                            <button type="submit" name="remove_assignment" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Remove assignment from <?php echo htmlspecialchars($shop['shop_name']); ?>?')">
                                                                <i class="mdi mdi-account-remove"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-info ms-1" 
                                                                onclick="assignShop(<?php echo $shop['id']; ?>, '<?php echo htmlspecialchars($shop['shop_name']); ?>')">
                                                            <i class="mdi mdi-account-check"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-store"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Shops</p>
                                        <h4 class="mb-0"><?php echo count($shops); ?></h4>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Assigned Shops</p>
                                        <?php
                                        $assigned_count = 0;
                                        foreach ($shops as $shop) {
                                            if ($shop['lineman_name']) {
                                                $assigned_count++;
                                            }
                                        }
                                        ?>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Unassigned Shops</p>
                                        <h4 class="mb-0"><?php echo count($shops) - $assigned_count; ?></h4>
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
                                            <i class="mdi mdi-account-group"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Active Line Men</p>
                                        <h4 class="mb-0"><?php echo count($linemen); ?></h4>
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
// Function to auto-fill form when clicking assign button
function assignShop(shopId, shopName) {
    // Set the shop select
    const shopSelect = document.querySelector('select[name="shop_id"]');
    shopSelect.value = shopId;
    
    // Scroll to the assignment form
    document.querySelector('.card-header').scrollIntoView({ behavior: 'smooth' });
    
    // Show success message
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-info alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="mdi mdi-information-outline me-2"></i>
        Shop "<strong>${shopName}</strong>" selected. Now choose a line man and click "Assign Line Man".
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert-dismissible:not(.auth-alert)');
    existingAlerts.forEach(alert => alert.remove());
    
    // Insert alert after page title
    const pageTitle = document.querySelector('.page-title-box');
    pageTitle.parentNode.insertBefore(alertDiv, pageTitle.nextSibling);
    
    // Auto focus on lineman select
    setTimeout(() => {
        document.querySelector('select[name="lineman_id"]').focus();
    }, 500);
}

// Filter shops table
function filterShops() {
    const input = document.getElementById('searchShops');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

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

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
});
</script>

</body>

</html>