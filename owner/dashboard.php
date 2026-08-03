<?php

require_once '../config/config.php';


if(session_status() === PHP_SESSION_NONE){
    session_start();
}



/*
================================
OWNER AUTH
================================
*/

if(
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'owner'
){

    header("Location: ../login.php");
    exit();

}


$owner_id = $_SESSION['user_id'];


$error = "";







/*
================================
CSRF TOKEN
================================
*/

if(empty($_SESSION['csrf_token'])){

    $_SESSION['csrf_token'] =
    bin2hex(random_bytes(32));

}








/*
================================
AUDIT LOG
================================
*/

function insertOwnerAudit(

$conn,
$user_id,
$action,
$table,
$record_id

){


$stmt=mysqli_prepare(

$conn,

"
INSERT INTO audit_logs

(
user_id,
action,
table_name,
record_id,
ip_address,
user_agent
)

VALUES(?,?,?,?,?,?)

"

);


$ip=$_SERVER['REMOTE_ADDR'] ?? '';

$agent=$_SERVER['HTTP_USER_AGENT'] ?? '';



mysqli_stmt_bind_param(

$stmt,

"ississ",

$user_id,
$action,
$table,
$record_id,
$ip,
$agent

);



mysqli_stmt_execute($stmt);


}








/*
================================
UPDATE BOOKING STATUS
================================
*/


if(

$_SERVER['REQUEST_METHOD']=="POST"

&&

isset($_POST['update_booking_status'])

){



if(

!isset($_POST['csrf_token'])

||

!hash_equals(

$_SESSION['csrf_token'],

$_POST['csrf_token']

)

){

die("Invalid CSRF Token");

}





$booking_id =
intval($_POST['booking_id']);



$status =
trim($_POST['status_value']);




$allowed_status=[

"Pending",
"Confirmed",
"Checked Out",
"Cancelled"

];




if(in_array($status,$allowed_status)){



$check=mysqli_prepare(

$conn,

"
SELECT

b.customer_id,

b.booking_code,

h.hotel_name

FROM bookings b

JOIN hotels h

ON b.hotel_id=h.hotel_id


WHERE b.booking_id=?

AND h.owner_id=?

"

);



mysqli_stmt_bind_param(

$check,

"ii",

$booking_id,

$owner_id

);



mysqli_stmt_execute($check);


$result=mysqli_stmt_get_result($check);


$booking=mysqli_fetch_assoc($result);




if($booking){



$update=mysqli_prepare(

$conn,

"
UPDATE bookings

SET booking_status=?

WHERE booking_id=?

"

);



mysqli_stmt_bind_param(

$update,

"si",

$status,

$booking_id

);



mysqli_stmt_execute($update);




insertOwnerAudit(

$conn,

$owner_id,

"UPDATE_BOOKING_STATUS",

"bookings",

$booking_id

);



header(

"Location: dashboard.php?msg=updated"

);


exit();



}


}



}



/*
================================
OWNER STATISTICS FUNCTION
================================
*/


function getOwnerCount(

$conn,

$sql,

$owner_id

){


$stmt=mysqli_prepare(

$conn,

$sql

);



mysqli_stmt_bind_param(

$stmt,

"i",

$owner_id

);



mysqli_stmt_execute($stmt);



$result=mysqli_stmt_get_result($stmt);



$data=mysqli_fetch_assoc($result);



return $data['total'] ?? 0;


}








/*
================================
TOTAL HOTELS
================================
*/


$total_hotels=getOwnerCount(

$conn,

"
SELECT COUNT(*) total

FROM hotels

WHERE owner_id=?

",

$owner_id

);









/*
================================
TOTAL BOOKINGS
================================
*/


$total_bookings=getOwnerCount(

$conn,

"
SELECT COUNT(b.booking_id) total

FROM bookings b

JOIN hotels h

ON b.hotel_id=h.hotel_id

WHERE h.owner_id=?

",

$owner_id

);









/*
================================
ACTIVE GUESTS
================================
*/


$active_guests=getOwnerCount(

$conn,

"
SELECT COUNT(b.booking_id) total

FROM bookings b

JOIN hotels h

ON b.hotel_id=h.hotel_id

WHERE h.owner_id=?

AND b.booking_status='Confirmed'

",

$owner_id

);









/*
================================
TOTAL REVENUE
================================
*/


$earn_stmt=mysqli_prepare(

$conn,

"
SELECT

COALESCE(SUM(b.total_amount),0) total


FROM bookings b


JOIN hotels h

ON b.hotel_id=h.hotel_id


WHERE h.owner_id=?


AND b.booking_status IN

('Confirmed','Checked Out')

"

);



