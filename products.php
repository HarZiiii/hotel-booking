<?php
// Session စတင်ရန် (Customer ID ကို အသုံးပြုရန် လိုအပ်သည်)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// URL ကနေ hotel_id ပါမပါ စစ်ဆေးခြင်း
if (!isset($_GET['hotel_id']) || empty($_GET['hotel_id'])) {
    header("Location: index.php");
    exit();
}

$hotel_id = mysqli_real_escape_string($conn, $_GET['hotel_id']);

// ဟိုတယ်အချက်အလက်ကို ဆွဲထုတ်ခြင်း
$hotel_query = "SELECT * FROM hotels WHERE hotel_id = '$hotel_id' AND (status = 'approved' OR status = 'Approved') LIMIT 1";
$hotel_result = mysqli_query($conn, $hotel_query);

if (!$hotel_result || mysqli_num_rows($hotel_result) == 0) {
    echo "
    <div class='container my-5'>
        <div class='alert alert-danger shadow-sm rounded-4 p-4 text-center' role='alert'>
            <i class='fa-solid fa-triangle-exclamation fa-3x mb-3 text-danger'></i>
            <h4 class='fw-bold'>Hotel Not Found</h4>
            <p class='text-muted mb-3'>The hotel you are looking for does not exist or is currently unavailable.</p>
            <a href='index.php' class='btn btn-primary rounded-pill px-4'><i class='fa-solid fa-arrow-left me-2'></i>Back to Home</a>
        </div>
    </div>";
    require_once 'includes/footer.php';
    exit();
}

$hotel = mysqli_fetch_assoc($hotel_result);

// ယခုဟိုတယ်ကို ဤ Customer သိမ်းဆည်းထားပြီးသား ဟုတ်မဟုတ် စစ်ဆေးခြင်း (Wishlist Status)
$customer_id = $_SESSION['user_id'] ?? 0;
$is_wishlisted = false;
if ($customer_id > 0) {
    $wish_check = mysqli_query($conn, "SELECT * FROM wishlist WHERE customer_id = '$customer_id' AND hotel_id = '$hotel_id'");
    if ($wish_check && mysqli_num_rows($wish_check) > 0) {
        $is_wishlisted = true;
    }
}

