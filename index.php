<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$search_city = trim($_GET['search_city'] ?? '');
$check_in = trim($_GET['check_in'] ?? '');
$check_out = trim($_GET['check_out'] ?? '');
$guests = max(1, min(12, (int)($_GET['guests'] ?? 2)));
$rooms = max(1, min(6, (int)($_GET['rooms'] ?? 1)));

$query = "SELECT h.*, u.username AS owner_name,
          (SELECT MIN(r.base_price) FROM rooms r WHERE r.hotel_id=h.hotel_id AND r.room_status='available') AS starting_price,
          (SELECT ri.image_path FROM room_images ri INNER JOIN rooms rr ON rr.room_id=ri.room_id WHERE rr.hotel_id=h.hotel_id ORDER BY ri.is_cover DESC,ri.sort_order ASC LIMIT 1) AS room_image
          FROM hotels h LEFT JOIN users u ON h.owner_id=u.user_id
          WHERE LOWER(h.status)='approved'";
$params=[];$types='';
if($search_city!==''){$query.=" AND (h.city LIKE ? OR h.hotel_name LIKE ? OR h.country LIKE ?)";$like='%'.$search_city.'%';$params=[$like,$like,$like];$types='sss';}
$query.=" ORDER BY h.star_rating DESC,h.hotel_id DESC";
$stmt=mysqli_prepare($conn,$query);
if($params){mysqli_stmt_bind_param($stmt,$types,...$params);} mysqli_stmt_execute($stmt);$result=mysqli_stmt_get_result($stmt);
?>
<section class="hbs-hero">
  <div class="hbs-container position-relative" style="z-index:2">
    <span class="hbs-eyebrow"><i class="fa-solid fa-shield-heart"></i> Trusted stays. Smarter booking.</span>
    <h1>Find your next stay, without the booking hassle.</h1>
    <p>Compare approved hotels, choose the right room, and manage your trip from one clean booking experience.</p>
  </div>
</section>
<div class="hbs-search-wrap"><div class="hbs-container">
<form class="hbs-search" method="GET" action="index.php"><div class="hbs-search-grid">
  <div class="hbs-search-field"><i class="fa-solid fa-location-dot"></i><div class="w-100"><label>Destination</label><input name="search_city" value="<?=htmlspecialchars($search_city)?>" placeholder="Where are you going?"></div></div>
  <div class="hbs-search-field"><i class="fa-regular fa-calendar"></i><div class="w-100"><label>Check-in</label><input type="date" name="check_in" value="<?=htmlspecialchars($check_in)?>" min="<?=date('Y-m-d')?>"></div></div>
  <div class="hbs-search-field"><i class="fa-regular fa-calendar-check"></i><div class="w-100"><label>Check-out</label><input type="date" name="check_out" value="<?=htmlspecialchars($check_out)?>" min="<?=date('Y-m-d',strtotime('+1 day'))?>"></div></div>
  <div class="hbs-search-field"><i class="fa-solid fa-user-group"></i><div class="w-100"><label>Guests & rooms</label><div class="d-flex gap-2"><select name="guests"><?php for($i=1;$i<=12;$i++):?><option value="<?=$i?>" <?=$guests===$i?'selected':''?>><?=$i?> guest<?=$i>1?'s':''?></option><?php endfor;?></select><select name="rooms"><?php for($i=1;$i<=6;$i++):?><option value="<?=$i?>" <?=$rooms===$i?'selected':''?>><?=$i?> room<?=$i>1?'s':''?></option><?php endfor;?></select></div></div></div>
  <button class="hbs-search-btn" type="submit"><i class="fa-solid fa-magnifying-glass me-2"></i>Search</button>
</div></form></div></div>

<section class="hbs-section pb-2"><div class="hbs-container"><div class="hbs-trust-grid">
<div class="hbs-trust"><i class="fa-solid fa-badge-check"></i><div><b>Verified properties</b><span>Only approved hotel partners are shown.</span></div></div>
<div class="hbs-trust"><i class="fa-solid fa-tags"></i><div><b>Clear pricing</b><span>See starting room rates before opening a hotel.</span></div></div>
<div class="hbs-trust"><i class="fa-solid fa-headset"></i><div><b>Booking support</b><span>Your reservations stay connected to one account.</span></div></div>
</div></div></section>

<section class="hbs-section"><div class="hbs-container">
<div class="d-flex justify-content-between align-items-end gap-3 mb-4"><div><h2 class="hbs-section-title"><?= $search_city!=='' ? 'Stays matching “'.htmlspecialchars($search_city).'”' : 'Popular stays for your next trip' ?></h2><div class="hbs-section-sub">Browse verified hotels and open a property to compare available rooms.</div></div><?php if($search_city!==''||$check_in!==''):?><a class="btn btn-outline-secondary btn-sm rounded-pill px-3" href="index.php">Clear search</a><?php endif;?></div>
<?php if($result && mysqli_num_rows($result)>0):?><div class="hotel-grid">
<?php while($hotel=mysqli_fetch_assoc($result)):
$view='products.php?hotel_id='.(int)$hotel['hotel_id']; if($check_in)$view.='&check_in='.urlencode($check_in);if($check_out)$view.='&check_out='.urlencode($check_out);$view.='&guests='.$guests;
$img=''; if(!empty($hotel['room_image'])){foreach(['assets/images/rooms/'.$hotel['room_image'],'assets/images/'.$hotel['room_image'],$hotel['room_image']] as $candidate){if(file_exists($candidate)){$img=$candidate;break;}}}
?>
<article class="hotel-card"><div class="hotel-media"><?php if($img):?><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($hotel['hotel_name'])?>"><?php else:?><div class="hotel-placeholder"><i class="fa-solid fa-hotel"></i></div><?php endif;?><span class="hotel-rating"><i class="fa-solid fa-star text-warning me-1"></i><?=number_format((float)$hotel['star_rating'],1)?></span></div>
<div class="hotel-body"><div class="hotel-location"><i class="fa-solid fa-location-dot me-1"></i><?=htmlspecialchars(trim(($hotel['city']??'').' · '.($hotel['country']??''),' ·'))?></div><h3 class="hotel-name"><?=htmlspecialchars($hotel['hotel_name'])?></h3><p class="hotel-desc"><?=htmlspecialchars(mb_strimwidth($hotel['description']??'Comfortable stay with easy booking and property support.',0,120,'…'))?></p><div class="hotel-meta"><span><i class="fa-regular fa-clock me-1"></i>Check-in <?=date('g:i A',strtotime($hotel['check_in_time']))?></span><span><i class="fa-solid fa-user-tie me-1"></i>Verified partner</span></div><div class="d-flex justify-content-between align-items-end"><div><small class="text-muted d-block" style="font-size:.67rem">Starting from</small><strong><?= $hotel['starting_price']!==null ? number_format((float)$hotel['starting_price']).' MMK' : 'View rooms' ?></strong></div><a class="btn btn-primary px-3" href="<?=htmlspecialchars($view)?>">See availability</a></div></div></article>
<?php endwhile;?></div><?php else:?><div class="bg-white border rounded-4 p-5 text-center"><i class="fa-solid fa-map-location-dot fa-3x text-primary mb-3"></i><h4 class="fw-bold">No stays found</h4><p class="text-muted">Try another destination or clear your filters.</p><a href="index.php" class="btn btn-primary">Browse all stays</a></div><?php endif;?>
</div></section>
<?php require_once 'includes/footer.php'; ?>