mysqli_stmt_bind_param(

$earn_stmt,

"i",

$owner_id

);



mysqli_stmt_execute($earn_stmt);



$earn_result=mysqli_stmt_get_result($earn_stmt);



$total_earnings=

mysqli_fetch_assoc($earn_result)['total'];









/*
================================
SEARCH FILTER
================================
*/


$search=

trim($_GET['search'] ?? '');



$status_filter=

trim($_GET['status'] ?? '');





$where=[];

$params=[];

$types="i";



$params[]=$owner_id;







if($search!=""){



$where[]="

(

b.booking_code LIKE ?

OR h.hotel_name LIKE ?

OR u.full_name LIKE ?

)

";



$key="%".$search."%";



$params[]=$key;

$params[]=$key;

$params[]=$key;


$types.="sss";


}








if($status_filter!=""){



$where[]="

b.booking_status=?

";



$params[]=$status_filter;


$types.="s";


}








$where_sql="";



if(count($where)>0){



$where_sql=

" AND ".implode(

" AND ",

$where

);


}









/*
================================
PAGINATION
================================
*/


$limit=20;


$page=

intval($_GET['page'] ?? 1);



if($page<1){

$page=1;

}





$count_sql=mysqli_prepare(

$conn,

"
SELECT COUNT(b.booking_id) total

FROM bookings b

JOIN hotels h

ON b.hotel_id=h.hotel_id


LEFT JOIN users u

ON b.customer_id=u.user_id


WHERE h.owner_id=?

$where_sql

"

);



mysqli_stmt_bind_param(

$count_sql,

$types,

...$params

);



mysqli_stmt_execute($count_sql);



$count_result=mysqli_stmt_get_result($count_sql);



$total_records=

mysqli_fetch_assoc($count_result)['total'] ?? 0;



$total_pages=

ceil(

$total_records/$limit

);





$offset=

($page-1)*$limit;









/*
================================
FETCH BOOKINGS
================================
*/


$sql=

"

SELECT


b.*,


h.hotel_name,


u.full_name customer_name,


u.email customer_email



FROM bookings b



JOIN hotels h

ON b.hotel_id=h.hotel_id



LEFT JOIN users u

ON b.customer_id=u.user_id



WHERE h.owner_id=?


$where_sql



ORDER BY b.created_at DESC



LIMIT ? OFFSET ?



";





$stmt=mysqli_prepare(

$conn,

$sql

);





$params[]=$limit;

$params[]=$offset;



$types.="ii";




mysqli_stmt_bind_param(

$stmt,

$types,

...$params

);





mysqli_stmt_execute($stmt);



$bookings_result=

mysqli_stmt_get_result($stmt);









/*
================================
NOTIFICATION COUNT
================================
*/


$noti_stmt=mysqli_prepare(

$conn,

"
SELECT COUNT(*) total

FROM notifications

WHERE user_id=?

AND is_read=0

"

);



mysqli_stmt_bind_param(

$noti_stmt,

"i",

$owner_id

);



mysqli_stmt_execute($noti_stmt);



$noti_result=mysqli_stmt_get_result($noti_stmt);



$total_notifications=

mysqli_fetch_assoc($noti_result)['total'] ?? 0;


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>
Owner Dashboard | Hotel Partner Hub
</title>


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"

rel="stylesheet">


<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"

rel="stylesheet">


<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"

rel="stylesheet">


<link href="../assets/css/owner.css"

rel="stylesheet">



<style>


body{

font-family:'Poppins',sans-serif;

background:#f4f6f9;

}



.main-content{

margin-left:260px;

padding:30px;

}



.topbar{

background:#fff;

padding:20px;

border-radius:15px;

box-shadow:0 3px 12px rgba(0,0,0,.05);

margin-bottom:25px;

}



.card-box{

background:#fff;

padding:22px;

border-radius:15px;

box-shadow:0 3px 12px rgba(0,0,0,.05);

margin-bottom:25px;

}



.stat-card{

background:#fff;

padding:20px;

border-radius:15px;

box-shadow:0 3px 12px rgba(0,0,0,.05);

}



</style>


</head>




<body>



<div class="wrapper">


<?php include '../includes/owner_sidebar.php'; ?>



<main class="main-content">





<div class="topbar d-flex justify-content-between align-items-center">


<div>


<h3 class="fw-bold">


<i class="fa-solid fa-chart-line text-primary"></i>

Owner Dashboard


</h3>


<p class="text-muted mb-0">

