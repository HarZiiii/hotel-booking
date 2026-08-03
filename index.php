<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Search Parameters Handling
$search_city = isset($_GET['search_city']) ? trim($_GET['search_city']) : '';
$check_in    = isset($_GET['check_in']) ? trim($_GET['check_in']) : '';
$check_out   = isset($_GET['check_out']) ? trim($_GET['check_out']) : '';
$guests      = isset($_GET['guests']) ? (int)$_GET['guests'] : 1;
$rooms       = isset($_GET['rooms']) ? (int)$_GET['rooms'] : 1;

// Query with Prepared Statement
$query = "SELECT h.*, u.username as owner_name 
          FROM hotels h 
          LEFT JOIN users u ON h.owner_id = u.user_id 
          WHERE (h.status = 'approved' OR h.status = 'Approved')";

$params = [];
$types  = "";

if (!empty($search_city)) {
    $query .= " AND h.city LIKE ?";
    $params[] = '%' . $search_city . '%';
    $types .= "s";
}

$query .= " ORDER BY h.star_rating DESC, h.hotel_id DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<main class="container my-4">

    <!-- Hero Banner with Booking.com / AirAsia Style Search Widget -->
    <div class="card border-0 text-white overflow-hidden shadow-lg mb-5 rounded-4" 
         style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.82)), url('assets/images/hero-bg.jpg') center/cover no-repeat; min-height: 380px;">
        <div class="card-body p-4 p-md-5 d-flex flex-column justify-content-center text-center">
            
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mx-auto mb-3" style="font-size: 0.78rem;">
                <i class="fa-solid fa-plane-departure me-1.5"></i> Find & Book Premium Stays
            </span>
            <h1 class="fw-extrabold tracking-tight mb-2 display-6">Search Hotels, Resorts & Rooms</h1>
            <p class="text-white-50 mx-auto mb-4" style="max-width: 580px; font-size: 0.95rem;">
                Compare prices and check availability across top-rated destinations in Myanmar.
            </p>

            <!-- Booking.com / AirAsia Modern Search Box -->
            <form action="index.php" method="GET" class="bg-white rounded-4 p-3 shadow-lg text-dark text-start border mx-auto w-100" style="max-width: 980px;">
                <div class="row g-2 align-items-center">
                    
                    <!-- Destination / City -->
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-location-dot text-primary me-1"></i> Destination
                        </label>
                        <div class="input-group">
                            <input type="text" name="search_city" class="form-control border-0 bg-light fs-7 py-2.5 rounded-3 fw-medium" 
                                   placeholder="e.g. Yangon, Mandalay" value="<?php echo htmlspecialchars($search_city); ?>">
                        </div>
                    </div>

                    <!-- Check-in Date -->
                    <div class="col-lg-2 col-md-6 col-6">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-calendar-check text-primary me-1"></i> Check-in
                        </label>
                        <input type="date" name="check_in" class="form-control border-0 bg-light fs-7 py-2.5 rounded-3 fw-medium" 
                               value="<?php echo htmlspecialchars($check_in); ?>" min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Check-out Date -->
                    <div class="col-lg-2 col-md-6 col-6">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-calendar-xmark text-primary me-1"></i> Check-out
                        </label>
                        <input type="date" name="check_out" class="form-control border-0 bg-light fs-7 py-2.5 rounded-3 fw-medium" 
                               value="<?php echo htmlspecialchars($check_out); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>

                    <!-- Guests & Rooms Options -->
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label text-uppercase text-muted fw-bold mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <i class="fa-solid fa-users text-primary me-1"></i> Occupancy
                        </label>
                        <div class="d-flex gap-2">
                            <select name="guests" class="form-select border-0 bg-light fs-7 py-2.5 rounded-3 fw-medium">
                                <?php for($i=1; $i<=6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($guests == $i)?'selected':''; ?>>
                                        <?php echo $i; ?> Guest<?php echo ($i>1)?'s':''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="rooms" class="form-select border-0 bg-light fs-7 py-2.5 rounded-3 fw-medium">
                                <?php for($r=1; $r<=4; $r++): ?>
                                    <option value="<?php echo $r; ?>" <?php echo ($rooms == $r)?'selected':''; ?>>
                                        <?php echo $r; ?> Room<?php echo ($r>1)?'s':''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Search Submit Button -->
                    <div class="col-lg-2 col-md-12">
                        <label class="form-label d-none d-lg-block mb-1">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold fs-7 shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <i class="fa-solid fa-magnifying-glass"></i> Search
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>

    <!-- Hotel Properties Listing Grid -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-extrabold text-dark tracking-tight mb-1">
                <?php echo !empty($search_city) ? "Hotels in '" . htmlspecialchars($search_city) . "'" : "Recommended Stays"; ?>
            </h4>
            <p class="text-secondary mb-0" style="font-size: 0.88rem;">Handpicked verified properties ready for instant booking</p>
        </div>
        <?php if(!empty($search_city) || !empty($check_in)): ?>
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1.5 fw-semibold fs-8">
                <i class="fa-solid fa-xmark me-1"></i> Reset Filters
            </a>
        <?php endif; ?>
    </div>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <div class="row g-4">
            <?php while($hotel = mysqli_fetch_assoc($result)): ?>
                <?php 
                    $hotel_img = !empty($hotel['image_url']) ? $hotel['image_url'] : (!empty($hotel['cover_image']) ? $hotel['cover_image'] : '');
                    $rating    = !empty($hotel['star_rating']) ? number_format((float)$hotel['star_rating'], 1) : '5.0';
                    
                    // URL Redirect Parameter တည်ဆောက်ခြင်း
                    $view_url = "products.php?hotel_id=" . $hotel['hotel_id'];
                    if (!empty($check_in))  $view_url .= "&check_in=" . urlencode($check_in);
                    if (!empty($check_out)) $view_url .= "&check_out=" . urlencode($check_out);
                    if ($guests > 1)        $view_url .= "&guests=" . $guests;
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 d-flex flex-column hover-lift">
                        
                        <!-- Hotel Image Container -->
                        <div class="position-relative bg-light" style="height: 200px;">
                            <?php if (!empty($hotel_img) && file_exists($hotel_img)): ?>
                                <img src="<?php echo htmlspecialchars($hotel_img); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo htmlspecialchars($hotel['hotel_name']); ?>">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                    <i class="fa-solid fa-hotel fa-3x opacity-50"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Rating Badge -->
                            <div class="position-absolute top-0 end-0 m-3 badge bg-dark bg-opacity-75 backdrop-blur text-warning border border-white border-opacity-20 px-2.5 py-1.5 rounded-pill fw-bold fs-8">
                                <i class="fa-solid fa-star me-1"></i><?php echo $rating; ?>
                            </div>
                        </div>

                        <!-- Card Body Details -->
                        <div class="card-body p-4 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <span class="text-primary fw-bold text-uppercase fs-8 d-block mb-1">
                                    <i class="fa-solid fa-location-dot me-1"></i><?php echo htmlspecialchars($hotel['city'] ?? 'Myanmar'); ?><?php echo !empty($hotel['country']) ? ', ' . htmlspecialchars($hotel['country']) : ''; ?>
                                </span>
                                <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($hotel['hotel_name']); ?></h5>
                                <p class="text-secondary fs-7 mb-3" style="min-height: 42px; line-height: 1.5;">
                                    <?php 
                                        $desc = strip_tags($hotel['description'] ?? '');
                                        echo htmlspecialchars(mb_strlen($desc) > 80 ? mb_substr($desc, 0, 80) . '...' : $desc); 
                                    ?>
                                </p>
                            </div>

                            <a href="<?php echo $view_url; ?>" class="btn btn-primary rounded-3 w-100 py-2 fw-bold fs-7 shadow-sm d-flex align-items-center justify-content-center gap-2">
                                Check Available Rooms <i class="fa-solid fa-arrow-right fs-8"></i>
                            </a>
                        </div>

                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-white rounded-4 border shadow-sm my-4">
            <i class="fa-solid fa-building-circle-xmark fa-3x text-muted mb-3 opacity-50"></i>
            <h5 class="fw-bold text-dark mb-1">No Matching Hotels Found</h5>
            <p class="text-secondary fs-7 mb-0">Try searching for a different city or clearing your date filters.</p>
        </div>
    <?php endif; ?>

</main>

<?php require_once 'includes/footer.php'; ?>