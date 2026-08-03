<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// User Login စစ်ဆေးခြင်း
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];

// Prepared Statement ဖြင့် Booking List ဆွဲထုတ်ခြင်း
$stmt = mysqli_prepare($conn, "SELECT b.*, h.hotel_name, h.city 
                               FROM bookings b 
                               JOIN hotels h ON b.hotel_id = h.hotel_id 
                               WHERE b.customer_id = ? 
                               ORDER BY b.created_at DESC");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Booking History</title>
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
        .history-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .table-custom th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 2px solid #e2e8f0;
        }
        .table-custom td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.92rem;
        }
        .table-custom tbody tr:hover {
            background-color: #f8fafc;
            transition: background 0.2s ease;
        }
        .booking-code-badge {
            font-family: monospace;
            font-size: 0.9rem;
            font-weight: 700;
            color: #2563eb;
            background-color: #eff6ff;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #dbeafe;
        }
    </style>
</head>
<body>

<div class="container my-5" style="max-width: 1100px;">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h2 class="fw-bold text-dark m-0">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>My Booking History
            </h2>
            <p class="text-muted small m-0 mt-1">Track your real-time room reservations, statuses, and schedules.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-plus me-1"></i> New Booking
            </a>
        </div>
    </div>

    <!-- Success Message Alert -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>
            Your reservation has been created successfully! Please manage or check your booking details below.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Booking List Container -->
    <div class="history-card p-0">
        <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-custom align-middle m-0">
                    <thead>
                        <tr>
                            <th>Booking Code</th>
                            <th>Hotel Name</th>
                            <th>Check-In / Out</th>
                            <th class="text-center">Rooms</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date Ordered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <span class="booking-code-badge"><?= htmlspecialchars($row['booking_code']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['hotel_name']); ?></div>
                                    <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($row['city'] ?? 'Location'); ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark"><?= date('d M Y', strtotime($row['check_in'])); ?></div>
                                    <small class="text-muted">to <?= date('d M Y', strtotime($row['check_out'])); ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1"><?= $row['rooms_booked']; ?> Room(s)</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?= number_format($row['total_amount'], 0); ?> <small class="text-muted fw-normal">MMK</small></span>
                                </td>
                                <td>
                                    <?php 
                                    $status = $row['booking_status'];
                                    $badge_class = "bg-warning-subtle text-warning border-warning-subtle"; // Default: Pending

                                    if ($status === 'Confirmed') { 
                                        $badge_class = "bg-success-subtle text-success border-success-subtle"; 
                                    } elseif ($status === 'Cancelled' || $status === 'Expired') { 
                                        $badge_class = "bg-danger-subtle text-danger border-danger-subtle"; 
                                    } elseif ($status === 'Checked Out' || $status === 'Completed') { 
                                        $badge_class = "bg-info-subtle text-info border-info-subtle"; 
                                    }
                                    ?>
                                    <span class="badge border px-3 py-2 rounded-pill fw-semibold <?= $badge_class; ?>">
                                        <i class="fa-solid fa-circle fa-2xs me-1"></i><?= htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= date('d M Y, h:i A', strtotime($row['created_at'])); ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="text-center py-5 px-3">
                <div class="mb-3">
                    <i class="fa-solid fa-folder-open fa-4x text-light-emphasis"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">No Reservations Found</h5>
                <p class="text-muted small mb-4">You haven't made any hotel room bookings yet.</p>
                <a href="index.php" class="btn btn-primary rounded-pill px-4 py-2">
                    <i class="fa-solid fa-magnifying-glass me-2"></i>Browse Hotels Now
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>