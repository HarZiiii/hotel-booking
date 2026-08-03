<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg custom-navbar sticky-top py-3">
    <div class="container" style="max-width: 1100px;">
        
        <!-- Brand Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fa-solid fa-hotel fs-6"></i>
            </div>
            <span class="fs-5 tracking-tight">Stay<span class="text-info">Flow</span></span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars fs-5 text-white"></i>
        </button>

        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-menu navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-2 <?php echo ($current_page == 'index.php') ? 'active fw-bold' : ''; ?>" href="index.php">
                        <i class="fa-solid fa-house me-1 fs-7"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-2 <?php echo ($current_page == 'about.php') ? 'active fw-bold' : ''; ?>" href="about.php">
                        <i class="fa-solid fa-circle-info me-1 fs-7"></i> About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-2 <?php echo ($current_page == 'contact.php') ? 'active fw-bold' : ''; ?>" href="contact.php">
                        <i class="fa-solid fa-envelope me-1 fs-7"></i> Contact Support
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-2 <?php echo ($current_page == 'gallery.php') ? 'active fw-bold' : ''; ?>" href="gallery.php">
                        <i class="fa-solid fa-images me-1 fs-7"></i> Gallery
                    </a>
                </li>
            </ul>

            <!-- Right Side Auth Menu -->
            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light border-secondary-subtle dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1.5 fs-7 text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user-circle text-primary fs-6"></i>
                            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Account'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end rounded-3 p-2 mt-2">
                            <li>
                                <a class="dropdown-item rounded-2 py-2 fs-7" href="profile.php">
                                    <i class="fa-solid fa-id-card me-2 text-primary"></i> Profile Info
                                </a>
                            </li>
                            <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2 fs-7 text-danger" href="logout.php">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i> Log Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-link text-white text-decoration-none fw-semibold fs-7 px-3">Log In</a>
                    <a href="register.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold fs-7 shadow-sm">
                        Register
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>