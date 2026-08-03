<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// 1. Gallery ထဲမှာ ပါဝင်နေတဲ့ Hotel List များကို Filter အတွက် သီးသန့် ဆွဲထုတ်ခြင်း
$hotels_query = "SELECT DISTINCT h.hotel_id, h.hotel_name, h.city 
                 FROM hotels h
                 JOIN hotel_images gi ON h.hotel_id = gi.hotel_id
                 WHERE h.status = 'approved' OR h.status = 'Approved'
                 ORDER BY h.hotel_name ASC";
$hotels_result = mysqli_query($conn, $hotels_query);

// 2. Filter Request စစ်ဆေးခြင်း (User က Hotel တစ်ခုတည်း သီးသန့် ရွေးထားသလား)
$selected_hotel_id = isset($_GET['hotel_id']) ? (int)$_GET['hotel_id'] : 0;

// 3. Hotel Images များကို Query ထုတ်ခြင်း
$query = "SELECT gi.*, h.hotel_name, h.city 
          FROM hotel_images gi
          JOIN hotels h ON gi.hotel_id = h.hotel_id
          WHERE (h.status = 'approved' OR h.status = 'Approved')";

if ($selected_hotel_id > 0) {
    $query .= " AND h.hotel_id = " . $selected_hotel_id;
}

$query .= " ORDER BY gi.is_cover DESC, gi.sort_order ASC, gi.uploaded_at DESC";
$result = mysqli_query($conn, $query);
?>

<main class="container my-4">

    <!-- Header & Intro Section -->
    <div class="text-center mx-auto mb-4" style="max-width: 650px;">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold mb-2" style="font-size: 0.78rem;">
            <i class="fa-solid fa-camera-retro me-1"></i> Destination Showcase
        </span>
        <h2 class="fw-extrabold text-dark tracking-tight mb-2">Hotel Photo Gallery</h2>
        <p class="text-secondary fs-7 mb-0">Explore luxury rooms, amenities, and scenic views from top-rated hotels across Myanmar.</p>
    </div>

    <!-- Hotel Filter Navigation Bar -->
    <?php if ($hotels_result && mysqli_num_rows($hotels_result) > 0): ?>
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-4 pb-2">
            <!-- All Hotels Button -->
            <a href="gallery.php" class="btn btn-sm rounded-pill px-3 py-2 fw-semibold transition-all <?php echo ($selected_hotel_id === 0) ? 'btn-primary shadow-sm' : 'btn-outline-secondary'; ?>">
                <i class="fa-solid fa-border-all me-1"></i> All Hotels
            </a>

            <!-- Individual Hotel Filter Pills -->
            <?php while($h = mysqli_fetch_assoc($hotels_result)): ?>
                <a href="gallery.php?hotel_id=<?php echo $h['hotel_id']; ?>" 
                   class="btn btn-sm rounded-pill px-3 py-2 fw-semibold transition-all <?php echo ($selected_hotel_id === (int)$h['hotel_id']) ? 'btn-primary shadow-sm' : 'btn-outline-secondary'; ?>">
                    <?php echo htmlspecialchars($h['hotel_name']); ?>
                    <span class="opacity-75 fs-8 ms-1">(<?php echo htmlspecialchars($h['city']); ?>)</span>
                </a>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <!-- Image Grid Gallery -->
    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <div class="row g-3 g-md-4">
            <?php while($img = mysqli_fetch_assoc($result)): ?>
                <?php 
                    $image_src = 'assets/images/' . $img['image_path'];
                    $has_image = !empty($img['image_path']) && file_exists($image_src);
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 gallery-card position-relative bg-light" style="cursor: pointer;">
                        
                        <!-- Image Container with Hover Effect -->
                        <div class="position-relative overflow-hidden" style="height: 220px;" 
                             onclick="openLightbox('<?php echo $has_image ? htmlspecialchars($image_src) : ''; ?>', '<?php echo htmlspecialchars(addslashes($img['hotel_name'])); ?>', '<?php echo htmlspecialchars(addslashes($img['city'])); ?>')">
                            
                            <?php if($has_image): ?>
                                <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                     alt="<?php echo htmlspecialchars($img['hotel_name']); ?>" 
                                     class="w-100 h-100 object-fit-cover gallery-img transition-all">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted bg-secondary-subtle">
                                    <i class="fa-solid fa-hotel fa-2x mb-1 opacity-50"></i>
                                    <span style="font-size: 11px;">Visual Coming Soon</span>
                                </div>
                            <?php endif; ?>

                            <!-- Cover / Featured Badge -->
                            <?php if(!empty($img['is_cover'])): ?>
                                <span class="position-absolute top-0 start-0 m-2 badge bg-primary border border-white border-opacity-20 px-2 py-1 rounded-2 fs-8 fw-semibold shadow-sm">
                                    <i class="fa-solid fa-star me-1"></i> Featured
                                </span>
                            <?php endif; ?>

                            <!-- Hover Overlay Icon -->
                            <div class="gallery-overlay position-absolute inset-0 bg-dark bg-opacity-40 d-flex align-items-center justify-content-center opacity-0 transition-all">
                                <span class="btn btn-light btn-sm rounded-circle shadow-sm">
                                    <i class="fa-solid fa-magnifying-glass-plus text-primary"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer Info -->
                        <div class="card-body p-3 bg-white border-top">
                            <h6 class="fw-bold text-dark text-truncate mb-1" style="font-size: 0.9rem;">
                                <?php echo htmlspecialchars($img['hotel_name']); ?>
                            </h6>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-secondary fs-8">
                                    <i class="fa-solid fa-location-dot text-danger me-1"></i><?php echo htmlspecialchars($img['city']); ?>
                                </span>
                                <a href="products.php?hotel_id=<?php echo $img['hotel_id']; ?>" class="text-primary fw-semibold fs-8 text-decoration-none">
                                    View Hotel <i class="fa-solid fa-angle-right fs-9 ms-0.5"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-5 bg-white rounded-4 border shadow-sm my-4">
            <i class="fa-regular fa-image fa-3x text-muted mb-3 opacity-50"></i>
            <h5 class="fw-bold text-dark mb-1">No Gallery Photos Found</h5>
            <p class="text-secondary fs-7 mb-3">There are no uploaded photos available for this selection.</p>
            <a href="gallery.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-semibold">
                View All Hotel Photos
            </a>
        </div>
    <?php endif; ?>

</main>

<!-- Lightbox Modal Viewer -->
<div class="modal fade" id="galleryLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 position-relative text-center">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3 shadow-sm bg-dark p-2 rounded-circle" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="lightboxImage" src="" class="img-fluid rounded-4 shadow-lg mb-2" style="max-height: 80vh; object-fit: contain;">
                <div class="bg-dark bg-opacity-75 backdrop-blur text-white p-3 rounded-3 d-inline-block shadow-sm">
                    <h6 id="lightboxTitle" class="fw-bold mb-0 text-white"></h6>
                    <small id="lightboxCity" class="text-white-50 fs-8"></small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .gallery-card:hover .gallery-img {
        transform: scale(1.05);
    }
    .gallery-card:hover .gallery-overlay {
        opacity: 1 !important;
    }
    .transition-all {
        transition: all 0.3s ease-in-out;
    }
</style>

<script>
function openLightbox(imageSrc, hotelName, city) {
    if(!imageSrc) return;
    document.getElementById('lightboxImage').src = imageSrc;
    document.getElementById('lightboxTitle').innerText = hotelName;
    document.getElementById('lightboxCity').innerText = city;
    
    var myModal = new bootstrap.Modal(document.getElementById('galleryLightbox'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>