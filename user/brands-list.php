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
                        
                        // Add new brand
                        if ($action == 'add') {
                            $brand_name = mysqli_real_escape_string($conn, $_POST['brand_name']);
                            $status = mysqli_real_escape_string($conn, $_POST['status']);
                            
                            // Check if brand already exists
                            $check_sql = "SELECT id FROM brands WHERE brand_name = '$brand_name'";
                            $check_result = mysqli_query($conn, $check_sql);
                            
                            if (mysqli_num_rows($check_result) > 0) {
                                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        Brand "'.$brand_name.'" already exists!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                $insert_sql = "INSERT INTO brands (brand_name, status) VALUES ('$brand_name', '$status')";
                                
                                if (mysqli_query($conn, $insert_sql)) {
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
                        
                        // Update brand
                        elseif ($action == 'update') {
                            $brand_id = mysqli_real_escape_string($conn, $_POST['brand_id']);
                            $brand_name = mysqli_real_escape_string($conn, $_POST['brand_name']);
                            $status = mysqli_real_escape_string($conn, $_POST['status']);
                            
                            // Check if new name already exists (excluding current brand)
                            $check_sql = "SELECT id FROM brands WHERE brand_name = '$brand_name' AND id != '$brand_id'";
                            $check_result = mysqli_query($conn, $check_sql);
                            
                            if (mysqli_num_rows($check_result) > 0) {
                                echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        Brand "'.$brand_name.'" already exists!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                $update_sql = "UPDATE brands SET brand_name = '$brand_name', status = '$status', updated_at = NOW() WHERE id = '$brand_id'";
                                
                                if (mysqli_query($conn, $update_sql)) {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Brand updated successfully!
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
                        
                        // Delete brand
                        elseif ($action == 'delete') {
                            $brand_id = mysqli_real_escape_string($conn, $_POST['brand_id']);
                            
                            // Check if brand has products
                            $check_sql = "SELECT COUNT(*) as product_count FROM products WHERE brand_id = '$brand_id'";
                            $check_result = mysqli_query($conn, $check_sql);
                            $check_data = mysqli_fetch_assoc($check_result);
                            
                            if ($check_data['product_count'] > 0) {
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="mdi mdi-alert-circle-outline me-2"></i>
                                        Cannot delete brand! There are ' . $check_data['product_count'] . ' products assigned to this brand.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                            } else {
                                $delete_sql = "DELETE FROM brands WHERE id = '$brand_id'";
                                
                                if (mysqli_query($conn, $delete_sql)) {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="mdi mdi-check-all me-2"></i>
                                            Brand deleted successfully!
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
                
                // Get brands count for stats
                $stats_sql = "SELECT 
                    COUNT(*) as total_brands,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_brands,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_brands
                    FROM brands";
                
                $stats_result = mysqli_query($conn, $stats_sql);
                $stats = mysqli_fetch_assoc($stats_result);
                
                // Get brands with product counts
                $brands_sql = "SELECT 
                    b.*,
                    COUNT(p.id) as product_count,
                    SUM(p.quantity) as total_stock,
                    SUM(p.stock_price * p.quantity) as stock_value,
                    SUM(p.profit) as total_profit
                    FROM brands b
                    LEFT JOIN products p ON b.id = p.brand_id
                    $where_clause
                    GROUP BY b.id
                    ORDER BY b.brand_name ASC";
                
                $brands_result = mysqli_query($conn, $brands_sql);
                ?>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-tag-outline"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Brands</p>
                                        <h4 class="mb-0"><?php echo number_format($stats['total_brands']); ?></h4>
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
                                        <h4 class="mb-0 text-success"><?php echo number_format($stats['active_brands']); ?></h4>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Branded Products</p>
                                        <h4 class="mb-0 text-warning">
                                            <?php 
                                            $product_sql = "SELECT COUNT(*) as total FROM products WHERE brand_id IS NOT NULL";
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
                                            <i class="mdi mdi-chart-line"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Total Profit</p>
                                        <h4 class="mb-0 text-info">
                                            <?php 
                                            $profit_sql = "SELECT SUM(profit) as total_profit FROM products";
                                            $profit_result = mysqli_query($conn, $profit_sql);
                                            $profit_data = mysqli_fetch_assoc($profit_result);
                                            echo '₹' . number_format($profit_data['total_profit'] ?? 0, 2);
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
                    <!-- Add New Brand Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-plus-circle text-success me-1"></i> Add New Brand
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="brands-list.php" id="addBrandForm">
                                    <input type="hidden" name="action" value="add">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Brand Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="brand_name" required 
                                               placeholder="e.g., Bisleri, Kinley, Aquafina" maxlength="100"
                                               id="brandNameInput">
                                        <small class="text-muted">Unique brand name for products</small>
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
                                            <i class="mdi mdi-plus-circle me-1"></i> Add Brand
                                        </button>
                                    </div>
                                </form>
                                

                            </div>
                        </div>
                        
                        <!-- Quick Water Brands -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-lightning-bolt text-warning me-1"></i> Quick Water Brands
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Bisleri">
                                            <small>Bisleri</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Kinley">
                                            <small>Kinley</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Aquafina">
                                            <small>Aquafina</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Himalayan">
                                            <small>Himalayan</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Bailley">
                                            <small>Bailley</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Kingfisher">
                                            <small>Kingfisher</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="EVian">
                                            <small>Evian</small>
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button type="button" class="btn btn-outline-primary w-100 quick-brand" data-brand="Local">
                                            <small>Local</small>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Top Performing Brands -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-trophy text-success me-1"></i> Top Brands (Profit)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $top_brands_sql = "SELECT 
                                    b.brand_name,
                                    COUNT(p.id) as product_count,
                                    SUM(p.profit) as total_profit
                                    FROM brands b
                                    JOIN products p ON b.id = p.brand_id
                                    WHERE p.quantity > 0
                                    GROUP BY b.id
                                    ORDER BY total_profit DESC
                                    LIMIT 5";
                                $top_brands_result = mysqli_query($conn, $top_brands_sql);
                                
                                if (mysqli_num_rows($top_brands_result) > 0) {
                                    $rank = 1;
                                    while ($brand = mysqli_fetch_assoc($top_brands_result)) {
                                        $medal_class = '';
                                        if ($rank == 1) $medal_class = 'text-warning';
                                        elseif ($rank == 2) $medal_class = 'text-secondary';
                                        elseif ($rank == 3) $medal_class = 'text-danger';
                                        else $medal_class = 'text-muted';
                                        
                                        echo '<div class="d-flex align-items-center mb-3">
                                            <div class="flex-shrink-0">
                                                <span class="avatar-xs">
                                                    <span class="avatar-title rounded-circle bg-light text-dark fw-bold">
                                                        '.$rank.'
                                                    </span>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">'.$brand['brand_name'].'</h6>
                                                <small class="text-muted">'.$brand['product_count'].' products</small>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="text-success fw-bold">₹'.number_format($brand['total_profit'], 2).'</span>
                                            </div>
                                        </div>';
                                        $rank++;
                                    }
                                } else {
                                    echo '<div class="text-center py-3">
                                        <div class="text-muted">
                                            <i class="mdi mdi-chart-line display-5"></i>
                                            <p class="mt-2 mb-0">No brand data yet</p>
                                        </div>
                                    </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Brands List -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="mdi mdi-tag-multiple-outline text-primary me-1"></i> 
                                        All Brands
                                    </h5>
                                    <div class="d-flex gap-2">
                                        <div class="search-box">
                                            <input type="text" class="form-control" id="searchInput" placeholder="Search brands...">
                                            <i class="ri-search-line search-icon"></i>
                                        </div>
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
                                                <li><a class="dropdown-item filter-option" href="brands-list.php">All Brands</a></li>
                                                <li><a class="dropdown-item filter-option" href="brands-list.php?status=active">Active Only</a></li>
                                                <li><a class="dropdown-item filter-option" href="brands-list.php?status=inactive">Inactive Only</a></li>
                                            </ul>
                                        </div>
                                        <button type="button" class="btn btn-info" onclick="printBrands()">
                                            <i class="mdi mdi-printer me-1"></i> Print
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="exportBrands()">
                                            <i class="mdi mdi-file-export me-1"></i> Export
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($brands_result) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-centered align-middle table-nowrap mb-0" id="brandsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Brand Name</th>
                                                <th>Products</th>
                                                <th>Stock</th>
                                                <th>Stock Value</th>
                                                <th>Profit</th>
                                                <th>Status</th>
                                                <th>Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $counter = 1;
                                            while ($brand = mysqli_fetch_assoc($brands_result)): 
                                                $status_class = $brand['status'] == 'active' ? 'badge-soft-success' : 'badge-soft-danger';
                                                $profit_class = $brand['total_profit'] > 0 ? 'text-success' : ($brand['total_profit'] < 0 ? 'text-danger' : 'text-muted');
                                            ?>
                                            <tr id="brandRow<?php echo $brand['id']; ?>" data-brand-name="<?php echo htmlspecialchars($brand['brand_name']); ?>">
                                                <td><?php echo $counter; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <div class="avatar-xs">
                                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                                    <?php echo strtoupper(substr($brand['brand_name'], 0, 1)); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($brand['brand_name']); ?></h5>
                                                            <small class="text-muted">ID: <?php echo $brand['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <h5 class="mb-0 <?php echo $brand['product_count'] > 0 ? 'text-warning' : 'text-muted'; ?>">
                                                            <?php echo $brand['product_count']; ?>
                                                        </h5>
                                                        <small class="text-muted">products</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($brand['product_count'] > 0): ?>
                                                    <div class="text-center">
                                                        <h6 class="mb-0 <?php echo $brand['total_stock'] < 10 ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo number_format($brand['total_stock']); ?>
                                                        </h6>
                                                        <small class="text-muted">units</small>
                                                    </div>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($brand['product_count'] > 0): ?>
                                                    <span class="text-info">₹<?php echo number_format($brand['stock_value'], 2); ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($brand['product_count'] > 0): ?>
                                                    <span class="<?php echo $profit_class; ?>">
                                                        ₹<?php echo number_format($brand['total_profit'], 2); ?>
                                                    </span>
                                                    <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?> font-size-12">
                                                        <?php echo ucfirst($brand['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d M, Y', strtotime($brand['updated_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-info edit-brand" 
                                                                data-id="<?php echo $brand['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($brand['brand_name']); ?>"
                                                                data-status="<?php echo $brand['status']; ?>">
                                                            <i class="mdi mdi-pencil-outline"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-brand" 
                                                                data-id="<?php echo $brand['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($brand['brand_name']); ?>"
                                                                data-count="<?php echo $brand['product_count']; ?>">
                                                            <i class="mdi mdi-delete-outline"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-success view-products" 
                                                                data-id="<?php echo $brand['id']; ?>"
                                                                data-name="<?php echo htmlspecialchars($brand['brand_name']); ?>">
                                                            <i class="mdi mdi-eye-outline"></i>
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
                                            Showing <?php echo mysqli_num_rows($brands_result); ?> 
                                            <?php echo $filter_status; ?> brands
                                        </small>
                                        <a href="brands-list.php" class="btn btn-sm btn-light">
                                            Clear Filter
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="mdi mdi-tag-outline display-4"></i>
                                        <h4 class="mt-3">No Brands Found</h4>
                                        <p class="mb-0">Add your first brand using the form on the left.</p>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-primary quick-brand" data-brand="Bisleri">
                                                <i class="mdi mdi-plus me-1"></i> Add Bisleri
                                            </button>
                                            <button type="button" class="btn btn-primary quick-brand ms-2" data-brand="Kinley">
                                                <i class="mdi mdi-plus me-1"></i> Add Kinley
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
<!-- Brand Performance Chart -->
<div class="card mt-3">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="mdi mdi-chart-bar text-primary me-1"></i> Brand Performance
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Top 5 Brands by Stock Value</h6>
                <?php
                // Reset pointer for brand results
                mysqli_data_seek($brands_result, 0);
                $chart_brands = [];
                $counter = 0;
                
                // Properly fetch brands with stock value > 0
                while ($brand = mysqli_fetch_assoc($brands_result)) {
                    if ($brand['stock_value'] > 0 && $counter < 5) {
                        $chart_brands[] = $brand;
                        $counter++;
                    }
                }
                
                if (count($chart_brands) > 0) {
                    $max_value = max(array_column($chart_brands, 'stock_value'));
                    foreach ($chart_brands as $brand) {
                        $percentage = $max_value > 0 ? ($brand['stock_value'] / $max_value * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted"><?php echo htmlspecialchars($brand['brand_name']); ?></span>
                                <span class="text-info">₹<?php echo number_format($brand['stock_value'], 2); ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-info" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="text-center py-3">
                        <div class="text-muted">
                            <i class="mdi mdi-chart-line display-5"></i>
                            <p class="mt-2 mb-0">No brand performance data</p>
                        </div>
                    </div>';
                }
                ?>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted mb-3">Brand Distribution</h6>
                <div class="text-center">
                    <div class="d-flex justify-content-center">
                        <div class="text-center mx-4">
                            <h4 class="mb-0 text-success"><?php echo number_format($stats['active_brands']); ?></h4>
                            <small class="text-muted">Active</small>
                        </div>
                        <div class="text-center mx-4">
                            <h4 class="mb-0 text-danger"><?php echo number_format($stats['inactive_brands']); ?></h4>
                            <small class="text-muted">Inactive</small>
                        </div>
                        <div class="text-center mx-4">
                            <?php
                            $no_brand_sql = "SELECT COUNT(*) as count FROM products WHERE brand_id IS NULL";
                            $no_brand_result = mysqli_query($conn, $no_brand_sql);
                            $no_brand = mysqli_fetch_assoc($no_brand_result);
                            ?>
                            <h4 class="mb-0 text-warning"><?php echo number_format($no_brand['count']); ?></h4>
                            <small class="text-muted">Unbranded</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="mdi mdi-information-outline me-1"></i>
                            Total Products: 
                            <?php 
                            $total_products_sql = "SELECT COUNT(*) as total FROM products";
                            $total_products_result = mysqli_query($conn, $total_products_sql);
                            $total_products = mysqli_fetch_assoc($total_products_result);
                            echo number_format($total_products['total']);
                            ?>
                        </small>
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

<!-- Edit Brand Modal -->
<div class="modal fade" id="editBrandModal" tabindex="-1" aria-labelledby="editBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBrandModalLabel">Edit Brand</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="brands-list.php" id="editBrandForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="brand_id" id="editBrandId">
                    
                    <div class="mb-3">
                        <label class="form-label">Brand Name</label>
                        <input type="text" class="form-control" name="brand_name" id="editBrandName" required 
                               maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editBrandStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info" id="editBrandInfo">
                        <i class="mdi mdi-information-outline me-2"></i>
                        <small>Updating brand will affect all products under this brand.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmEditBrand">Update Brand</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Brand Modal -->
<div class="modal fade" id="deleteBrandModal" tabindex="-1" aria-labelledby="deleteBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteBrandModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<strong id="deleteBrandName"></strong>"?</p>
                <div id="deleteBrandWarning">
                    <!-- Warning message will be inserted here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBrand">Delete Brand</button>
            </div>
        </div>
    </div>
</div>

<!-- View Products Modal -->
<div class="modal fade" id="viewProductsModal" tabindex="-1" aria-labelledby="viewProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewProductsModalLabel">
                    <i class="mdi mdi-eye-outline me-1"></i> 
                    Products in: <span id="viewBrandName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="productsList">
                    <!-- Products will be loaded here via AJAX -->
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading products...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="addProductToBrand">
                    <i class="mdi mdi-plus-circle me-1"></i> Add Product
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Variables for modals
let editBrandId = null;
let deleteBrandId = null;
let deleteBrandName = null;
let deleteProductCount = null;
let viewBrandId = null;
let viewBrandName = null;

// Quick brand buttons
document.querySelectorAll('.quick-brand').forEach(button => {
    button.addEventListener('click', function() {
        const brandName = this.getAttribute('data-brand');
        document.getElementById('brandNameInput').value = brandName;
        document.getElementById('brandNameInput').focus();
    });
});

// Edit brand
document.querySelectorAll('.edit-brand').forEach(button => {
    button.addEventListener('click', function() {
        editBrandId = this.getAttribute('data-id');
        const brandName = this.getAttribute('data-name');
        const brandStatus = this.getAttribute('data-status');
        
        document.getElementById('editBrandId').value = editBrandId;
        document.getElementById('editBrandName').value = brandName;
        document.getElementById('editBrandStatus').value = brandStatus;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('editBrandModal'));
        modal.show();
    });
});

// Delete brand
document.querySelectorAll('.delete-brand').forEach(button => {
    button.addEventListener('click', function() {
        deleteBrandId = this.getAttribute('data-id');
        deleteBrandName = this.getAttribute('data-name');
        deleteProductCount = parseInt(this.getAttribute('data-count'));
        
        document.getElementById('deleteBrandName').textContent = deleteBrandName;
        
        // Show appropriate warning
        const warningDiv = document.getElementById('deleteBrandWarning');
        if (deleteProductCount > 0) {
            warningDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert-circle me-2"></i>
                    This brand has ${deleteProductCount} product(s). 
                    Brands with products cannot be deleted.<br><br>
                    <strong>Solution:</strong> 
                    <ol class="mb-0">
                        <li>Move products to another brand first</li>
                        <li>Or delete the products first</li>
                        <li>Then delete the brand</li>
                    </ol>
                </div>
            `;
            document.getElementById('confirmDeleteBrand').style.display = 'none';
        } else {
            warningDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="mdi mdi-alert me-2"></i>
                    This action cannot be undone. The brand will be permanently deleted.
                </div>
            `;
            document.getElementById('confirmDeleteBrand').style.display = 'block';
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('deleteBrandModal'));
        modal.show();
    });
});

// View products
document.querySelectorAll('.view-products').forEach(button => {
    button.addEventListener('click', function() {
        viewBrandId = this.getAttribute('data-id');
        viewBrandName = this.getAttribute('data-name');
        
        document.getElementById('viewBrandName').textContent = viewBrandName;
        document.getElementById('addProductToBrand').href = 'add-product.php?brand_id=' + viewBrandId;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('viewProductsModal'));
        modal.show();
        
        // Load products via AJAX
        loadBrandProducts(viewBrandId);
    });
});

// Load brand products via AJAX
function loadBrandProducts(brandId) {
    fetch('get-brand-products.php?brand_id=' + brandId)
        .then(response => response.json())
        .then(data => {
            const productsList = document.getElementById('productsList');
            
            if (data.success && data.products.length > 0) {
                let html = `
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.products.forEach(product => {
                    const statusClass = product.status === 'active' ? 'badge-soft-success' : 
                                      product.status === 'inactive' ? 'badge-soft-danger' : 'badge-soft-warning';
                    const stockClass = product.quantity < 10 ? 'text-danger' : 'text-success';
                    
                    html += `
                        <tr>
                            <td>
                                <strong>${product.product_name}</strong><br>
                                <small class="text-muted">${product.product_code}</small>
                            </td>
                            <td class="${stockClass}">${product.quantity}</td>
                            <td>
                                <small>Stock: ₹${product.stock_price.toFixed(2)}</small><br>
                                <small>Customer: ₹${product.customer_price.toFixed(2)}</small>
                            </td>
                            <td>
                                <span class="badge ${statusClass}">${product.status}</span>
                            </td>
                            <td>
                                <a href="product-view.php?id=${product.id}" class="btn btn-sm btn-info">
                                    <i class="mdi mdi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="mdi mdi-information-outline me-2"></i>
                        Showing ${data.products.length} products in this brand
                    </div>
                `;
                
                productsList.innerHTML = html;
            } else {
                productsList.innerHTML = `
                    <div class="text-center py-5">
                        <div class="text-muted">
                            <i class="mdi mdi-package-variant-closed display-4"></i>
                            <h5 class="mt-3">No Products Found</h5>
                            <p>No products are assigned to this brand yet.</p>
                            <a href="add-product.php?brand_id=${brandId}" class="btn btn-primary mt-2">
                                <i class="mdi mdi-plus-circle me-1"></i> Add First Product
                            </a>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('productsList').innerHTML = `
                <div class="alert alert-danger">
                    <i class="mdi mdi-alert-circle me-2"></i>
                    Error loading products: ${error}
                </div>
            `;
        });
}

// Confirm edit brand
document.getElementById('confirmEditBrand').addEventListener('click', function() {
    // Check if brand name is not empty
    const brandName = document.getElementById('editBrandName').value.trim();
    if (brandName.length < 2) {
        alert('Brand name must be at least 2 characters long');
        return;
    }
    
    // Submit the form
    document.getElementById('editBrandForm').submit();
});

// Confirm delete brand
document.getElementById('confirmDeleteBrand').addEventListener('click', function() {
    if (deleteBrandId && deleteProductCount === 0) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'brands-list.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'brand_id';
        idInput.value = deleteBrandId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
});

// Form validation for add brand
document.getElementById('addBrandForm').addEventListener('submit', function(e) {
    const brandName = document.querySelector('input[name="brand_name"]').value.trim();
    
    if (brandName.length < 2) {
        e.preventDefault();
        alert('Brand name must be at least 2 characters long');
        return false;
    }
    
    if (brandName.length > 100) {
        e.preventDefault();
        alert('Brand name must be less than 100 characters');
        return false;
    }
    
    return true;
});

// Search functionality
function searchBrands() {
    const searchInput = document.getElementById('searchInput');
    const searchTerm = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll('#brandsTable tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const brandName = row.getAttribute('data-brand-name').toLowerCase();
        const text = row.textContent.toLowerCase();
        
        if (brandName.includes(searchTerm) || text.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update search info
    updateSearchInfo(visibleCount);
}

// Update search info
function updateSearchInfo(visibleCount) {
    let searchInfo = document.getElementById('searchInfo');
    
    if (!searchInfo) {
        searchInfo = document.createElement('div');
        searchInfo.id = 'searchInfo';
        searchInfo.className = 'alert alert-info mt-3 py-2';
        document.querySelector('.card-body').appendChild(searchInfo);
    }
    
    if (visibleCount === 0 && document.getElementById('searchInput').value) {
        searchInfo.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <small>
                    <i class="mdi mdi-magnify-close me-2"></i>
                    No brands found for "${document.getElementById('searchInput').value}"
                </small>
                <button type="button" class="btn btn-sm btn-light" onclick="clearSearch()">
                    Clear Search
                </button>
            </div>
        `;
        searchInfo.style.display = 'block';
    } else if (document.getElementById('searchInput').value) {
        searchInfo.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <small>
                    <i class="mdi mdi-magnify me-2"></i>
                    Showing ${visibleCount} of <?php echo $stats['total_brands']; ?> brands
                </small>
                <button type="button" class="btn btn-sm btn-light" onclick="clearSearch()">
                    Clear Search
                </button>
            </div>
        `;
        searchInfo.style.display = 'block';
    } else {
        searchInfo.style.display = 'none';
    }
}

// Clear search
function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchBrands();
}

// Print brands
function printBrands() {
    const printContent = document.getElementById('brandsTable').outerHTML;
    const filterInfo = document.querySelector('.alert-info')?.innerHTML || '';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Product Brands - APR Water Agencies</title>
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
                <div class="title">Product Brands Report</div>
                <div class="subtitle">APR Water Agencies</div>
                <div class="subtitle">Printed on: ${new Date().toLocaleString()}</div>
            </div>
            
            ${filterInfo ? `<div class="info-box">${filterInfo}</div>` : ''}
            
            ${printContent}
            
            <div class="footer">
                Total Brands: <?php echo $stats['total_brands']; ?> | 
                Active: <?php echo $stats['active_brands']; ?> | 
                Inactive: <?php echo $stats['inactive_brands']; ?>
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

// Export brands (placeholder function)
function exportBrands() {
    // This would typically be an AJAX call to export-brands.php
    const table = document.getElementById('brandsTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "data:text/csv;charset=utf-8,Brand Name,Products,Total Stock,Stock Value,Total Profit,Status,Last Updated\n";
    
    // Add rows
    rows.forEach((row, index) => {
        if (index > 0) { // Skip header row
            const cols = row.querySelectorAll('td');
            const brandData = [
                row.getAttribute('data-brand-name'),
                cols[2].querySelector('h5').textContent,
                cols[3].querySelector('h6')?.textContent || '-',
                cols[4].textContent,
                cols[5].textContent,
                cols[6].querySelector('span').textContent,
                cols[7].textContent
            ];
            csvContent += brandData.join(",") + "\n";
        }
    });
    
    // Download CSV
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "brands_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus on brand name input
    const brandInput = document.getElementById('brandNameInput');
    if (brandInput) {
        brandInput.focus();
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', searchBrands);
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
        // Ctrl+N to focus on new brand form
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const brandInput = document.getElementById('brandNameInput');
            if (brandInput) {
                brandInput.focus();
            }
        }
        // Ctrl+F to focus on search
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
            printBrands();
        }
        // Ctrl+E to export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportBrands();
        }
        // Escape to clear search
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value) {
                searchInput.value = '';
                searchBrands();
            }
        }
    });
    
    // Auto-check for duplicate brand names
    const brandNameInput = document.getElementById('brandNameInput');
    if (brandNameInput) {
        let checkTimeout;
        brandNameInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(() => {
                const name = this.value.trim();
                if (name.length >= 2) {
                    // In a real implementation, this would be an AJAX call
                    console.log('Checking brand:', name);
                }
            }, 500);
        });
    }
    
    // Initialize search info
    updateSearchInfo(<?php echo $stats['total_brands']; ?>);
});

// Function to refresh brands list
function refreshBrands() {
    window.location.reload();
}

// Add this file to your project:
// get-brand-products.php (for AJAX product loading)
</script>

</body>

</html>