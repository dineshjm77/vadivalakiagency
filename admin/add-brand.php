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
                
                // Handle form submission for adding new brand
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_brand'])) {
                    $brand_name = mysqli_real_escape_string($conn, $_POST['brand_name']);
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Check if brand already exists
                    $check_sql = "SELECT id FROM brands WHERE brand_name = '$brand_name'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Brand already exists!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Insert into database
                        $sql = "INSERT INTO brands (brand_name, status, created_at) 
                                VALUES ('$brand_name', '$status', NOW())";
                        
                        if (mysqli_query($conn, $sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Brand added successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                
                // Handle brand deletion
                if (isset($_GET['delete_id'])) {
                    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
                    $delete_sql = "DELETE FROM brands WHERE id = '$delete_id'";
                    
                    if (mysqli_query($conn, $delete_sql)) {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-all me-2"></i>
                                Brand deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-block-helper me-2"></i>
                                Error deleting brand: ' . mysqli_error($conn) . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    }
                }
                
                // Handle brand status update
                if (isset($_GET['toggle_status'])) {
                    $toggle_id = mysqli_real_escape_string($conn, $_GET['toggle_status']);
                    
                    // Get current status
                    $get_sql = "SELECT status FROM brands WHERE id = '$toggle_id'";
                    $get_result = mysqli_query($conn, $get_sql);
                    
                    if (mysqli_num_rows($get_result) > 0) {
                        $row = mysqli_fetch_assoc($get_result);
                        $new_status = ($row['status'] == 'active') ? 'inactive' : 'active';
                        
                        $update_sql = "UPDATE brands SET status = '$new_status' WHERE id = '$toggle_id'";
                        if (mysqli_query($conn, $update_sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Brand status updated!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                
                // Handle brand edit
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_brand'])) {
                    $edit_id = mysqli_real_escape_string($conn, $_POST['edit_id']);
                    $edit_name = mysqli_real_escape_string($conn, $_POST['edit_name']);
                    $edit_status = mysqli_real_escape_string($conn, $_POST['edit_status']);
                    
                    // Check if new name already exists (excluding current brand)
                    $check_sql = "SELECT id FROM brands WHERE brand_name = '$edit_name' AND id != '$edit_id'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Brand name already exists!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        $update_sql = "UPDATE brands SET brand_name = '$edit_name', status = '$edit_status' WHERE id = '$edit_id'";
                        
                        if (mysqli_query($conn, $update_sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Brand updated successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-block-helper me-2"></i>
                                    Error updating brand: ' . mysqli_error($conn) . '
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                ?>

                <div class="row">
                    <!-- Add Brand Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Add New Brand</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="addBrandForm">
                                    <div class="mb-3">
                                        <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="brand_name" required 
                                               placeholder="e.g., Bisleri, Amul, Pepsi">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" name="add_brand" class="btn btn-primary w-100">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Brand
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Brand Stats</h5>
                                <?php
                                $total_sql = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                                    FROM brands";
                                $stats_result = mysqli_query($conn, $total_sql);
                                $stats = mysqli_fetch_assoc($stats_result);
                                ?>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="mb-0"><?php echo $stats['total']; ?></h4>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="mb-0 text-success"><?php echo $stats['active']; ?></h4>
                                        <small class="text-muted">Active</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="mb-0 text-danger"><?php echo $stats['inactive']; ?></h4>
                                        <small class="text-muted">Inactive</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        

                    </div>

                    <!-- Brand List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">All Brands</h4>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-primary"><?php echo $stats['total']; ?> Brands</span>
                                        <div class="search-box">
                                            <input type="text" class="form-control form-control-sm" id="searchBrands" placeholder="Search brands...">
                                            <i class="ri-search-line search-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch all brands
                                $sql = "SELECT * FROM brands ORDER BY created_at DESC";
                                $result = mysqli_query($conn, $sql);
                                
                                if (mysqli_num_rows($result) > 0) {
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="brandsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Brand Name</th>
                                                <th>Status</th>
                                                <th>Created Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $counter = 1;
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $status_class = $row['status'] == 'active' ? 'badge-soft-success' : 'badge-soft-danger';
                                                $created_date = date('d M, Y', strtotime($row['created_at']));
                                            ?>
                                            <tr data-brand-name="<?php echo strtolower($row['brand_name']); ?>">
                                                <td><?php echo $counter; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-xs me-3">
                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                <?php echo strtoupper(substr($row['brand_name'], 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo $row['brand_name']; ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $created_date; ?></td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="mdi mdi-dots-horizontal"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#editBrandModal" 
                                                                        onclick="setEditData(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['brand_name'], ENT_QUOTES); ?>', '<?php echo $row['status']; ?>')">
                                                                    <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-warning" href="?toggle_status=<?php echo $row['id']; ?>">
                                                                    <i class="mdi mdi-toggle-switch me-1"></i> 
                                                                    <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item text-danger" href="?delete_id=<?php echo $row['id']; ?>" 
                                                                   onclick="return confirm('Are you sure you want to delete this brand?')">
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
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php
                                } else {
                                ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="mdi mdi-tag-outline display-4"></i>
                                        <h5 class="mt-2">No Brands Found</h5>
                                        <p>Add your first brand using the form on the left</p>
                                    </div>
                                </div>
                                <?php
                                }
                                
                                mysqli_close($conn);
                                ?>
                            </div>
                        </div>
                        
                        <!-- Brand Usage Info -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-information-outline me-1"></i> Brand Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Why Add Brands?</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2">
                                                <i class="mdi mdi-check-circle text-success me-1"></i>
                                                <small>Organize products by brand</small>
                                            </li>
                                            <li class="mb-2">
                                                <i class="mdi mdi-check-circle text-success me-1"></i>
                                                <small>Filter products by brand</small>
                                            </li>
                                            <li>
                                                <i class="mdi mdi-check-circle text-success me-1"></i>
                                                <small>Track sales by brand</small>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary">Usage Tips</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2">
                                                <i class="mdi mdi-lightbulb-on text-warning me-1"></i>
                                                <small>Use unique brand names</small>
                                            </li>
                                            <li class="mb-2">
                                                <i class="mdi mdi-lightbulb-on text-warning me-1"></i>
                                                <small>Deactivate unused brands</small>
                                            </li>
                                            <li>
                                                <i class="mdi mdi-lightbulb-on text-warning me-1"></i>
                                                <small>Add brands before products</small>
                                            </li>
                                        </ul>
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

<!-- Edit Brand Modal -->
<div class="modal fade" id="editBrandModal" tabindex="-1" aria-labelledby="editBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBrandModalLabel">Edit Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Brand Name</label>
                        <input type="text" class="form-control" name="edit_name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="edit_status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_brand" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to set edit data in modal
function setEditData(id, name, status) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_status').value = status;
}

// Form validation
document.getElementById('addBrandForm').addEventListener('submit', function(e) {
    const brandName = document.querySelector('input[name="brand_name"]').value;
    
    if (brandName.trim().length < 2) {
        alert('Brand name must be at least 2 characters long');
        e.preventDefault();
        return false;
    }
    
    return true;
});

// Search functionality
document.getElementById('searchBrands').addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#brandsTable tbody tr');
    
    rows.forEach(row => {
        const brandName = row.getAttribute('data-brand-name');
        if (brandName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

</body>

</html>