Hotel management overview

</p>


</div>




<a href="notifications.php"

class="btn btn-light position-relative">


<i class="fa-solid fa-bell"></i>


<?php if($total_notifications>0): ?>


<span class="badge bg-danger position-absolute top-0 start-100 translate-middle">

<?=$total_notifications?>

</span>


<?php endif; ?>


</a>


</div>







<?php if(isset($_GET['msg']) && $_GET['msg']=="updated"): ?>


<div class="alert alert-success">

Booking status updated successfully.

</div>


<?php endif; ?>







<?php if($error!=""): ?>


<div class="alert alert-danger">

<?=htmlspecialchars($error)?>

</div>


<?php endif; ?>









<!-- STATISTICS -->


<div class="row g-4 mb-4">



<div class="col-md-3">


<div class="stat-card">


<h6 class="text-muted">

Hotels

</h6>


<h2 class="fw-bold">

<?=$total_hotels?>

</h2>


<i class="fa-solid fa-hotel text-primary fa-2x"></i>


</div>


</div>







<div class="col-md-3">


<div class="stat-card">


<h6 class="text-muted">

Bookings

</h6>


<h2 class="fw-bold">

<?=$total_bookings?>

</h2>


<i class="fa-solid fa-calendar-check text-success fa-2x"></i>


</div>


</div>







<div class="col-md-3">


<div class="stat-card">


<h6 class="text-muted">

Active Guests

</h6>


<h2 class="fw-bold">

<?=$active_guests?>

</h2>


<i class="fa-solid fa-users text-warning fa-2x"></i>


</div>


</div>







<div class="col-md-3">


<div class="stat-card">


<h6 class="text-muted">

Revenue

</h6>


<h2 class="fw-bold">

<?=number_format($total_earnings,2)?>

</h2>


<i class="fa-solid fa-money-bill text-info fa-2x"></i>


</div>


</div>



</div>









<!-- FILTER -->


<div class="card-box">


<form method="GET"

class="row g-3">



<div class="col-md-6">


<input type="text"

name="search"

class="form-control"

placeholder="Search booking..."

value="<?=htmlspecialchars($search)?>">


</div>






<div class="col-md-3">


<select name="status"

class="form-select">


<option value="">

All Status

</option>


<option value="Pending">

Pending

</option>


<option value="Confirmed">

Confirmed

</option>


<option value="Checked Out">

Checked Out

</option>


<option value="Cancelled">

Cancelled

</option>


</select>


</div>







<div class="col-md-3">


<button class="btn btn-primary w-100">


<i class="fa-solid fa-filter"></i>

Search


</button>


</div>


</form>


</div>









<!-- BOOKINGS -->


<div class="card-box">


<h5 class="fw-bold mb-3">

Recent Bookings

</h5>




<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">


<tr>

<th>Code</th>

<th>Hotel</th>

<th>Customer</th>

<th>Amount</th>

<th>Status</th>

<th>Action</th>

</tr>


</thead>



<tbody>



<?php if(mysqli_num_rows($bookings_result)>0): ?>


<?php while($b=mysqli_fetch_assoc($bookings_result)): ?>


<tr>



<td>

<?=htmlspecialchars($b['booking_code'])?>

</td>




<td>

<?=htmlspecialchars($b['hotel_name'])?>

</td>




<td>

<?=htmlspecialchars($b['customer_name'] ?? '-')?>

<br>

<small>

<?=htmlspecialchars($b['customer_email'] ?? '')?>

</small>

</td>




<td>

<?=number_format($b['total_amount'] ?? 0,2)?> MMK

</td>




<td>

<span class="badge bg-info">

<?=$b['booking_status']?>

</span>

</td>




<td>



<form method="POST">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="booking_id"

value="<?=$b['booking_id']?>">



<input type="hidden"

name="update_booking_status"

value="1">





<select name="status_value"

class="form-select form-select-sm"

onchange="this.form.submit()">



<option>

<?=$b['booking_status']?>

</option>


<option value="Confirmed">

Confirmed

</option>


<option value="Checked Out">

Checked Out

</option>


<option value="Cancelled">

Cancelled

</option>


</select>



</form>


</td>


</tr>



<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="6"

class="text-center">

No bookings found.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>







<!-- PAGINATION -->


<?php if($total_pages>1): ?>


<nav>


<ul class="pagination justify-content-center">


<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>


</ul>


</nav>


<?php endif; ?>







<footer class="text-center text-muted mt-4">

Hotel Booking System V3 © <?=date('Y')?>

</footer>



</main>


</div>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>


</html>