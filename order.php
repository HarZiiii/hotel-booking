<?php
ob_start();
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// User Login မဝင်ထားပါက Login Page သို့ ရွှေ့ခြင်း
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// GET Data စစ်ဆေးခြင်း
if (!isset($_GET['room_id']) || !isset($_GET['check_in']) || !isset($_GET['check_out']) || !isset($_GET['quantity'])) {
    header("Location: index.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$room_id = mysqli_real_escape_string($conn, $_GET['room_id']);
$check_in = mysqli_real_escape_string($conn, $_GET['check_in']);
$check_out = mysqli_real_escape_string($conn, $_GET['check_out']);
$quantity = intval($_GET['quantity']);

if ($quantity <= 0) {
    $quantity = 1;
}

// Room & Hotel Details ဆွဲထုတ်ခြင်း (Prepared Statement)
$stmt = mysqli_prepare($conn, "SELECT r.*, h.hotel_id, h.hotel_name, h.city 
                               FROM rooms r 
                               JOIN hotels h ON r.hotel_id = h.hotel_id 
                               WHERE r.room_id = ? AND r.room_status = 'available' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $room_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "
    <div class='container my-5'>
        <div class='alert alert-danger shadow-sm rounded-4 p-4 text-center' role='alert'>
            <i class='fa-solid fa-circle-exclamation fa-3x mb-3 text-danger'></i>
            <h4 class='fw-bold'>Room Not Available</h4>
            <p class='text-muted mb-3'>The requested room is currently unavailable for booking.</p>
            <a href='index.php' class='btn btn-primary rounded-pill px-4'><i class='fa-solid fa-arrow-left me-2'></i>Back to Home</a>
        </div>
    </div>";
    require_once 'includes/footer.php';
    exit();
}

$room = mysqli_fetch_assoc($result);
$hotel_id = $room['hotel_id'];

// ရက်စွဲ ခြားနားချက် (Nights) တွက်ချက်ခြင်း
$date1 = new DateTime($check_in);
$date2 = new DateTime($check_out);
$interval = $date1->diff($date2);
$nights = $interval->days;

if ($nights <= 0 || $date2 <= $date1) {
    echo "
    <div class='container my-5'>
        <div class='alert alert-warning shadow-sm rounded-4 p-4 text-center' role='alert'>
            <i class='fa-solid fa-calendar-xmark fa-3x mb-3 text-warning'></i>
            <h4 class='fw-bold'>Invalid Date Range</h4>
            <p class='text-muted mb-3'>Check-out date must be at least 1 day after Check-in date.</p>
            <a href='javascript:history.back()' class='btn btn-outline-secondary rounded-pill px-4'><i class='fa-solid fa-arrow-left me-2'></i>Go Back & Fix Dates</a>
        </div>
    </div>";
    require_once 'includes/footer.php';
    exit();
}

// စုစုပေါင်း ကျသင့်ငွေ တွက်ချက်ခြင်း
$price_per_night = $room['base_price'];
$total_amount = $price_per_night * $nights * $quantity;

// Form Submit - Booking အတည်ပြုခြင်း
$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    // Unique Booking Code ထုတ်ယူခြင်း (ဥပမာ- HBSV3-65AD89)
    $booking_code = "HBSV3-" . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    
    // Transaction စတင်ခြင်း
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Insert into bookings table
        $stmt1 = mysqli_prepare($conn, "INSERT INTO bookings (booking_code, customer_id, hotel_id, check_in, check_out, rooms_booked, total_amount, booking_status, cancellation_policy) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Free Cancellation')");
        mysqli_stmt_bind_param($stmt1, "siissid", $booking_code, $customer_id, $hotel_id, $check_in, $check_out, $quantity, $total_amount);
        mysqli_stmt_execute($stmt1);
        $booking_id = mysqli_insert_id($conn);
        
        // 2. Insert into booking_rooms
        $stmt2 = mysqli_prepare($conn, "INSERT INTO booking_rooms (booking_id, room_id, quantity, price_per_night, total_price) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt2, "iiidd", $booking_id, $room_id, $quantity, $price_per_night, $total_amount);
        mysqli_stmt_execute($stmt2);
        
        // 3. System Notification
        $noti_msg = "Your reservation ($booking_code) for " . $room['hotel_name'] . " was created successfully. Please finalize payment.";
        $stmt3 = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, message, notification_type) VALUES (?, 'Booking Created', ?, 'Booking')");
        mysqli_stmt_bind_param($stmt3, "is", $customer_id, $noti_msg);
        mysqli_stmt_execute($stmt3);
        
        // Commit Transaction & Redirect to payment.php
        mysqli_commit($conn);
        header("Location: payment.php?booking_id=" . $booking_id);
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = "Reservation process failed. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Confirmation - <?= htmlspecialchars($room['hotel_name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            color: #333;
        }
        .order-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .summary-row {
            padding: 12px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .btn-confirm {
            background: #16a34a;
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            padding: 14px;
            width: 100%;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-confirm:hover {
            background: #15803d;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
        }
    </style>
</head>
<body>

<div class="container my-5" style="max-width: 720px;">
    
    <!-- Header Title -->
    <div class="text-center mb-4">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold mb-2">
            <i class="fa-solid fa-shield-halved me-1"></i> Review Your Reservation
        </span>
        <h2 class="fw-bold text-dark">Booking Summary & Confirmation</h2>
        <p class="text-muted small">Please verify your booking details before confirming.</p>
    </div>

    <!-- Error Alert -->
    <?php if ($error_msg): ?>
        <div class="alert alert-danger rounded-3 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- Summary Main Card -->
    <div class="order-card p-4 p-md-5 mb-4">
        
        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
            <i class="fa-solid fa-hotel text-primary me-2"></i>Hotel & Room Details
        </h5>

        <div class="summary-row d-flex justify-content-between align-items-center">
            <span class="text-muted">Hotel Name</span>
            <span class="fw-semibold text-dark"><?= htmlspecialchars($room['hotel_name']); ?> (<?= htmlspecialchars($room['city']); ?>)</span>
        </div>

        <div class="summary-row d-flex justify-content-between align-items-center">
            <span class="text-muted">Room Selected</span>
            <span class="fw-semibold text-dark"><?= htmlspecialchars($room['room_name']); ?> <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($room['room_type']); ?></span></span>
        </div>

        <div class="summary-row d-flex justify-content-between align-items-center">
            <span class="text-muted">Stay Dates</span>
            <span class="fw-semibold text-dark"><?= date('M d, Y', strtotime($check_in)); ?> <i class="fa-solid fa-arrow-right fa-xs text-muted mx-1"></i> <?= date('M d, Y', strtotime($check_out)); ?></span>
        </div>

        <div class="summary-row d-flex justify-content-between align-items-center">
            <span class="text-muted">Total Duration</span>
            <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill"><?= $nights; ?> Night(s)</span>
        </div>

        <div class="summary-row d-flex justify-content-between align-items-center">
            <span class="text-muted">Quantity</span>
            <span class="fw-semibold text-dark"><?= $quantity; ?> Room(s)</span>
        </div>

        <div class="summary-row d-flex justify-content-between align-items-center">
            <span class="text-muted">Base Rate / Night</span>
            <span class="fw-semibold text-dark"><?= number_format($price_per_night, 0); ?> MMK</span>
        </div>

        <!-- Total Breakdown Box -->
        <div class="mt-4 p-3 bg-light rounded-4 border d-flex justify-content-between align-items-center">
            <div>
                <span class="text-uppercase small text-muted fw-bold d-block">Grand Total</span>
                <small class="text-muted">Taxes & fees included</small>
            </div>
            <div class="text-end">
                <h3 class="fw-bold text-success m-0"><?= number_format($total_amount, 0); ?> <span class="fs-6 text-muted fw-normal">MMK</span></h3>
            </div>
        </div>

        <!-- Cancellation Policy Badge -->
        <div class="mt-3 text-center">
            <small class="text-muted">
                <i class="fa-solid fa-circle-check text-success me-1"></i> Free cancellation policy applied to this booking.
            </small>
        </div>
    </div>

    <!-- Actions Form -->
    <form action="" method="POST">
        <button type="submit" name="confirm_booking" class="btn-confirm mb-3">
            <i class="fa-solid fa-circle-check me-2"></i> Proceed to Secure Payment
        </button>
        <a href="javascript:history.back()" class="btn btn-light w-100 rounded-3 py-2 text-secondary border">
            <i class="fa-solid fa-arrow-left me-1"></i> Cancel & Change Selection
        </a>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>