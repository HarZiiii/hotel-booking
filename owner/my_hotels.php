<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];
$msg = '';
$error = '';

// Add New Hotel & Agreement Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hotel'])) {
    $hotel_name  = mysqli_real_escape_string($conn, trim($_POST['hotel_name']));
    $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone       = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $city        = mysqli_real_escape_string($conn, trim($_POST['city']));
    $location    = mysqli_real_escape_string($conn, trim($_POST['location']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $rating      = isset($_POST['rating']) ? (float)$_POST['rating'] : 4.5;
    $check_in    = mysqli_real_escape_string($conn, trim($_POST['check_in_time']));
    $check_out   = mysqli_real_escape_string($conn, trim($_POST['check_out_time']));
    $status      = mysqli_real_escape_string($conn, trim($_POST['status']));

    // Agreement Checkbox စစ်ဆေးခြင်း
    if (!isset($_POST['agree_terms'])) {
        $error = "You must agree to the Terms & Conditions and Owner Agreements before registering a hotel.";
    } else {
        // Image Upload Handling
        $image_name = 'default_hotel.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = 'hotel_' . time() . '_' . rand(100, 999) . '.' . $ext;
            
            $upload_dir = '../assets/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }

        // ဟိုတယ်အချက်အလက် သိမ်းမည်
        $insert_sql = "INSERT INTO hotels (owner_id, hotel_name, email, phone, city, location, description, rating, check_in_time, check_out_time, image, status) 
                       VALUES ('$owner_id', '$hotel_name', '$email', '$phone', '$city', '$location', '$description', '$rating', '$check_in', '$check_out', '$image_name', '$status')";
        
        if (mysqli_query($conn, $insert_sql)) {
            $hotel_id = mysqli_insert_id($conn);

            // ၁. Facilities များကို သိမ်းဆည်းခြင်း
            if (isset($_POST['facilities']) && is_array($_POST['facilities'])) {
                foreach ($_POST['facilities'] as $facility_id) {
                    $facility_id = mysqli_real_escape_string($conn, $facility_id);
                    mysqli_query($conn, "INSERT INTO hotel_facilities (hotel_id, facility_id) VALUES ('$hotel_id', '$facility_id')");
                }
            }

            // ၂. Owner Agreement / Terms & Conditions ကို မှတ်တမ်းတင် သိမ်းဆည်းခြင်း
            $agreement_version = 'v1.0';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            $agreement_sql = "INSERT INTO owner_agreements (owner_id, hotel_id, agreement_version, ip_address) 
                              VALUES ('$owner_id', '$hotel_id', '$agreement_version', '$ip_address')";
            mysqli_query($conn, $agreement_sql);

            header("Location: my_hotels.php?msg=added");
            exit();
        } else {
            $error = "Failed to add hotel: " . mysqli_error($conn);
        }
    }
}

// Fetch Owner's Hotels
$hotels_query = mysqli_query($conn, "SELECT * FROM hotels WHERE owner_id = '$owner_id' ORDER BY hotel_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Hotels | Hotel Partner Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/owner.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #333; margin: 0; }
        .wrapper { display: flex; width: 100%; min-height: 100vh; }
        .sidebar { width: 260px; background: #0f172a; color: #fff; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; }
        .sidebar .brand { padding: 20px; font-size: 19px; font-weight: 700; border-bottom: 1px solid #1e293b; display: flex; align-items: center; gap: 10px; color: #38bdf8; }
        .sidebar ul { list-style: none; padding: 10px 0; margin: 0; }
        .sidebar ul li a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 14px; }
        .sidebar ul li a:hover, .sidebar ul li.active a { background: #1e293b; color: #38bdf8; border-left: 4px solid #38bdf8; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 25px 30px; }
        .topbar { background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .card-box { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #eef2f6; margin-bottom: 25px; }
        .hotel-img { width: 100%; height: 180px; object-fit: cover; border-top-left-radius: 12px; border-top-right-radius: 12px; }
    </style>
</head>
<body>

<div class="wrapper">
    <?php include '../includes/owner_sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h4 class="m-0 fw-bold text-dark"><i class="fa-solid fa-building-user text-primary me-2"></i>My Managed Hotels</h4>
                <small class="text-muted">Register and update your hotel properties</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHotelModal">
                <i class="fa-solid fa-plus me-1"></i> Add New Hotel
            </button>
        </header>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fa-solid fa-circle-check me-2"></i>Hotel property registered successfully along with terms agreement!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if ($hotels_query && mysqli_num_rows($hotels_query) > 0): ?>
                <?php while ($hotel = mysqli_fetch_assoc($hotels_query)): ?>
                    <?php 
                        $hotel_location = $hotel['location'] ?? $hotel['address'] ?? 'Location N/A';
                        $hotel_city     = $hotel['city'] ?? '';
                        $hotel_rating   = $hotel['rating'] ?? $hotel['star_rating'] ?? '4.5';
                        $hotel_image    = $hotel['image'] ?? $hotel['hotel_image'] ?? 'default_hotel.jpg';
                        $hotel_status   = $hotel['status'] ?? 'Active';
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card card-box p-0 h-100 border-0 shadow-sm">
                            <img src="../assets/images/<?= htmlspecialchars($hotel_image) ?>" class="hotel-img" alt="Hotel Image" onerror="this.src='https://via.placeholder.com/300x180?text=Hotel+Image'">
                            <div class="p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h5 class="fw-bold m-0 text-dark"><?= htmlspecialchars($hotel['hotel_name'] ?? 'Hotel Name') ?></h5>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-star me-1"></i><?= htmlspecialchars($hotel_rating) ?></span>
                                </div>
                                
                                <p class="text-muted small mb-2">
                                    <i class="fa-solid fa-location-dot me-1 text-danger"></i>
                                    <?= htmlspecialchars($hotel_location) ?><?= !empty($hotel_city) ? ' ('.htmlspecialchars($hotel_city).')' : '' ?>
                                </p>

                                <?php if (!empty($hotel['phone']) || !empty($hotel['email'])): ?>
                                    <p class="text-muted small mb-2">
                                        <i class="fa-solid fa-phone me-1 text-primary"></i><?= htmlspecialchars($hotel['phone'] ?? 'N/A') ?> 
                                    </p>
                                <?php endif; ?>

                                <p class="text-secondary small mb-3 text-truncate"><?= htmlspecialchars($hotel['description'] ?? '') ?></p>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <small class="text-muted"><i class="fa-regular fa-clock me-1"></i>In: <?= htmlspecialchars($hotel['check_in_time'] ?? '14:00') ?> | Out: <?= htmlspecialchars($hotel['check_out_time'] ?? '12:00') ?></small>
                                    <span class="badge <?= strtolower($hotel_status) === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($hotel_status) ?></span>
                                </div>

                                <a href="manage_rooms.php?hotel_id=<?= $hotel['hotel_id'] ?>" class="btn btn-outline-primary w-100 btn-sm">
                                    <i class="fa-solid fa-bed me-1"></i> Manage Rooms
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card-box text-center py-5 text-muted">
                        <i class="fa-solid fa-hotel fs-1 mb-2"></i>
                        <p>No hotels added yet. Click "Add New Hotel" to register your first property.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- DETAILED ADD HOTEL MODAL -->
<div class="modal fade" id="addHotelModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="my_hotels.php" enctype="multipart/form-data">
                <input type="hidden" name="add_hotel" value="1">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-building-circle-check text-primary me-2"></i>Add Complete Hotel Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    <!-- Basic Info -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Hotel Name <span class="text-danger">*</span></label>
                            <input type="text" name="hotel_name" class="form-control" placeholder="e.g. Inle Lake Horizon Resort" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">City / Region <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" placeholder="e.g. Taunggyi / Nyaung Shwe" required>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Contact Email</label>
                            <input type="email" name="email" class="form-control" placeholder="info@hotel.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Contact Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" placeholder="09xxxxxxxxx" required>
                        </div>
                    </div>

                    <!-- Address & Rating -->
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Full Address / Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. No. 12, Main Road, Inle Lake, Shan State" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Rating (1 to 5) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" max="5" min="1" name="rating" class="form-control" value="4.5" required>
                        </div>
                    </div>

                    <!-- Check-in/out Policies -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Standard Check-in Time</label>
                            <input type="time" name="check_in_time" class="form-control" value="14:00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Standard Check-out Time</label>
                            <input type="time" name="check_out_time" class="form-control" value="12:00">
                        </div>
                    </div>

                    <!-- Facilities Checkboxes -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotel Facilities / Amenities</label>
                        <div class="row g-2 border p-3 rounded bg-light">
                            <?php
                            $fac_query = "SELECT * FROM facilities";
                            $fac_result = mysqli_query($conn, $fac_query);
                            if ($fac_result && mysqli_num_rows($fac_result) > 0) {
                                while ($fac = mysqli_fetch_assoc($fac_result)) {
                            ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="facilities[]" value="<?= $fac['facility_id']; ?>" id="facility_<?= $fac['facility_id']; ?>">
                                        <label class="form-check-label small" for="facility_<?= $fac['facility_id']; ?>">
                                            <?= htmlspecialchars($fac['facility_name']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php 
                                }
                            } else {
                                echo '<p class="text-muted small m-0">No facilities found in database.</p>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Hotel Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Describe your hotel facilities, surroundings, and service style..."></textarea>
                    </div>

                    <!-- Image & Status -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Main Hotel Cover Photo</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Property Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Owner Agreement Terms & Conditions Checkbox -->
                    <div class="mb-3 border-top pt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="agree_terms" id="agree_terms" required>
                            <label class="form-check-label small fw-semibold text-danger" for="agree_terms">
                                I agree to the <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#termsModal" class="text-decoration-underline text-primary">Partner Terms & Conditions</a> and System Usage Agreement (v1.0). <span class="text-danger">*</span>
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                            <i class="fa-solid fa-shield-halved me-1 text-success"></i> Your IP address and agreement timestamp will be recorded for legal and compliance verification.
                        </small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Hotel Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Terms & Conditions Popup Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="termsModalLabel">
                    <i class="fa-solid fa-file-contract text-info me-2"></i>Partner Terms & Conditions (v1.0)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-start" style="font-size: 14px; line-height: 1.6;">
                <h6 class="fw-bold text-primary">1. Introduction & Acceptance</h6>
                <p>Welcome to the Hotel Partner Hub. By registering your property, you agree to comply with and be bound by the following terms and conditions. Please read them carefully.</p>

                <h6 class="fw-bold text-primary mt-3">2. Commission & Payments</h6>
                <p>The platform charges a standard agreed percentage commission on every confirmed booking made through the system. Payouts and settlements will be processed on a monthly/weekly basis as per partner agreement terms.</p>

                <h6 class="fw-bold text-primary mt-3">3. Room Availability & Pricing Accuracy</h6>
                <p>Partners are solely responsible for maintaining up-to-date room inventories, rates, and accurate facility details. Overbooking or failing to honor confirmed guest reservations may lead to penalties or account suspension.</p>

                <h6 class="fw-bold text-primary mt-3">4. Cancellation & Refund Policies</h6>
                <p>Hotels must adhere to the selected cancellation policies configured in the system. Any dispute regarding guest refunds will be mediated according to platform regulations.</p>

                <h6 class="fw-bold text-primary mt-3">5. Data Privacy & Compliance</h6>
                <p>By checking the agreement box, you consent to record your IP address, timestamp, and agreement version as legal compliance proof under system security policies.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal" onclick="document.getElementById('agree_terms').checked = true;">I Understand & Agree</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>