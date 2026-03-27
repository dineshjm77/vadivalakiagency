<header id="page-topbar">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box">
                    <a href="index.php" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="assets/images/logo-sm.png" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="assets/images/logo-dark.png" alt="" height="20">
                        </span>
                    </a>

                    <a href="index.php" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="assets/images/logo-sm.png" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="assets/images/logo-light.png" alt="" height="40">
                        </span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect" id="vertical-menu-btn">
                    <i class="mdi mdi-menu"></i>
                </button>

                <div class="d-none d-sm-block ms-2">
                    <h4 class="page-title font-size-18">
                        Dashboard 
                        <?php if (isset($_SESSION['name'])): ?>
                        <small class="text-muted ms-2">| Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></small>
                        <?php endif; ?>
                    </h4>
                </div>

            </div>

            

            <div class="d-flex">

                <div class="dropdown d-inline-block ms-2">
                    <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <img class="rounded-circle header-profile-user" src="assets/images/users/avatar-1.jpg"
                            alt="Header Avatar">
                        <?php if (isset($_SESSION['username'])): ?>
                        <span class="d-none d-xl-inline-block ms-1 fw-medium">
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <!-- User Info -->
                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-2">
                                    <img class="rounded-circle" src="assets/images/users/avatar-1.jpg" alt="User" height="40">
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h6>
                                    <small class="text-muted">
                                        <?php 
                                        if (isset($_SESSION['user_role'])) {
                                            echo ucfirst(str_replace('_', ' ', $_SESSION['user_role']));
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        
                        <!-- item-->
                        <a class="dropdown-item" href="my-profile.php"><i class="dripicons-user font-size-16 align-middle me-2"></i>
                            Profile</a>
                        <a class="dropdown-item" href="change-password.php"><i class="dripicons-lock font-size-16 align-middle me-2"></i> Change Password</a>
                        <a class="dropdown-item d-block" href="settings.php"><i class="dripicons-gear font-size-16 align-middle me-2"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="../logout.php"><i class="dripicons-exit font-size-16 align-middle me-2"></i>
                            Logout</a>
                    </div>
                </div>

                <div class="dropdown d-inline-block">
                    <button type="button" class="btn header-item noti-icon right-bar-toggle waves-effect">
                        <i class="mdi mdi-spin mdi-cog"></i>
                    </button>
                </div>

            </div>
        </div>
    </header>