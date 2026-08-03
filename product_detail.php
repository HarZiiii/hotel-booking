<?php
// Session စတင်ရန် (Login ဝင်ထားမှုရှိမရှိ စစ်ဆေးရန်အတွက် လိုအပ်သည်)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php'; // Database connection ထည့်သွင်းရန်
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Check room_id parameter
if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
    header("Location: index.php");
    exit();
}

$room_id = mysqli_real_escape_string($conn, $_GET['room_id']);

// Fetch Room, Hotel details
$query = "SELECT r.*, h.hotel_name, h.address, h.city, h.country, h.star_rating, h.hotel_id 
          FROM rooms r 
          JOIN hotels h ON r.hotel_id = h.hotel_id 
          WHERE r.room_id = '$room_id' LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo "
    <div class='container my-5'>
        <div class='alert alert-danger shadow-sm rounded-4 p-4 text-center' role='alert'>
            <i class='fa-solid fa-bed-pulse fa-3x mb-3 text-danger'></i>
            <h4 class='fw-bold'>Room Details Not Found</h4>
            <p class='text-muted mb-3'>The requested room is unavailable or does not exist.</p>
            <a href='index.php' class='btn btn-primary rounded-pill px-4'><i class='fa-solid fa-arrow-left me-2'></i>Back to Home</a>
        </div>
    </div>";
    require_once 'includes/footer.php';
    exit();
}

$room = mysqli_fetch_assoc($result);
$hotel_id = $room['hotel_id'];

// ယခုဟိုတယ်ကို ဤ Customer သိမ်းဆည်းထားပြီးသား ဟုတ်မဟုတ် စစ်ဆေးခြင်း (Wishlist Status)
$customer_id = $_SESSION['user_id'] ?? 0;
$is_wishlisted = false;
if ($customer_id > 0) {
    $wish_check = mysqli_query($conn, "SELECT * FROM wishlist WHERE customer_id = '$customer_id' AND hotel_id = '$hotel_id'");
    if ($wish_check && mysqli_num_rows($wish_check) > 0) {
        $is_wishlisted = true;
    }
}

