<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// User က Login မဝင်ထားရင် Login Page သို့ Redirect လုပ်ခြင်း
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Secure Prepared Statement ဖြင့် Database မှ Profile Details ကို ဆွဲထုတ်ခြင်း
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "<main class='container my-5'><div class='alert alert-danger border-0 rounded-4 shadow-sm p-4'><i class='fa-solid fa-triangle-exclamation me-2'></i>User record not found.</div></main>";
    require_once 'includes/footer.php';
    exit();
}

// User Role အလိုက် Badge Color သတ်မှတ်ခြင်း
$role_badge_class = "bg-primary-subtle text-primary border-primary-subtle";
if ($user['role'] === 'admin') {
    $role_badge_class = "bg-danger-subtle text-danger border-danger-subtle";
} elseif ($user['role'] === 'owner') {
    $role_badge_class = "bg-warning-subtle text-warning-emphasis border-warning-subtle";
}
?>

<main class="container my-5" style="max-width: 960px;">

    <!-- Page Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-extrabold text-dark tracking-tight mb-1">
                <i class="fa-solid fa-id-card text-primary me-2"></i>Account Overview
            </h2>
            <p class="text-secondary mb-0" style="font-size: 0.88rem;">Manage your profile information and access details.</p>
        </div>
        <a href="edit_profile.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 fw-semibold fs-7 shadow-sm">
            <i class="fa-solid fa-pen-to-square me-1.5"></i> Edit Profile
        </a>
    </div>

    <div class="row g-4">
        
        <!-- Left Section: Profile Avatar, Role & Quick Stats -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-white h-100">
                
                <!-- Avatar Container -->
                <div class="position-relative d-inline-block mx-auto mb-3">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-primary border border-2 border-primary-subtle shadow-sm mx-auto" style="width: 110px; height: 110px;">
                        <i class="fa-solid fa-user-tie fa-3x opacity-75"></i>
                    </div>
                </div>

                <!-- Username & Role -->
                <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
                <div class="mb-3">
                    <span class="badge border px-3 py-1.5 rounded-pill text-uppercase fw-bold <?php echo $role_badge_class; ?>" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-shield-halved me-1"></i> <?php echo htmlspecialchars($user['role']); ?>
                    </span>
                </div>

                <hr class="my-3 opacity-25">

                <!-- Account Status Meta -->
                <div class="text-start d-flex flex-column gap-2.5">
                    <div class="d-flex align-items-center gap-2.5 text-secondary" style="font-size: 0.82rem;">
                        <i class="fa-regular fa-calendar text-primary flex-shrink-0" style="width: 16px;"></i>
                        <div>
                            <span class="d-block text-muted fs-8">Member Since</span>
                            <strong class="text-dark"><?php echo date('d M Y', strtotime($user['created_at'])); ?></strong>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2.5 text-secondary" style="font-size: 0.82rem;">
                        <i class="fa-solid fa-right-to-bracket text-primary flex-shrink-0" style="width: 16px;"></i>
                        <div>
                            <span class="d-block text-muted fs-8">Last Session Access</span>
                            <strong class="text-dark"><?php echo !empty($user['last_login']) ? date('d M Y, H:i', strtotime($user['last_login'])) : 'First Login'; ?></strong>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Right Section: Profile Information Grid -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white h-100">
                
                <!-- Section 1: Personal Coordinates -->
                <div class="mb-4">
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user text-primary fs-6"></i> Personal Coordinates
                    </h5>
                    
                    <div class="row g-3" style="font-size: 0.88rem;">
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-8 mb-0.5">Full Name</span>
                            <strong class="text-dark"><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-8 mb-0.5">First / Last Name</span>
                            <strong class="text-dark"><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-8 mb-0.5">Gender Designation</span>
                            <span class="badge bg-light text-dark border font-normal px-2.5 py-1 rounded-2">
                                <?php echo htmlspecialchars($user['gender'] ?? 'Not Configured'); ?>
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-8 mb-0.5">Date of Birth</span>
                            <strong class="text-dark"><?php echo !empty($user['date_of_birth']) ? date('d M Y', strtotime($user['date_of_birth'])) : 'Not Provided'; ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Contact & Location Registry -->
                <div>
                    <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                        <i class="fa-solid fa-location-dot text-primary fs-6"></i> Contact & Residential Registry
                    </h5>

                    <div class="row g-3" style="font-size: 0.88rem;">
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-8 mb-0.5">Email Address</span>
                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-primary fw-semibold text-decoration-none">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </a>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block fs-8 mb-0.5">Phone Index</span>
                            <strong class="text-dark"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></strong>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block fs-8 mb-0.5">Street Address</span>
                            <span class="text-dark"><?php echo nl2br(htmlspecialchars($user['address'] ?? 'Not Specified')); ?></span>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted d-block fs-8 mb-0.5">City / Location</span>
                            <strong class="text-dark"><?php echo htmlspecialchars($user['city'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted d-block fs-8 mb-0.5">Country Jurisdiction</span>
                            <strong class="text-dark"><?php echo htmlspecialchars($user['country'] ?? '-'); ?></strong>
                        </div>
                        <div class="col-sm-4">
                            <span class="text-muted d-block fs-8 mb-0.5">Postal Zip Code</span>
                            <code class="bg-light text-dark px-2 py-1 rounded border fs-8"><?php echo htmlspecialchars($user['postal_code'] ?? 'None'); ?></code>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

</main>

<?php require_once 'includes/footer.php'; ?>