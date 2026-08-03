<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}

$owner_id = $_SESSION['user_id'];

// Fetch Reviews for Owner's Hotels using 'customer_id' according to hotel_booking_system_v3 schema
$reviews_query_sql = "
    SELECT r.*, h.hotel_name, u.username, u.email 
    FROM reviews r 
    JOIN hotels h ON r.hotel_id = h.hotel_id 
    LEFT JOIN users u ON r.customer_id = u.user_id 
    WHERE h.owner_id = '$owner_id' 
    ORDER BY r.review_id DESC
";

try {
    $reviews_query = mysqli_query($conn, $reviews_query_sql);
} catch (mysqli_sql_exception $e) {
    // Fallback if users table uses customer_id primary key
    $reviews_query_sql = "
        SELECT r.*, h.hotel_name, u.username, u.email 
        FROM reviews r 
        JOIN hotels h ON r.hotel_id = h.hotel_id 
        LEFT JOIN users u ON r.customer_id = u.customer_id 
        WHERE h.owner_id = '$owner_id' 
        ORDER BY r.review_id DESC
    ";
    $reviews_query = mysqli_query($conn, $reviews_query_sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Guest Reviews | Hotel Partner Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../assets/css/owner.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/owner.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #333; margin: 0; }
        .wrapper { display: flex; width: 100%; min-height: 100vh; }
        .sidebar { width: 260px; background: #0f172a; color: #fff; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; }
        .sidebar .brand { padding: 20px; font-size: 19px; font-weight: 700; border-bottom: 1px solid #1e293b; display: flex; align-items: center; gap: 10px; color: #38bdf8; }
        .sidebar ul { list-style: none; padding: 10px 0; margin: 0; }
        .sidebar ul li a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #94a3b8; text-decoration: none; font-size: 14px; }
        .sidebar ul li a:hover, .sidebar ul li.active a { background: #1e293b; color: #38bdf8; border-left: 4px solid #38bdf8; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 25px 30px; }
        .topbar { background: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); margin-bottom: 25px; }
        .card-box { background: #fff; border-radius: 12px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #eef2f6; margin-bottom: 25px; }
        .star-rating { color: #f59e0b; }
    </style>
</head>
<body>

<div class="wrapper">
    
    <?php include '../includes/owner_sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <h4 class="m-0 fw-bold text-dark"><i class="fa-solid fa-star text-warning me-2"></i>Guest Reviews & Ratings</h4>
            <small class="text-muted">Monitor feedback and ratings left by checked-out guests</small>
        </header>

        <div class="card-box">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Review ID</th>
                            <th>Hotel Name</th>
                            <th>Guest Name</th>
                            <th>Rating</th>
                            <th>Review & Comment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reviews_query && mysqli_num_rows($reviews_query) > 0): ?>
                            <?php while ($rev = mysqli_fetch_assoc($reviews_query)): ?>
                                <?php 
                                    $rating = (int)($rev['rating'] ?? $rev['score'] ?? 5);
                                    $status = $rev['review_status'] ?? $rev['status'] ?? 'Approved';
                                ?>
                            <tr>
                                <td><strong>#<?= $rev['review_id'] ?></strong></td>
                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($rev['hotel_name'] ?? 'N/A') ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($rev['username'] ?? 'Guest User') ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($rev['email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <div class="star-rating fs-6">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-<?= $i <= $rating ? 'solid' : 'regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                        <span class="ms-1 fw-bold text-dark">(<?= $rating ?>/5)</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($rev['title'])): ?>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($rev['title']) ?></strong>
                                    <?php endif; ?>
                                    <span class="text-secondary small"><?= htmlspecialchars($rev['comment'] ?? $rev['review_text'] ?? 'No written comment provided.') ?></span>
                                </td>
                                <td>
                                    <?php if (strtolower($status) === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif (strtolower($status) === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($status) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No guest reviews found for your properties yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>