// Image resolution logic
$room_img = !empty($room['room_image']) ? $room['room_image'] : (!empty($room['image_url']) ? $room['image_url'] : '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($room['room_name']); ?> - Room Details</title>
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
        .detail-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            position: relative;
        }
        .room-hero-img {
            height: 380px;
            width: 100%;
            object-fit: cover;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .spec-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
        }
        .booking-sidebar {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 24px;
        }
        .btn-reserve {
            background: #0284c7;
            color: #ffffff;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-reserve:hover {
            background: #0369a1;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
        }
        .wishlist-btn {
            border: none;
            background: #ffffff;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .wishlist-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>

<div class="container my-4">
    <!-- Top Back Button -->
    <div class="mb-3">
        <a href="products.php?hotel_id=<?= $room['hotel_id']; ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Hotel Rooms
        </a>
    </div>

    <div class="row g-4">
        <!-- Left Side: Room Details Content -->
        <div class="col-lg-8">
            <div class="detail-card p-4">
                
                <!-- Wishlist Heart Button (Top Right Inside Card) -->
                <div class="position-absolute top-0 end-0 m-4 z-3">
                    <button class="wishlist-btn" data-hotel-id="<?= $hotel_id; ?>" title="Save to Wishlist">
                        <i class="fa-<?= $is_wishlisted ? 'solid text-danger' : 'regular text-secondary'; ?> fa-heart fa-lg"></i>
                    </button>
                </div>

                <!-- Room Image Hero -->
                <div class="room-hero-img rounded-4 mb-4 overflow-hidden">
                    <?php if (!empty($room_img) && file_exists($room_img)): ?>
                        <img src="<?= htmlspecialchars($room_img); ?>" alt="<?= htmlspecialchars($room['room_name']); ?>" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-bed fa-6x text-secondary opacity-50"></i>
                    <?php endif; ?>
                </div>

                <!-- Header Info -->
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2 pe-5">
                    <div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-2">
                            <?= htmlspecialchars($room['room_type']); ?>
                        </span>
                        <h2 class="fw-bold text-dark m-0"><?= htmlspecialchars($room['room_name']); ?></h2>
                    </div>
                </div>

                <p class="text-muted small mb-4">
                    <i class="fa-solid fa-building text-primary me-1"></i> Located in 
                    <strong><?= htmlspecialchars($room['hotel_name']); ?></strong> 
                    <span class="ms-1">(<?= htmlspecialchars($room['city']); ?>)</span>
                </p>

                <hr class="text-black-50 my-4">

                <!-- Specifications Grid -->
                <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-sliders text-primary me-2"></i>Specifications & Amenities</h5>
                
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-md-4">
                        <div class="spec-item d-flex align-items-center gap-3">
                            <i class="fa-solid fa-bed fa-lg text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Bed Type</small>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($room['bed_type']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="spec-item d-flex align-items-center gap-3">
                            <i class="fa-solid fa-expand fa-lg text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Room Size</small>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($room['room_size']); ?> <?= htmlspecialchars($room['room_size_unit']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="spec-item d-flex align-items-center gap-3">
                            <i class="fa-solid fa-users fa-lg text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Max Guests</small>
                                <span class="fw-semibold text-dark"><?= $room['max_adults']; ?> Adults, <?= $room['max_children']; ?> Kids</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="spec-item d-flex align-items-center gap-3">
                            <i class="fa-solid fa-layer-group fa-lg text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Floor Level</small>
                                <span class="fw-semibold text-dark">Floor <?= htmlspecialchars($room['floor_no']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="spec-item d-flex align-items-center gap-3">
                            <i class="fa-solid fa-smoking fa-lg text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Smoking</small>
                                <span class="fw-semibold text-dark"><?= $room['smoking_allowed'] ? 'Allowed' : 'Non-Smoking'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="spec-item d-flex align-items-center gap-3">
                            <i class="fa-solid fa-door-open fa-lg text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Total Units</small>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($room['total_rooms']); ?> Rooms</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <?php if (!empty($room['room_description'])): ?>
                    <h5 class="fw-bold text-dark mb-2"><i class="fa-solid fa-align-left text-primary me-2"></i>Room Description</h5>
                    <p class="text-secondary lh-base mb-0">
                        <?= nl2br(htmlspecialchars($room['room_description'])); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Real-time Booking Panel -->
        <div class="col-lg-4">
            <div class="booking-sidebar p-4">
                <div class="mb-3">
                    <span class="text-muted small">Base Rate Per Night</span>
                    <div class="d-flex align-items-baseline gap-1 mt-1">
                        <h2 class="fw-bold text-dark m-0"><?= number_format($room['base_price'], 0); ?></h2>
                        <span class="text-muted fw-semibold">MMK</span>
                    </div>
                </div>

                <hr class="text-black-50 my-3">

                <!-- Booking Form -->
                <form action="order.php" method="GET">
                    <input type="hidden" name="room_id" value="<?= $room['room_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">
                            <i class="fa-solid fa-calendar-days text-primary me-1"></i> Check-in Date
                        </label>
                        <input type="date" name="check_in" class="form-control rounded-3" min="<?= date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-secondary">
                            <i class="fa-solid fa-calendar-days text-primary me-1"></i> Check-out Date
                        </label>
                        <input type="date" name="check_out" class="form-control rounded-3" min="<?= date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-secondary">
                            <i class="fa-solid fa-cubes text-primary me-1"></i> Rooms to Book
                        </label>
                        <input type="number" name="quantity" class="form-control rounded-3" value="1" min="1" max="<?= $room['total_rooms']; ?>" required>
                        <small class="text-muted d-block mt-1">Available: <?= $room['total_rooms']; ?> rooms</small>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button type="submit" class="btn-reserve">
                            <i class="fa-solid fa-bolt me-1"></i> Proceed to Reservation
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary w-100 rounded-3 py-2 fw-semibold">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Login to Reserve Room
                        </a>
                    <?php endif; ?>
                </form>

                <div class="mt-4 p-3 bg-light rounded-3 text-center border">
                    <small class="text-muted d-block">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i> 100% Secured Booking Transaction
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AJAX Script for Wishlist Toggle -->
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('.wishlist-btn')) {
        const btn = e.target.closest('.wishlist-btn');
        const hotelId = btn.getAttribute('data-hotel-id');
        const icon = btn.querySelector('i');

        fetch('toggle_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'hotel_id=' + hotelId
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'added') {
                icon.classList.remove('fa-regular', 'text-secondary');
                icon.classList.add('fa-solid', 'text-danger');
            } else if (data.status === 'removed') {
                icon.classList.remove('fa-solid', 'text-danger');
                icon.classList.add('fa-regular', 'text-secondary');
            } else if (data.status === 'unauthorized') {
                window.location.href = 'login.php';
            } else {
                alert(data.message || 'Something went wrong!');
            }
        })
        .catch(error => console.error('Error:', error));
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>