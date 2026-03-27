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
                
                // Handle form submissions
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    if (isset($_POST['action'])) {
                        $action = $_POST['action'];
                        
                        // Add new category
                        if ($action == 'add') {
                            $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
                            $status = mysqli_real_escape_string($conn, $_POST['status']);
                            
                            // Check if category already exists
                            $check_sql = "SELECT id FROM categories WHERE category_name = '$category_name'";
                            $check_result = mysqli_query($conn, $check_sql);
                            
                            if (mysqli_num_rows($check_result) > 0) {
                                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        Category "'.$category_name.'" already exists!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                $insert_sql = "INSERT INTO categories (category_name, status) VALUES ('$category_name', '$status')";
                                
                                if (mysqli_query($conn, $insert_sql)) {
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
                        
                        // Update category
                        elseif ($action == 'update') {
                            $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
                            $category_name = mysqli_real_escape_string($conn, $_POST['category_name']);
                            $status = mysqli_real_escape_string($conn, $_POST['status']);
                            
                            // Check if new name already exists (excluding current category)
                            $check_sql = "SELECT id FROM categories WHERE category_name = '$category_name' AND id != '$category_id'";
                            $check_result = mysqli_query($conn, $check_sql);
                            
                            if (mysqli_num_rows($check_result) > 0) {
                                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        Category "'.$category_name.'" already exists!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                $update_sql = "UPDATE categories SET category_name = '$category_name', status = '$status', updated_at = NOW() WHERE id = '$category_id'";
                                
                                if (mysqli_query($conn, $update_sql)) {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Category updated successfully!
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
                        
                        // Delete category
                        elseif ($action == 'delete') {
                            $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
                            
                            // Check if category has products
                            $check_sql = "SELECT COUNT(*) as product_count FROM products WHERE category_id = '$category_id'";
                            $check_result = mysqli_query($conn, $check_sql);
                            $check_data = mysqli_fetch_assoc($check_result);
                            
                            if ($check_data['product_count'] > 0) {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        Cannot delete category! There are ' . $check_data['product_count'] . ' products assigned to this category.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                $delete_sql = "DELETE FROM categories WHERE id = '$category_id'";
                                
                                if (mysqli_query($conn, $delete_sql)) {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Category deleted successfully!
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
                    }
                }
                
                // Get filter status
                $filter_status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
                
                // Build WHERE clause
                $where_conditions = [];
                if ($filter_status == 'active') {
                    $where_conditions[] = "status = 'active'";
                } elseif ($filter_status == 'inactive') {
                    $where_conditions[] = "status = 'inactive'";
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                }
                
                // Get categories count for stats
                $stats_sql = "SELECT 
                    COUNT(*) as total_categories,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_categories,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_categories
                    FROM categories";
                
                $stats_result = mysqli_query($conn, $stats_sql);
                $stats = mysqli_fetch_assoc($stats_result);
                
                // Get categories with product counts
                $categories_sql = "SELECT 
                    c.*,
                    COUNT(p.id) as product_count,
                    SUM(p.quantity) as total_stock,
                    SUM(p.stock_price * p.quantity) as stock_value
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id
                    $where_clause
                    GROUP BY c.id
                    ORDER BY c.category_name ASC";
                
                $categories_result = mysqli_query($conn, $categories_sql);
                ?>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-folder-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Categories</p>
                                        <h4 class="mb-0"><?php echo number_format($stats['total_categories']); ?></h4>
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
                                        <h4 class="mb-0 text-success"><?php echo number_format($stats['active_categories']); ?></h4>
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
                                            <i class="mdi mdi-package-variant"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Products</p>
                                        <h4 class="mb-0 text-warning">
                                            <?php 
                                            $product_sql = "SELECT COUNT(*) as total FROM products";
                                            $product_result = mysqli_query($conn, $product_sql);
                                            $product_data = mysqli_fetch_assoc($product_result);
                                            echo number_format($product_data['total']);
                                            ?>
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
                                        <span class="avatar-title bg-info-subtle text-info rounded-2 fs-2">
                                            <i class="mdi mdi-currency-inr"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Stock Value</p>
                                        <h4 class="mb-0 text-info">
                                            <?php 
                                            $value_sql = "SELECT SUM(stock_price * quantity) as total_value FROM products";
                                            $value_result = mysqli_query($conn, $value_sql);
                                            $value_data = mysqli_fetch_assoc($value_result);
                                            echo '₹' . number_format($value_data['total_value'] ?? 0, 2);
                                            ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <!-- Add New Category Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-plus-circle text-success me-1"></i> Add New Category
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="product-categories.php" id="addCategoryForm">
                                    <input type="hidden" name="action" value="add">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="category_name" required 
                                               placeholder="e.g., Mineral Water, Coolers, Accessories" maxlength="100"
                                               id="categoryNameInput">
                                        <small class="text-muted">Unique category name for products</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="mdi mdi-plus-circle me-1"></i> Add Category
                                        </button>
                                    </div>
                                </form>
                                

                            </div>
                        </div>
                        
                        <!-- Quick Categories -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-lightning-bolt text-warning me-1"></i> Quick Categories
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-category" data-category="Mineral Water">
                                            Mineral Water
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-category" data-category="Water Coolers">
                                            Water Coolers
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-category" data-category="Bottles">
                                            Bottles
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-category" data-category="Accessories">
                                            Accessories
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-category" data-category="Dispensers">
                                            Dispensers
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-category" data-category="Filters">
                                            Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="mdi mdi-folder-multiple-outline text-primary me-1"></i> 
                                        All Categories
                                    </h5>
                                    <div class="d-flex gap-2">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="mdi mdi-filter me-1"></i> 
                                                <?php 
                                                $filter_text = 'All';
                                                if ($filter_status == 'active') $filter_text = 'Active Only';
                                                if ($filter_status == 'inactive') $filter_text = 'Inactive Only';
                                                echo $filter_text;
                                                ?>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item filter-option" href="product-categories.php">All Categories</a></li>
                                                <li><a class="dropdown-item filter-option" href="product-categories.php?status=active">Active Only</a></li>
                                                <li><a class="dropdown-item filter-option" href="product-categories.php?status=inactive">Inactive Only</a></li>
                                            </ul>
                                        </div>
                                        <button type="button" class="btn btn-info" onclick="printCategories()">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($categories_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="categoriesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Category Name</th>
                                                <th>Products</th>
                                                <th>Total Stock</th>
                                                <th>Stock Value</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $counter = 1;
                                            while ($category = mysqli_fetch_assoc($categories_result)): 
                                                $status_class = $category['status'] == 'active' ? 'badge-soft-success' : 'badge-soft-danger';
                                            ?>
                                            <tr id="categoryRow<?php echo $category['id']; ?>">
                                                <td><?php echo $counter; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <div class="avatar-xs">
                                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                    <?php echo strtoupper(substr($category['category_name'], 0, 1)); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($category['category_name']); ?></h5>
                                                            <small class="text-muted">ID: <?php echo $category['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <h5 class="mb-0 <?php echo $category['product_count'] > 0 ? 'text-warning' : 'text-muted'; ?>">
                                                            <?php echo $category['product_count']; ?>
                                                        </h5>
                                                        <small class="text-muted">products</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($category['product_count'] > 0): ?>
                                                    <div class="text-center">
                                                        <h6 class="mb-0 <?php echo $category['total_stock'] < 10 ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo number_format($category['total_stock']); ?>
                                                        </h6>
                                                        <small class="text-muted">units</small>
                                                    </div>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($category['product_count'] > 0): ?>
                                                    <span class="text-info">₹<?php echo number_format($category['stock_value'], 2); ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?> font-size-12">
                                                        <?php echo ucfirst($category['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d M, Y', strtotime($category['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-info edit-category" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                                data-status="<?php echo $category['status']; ?>">
                                                            <i class="mdi mdi-pencil-outline"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-category" 
                                                                data-id="<?php echo $category['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                                data-count="<?php echo $category['product_count']; ?>">
                                                            <i class="mdi mdi-delete-outline"></i>
                                                        </button>
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
                                
                                <?php if ($filter_status != 'all'): ?>
                                <div class="alert alert-info mt-3 py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small>
                                            <i class="mdi mdi-information-outline me-2"></i>
                                            Showing <?php echo mysqli_num_rows($categories_result); ?> 
                                            <?php echo $filter_status; ?> categories
                                        </small>
                                        <a href="product-categories.php" class="btn btn-sm btn-light">
                                            Clear Filter
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="mdi mdi-folder-outline display-4"></i>
                                        <h4 class="mt-3">No Categories Found</h4>
                                        <p class="mb-0">Add your first category using the form on the left.</p>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="product-categories.php" id="editCategoryForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="category_id" id="editCategoryId">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="category_name" id="editCategoryName" required 
                               maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editCategoryStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info" id="editCategoryInfo">
                        <i class="mdi mdi-information-outline me-2"></i>
                        <small>Updating category will affect all products in this category.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmEditCategory">Update Category</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<strong id="deleteCategoryName"></strong>"?</p>
                <div id="deleteCategoryWarning">
                    <!-- Warning message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteCategory">Delete Category</button>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Variables for modals
let editCategoryId = null;
let deleteCategoryId = null;
let deleteCategoryName = null;
let deleteProductCount = null;

// Quick category buttons
document.querySelectorAll('.quick-category').forEach(button => {
    button.addEventListener('click', function() {
        const categoryName = this.getAttribute('data-category');
        document.getElementById('categoryNameInput').value = categoryName;
        document.getElementById('categoryNameInput').focus();
    });
});

// Edit category
document.querySelectorAll('.edit-category').forEach(button => {
    button.addEventListener('click', function() {
        editCategoryId = this.getAttribute('data-id');
        const categoryName = this.getAttribute('data-name');
        const categoryStatus = this.getAttribute('data-status');
        
        document.getElementById('editCategoryId').value = editCategoryId;
        document.getElementById('editCategoryName').value = categoryName;
        document.getElementById('editCategoryStatus').value = categoryStatus;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        modal.show();
    });
});

// Delete category
document.querySelectorAll('.delete-category').forEach(button => {
    button.addEventListener('click', function() {
        deleteCategoryId = this.getAttribute('data-id');
        deleteCategoryName = this.getAttribute('data-name');
        deleteProductCount = parseInt(this.getAttribute('data-count'));
        
        document.getElementById('deleteCategoryName').textContent = deleteCategoryName;
        
        // Show appropriate warning
        const warningDiv = document.getElementById('deleteCategoryWarning');
        if (deleteProductCount > 0) {
            warningDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert-circle me-2"></i>
                    This category has ${deleteProductCount} product(s). 
                    Categories with products cannot be deleted.<br><br>
                    <strong>Solution:</strong> 
                    <ol class="mb-0">
                        <li>Move products to another category first</li>
                        <li>Or delete the products first</li>
                        <li>Then delete the category</li>
                    </ol>
                </div>
            `;
            document.getElementById('confirmDeleteCategory').style.display = 'none';
        } else {
            warningDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert me-2"></i>
                    This action cannot be undone. The category will be permanently deleted.
                </div>
            `;
            document.getElementById('confirmDeleteCategory').style.display = 'block';
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
        modal.show();
    });
});

// Confirm edit category
document.getElementById('confirmEditCategory').addEventListener('click', function() {
    // Check if category name is not empty
    const categoryName = document.getElementById('editCategoryName').value.trim();
    if (categoryName.length < 2) {
        alert('Category name must be at least 2 characters long');
        return;
    }
    
    // Submit the form
    document.getElementById('editCategoryForm').submit();
});

// Confirm delete category
document.getElementById('confirmDeleteCategory').addEventListener('click', function() {
    if (deleteCategoryId && deleteProductCount === 0) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'product-categories.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'category_id';
        idInput.value = deleteCategoryId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
});

// Form validation for add category
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    const categoryName = document.querySelector('input[name="category_name"]').value.trim();
    
    if (categoryName.length < 2) {
        e.preventDefault();
        alert('Category name must be at least 2 characters long');
        return false;
    }
    
    if (categoryName.length > 100) {
        e.preventDefault();
        alert('Category name must be less than 100 characters');
        return false;
    }
    
    // Check for special characters (optional)
    const specialChars = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/;
    if (specialChars.test(categoryName)) {
        if (!confirm('Category name contains special characters. Are you sure?')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});

// Search functionality
function searchCategories() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll('#categoriesTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    const noResultsDiv = document.getElementById('noResults');
    
    if (visibleRows.length === 0 && rows.length > 0) {
        if (!noResultsDiv) {
            const table = document.getElementById('categoriesTable');
            const newRow = table.insertRow();
            newRow.id = 'noResults';
            newRow.innerHTML = `
                <td colspan="8" class="text-center py-4">
                    <div class="text-muted">
                        <i class="mdi mdi-magnify display-4"></i>
                        <h5 class="mt-3">No categories found</h5>
                        <p>Try different search terms</p>
                    </div>
                </td>
            `;
        }
    } else if (noResultsDiv) {
        noResultsDiv.remove();
    }
}

// Print categories
function printCategories() {
    const printContent = document.getElementById('categoriesTable').outerHTML;
    const filterInfo = document.querySelector('.alert-info')?.innerHTML || '';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Product Categories - APR Water Agencies</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { color: #666; margin-top: 10px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; text-align: left; }
                .table td { border: 1px solid #dee2e6; padding: 8px; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                .badge-soft-success { background-color: #d4edda; color: #155724; }
                .badge-soft-danger { background-color: #f8d7da; color: #721c24; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Product Categories Report</div>
                <div class="subtitle">APR Water Agencies</div>
                <div class="subtitle">Printed on: ${new Date().toLocaleString()}</div>
            </div>
            
            ${filterInfo ? `<div class="info-box">${filterInfo}</div>` : ''}
            
            ${printContent}
            
            <div class="footer">
                Total Categories: <?php echo $stats['total_categories']; ?> | 
                Active: <?php echo $stats['active_categories']; ?> | 
                Inactive: <?php echo $stats['inactive_categories']; ?>
            </div>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Print Now
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                    Close
                </button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
}

// Export categories (placeholder function)
function exportCategories() {
    // This would typically be an AJAX call to export-categories.php
    alert('Export feature would generate a CSV file of all categories.');
    // window.location.href = 'export-categories.php';
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus on category name input
    const categoryInput = document.getElementById('categoryNameInput');
    if (categoryInput) {
        categoryInput.focus();
    }
    
    // Filter options
    document.querySelectorAll('.filter-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = this.getAttribute('href');
        });
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+N to focus on new category form
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const categoryInput = document.getElementById('categoryNameInput');
            if (categoryInput) {
                categoryInput.focus();
            }
        }
        // Ctrl+F to focus on search (if search exists)
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
        // Ctrl+P to print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printCategories();
        }
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                searchCategories();
            }
        }
    });
    
    // Auto-check for duplicate category names
    const categoryNameInput = document.getElementById('categoryNameInput');
    if (categoryNameInput) {
        let checkTimeout;
        categoryNameInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(() => {
                const name = this.value.trim();
                if (name.length >= 2) {
                    // In a real implementation, this would be an AJAX call
                    // For now, we'll just log it
                    console.log('Checking category:', name);
                }
            }, 500);
        });
    }
});

// Function to refresh categories list
function refreshCategories() {
    window.location.reload();
}

// Function to bulk update categories (placeholder)
function bulkUpdateCategories() {
    alert('Bulk update feature would allow updating multiple categories at once.');
}

// Function to export categories to CSV
function exportToCSV() {
    const table = document.getElementById('categoriesTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add headers
    const headers = [];
    table.querySelectorAll('th').forEach(header => {
        headers.push(header.textContent);
    });
    csvContent += headers.join(",") + "\r\n";
    
    // Add rows
    rows.forEach((row, index) => {
        if (index > 0) { // Skip header row
            const cols = row.querySelectorAll('td');
            const rowData = [];
            cols.forEach(col => {
                rowData.push(col.textContent.trim());
            });
            csvContent += rowData.join(",") + "\r\n";
        }
    });
    
    // Download CSV
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "categories_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

</body>

</html>