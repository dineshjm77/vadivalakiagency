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
                
                // Check if ID is provided
                if (!isset($_GET['id']) || empty($_GET['id'])) {
                    echo '<div class="alert alert-danger">Product ID not provided.</div>';
                    echo '<a href="products-list.php" class="btn btn-primary mt-3">Back to Products</a>';
                    exit();
                }
                
                $product_id = mysqli_real_escape_string($conn, $_GET['id']);
                
                // Fetch product details with joins
                $sql = "SELECT 
                    p.*,
                    c.category_name,
                    b.brand_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN brands b ON p.brand_id = b.id
                    WHERE p.id = '$product_id'";
                
                $result = mysqli_query($conn, $sql);
                
                if (!$result || mysqli_num_rows($result) == 0) {
                    echo '<div class="alert alert-danger">Product not found.</div>';
                    echo '<a href="products-list.php" class="btn btn-primary mt-3">Back to Products</a>';
                    mysqli_close($conn);
                    exit();
                }
                
                $product = mysqli_fetch_assoc($result);
                
                // Calculate additional values
                $total_stock_value = $product['stock_price'] * $product['quantity'];
                $total_selling_value = $product['customer_price'] * $product['quantity'];
                $total_profit_value = $product['profit'] * $product['quantity'];
                
                // Status badge color
                $status_class = '';
                $status_text = ucfirst(str_replace('_', ' ', $product['status']));
                if ($product['status'] == 'active') $status_class = 'bg-success';
                elseif ($product['status'] == 'inactive') $status_class = 'bg-danger';
                elseif ($product['status'] == 'out_of_stock') $status_class = 'bg-warning';
                
                // Stock status
                $stock_status = '';
                $stock_class = '';
                if ($product['quantity'] == 0) {
                    $stock_status = 'Out of Stock';
                    $stock_class = 'bg-danger';
                } elseif ($product['quantity'] < 10) {
                    $stock_status = 'Low Stock';
                    $stock_class = 'bg-warning';
                } else {
                    $stock_status = 'In Stock';
                    $stock_class = 'bg-success';
                }
                
                // Profit color
                $profit_class = $product['profit'] >= 0 ? 'text-success' : 'text-danger';
                $profit_icon = $product['profit'] >= 0 ? 'mdi-arrow-up' : 'mdi-arrow-down';
                
                // Format dates
                $created_date = date('d M, Y', strtotime($product['created_at']));
                $updated_date = date('d M, Y', strtotime($product['updated_at']));
                
                mysqli_close($conn);
                ?>

                <!-- Product Header -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-4">
                                        <div class="avatar-xxl">
                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle display-1">
                                                <?php echo strtoupper(substr($product['product_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="row">
                                            <div class="col-lg-8">
                                                <h4 class="card-title mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                                <p class="text-muted mb-2"><?php echo $product['product_code']; ?></p>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <span class="badge <?php echo $status_class; ?> font-size-14"><?php echo $status_text; ?></span>
                                                    <span class="badge <?php echo $stock_class; ?> font-size-14">
                                                        <i class="mdi mdi-package-variant me-1"></i> <?php echo $stock_status; ?> (<?php echo $product['quantity']; ?>)
                                                    </span>
                                                    <?php if (!empty($product['category_name'])): ?>
                                                    <span class="badge bg-info font-size-14">
                                                        <i class="mdi mdi-tag-outline me-1"></i> <?php echo $product['category_name']; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($product['brand_name'])): ?>
                                                    <span class="badge bg-secondary font-size-14">
                                                        <i class="mdi mdi-tag-text-outline me-1"></i> <?php echo $product['brand_name']; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-3">
                                                    <p class="text-muted mb-0">
                                                        <i class="mdi mdi-calendar-check me-1"></i> Created: <?php echo $created_date; ?>
                                                    </p>
                                                    <p class="text-muted mb-0">
                                                        <i class="mdi mdi-calendar-edit me-1"></i> Last Updated: <?php echo $updated_date; ?>
                                                    </p>
                                                    <?php if (!empty($product['description'])): ?>
                                                    <p class="text-muted mb-0 mt-2">
                                                        <i class="mdi mdi-text-box-outline me-1"></i> 
                                                        <?php echo htmlspecialchars($product['description']); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="mt-3 mt-lg-0">
                                                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                                        <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">
                                                            <i class="mdi mdi-pencil-outline me-1"></i> Edit
                                                        </a>
                                                        <a href="add-stock.php?product_id=<?php echo $product['id']; ?>" class="btn btn-success">
                                                            <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                                        </a>
                                                        <a href="products-list.php" class="btn btn-light">
                                                            <i class="mdi mdi-arrow-left me-1"></i> Back
                                                        </a>
                                                        <div class="dropdown">
                                                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="mdi mdi-dots-horizontal"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item" href="#" onclick="printProduct()">
                                                                        <i class="mdi mdi-printer me-1"></i> Print
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item" href="#" onclick="copyProductInfo()">
                                                                        <i class="mdi mdi-content-copy me-1"></i> Copy Details
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <a class="dropdown-item text-danger" href="delete-product.php?id=<?php echo $product['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')">
                                                                        <i class="mdi mdi-delete-outline me-1"></i> Delete
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span class="avatar-title bg-primary-subtle text-primary rounded-2 fs-2">
                                            <i class="mdi mdi-currency-inr"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Stock Price</p>
                                        <h4 class="mb-0">₹<?php echo number_format($product['stock_price'], 2); ?></h4>
                                        <small class="text-muted">Cost per unit</small>
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
                                            <i class="mdi mdi-cash"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Customer Price</p>
                                        <h4 class="mb-0">₹<?php echo number_format($product['customer_price'], 2); ?></h4>
                                        <small class="text-muted">Selling price</small>
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
                                        <span class="avatar-title <?php echo $profit_class; ?>-subtle <?php echo $profit_class; ?> rounded-2 fs-2">
                                            <i class="mdi <?php echo $profit_icon; ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-medium text-muted mb-0">Profit per Unit</p>
                                        <h4 class="mb-0 <?php echo $profit_class; ?>">
                                            ₹<?php echo number_format($product['profit'], 2); ?>
                                        </h4>
                                        <small class="text-muted"><?php echo number_format($product['profit_percentage'], 1); ?>% margin</small>
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
                                        <p class="text-uppercase fw-medium text-muted mb-0">Stock Quantity</p>
                                        <h4 class="mb-0"><?php echo number_format($product['quantity']); ?></h4>
                                        <small class="text-muted">
                                            <span class="badge <?php echo $stock_class; ?>"><?php echo $stock_status; ?></span>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row">
                    <!-- Product Details -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-information-outline me-1"></i> Product Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <th class="ps-0" width="40%">Product Code:</th>
                                                <td class="text-muted">
                                                    <span class="badge bg-primary-subtle text-primary"><?php echo $product['product_code']; ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Product Name:</th>
                                                <td class="text-muted"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Category:</th>
                                                <td class="text-muted">
                                                    <?php if (!empty($product['category_name'])): ?>
                                                    <span class="badge bg-info-subtle text-info"><?php echo $product['category_name']; ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Brand:</th>
                                                <td class="text-muted">
                                                    <?php if (!empty($product['brand_name'])): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary"><?php echo $product['brand_name']; ?></span>
                                                    <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Status:</th>
                                                <td>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Created Date:</th>
                                                <td class="text-muted"><?php echo $created_date; ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Last Updated:</th>
                                                <td class="text-muted"><?php echo $updated_date; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-chart-line me-1"></i> Financial Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <th class="ps-0" width="40%">Stock Price:</th>
                                                <td class="text-muted">₹<?php echo number_format($product['stock_price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Customer Price:</th>
                                                <td class="text-muted text-success">₹<?php echo number_format($product['customer_price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Profit per Unit:</th>
                                                <td class="text-muted <?php echo $profit_class; ?>">
                                                    ₹<?php echo number_format($product['profit'], 2); ?> 
                                                    (<?php echo number_format($product['profit_percentage'], 1); ?>%)
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Quantity Available:</th>
                                                <td class="text-muted">
                                                    <span class="<?php echo $product['quantity'] < 10 ? 'text-warning' : 'text-success'; ?>">
                                                        <?php echo number_format($product['quantity']); ?> units
                                                    </span>
                                                    <span class="badge <?php echo $stock_class; ?> ms-2"><?php echo $stock_status; ?></span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Total Stock Value:</th>
                                                <td class="text-muted text-warning">₹<?php echo number_format($total_stock_value, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Total Selling Value:</th>
                                                <td class="text-muted text-success">₹<?php echo number_format($total_selling_value, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="ps-0">Total Profit Potential:</th>
                                                <td class="text-muted <?php echo $profit_class; ?>">
                                                    ₹<?php echo number_format($total_profit_value, 2); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description Card -->
                <?php if (!empty($product['description'])): ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="mdi mdi-text-box-outline me-1"></i> Description
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted"><?php echo htmlspecialchars($product['description']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stock History (Optional - could be implemented later) -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <i class="mdi mdi-history me-1"></i> Quick Actions
                                    </h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="product-edit.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">
                                        <i class="mdi mdi-pencil-outline me-1"></i> Edit Product
                                    </a>
                                    <a href="add-stock.php?product_id=<?php echo $product['id']; ?>" class="btn btn-success">
                                        <i class="mdi mdi-plus-circle me-1"></i> Add Stock
                                    </a>
                                    <a href="products-list.php" class="btn btn-light">
                                        <i class="mdi mdi-arrow-left me-1"></i> Back to Products
                                    </a>
                                    <button type="button" class="btn btn-info" onclick="printProduct()">
                                        <i class="mdi mdi-printer me-1"></i> Print Details
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="copyProductInfo()">
                                        <i class="mdi mdi-content-copy me-1"></i> Copy Info
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

<!-- JAVASCRIPT -->
<?php include('includes/scripts.php')?>

<script>
// Function to print product details
function printProduct() {
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Product Details - <?php echo htmlspecialchars($product['product_name']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
                .product-title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px; }
                .product-code { font-size: 14px; color: #666; }
                .section { margin-bottom: 20px; }
                .section-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .info-table { width: 100%; border-collapse: collapse; }
                .info-table th { text-align: left; padding: 8px 0; color: #666; width: 40%; }
                .info-table td { padding: 8px 0; color: #333; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                .badge-success { background-color: #d4edda; color: #155724; }
                .badge-warning { background-color: #fff3cd; color: #856404; }
                .badge-danger { background-color: #f8d7da; color: #721c24; }
                .badge-info { background-color: #d1ecf1; color: #0c5460; }
                .text-success { color: #28a745; }
                .text-danger { color: #dc3545; }
                .text-warning { color: #ffc107; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                <div class="product-code"><?php echo $product['product_code']; ?></div>
                <div>Printed on: ${new Date().toLocaleString()}</div>
            </div>

            <div class="section">
                <div class="section-title">Product Information</div>
                <table class="info-table">
                    <tr>
                        <th>Product Code:</th>
                        <td><?php echo $product['product_code']; ?></td>
                    </tr>
                    <tr>
                        <th>Product Name:</th>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?php echo !empty($product['category_name']) ? $product['category_name'] : 'Not assigned'; ?></td>
                    </tr>
                    <tr>
                        <th>Brand:</th>
                        <td><?php echo !empty($product['brand_name']) ? $product['brand_name'] : 'Not assigned'; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><span class="badge badge-<?php echo $product['status'] == 'active' ? 'success' : ($product['status'] == 'inactive' ? 'danger' : 'warning'); ?>"><?php echo $status_text; ?></span></td>
                    </tr>
                    <tr>
                        <th>Stock Status:</th>
                        <td><span class="badge badge-<?php echo $stock_class == 'bg-success' ? 'success' : ($stock_class == 'bg-warning' ? 'warning' : 'danger'); ?>"><?php echo $stock_status; ?> (<?php echo $product['quantity']; ?>)</span></td>
                    </tr>
                    <tr>
                        <th>Created Date:</th>
                        <td><?php echo $created_date; ?></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <div class="section-title">Financial Information</div>
                <table class="info-table">
                    <tr>
                        <th>Stock Price (Cost):</th>
                        <td>₹<?php echo number_format($product['stock_price'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Customer Price (Selling):</th>
                        <td class="text-success">₹<?php echo number_format($product['customer_price'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Profit per Unit:</th>
                        <td class="<?php echo $profit_class; ?>">₹<?php echo number_format($product['profit'], 2); ?> (<?php echo number_format($product['profit_percentage'], 1); ?>%)</td>
                    </tr>
                    <tr>
                        <th>Quantity Available:</th>
                        <td><?php echo number_format($product['quantity']); ?> units</td>
                    </tr>
                    <tr>
                        <th>Total Stock Value:</th>
                        <td class="text-warning">₹<?php echo number_format($total_stock_value, 2); ?></td>
                    </tr>
                    <tr>
                        <th>Total Selling Value:</th>
                        <td class="text-success">₹<?php echo number_format($total_selling_value, 2); ?></td>
                    </tr>
                    <tr>
                        <th>Total Profit Potential:</th>
                        <td class="<?php echo $profit_class; ?>">₹<?php echo number_format($total_profit_value, 2); ?></td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($product['description'])): ?>
            <div class="section">
                <div class="section-title">Description</div>
                <p><?php echo htmlspecialchars($product['description']); ?></p>
            </div>
            <?php endif; ?>

            <div class="footer">
                APR Water Agencies - Product Management System<br>
                Generated on: ${new Date().toLocaleString()}
            </div>

            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Now</button>
                <button onclick="window.close()" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">Close</button>
            </div>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    
    // Auto print after loading
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

// Function to copy product information to clipboard
function copyProductInfo() {
    const productInfo = `
Product Details:
---------------
Product Name: ${document.querySelector('.card-title').textContent.trim()}
Product Code: <?php echo $product['product_code']; ?>
Category: <?php echo !empty($product['category_name']) ? $product['category_name'] : 'Not assigned'; ?>
Brand: <?php echo !empty($product['brand_name']) ? $product['brand_name'] : 'Not assigned'; ?>
Status: <?php echo $status_text; ?>
Stock Status: <?php echo $stock_status; ?> (<?php echo $product['quantity']; ?> units)

Pricing:
--------
Stock Price: ₹<?php echo number_format($product['stock_price'], 2); ?>
Customer Price: ₹<?php echo number_format($product['customer_price'], 2); ?>
Profit per Unit: ₹<?php echo number_format($product['profit'], 2); ?> (<?php echo number_format($product['profit_percentage'], 1); ?>%)

Stock Information:
-----------------
Quantity Available: <?php echo number_format($product['quantity']); ?>
Total Stock Value: ₹<?php echo number_format($total_stock_value, 2); ?>
Total Selling Value: ₹<?php echo number_format($total_selling_value, 2); ?>
Total Profit Potential: ₹<?php echo number_format($total_profit_value, 2); ?>

Created: <?php echo $created_date; ?>
Last Updated: <?php echo $updated_date; ?>
    `.trim();
    
    navigator.clipboard.writeText(productInfo).then(() => {
        alert('Product information copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        alert('Failed to copy information. Please try again.');
    });
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+P for print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printProduct();
    }
    // Ctrl+C for copy (when not in input field)
    if (e.ctrlKey && e.key === 'c' && !e.target.matches('input, textarea, select')) {
        e.preventDefault();
        copyProductInfo();
    }
    // Escape to go back
    if (e.key === 'Escape') {
        window.location.href = 'products-list.php';
    }
});
</script>

</body>

</html>