// ထိုဟိုတယ်မှာရှိတဲ့ Active ဖြစ်နေတဲ့ အခန်းတွေကို ဆွဲထုတ်ခြင်း
$rooms_query = "SELECT * FROM rooms WHERE hotel_id = '$hotel_id' AND room_status = 'available' ORDER BY base_price ASC";
$rooms_result = mysqli_query($conn, $rooms_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($hotel['hotel_name']); ?> - Available Rooms</title>
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
        .hotel-header-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            position: relative;
        }
        .room-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        .room-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
        }
        .room-img-container {
            height: 220px;
            background: #e2e8f0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .room-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .badge-type {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            color: #38bdf8;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
        }
        .spec-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-book {
            background: #0284c7;
            color: #ffffff;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 24px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-book:hover {
            background: #0369a1;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }
        .wishlist-btn {
            border: none;
            background: #ffffff;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .wishlist-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>

<div class="container my-4">
    <!-- Hotel Info Banner -->
    <div class="hotel-header-card p-4 mb-4">
        <!-- Wishlist Heart Button အနေအထား -->
        <div class="position-absolute top-0 end-0 m-4">
            <button class="wishlist-btn" data-hotel-id="<?= $hotel['hotel_id']; ?>" title="Save to Wishlist">
                <i class="fa-<?= $is_wishlisted ? 'solid text-danger' : 'regular text-secondary'; ?> fa-heart fa-lg"></i>
            </button>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pe-5">
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-semibold">
                <i class="fa-solid fa-star me-1"></i> <?= number_format($hotel['star_rating'], 1); ?> Star Rated
            </span>
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Hotels
            </a>
        </div>
        
        <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($hotel['hotel_name']); ?></h2>
        
        <p class="text-muted small mb-3">
            <i class="fa-solid fa-location-dot text-danger me-1"></i>
            <?= htmlspecialchars($hotel['address']); ?>, <?= htmlspecialchars($hotel['city']); ?>, <?= htmlspecialchars($hotel['country']); ?>
        </p>
        
        <?php if (!empty($hotel['description'])): ?>
            <div class="p-3 bg-light rounded-3 text-secondary small">
                <?= nl2br(htmlspecialchars($hotel['description'])); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Available Rooms Title -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold text-dark m-0"><i class="fa-solid fa-door-open text-primary me-2"></i>Available Room Types</h4>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2">
            <?= mysqli_num_rows($rooms_result); ?> Rooms Found
        </span>
    </div>

    <!-- Rooms List -->
    <?php if (mysqli_num_rows($rooms_result) > 0): ?>
        <div class="d-flex flex-column gap-4">
            <?php while($room = mysqli_fetch_assoc($rooms_result)): ?>
                <?php 
                    $room_img = !empty($room['room_image']) ? $room['room_image'] : (!empty($room['image_url']) ? $room['image_url'] : '');
                ?>
                <div class="room-card">
                    <div class="row g-0 align-items-stretch">
                        <!-- Room Image -->
                        <div class="col-lg-4 col-md-5">
                            <div class="room-img-container h-100">
                                <?php if (!empty($room_img) && file_exists($room_img)): ?>
                                    <img src="<?= htmlspecialchars($room_img); ?>" alt="<?= htmlspecialchars($room['room_name']); ?>">
                                <?php else: ?>
                                    <i class="fa-solid fa-bed fa-4x text-secondary opacity-50"></i>
                                <?php endif; ?>
                                <div class="badge-type">
                                    <?= htmlspecialchars($room['room_type']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Room Details -->
                        <div class="col-lg-8 col-md-7">
                            <div class="p-4 d-flex flex-column justify-content-between h-100">
                                <div>
                                    <h4 class="fw-bold text-dark mb-3"><?= htmlspecialchars($room['room_name']); ?></h4>
                                    
                                    <!-- Specs -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="spec-pill">
                                            <i class="fa-solid fa-expand text-primary"></i> <?= htmlspecialchars($room['room_size']); ?> <?= htmlspecialchars($room['room_size_unit']); ?>
                                        </span>
                                        <span class="spec-pill">
                                            <i class="fa-solid fa-user-group text-primary"></i> Max Adults: <?= htmlspecialchars($room['max_adults']); ?>
                                        </span>
                                        <span class="spec-pill">
                                            <i class="fa-solid fa-bed text-primary"></i> <?= htmlspecialchars($room['bed_type']); ?>
                                        </span>
                                    </div>

                                    <!-- Perks/Inclusions -->
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php if (!empty($room['breakfast_included']) && $room['breakfast_included'] == 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2 fw-medium">
                                                <i class="fa-solid fa-utensils me-1"></i> Free Breakfast
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($room['free_cancellation']) && $room['free_cancellation'] == 1): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3 py-2 fw-medium">
                                                <i class="fa-solid fa-circle-check me-1"></i> Free Cancellation
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Pricing & Action -->
                                <div class="d-flex justify-content-between align-items-end pt-3 border-top mt-3">
                                    <div>
                                        <span class="text-muted small d-block">Price per night</span>
                                        <span class="fs-3 fw-bold text-dark me-1"><?= number_format($room['base_price'], 0); ?></span>
                                        <span class="text-muted fw-medium">MMK</span>
                                    </div>
                                    <a href="product_detail.php?room_id=<?= $room['room_id']; ?>" class="btn-book">
                                        Select & Book <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-white rounded-4 border">
            <i class="fa-solid fa-bed-pulse fa-3x text-muted mb-3 opacity-50"></i>
            <h5 class="fw-bold text-dark mb-1">No Available Rooms</h5>
            <p class="text-muted small mb-0">Currently, there are no available rooms listed for this hotel.</p>
        </div>
    <?php endif; ?>
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