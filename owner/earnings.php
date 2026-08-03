<?php
require_once '../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header('Location: ../login.php');
    exit;
}

$owner_id = (int) $_SESSION['user_id'];
$error_msg = '';
$success_msg = isset($_GET['msg']) && $_GET['msg'] === 'success'
    ? 'Commission payment slip submitted successfully. Admin verification is pending.'
    : '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ownerAuditLog(mysqli $conn, int $userId, string $action, string $tableName, int $recordId = 0): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $stmt = mysqli_prepare($conn, "INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent) VALUES (?,?,?,?,?,?)");
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ississ', $userId, $action, $tableName, $recordId, $ip, $agent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function fetchMoney(mysqli $conn, string $sql, int $ownerId): float
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0.0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $ownerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return (float) ($row['amount'] ?? 0);
}

// Revenue belonging to this owner's confirmed / checked-out bookings.
$gross_revenue = fetchMoney(
    $conn,
    "SELECT COALESCE(SUM(b.total_amount),0) AS amount
     FROM bookings b
     INNER JOIN hotels h ON h.hotel_id=b.hotel_id
     WHERE h.owner_id=? AND b.booking_status IN ('Confirmed','Checked Out')",
    $owner_id
);

$total_commission = round($gross_revenue * 0.10, 2);
$net_owner_earnings = max(0, $gross_revenue - $total_commission);

$submitted_commission = fetchMoney(
    $conn,
    "SELECT COALESCE(SUM(commission_amount),0) AS amount FROM commissions WHERE owner_id=?",
    $owner_id
);

$paid_commission = fetchMoney(
    $conn,
    "SELECT COALESCE(SUM(commission_amount),0) AS amount FROM commissions WHERE owner_id=? AND commission_status='Paid'",
    $owner_id
);

$pending_commission = fetchMoney(
    $conn,
    "SELECT COALESCE(SUM(commission_amount),0) AS amount FROM commissions WHERE owner_id=? AND commission_status='Pending'",
    $owner_id
);

