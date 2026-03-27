
<div id="sidebar-menu">
    <!-- Left Menu Start -->
    <ul class="metismenu list-unstyled" id="side-menu">
        <li class="menu-title">Main</li>

        <li>
            <a href="index.php" class="waves-effect">
                <i class="dripicons-device-desktop"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="orders.php" class="waves-effect">
                <i class="dripicons-checklist"></i>
                <span>Orders</span>
            </a>
        </li>
        
        <!-- Quick Order Option Added Here -->
        <li>
            <a href="quick-order.php" class="waves-effect">
                <i class="dripicons-rocket"></i>
                <span>Quick Order</span>
            </a>
        </li>

        <li>
            <a href="my-shops.php" class="waves-effect">
                <i class="dripicons-store"></i>
                <span>My Shops</span>
            </a>
        </li>

        <!-- Add New Customer - Using alternative icon -->
        <li>
            <a href="add-customer.php" class="waves-effect">
                <i class="dripicons-user-id"></i> <!-- Alternative icon -->
                <span>Add New Customer</span>
            </a>
        </li>

        <!-- Alternative: Using Font Awesome if Dripicons not available -->
        <!--
        <li>
            <a href="add-customer.php" class="waves-effect">
                <i class="fas fa-user-plus"></i>
                <span>Add New Customer</span>
            </a>
        </li>
        -->

        <!-- Customers - Single Item (No Dropdown) -->
        <li>
            <a href="active-customers.php" class="waves-effect">
                <i class="dripicons-user-group"></i>
                <span>All Customers</span>
            </a>
        </li>

        <li class="menu-title">Payments & Receipts</li>

        <li>
            <a href="pending-payments.php" class="waves-effect">
                <i class="dripicons-card"></i>
                <span>Pending Payments</span>
            </a>
        </li>

        <li>
            <a href="daily-collection.php" class="waves-effect">
                <i class="dripicons-briefcase"></i>
                <span>Daily Collection</span>
            </a>
        </li>

        <li>
            <a href="javascript: void(0);" class="has-arrow waves-effect">
                  <i class="dripicons-user-id"></i> <!-- Alternative icon -->
                <span>Receipts</span>
            </a>
            <ul class="sub-menu" aria-expanded="false">
                <li><a href="today-receipts.php"></i> Today's Receipts</a></li>
                <li><a href="all-receipts.php"></i> All Receipts</a></li>
            </ul>
        </li>

        <li class="menu-title">Products & Catalog</li>

        <li>
            <a href="product-catalog.php" class="waves-effect">
                <i class="dripicons-view-thumb"></i>
                <span>Product Catalog</span>
            </a>
        </li>

        <li>
            <a href="available-stock.php" class="waves-effect">
                <i class="dripicons-basket"></i>
                <span>Available Stock</span>
            </a>
        </li>

        <li>
            <a href="price-list.php" class="waves-effect">
                <i class="dripicons-tags"></i>
                <span>Price List</span>
            </a>
        </li>

        <li class="menu-title">Reports</li>

        <li>
            <a href="daily-report.php" class="waves-effect">
                <i class="dripicons-document-new"></i>
                <span>Daily Report</span>
            </a>
        </li>

        <li>
            <a href="sales-report.php" class="waves-effect">
                <i class="dripicons-graph-bar"></i>
                <span>Sales Report</span>
            </a>
        </li>

        <li>
            <a href="collection-report.php" class="waves-effect">
                <i class="dripicons-wallet"></i>
                <span>Collection Report</span>
            </a>
        </li>

        <!-- Logout Option -->
        <li class="menu-title mt-5">Account</li>
        
        <li>
            <a href="../logout.php" class="waves-effect text-danger" 
               onclick="return confirm('Are you sure you want to logout?')">
                <i class="dripicons-exit"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

