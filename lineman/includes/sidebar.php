<?php
$currentPage = $currentPage ?? 'dashboard';
?>
<div class="brand-box"><i class="fas fa-truck-fast me-2"></i> Lineman Panel</div>
<ul class="side-menu">
    <li class="menu-title">Main</li>
    <li>
        <a href="index.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-gauge"></i><span>Dashboard</span>
        </a>
    </li>

    <li>
    <a href="create-order.php" class="<?php echo $currentPage === 'create-order' ? 'active' : ''; ?>">
        <i class="fas fa-cart-plus"></i><span>Create Order</span>
    </a>
</li>
    <li>
    <a href="assign-order.php" class="<?php echo $currentPage === 'orders' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice"></i><span>Assigned Orders</span>
    </a>
</li>
    <li>
        <a href="Sale-Invoice.php" class="<?php echo $currentPage === 'first_sale' ? 'active' : ''; ?>">
            <i class="fas fa-file-circle-plus"></i><span>First Sale Invoice</span>
        </a>
    </li>
    <li>
        <a href="Performance-Invoice.php" class="<?php echo $currentPage === 'performance' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i><span>Performance Invoice</span>
        </a>
    </li>
    <li>
        <a href="Completed-Invoice.php" class="<?php echo $currentPage === 'completed' ? 'active' : ''; ?>">
            <i class="fas fa-circle-check"></i><span>Completed Invoice</span>
        </a>
    </li>
    <li>
        <a href="Collection-History.php" class="<?php echo $currentPage === 'collections' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i><span>Collection History</span>
        </a>
    </li>

    <li class="menu-title">Account</li>
    <li>
        <a href="logout.php">
            <i class="fas fa-right-from-bracket"></i><span>Logout</span>
        </a>
    </li>
</ul>