// Always calculate what can be submitted from booking rows that do not yet have commission records.
$outstanding_stmt = mysqli_prepare(
    $conn,
    "SELECT b.booking_id, b.booking_code, b.total_amount, h.hotel_name
     FROM bookings b
     INNER JOIN hotels h ON h.hotel_id=b.hotel_id
     LEFT JOIN commissions c ON c.booking_id=b.booking_id AND c.owner_id=?
     WHERE h.owner_id=?
       AND b.booking_status IN ('Confirmed','Checked Out')
       AND c.commission_id IS NULL
     ORDER BY b.booking_id ASC"
);
$outstanding_bookings = [];
$unsubmitted_commission = 0.0;
if ($outstanding_stmt) {
    mysqli_stmt_bind_param($outstanding_stmt, 'ii', $owner_id, $owner_id);
    mysqli_stmt_execute($outstanding_stmt);
    $outstanding_result = mysqli_stmt_get_result($outstanding_stmt);
    while ($outstanding_result && ($row = mysqli_fetch_assoc($outstanding_result))) {
        $row['commission_amount'] = round(((float)$row['total_amount']) * 0.10, 2);
        $unsubmitted_commission += $row['commission_amount'];
        $outstanding_bookings[] = $row;
    }
    mysqli_stmt_close($outstanding_stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_commission'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $error_msg = 'Invalid request token. Please refresh and try again.';
    } elseif ($unsubmitted_commission <= 0 || empty($outstanding_bookings)) {
        $error_msg = 'There is no new commission available to submit.';
    } elseif (!isset($_FILES['payment_slip']) || $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'Please choose a payment slip image.';
    } else {
        $file = $_FILES['payment_slip'];
        $maxSize = 2 * 1024 * 1024;
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '';

        if ($file['size'] <= 0 || $file['size'] > $maxSize || !in_array($extension, $allowedExtensions, true) || !in_array($mime, $allowedMimes, true)) {
            $error_msg = 'Payment slip must be a JPG, PNG or WEBP image under 2 MB.';
        } else {
            $uploadDir = '../assets/images/slips/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
                $error_msg = 'Unable to create the payment slip folder.';
            } else {
                $newName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
                $destination = $uploadDir . $newName;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $error_msg = 'Unable to save the payment slip. Please check folder permissions.';
                } else {
                    mysqli_begin_transaction($conn);
                    try {
                        $insert = mysqli_prepare(
                            $conn,
                            "INSERT INTO commissions
                             (booking_id, owner_id, amount, payment_slip, booking_amount, commission_rate, commission_amount, owner_amount, commission_status)
                             VALUES (?,?,0,?,?,?,?,?,'Pending')"
                        );
                        if (!$insert) {
                            throw new Exception(mysqli_error($conn));
                        }

                        $inserted = 0;
                        foreach ($outstanding_bookings as $booking) {
                            $bookingId = (int)$booking['booking_id'];
                            $bookingAmount = (float)$booking['total_amount'];
                            $commissionRate = 10.00;
                            $commissionAmount = round($bookingAmount * 0.10, 2);
                            $ownerAmount = round($bookingAmount - $commissionAmount, 2);

                            mysqli_stmt_bind_param(
                                $insert,
                                'iisdddd',
                                $bookingId,
                                $owner_id,
                                $newName,
                                $bookingAmount,
                                $commissionRate,
                                $commissionAmount,
                                $ownerAmount
                            );
                            if (!mysqli_stmt_execute($insert)) {
                                throw new Exception(mysqli_stmt_error($insert));
                            }
                            $inserted++;
                        }
                        mysqli_stmt_close($insert);

                        if ($inserted < 1) {
                            throw new Exception('No commission rows were created.');
                        }

                        $adminResult = mysqli_query($conn, "SELECT user_id FROM users WHERE role='admin' AND status='active' ORDER BY user_id ASC LIMIT 1");
                        if ($adminResult && ($admin = mysqli_fetch_assoc($adminResult))) {
                            $adminId = (int)$admin['user_id'];
                            $title = 'Commission Payment Submitted';
                            $message = 'A hotel owner submitted a commission payment slip for ' . number_format($unsubmitted_commission, 2) . ' MMK.';
                            $notify = mysqli_prepare($conn, "INSERT INTO notifications (user_id,title,message,notification_type) VALUES (?,?,?,'System')");
                            if ($notify) {
                                mysqli_stmt_bind_param($notify, 'iss', $adminId, $title, $message);
                                mysqli_stmt_execute($notify);
                                mysqli_stmt_close($notify);
                            }
                        }

                        ownerAuditLog($conn, $owner_id, 'COMMISSION_PAYMENT_SUBMITTED', 'commissions', 0);
                        mysqli_commit($conn);
                        header('Location: earnings.php?msg=success');
                        exit;
                    } catch (Throwable $e) {
                        mysqli_rollback($conn);
                        if (is_file($destination)) {
                            @unlink($destination);
                        }
                        $error_msg = 'Commission submission failed. Please try again.';
                    }
                }
            }
        }
    }
}

// Per-hotel summary.
$earnings_stmt = mysqli_prepare(
    $conn,
    "SELECT h.hotel_id, h.hotel_name,
            COUNT(CASE WHEN b.booking_status IN ('Confirmed','Checked Out') THEN 1 END) AS eligible_bookings,
            COALESCE(SUM(CASE WHEN b.booking_status IN ('Confirmed','Checked Out') THEN b.total_amount ELSE 0 END),0) AS total_revenue
     FROM hotels h
     LEFT JOIN bookings b ON b.hotel_id=h.hotel_id
     WHERE h.owner_id=?
     GROUP BY h.hotel_id, h.hotel_name
     ORDER BY h.hotel_name ASC"
);
mysqli_stmt_bind_param($earnings_stmt, 'i', $owner_id);
mysqli_stmt_execute($earnings_stmt);
$earnings_query = mysqli_stmt_get_result($earnings_stmt);

