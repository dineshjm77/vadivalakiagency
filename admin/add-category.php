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
                
                // Handle form submission for adding new category
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
                    $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
                    $status = mysqli_real_escape_string($conn, $_POST['status']);
                    
                    // Check if category already exists
                    $check_sql = "SELECT id FROM categories WHERE category_name = '$category_name'";
                    $check_result = mysqli_query($conn, $check_sql);
                    
                    if (mysqli_num_rows($check_result) > 0) {
                        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle-outline me-2"></i>
                                Category already exists!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        // Insert into database
                        $sql = "INSERT INTO categories (category_name, status, created_at) 
                                VALUES ('$category_name', '$status', NOW())";
                        
                        if (mysqli_query($conn, $sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Category added successfully!
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
                
                // Handle category deletion
                if (isset($_GET['delete_id'])) {
                    $delete_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
                    $delete_sql = "DELETE FROM categories WHERE id = '$delete_id'";
                    
                    if (mysqli_query($conn, $delete_sql)) {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-all me-2"></i>
                                Category deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-block-helper me-2"></i>
                                Error deleting category: ' . mysqli_error($conn) . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    }
                }
                
                // Handle category status update
                if (isset($_GET['toggle_status'])) {
                    $toggle_id = mysqli_real_escape_string($conn, $_GET['toggle_status']);
                    
                    // Get current status
                    $get_sql = "SELECT status FROM categories WHERE id = '$toggle_id'";
                    $get_result = mysqli_query($conn, $get_sql);
                    
                    if (mysqli_num_rows($get_result) > 0) {
                        $row = mysqli_fetch_assoc($get_result);
                        $new_status = ($row['status'] == 'active') ? 'inactive' : 'active';
                        
                        $update_sql = "UPDATE categories SET status = '$new_status' WHERE id = '$toggle_id'";
                        if (mysqli_query($conn, $update_sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-all me-2"></i>
                                    Category status updated!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                        }
                    }
                }
                
                // Handle category edit
                if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_category'])) {
                    $edit_id = mysqli_real_escape_string($conn, $_POST['edit_id']);
                    $edit_name = mysqli_real_escape_string($conn, $_POST['edit_name']);
                    $edit_status = mysqli_real_escape_string($conn, $_POST['edit_status']);
                    
                    $update_sql = "UPDATE categories SET category_name = '$edit_name', status = '$edit_status' WHERE id = '$edit_id'";
                    
                    if (mysqli_query($conn, $update_sql)) {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-all me-2"></i>
                                Category updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    } else {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-block-helper me-2"></i>
                                Error updating category: ' . mysqli_error($conn) . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                    }
                }
                ?>

                <div class="row">
                    <!-- Add Category Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Add New Category</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" id="addCategoryForm">
                                    <div class="mb-3">
                                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="category_name" required 
                                               placeholder="e.g., Mineral Water, Milk, Juice">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" name="add_category" class="btn btn-primary w-100">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i> Add Category
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card mt-3">
                            <div class="card-body">
                                <h5 class="card-title">Category Stats</h5>
                                <?php
                                $total_sql = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                                    FROM categories";
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

                    <!-- Category List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h4 class="card-title mb-0">All Categories</h4>
                                    <span class="badge bg-primary"><?php echo $stats['total']; ?> Categories</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                // Fetch all categories
                                $sql = "SELECT * FROM categories ORDER BY created_at DESC";
                                $result = mysqli_query($conn, $sql);
                                
                                if (mysqli_num_rows($result) > 0) {
                                ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Category Name</th>
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
                                            <tr>
                                                <td><?php echo $counter; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-xs me-3">
                                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                <?php echo strtoupper(substr($row['category_name'], 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo $row['category_name']; ?></h6>
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
                                                                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#editCategoryModal" 
                                                                        onclick="setEditData(<?php echo $row['id']; ?>, '<?php echo $row['category_name']; ?>', '<?php echo $row['status']; ?>')">
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
                                                                   onclick="return confirm('Are you sure you want to delete this category?')">
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
                                        <i class="mdi mdi-folder-outline display-4"></i>
                                        <h5 class="mt-2">No Categories Found</h5>
                                        <p>Add your first category using the form on the left</p>
                                    </div>
                                </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Popular Categories -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-star-circle me-1"></i> Popular Categories
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-primary-subtle text-primary px-3 py-2">
                                        <i class="mdi mdi-water me-1"></i> Mineral Water
                                    </span>
                                    <span class="badge bg-success-subtle text-success px-3 py-2">
                                        <i class="mdi mdi-cow me-1"></i> Milk & Dairy
                                    </span>
                                    <span class="badge bg-info-subtle text-info px-3 py-2">
                                        <i class="mdi mdi-glass-cocktail me-1"></i> Juice
                                    </span>
                                    <span class="badge bg-warning-subtle text-warning px-3 py-2">
                                        <i class="mdi mdi-bottle-soda me-1"></i> Soft Drinks
                                    </span>
                                    <span class="badge bg-danger-subtle text-danger px-3 py-2">
                                        <i class="mdi mdi-water-pump me-1"></i> Packaged Water
                                    </span>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
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
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
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
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    const categoryName = document.querySelector('input[name="category_name"]').value;
    
    if (categoryName.trim().length < 2) {
        alert('Category name must be at least 2 characters long');
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

</body>

</html>