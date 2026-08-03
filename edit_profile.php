<?php
// Relative Path ဖြင့် Config နှင့် Header ဖိုင်များကို ခေါ်ယူခြင်း
require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// User က Login မဝင်ထားရင် Login Page သို့ Redirect လုပ်ခြင်း
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Form တင်သွင်းလာသည့်အခါ (Update လုပ်ခြင်း)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name    = trim($_POST['first_name']);
    $last_name     = trim($_POST['last_name']);
    $full_name     = trim($_POST['full_name']);
    $gender        = trim($_POST['gender']);
    $date_of_birth = trim($_POST['date_of_birth']);
    $phone         = trim($_POST['phone']);
    $address       = trim($_POST['address']);
    $city          = trim($_POST['city']);
    $country       = trim($_POST['country']);
    $postal_code   = trim($_POST['postal_code']);

    if (!empty($full_name)) {
        // Secure Prepared Statement ဖြင့် အချက်အလက်များ Update လုပ်ရန်
        $update_sql = "UPDATE users SET 
                        first_name = ?, 
                        last_name = ?, 
                        full_name = ?, 
                        gender = ?, 
                        date_of_birth = ?, 
                        phone = ?, 
                        address = ?, 
                        city = ?, 
                        country = ?, 
                        postal_code = ? 
                      WHERE user_id = ?";

        $stmt_update = mysqli_prepare($conn, $update_sql);
        
        if ($stmt_update) {
            mysqli_stmt_bind_param(
                $stmt_update, 
                "ssssssssssi", 
                $first_name, $last_name, $full_name, $gender, 
                $date_of_birth, $phone, $address, $city, 
                $country, $postal_code, $user_id
            );

            if (mysqli_stmt_execute($stmt_update)) {
                $success_msg = "Profile updated successfully!";
            } else {
                $error_msg = "Failed to update profile: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt_update);
        } else {
            $error_msg = "Database query preparation failed: " . mysqli_error($conn);
        }
    } else {
        $error_msg = "Full name cannot be empty.";
    }
}

// လက်ရှိ User အချက်အလက်များကို Database မှ ဆွဲထုတ်ခြင်း
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "<main class='container my-5'><div class='alert alert-danger border-0 rounded-4 shadow-sm p-4'>User record not found.</div></main>";
    require_once 'includes/footer.php';
    exit();
}
?>

<main class="container my-5" style="max-width: 960px;">

    <!-- Page Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-extrabold text-dark tracking-tight mb-1">
                <i class="fa-solid fa-user-pen text-primary me-2"></i>Edit Profile
            </h2>
            <p class="text-secondary mb-0" style="font-size: 0.88rem;">Update your personal details and contact information.</p>
        </div>
        <a href="profile.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 fw-semibold fs-7 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1.5"></i> Back to Profile
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $success_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $error_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <form action="edit_profile.php" method="POST">
            
            <!-- Section 1: Personal Coordinates -->
            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                <i class="fa-solid fa-user text-primary fs-6"></i> Personal Coordinates
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label text-muted fs-8 fw-semibold">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fs-8 fw-semibold">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fs-8 fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 fw-semibold">Gender Designation</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select Gender --</option>
                        <option value="Male" <?= (isset($user['gender']) && $user['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (isset($user['gender']) && $user['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= (isset($user['gender']) && $user['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 fw-semibold">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                </div>
            </div>

            <!-- Section 2: Contact & Residential Registry -->
            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom d-flex align-items-center gap-2">
                <i class="fa-solid fa-location-dot text-primary fs-6"></i> Contact & Residential Registry
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 fw-semibold">Email Address (Cannot be changed)</label>
                    <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted fs-8 fw-semibold">Phone Index</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label text-muted fs-8 fw-semibold">Street Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fs-8 fw-semibold">City / Location</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fs-8 fw-semibold">Country Jurisdiction</label>
                    <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($user['country'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label text-muted fs-8 fw-semibold">Postal Zip Code</label>
                    <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                </div>
            </div>

            <!-- Submit Button -->
            <div class="text-end">
                <button type="submit" name="update_profile" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm">
                    <i class="fa-solid fa-floppy-disk me-1.5"></i> Save Changes
                </button>
            </div>

        </form>
    </div>

</main>

<?php require_once 'includes/footer.php'; ?>