// Payment history.
$history_stmt = mysqli_prepare(
    $conn,
    "SELECT c.commission_id, c.booking_amount, c.commission_amount, c.commission_status,
            c.payment_slip, c.created_at, b.booking_code, h.hotel_name
     FROM commissions c
     LEFT JOIN bookings b ON b.booking_id=c.booking_id
     LEFT JOIN hotels h ON h.hotel_id=b.hotel_id
     WHERE c.owner_id=?
     ORDER BY c.commission_id DESC"
);
mysqli_stmt_bind_param($history_stmt, 'i', $owner_id);
mysqli_stmt_execute($history_stmt);
$commission_history = mysqli_stmt_get_result($history_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Earnings & Commission | StayFlow Partner</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="../assets/css/owner.css" rel="stylesheet">
<style>
/* Earnings page owns its layout so it cannot collide with legacy owner-page CSS. */
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;overflow-x:hidden}
body.earnings-page{background:#f4f7fb!important}
.earnings-page .wrapper{display:block!important;width:100%!important;min-height:100vh!important}
.earnings-page .sidebar{position:fixed!important;inset:0 auto 0 0!important;width:270px!important;height:100vh!important;overflow-y:auto!important;z-index:1040!important;padding:12px!important}
.earnings-page .sidebar .brand{display:flex!important;align-items:center!important;gap:10px!important}
.earnings-page .sidebar ul{list-style:none!important;margin:0!important;padding:4px 0!important}
.earnings-page .sidebar ul li{display:block!important;width:100%!important}
.earnings-page .sidebar ul li a{display:flex!important;align-items:center!important;gap:11px!important;width:100%!important;text-decoration:none!important}
.earnings-page .main-content{display:block!important;margin:0 0 0 270px!important;width:calc(100% - 270px)!important;min-width:0!important;max-width:none!important;padding:26px 30px!important;overflow:visible!important}
.earnings-page .main-content> *{max-width:100%!important}
.earnings-page .topbar{width:100%!important;background:#fff!important;margin:0 0 22px!important}
.earnings-head{display:flex;justify-content:space-between;gap:16px;align-items:center}
.finance-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;width:100%!important;margin:0 0 22px!important;padding:0!important}
.finance-card{min-width:0;background:#fff;border:1px solid #e4e9f1;border-radius:18px;padding:20px;position:relative;overflow:hidden;box-shadow:0 5px 18px rgba(30,48,80,.045)}
.finance-card:after{content:"";position:absolute;width:90px;height:90px;border-radius:50%;right:-35px;top:-35px;background:rgba(11,114,231,.06)}
.finance-icon{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;margin-bottom:18px;font-size:18px}
.finance-label{font-size:11px;color:#738096;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.finance-value{font-size:clamp(18px,1.45vw,24px);line-height:1.25;font-weight:700;margin-top:5px;color:#172033;overflow-wrap:anywhere}
.finance-note{font-size:11px;line-height:1.5;color:#8b95a6;margin-top:6px}
.icon-blue{background:#eaf3ff;color:#0b72e7}.icon-green{background:#e9f8ef;color:#198754}.icon-orange{background:#fff3df;color:#d47a00}.icon-purple{background:#f1edff;color:#6f42c1}
.earnings-page .card-box{display:block!important;width:100%!important;min-width:0!important;background:#fff;border:1px solid #e4e9f1;border-radius:18px;padding:22px!important;margin:0 0 22px!important;box-shadow:0 5px 18px rgba(30,48,80,.045)}
.earnings-page .settlement-card{background:linear-gradient(135deg,#071a38,#0b315f)!important;color:#fff!important;border:0!important;padding:24px!important}
.settlement-card .text-muted{color:#b9c7db!important}.settlement-amount{font-size:30px;font-weight:700}.soft-panel{background:#f7f9fc;border:1px solid #e8edf4;border-radius:14px;padding:16px}
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:600;white-space:nowrap}.status-paid{background:#e9f8ef;color:#137a43}.status-pending{background:#fff3d9;color:#9c6800}.hotel-name{font-weight:600;color:#172033}.money{font-variant-numeric:tabular-nums;white-space:nowrap}
.earnings-page .table-responsive{width:100%!important;max-width:100%!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch}
.earnings-page .table{width:100%!important;margin:0!important}.table>thead>tr>th{white-space:nowrap}.empty-state{padding:34px;text-align:center;color:#8290a3}.empty-state i{font-size:28px;margin-bottom:10px}.upload-hint{font-size:11px;color:#8d98a9;margin-top:7px}
@media(max-width:1250px){.finance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.earnings-page .sidebar{position:relative!important;width:100%!important;height:auto!important;inset:auto!important}.earnings-page .sidebar ul{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}.earnings-page .main-content{margin-left:0!important;width:100%!important;padding:18px!important}}
@media(max-width:640px){.finance-grid{grid-template-columns:1fr}.earnings-head{align-items:flex-start;flex-direction:column}.earnings-head .btn{width:100%}.settlement-amount{font-size:25px}.earnings-page .sidebar ul{grid-template-columns:1fr}.earnings-page .main-content{padding:12px!important}.earnings-page .card-box,.earnings-page .settlement-card{padding:16px!important}}
</style>
</head>
<body class="earnings-page">
<div class="wrapper">
<?php include '../includes/owner_sidebar.php'; ?>
<main class="main-content">
<header class="topbar earnings-head">
    <div>
        <div class="text-primary fw-semibold small mb-1">FINANCE CENTER</div>
        <h4 class="fw-bold mb-1"><i class="fa-solid fa-wallet text-primary me-2"></i>Earnings & Commission</h4>
        <small class="text-muted">Track property sales, platform commission and settlement status.</small>
    </div>
    <a href="dashboard.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-2"></i>Dashboard</a>
</header>

<?php if ($success_msg): ?>
<div class="alert alert-success border-0 shadow-sm"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
<div class="alert alert-danger border-0 shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<section class="finance-grid">
    <div class="finance-card">
        <div class="finance-icon icon-green"><i class="fa-solid fa-chart-line"></i></div>
        <div class="finance-label">Gross Booking Revenue</div>
        <div class="finance-value money"><?= number_format($gross_revenue, 2) ?> MMK</div>
        <div class="finance-note">Confirmed + checked-out bookings</div>
    </div>
    <div class="finance-card">
        <div class="finance-icon icon-orange"><i class="fa-solid fa-percent"></i></div>
        <div class="finance-label">Platform Commission</div>
        <div class="finance-value money"><?= number_format($total_commission, 2) ?> MMK</div>
        <div class="finance-note">10% of eligible booking revenue</div>
    </div>
    <div class="finance-card">
        <div class="finance-icon icon-blue"><i class="fa-solid fa-sack-dollar"></i></div>
        <div class="finance-label">Owner Net Earnings</div>
        <div class="finance-value money"><?= number_format($net_owner_earnings, 2) ?> MMK</div>
        <div class="finance-note">Gross revenue minus platform fee</div>
    </div>
    <div class="finance-card">
        <div class="finance-icon icon-purple"><i class="fa-solid fa-circle-check"></i></div>
        <div class="finance-label">Commission Paid</div>
        <div class="finance-value money"><?= number_format($paid_commission, 2) ?> MMK</div>
        <div class="finance-note"><?= number_format($pending_commission, 2) ?> MMK awaiting admin verification</div>
    </div>
</section>

<div class="card-box settlement-card">
    <div class="row g-4 align-items-center">
        <div class="col-lg-5">
            <div class="small text-uppercase fw-semibold text-muted mb-2">Ready to submit</div>
            <div class="settlement-amount money"><?= number_format($unsubmitted_commission, 2) ?> MMK</div>
            <div class="text-muted mt-2 small">
                <?php if ($unsubmitted_commission > 0): ?>
                <?= count($outstanding_bookings) ?> booking<?= count($outstanding_bookings) === 1 ? '' : 's' ?> have not been submitted for commission settlement yet.
                <?php else: ?>
                There is no new commission payment to submit right now.
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-7">
            <?php if ($unsubmitted_commission > 0): ?>
            <form action="earnings.php" method="POST" enctype="multipart/form-data" class="soft-panel bg-white text-dark">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Payment Slip <span class="text-danger">*</span></label>
                        <input type="file" name="payment_slip" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                        <div class="upload-hint">JPG, PNG or WEBP · maximum 2 MB</div>
                    </div>
                    <div class="col-md-5">
                        <button type="submit" name="pay_commission" class="btn btn-success w-100 py-2"><i class="fa-solid fa-paper-plane me-2"></i>Submit Settlement</button>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="soft-panel bg-white text-dark d-flex align-items-center gap-3">
                <div class="finance-icon icon-green mb-0 flex-shrink-0"><i class="fa-solid fa-check"></i></div>
                <div><strong>All caught up</strong><div class="text-muted small">New eligible bookings will appear here automatically.</div></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card-box">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-building text-primary me-2"></i>Property Earnings</h5>
            <small class="text-muted">Revenue and commission breakdown for each hotel.</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Hotel</th><th>Eligible Bookings</th><th>Gross Revenue</th><th>Commission 10%</th><th>Net Earnings</th></tr></thead>
            <tbody>
            <?php if ($earnings_query && mysqli_num_rows($earnings_query) > 0): ?>
                <?php while ($e = mysqli_fetch_assoc($earnings_query)): $hotelRevenue=(float)$e['total_revenue']; $hotelCommission=round($hotelRevenue*.10,2); ?>
                <tr>
                    <td class="hotel-name"><?= htmlspecialchars($e['hotel_name']) ?></td>
                    <td><?= number_format((int)$e['eligible_bookings']) ?></td>
                    <td class="money text-success fw-semibold"><?= number_format($hotelRevenue, 2) ?> MMK</td>
                    <td class="money text-danger"><?= number_format($hotelCommission, 2) ?> MMK</td>
                    <td class="money text-primary fw-semibold"><?= number_format($hotelRevenue-$hotelCommission, 2) ?> MMK</td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5"><div class="empty-state"><i class="fa-regular fa-folder-open d-block"></i>No hotel earnings data available.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-box">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Commission History</h5>
            <small class="text-muted">Each booking gets one commission record, so duplicate submissions are prevented.</small>
        </div>
        <div class="d-flex gap-2 small">
            <span class="status-pill status-pending"><i class="fa-solid fa-clock"></i>Pending <?= number_format($pending_commission,2) ?> MMK</span>
            <span class="status-pill status-paid"><i class="fa-solid fa-check"></i>Paid <?= number_format($paid_commission,2) ?> MMK</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>#</th><th>Booking</th><th>Hotel</th><th>Booking Amount</th><th>Commission</th><th>Status</th><th>Slip</th><th>Submitted</th></tr></thead>
            <tbody>
            <?php if ($commission_history && mysqli_num_rows($commission_history) > 0): $no=1; ?>
                <?php while ($c=mysqli_fetch_assoc($commission_history)): $isPaid=($c['commission_status']==='Paid'); ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($c['booking_code'] ?: ('#'.$c['commission_id'])) ?></td>
                    <td><?= htmlspecialchars($c['hotel_name'] ?? '-') ?></td>
                    <td class="money"><?= number_format((float)$c['booking_amount'],2) ?> MMK</td>
                    <td class="money fw-semibold"><?= number_format((float)$c['commission_amount'],2) ?> MMK</td>
                    <td><span class="status-pill <?= $isPaid?'status-paid':'status-pending' ?>"><i class="fa-solid <?= $isPaid?'fa-check':'fa-clock' ?>"></i><?= htmlspecialchars($c['commission_status']) ?></span></td>
                    <td>
                        <?php if (!empty($c['payment_slip'])): ?>
                        <a href="../assets/images/slips/<?= rawurlencode($c['payment_slip']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-regular fa-image me-1"></i>View</a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-muted"><?= !empty($c['created_at']) ? date('d M Y, h:i A', strtotime($c['created_at'])) : 'N/A' ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fa-regular fa-receipt d-block"></i>No commission submissions yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="text-center text-muted py-4"><small>© <?= date('Y') ?> StayFlow Partner · Earnings & Commission</small></footer>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(()=>document.querySelectorAll('.alert').forEach(el=>{el.style.transition='opacity .25s';el.style.opacity='0';setTimeout(()=>el.remove(),250)}),4500);</script>
</body>
</html>
