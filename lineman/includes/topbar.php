<?php
$displayName = $_SESSION['name'] ?? 'Lineman';
$initial = strtoupper(substr($displayName, 0, 1));
$displayRole = ($_SESSION['user_role'] ?? '') === 'lineman' ? 'Line Man' : 'Admin Preview';
?>
<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-light mobile-toggle" type="button" id="linemanSidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h5 class="mb-0"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Lineman Panel'; ?></h5>
                <small class="text-muted">Welcome, <?php echo htmlspecialchars($displayName); ?></small>
            </div>
        </div>

        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar-circle me-2"><?php echo $initial; ?></div>
                <div class="text-start d-none d-sm-block">
                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($displayName); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($displayRole); ?></small>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($displayName); ?></h6></li>
                <li><a class="dropdown-item" href="index.php"><i class="fas fa-gauge me-2"></i>Dashboard</a></li>
                <li><a class="dropdown-item" href="index.php?section=orders#assigned-orders"><i class="fas fa-file-invoice me-2"></i>Assigned Orders</a></li>
                <li><a class="dropdown-item" href="index.php?section=collections#collection-history"><i class="fas fa-money-bill-wave me-2"></i>Collection History</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-right-from-bracket me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</header>
