<?php

require_once '../config/config.php';


/*
===========================================
1. SESSION & OWNER AUTHENTICATION
===========================================
*/

if(session_status() === PHP_SESSION_NONE){

    session_start();

}



if(
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'owner'
){

    header("Location: ../login.php");
    exit();

}



$owner_id = $_SESSION['user_id'];

$msg = "";
$error = "";









/*
===========================================
2. CSRF TOKEN
===========================================
*/


if(empty($_SESSION['csrf_token'])){


    $_SESSION['csrf_token'] =
    bin2hex(random_bytes(32));


}









/*
===========================================
3. AUDIT LOG FUNCTION
===========================================
*/


function insertOwnerAudit(

    $conn,
    $user_id,
    $action,
    $table_name,
    $record_id

){


    $ip =
    $_SERVER['REMOTE_ADDR']
    ??
    '127.0.0.1';


    $agent =
    $_SERVER['HTTP_USER_AGENT']
    ??
    'Unknown';



    $stmt = mysqli_prepare(

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

        VALUES

        (?,?,?,?,?,?)

        "

    );



    mysqli_stmt_bind_param(

        $stmt,

        "ississ",

        $user_id,

        $action,

        $table_name,

        $record_id,

        $ip,

        $agent

    );



    mysqli_stmt_execute($stmt);


}









/*
===========================================
4. UPDATE BOOKING STATUS
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
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

    intval(

        $_POST['booking_id'] ?? 0

    );



    $new_status =

    trim(

        $_POST['status_value'] ?? ''

    );





    $allowed_status = [

        'Pending',
        'Confirmed',
        'Checked Out',
        'Cancelled'

    ];







    if(

        $booking_id > 0

        &&

        in_array(

            $new_status,

            $allowed_status

        )

    ){



        /*
        ===========================
        CHECK OWNER ACCESS
        ===========================
        */


        $check_stmt = mysqli_prepare(

            $conn,

            "

            SELECT


            b.booking_code,

            b.customer_id,

            h.hotel_name


            FROM bookings b


            LEFT JOIN hotels h


            ON b.hotel_id=h.hotel_id


            WHERE

            b.booking_id=?

            AND

            h.owner_id=?


            "

        );



        mysqli_stmt_bind_param(

            $check_stmt,

            "ii",

            $booking_id,

            $owner_id

        );



        mysqli_stmt_execute(

            $check_stmt

        );



        $booking_result =

        mysqli_stmt_get_result(

            $check_stmt

        );



        $booking =

        mysqli_fetch_assoc(

            $booking_result

        );








        if($booking){



            /*
            ===========================
            UPDATE BOOKING
            ===========================
            */


            $update_stmt = mysqli_prepare(

                $conn,

                "

                UPDATE bookings

                SET booking_status=?

                WHERE booking_id=?

                "

            );



            mysqli_stmt_bind_param(

                $update_stmt,

                "si",

                $new_status,

                $booking_id

            );



            mysqli_stmt_execute(

                $update_stmt

            );









            /*
            ===========================
            CUSTOMER NOTIFICATION
            ===========================
            */


            if(!empty($booking['customer_id'])){


                $title =

                "Reservation Status Update";



                $message =

                "Your booking "

                .

                $booking['booking_code']

                .

                " at "

                .

                $booking['hotel_name']

                .

                " is now "

                .

                strtoupper($new_status);







                $notify_stmt = mysqli_prepare(

                    $conn,

                    "

                    INSERT INTO notifications

                    (

                    user_id,

                    title,

                    message,

                    type,

                    is_read,

                    created_at

                    )


                    VALUES

                    (?,?,?,'booking',0,NOW())


                    "

                );





                mysqli_stmt_bind_param(

                    $notify_stmt,

                    "iss",

                    $booking['customer_id'],

                    $title,

                    $message

                );



                mysqli_stmt_execute(

                    $notify_stmt

                );



            }









            /*
            ===========================
            AUDIT LOG
            ===========================
            */


            insertOwnerAudit(

                $conn,

                $owner_id,

                "BOOKING_STATUS_UPDATED",

                "bookings",

                $booking_id

            );








            header(

                "Location: dashboard.php?msg=updated"

            );


            exit();



        }

        else{


            $error =

            "Unauthorized booking action.";

        }


    }


}









/*
===========================================
5. OWNER STATISTICS
===========================================
*/


function getOwnerCount(

    $conn,

    $sql,

    $owner_id

){


    $stmt = mysqli_prepare(

        $conn,

        $sql

    );



    mysqli_stmt_bind_param(

        $stmt,

        "i",

        $owner_id

    );



    mysqli_stmt_execute($stmt);



    $result =

    mysqli_stmt_get_result($stmt);



    $data =

    mysqli_fetch_assoc($result);



    return $data['total'] ?? 0;


}








$total_hotels = getOwnerCount(

    $conn,

    "

    SELECT COUNT(*) total

    FROM hotels

    WHERE owner_id=?

    ",

    $owner_id

);





$total_bookings = getOwnerCount(

    $conn,

    "

    SELECT COUNT(b.booking_id) total

    FROM bookings b

    LEFT JOIN hotels h

    ON b.hotel_id=h.hotel_id

    WHERE h.owner_id=?

    ",

    $owner_id

);





$active_guests = getOwnerCount(

    $conn,

    "

    SELECT COUNT(b.booking_id) total

    FROM bookings b

    LEFT JOIN hotels h

    ON b.hotel_id=h.hotel_id

    WHERE h.owner_id=?

    AND b.booking_status='Confirmed'

    ",

    $owner_id

);









/*
Revenue Query
*/


$earnings_stmt = mysqli_prepare(

    $conn,

    "

    SELECT SUM(b.total_amount) total

    FROM bookings b

    LEFT JOIN hotels h

    ON b.hotel_id=h.hotel_id

    WHERE h.owner_id=?

    AND b.booking_status IN

    ('Confirmed','Checked Out')

    "

);



mysqli_stmt_bind_param(

    $earnings_stmt,

    "i",

    $owner_id

);



mysqli_stmt_execute(

    $earnings_stmt

);



$earnings_result =

mysqli_stmt_get_result(

    $earnings_stmt

);



$total_earnings =

mysqli_fetch_assoc(

    $earnings_result

)['total'] ?? 0;









/*
===========================================
6. SEARCH & FILTER
===========================================
*/


$search =

trim(

    $_GET['search'] ?? ''

);



$status_filter =

trim(

    $_GET['status'] ?? ''

);





$where = [];

$params = [];

$types = "i";






$params[] = $owner_id;





if($search !== ''){


    $where[] = "

    (

    b.booking_code LIKE ?

    OR h.hotel_name LIKE ?

    OR u.full_name LIKE ?

    )

    ";



    $keyword = "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;



    $types.="sss";


}







if($status_filter !== ''){


    $where[] =

    "b.booking_status=?";



    $params[]=$status_filter;


    $types.="s";


}








$where_sql = "";



if(count($where)>0){


    $where_sql =

    " AND ".implode(

        " AND ",

        $where

    );


}









/*
===========================================
7. PAGINATION
===========================================
*/


$limit = 20;


$page =

intval(

    $_GET['page'] ?? 1

);



if($page < 1){

    $page=1;

}



$offset =

($page-1)*$limit;









/*
===========================================
8. FETCH BOOKINGS
===========================================
*/


$sql =

"

SELECT


b.*,

h.hotel_name,

u.full_name customer_name,

u.email customer_email



FROM bookings b



LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id



LEFT JOIN users u

ON b.customer_id=u.user_id



WHERE h.owner_id=?


$where_sql


ORDER BY b.created_at DESC


LIMIT ? OFFSET ?


";



$stmt = mysqli_prepare(

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



$bookings_result =

mysqli_stmt_get_result($stmt);









/*
Notification Count
*/


$total_notifications = 0;


$noti_result = mysqli_query(

$conn,

"

SELECT COUNT(*) total

FROM notifications

WHERE user_id='$owner_id'

AND is_read=0

"

);



if($noti_result){


$total_notifications =

mysqli_fetch_assoc(

    $noti_result

)['total'] ?? 0;


}



?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Owner Dashboard | Hotel Partner Hub</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">


<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">


<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">


<style>


body{

font-family:'Poppins',sans-serif;

background:#f4f6f9;

color:#333;

}



.wrapper{

display:flex;

min-height:100vh;

}




.sidebar{

width:260px;

background:#0f172a;

color:white;

position:fixed;

top:0;

bottom:0;

left:0;

overflow-y:auto;

}



.brand{

padding:20px;

font-size:19px;

font-weight:700;

color:#38bdf8;

border-bottom:1px solid #1e293b;

display:flex;

align-items:center;

gap:10px;

}



.sidebar ul{

list-style:none;

padding:10px 0;

margin:0;

}



.sidebar ul li a{

display:flex;

align-items:center;

gap:12px;

padding:12px 20px;

color:#94a3b8;

text-decoration:none;

font-size:14px;

}



.sidebar ul li a:hover,
.sidebar ul li.active a{

background:#1e293b;

color:#38bdf8;

border-left:4px solid #38bdf8;

}





.main-content{

margin-left:260px;

width:calc(100% - 260px);

padding:25px 30px;

}





.topbar{

background:white;

padding:18px 25px;

border-radius:12px;

box-shadow:0 2px 12px rgba(0,0,0,.05);

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:25px;

}




.card-box{

background:white;

padding:22px;

border-radius:12px;

box-shadow:0 2px 10px rgba(0,0,0,.03);

margin-bottom:25px;

border:1px solid #eef2f6;

}




.stat-card{

border-radius:14px;

padding:22px;

background:white;

box-shadow:0 3px 10px rgba(0,0,0,.04);

height:100%;

}



.stat-icon{

width:50px;

height:50px;

border-radius:50%;

display:flex;

align-items:center;

justify-content:center;

font-size:22px;

}





.table td{

vertical-align:middle;

}




@media(max-width:768px){


.sidebar{

position:relative;

width:100%;

}



.wrapper{

display:block;

}



.main-content{

margin-left:0;

width:100%;

padding:15px;

}



.topbar{

flex-direction:column;

gap:15px;

}



}



</style>
<link href="../assets/css/owner.css" rel="stylesheet">

</head>



<body>



<div class="wrapper">





<?php include '../includes/owner_sidebar.php'; ?>










<main class="main-content">







<!-- TOP BAR -->

<header class="topbar">


<div>


<h4 class="fw-bold mb-1">


<i class="fa-solid fa-chart-line text-primary me-2"></i>


Owner Dashboard


</h4>


<small class="text-muted">

Monitor hotels, bookings and revenue performance


</small>


</div>







<div>


<a href="notifications.php"

class="btn btn-light position-relative rounded-circle">


<i class="fa-solid fa-bell"></i>





<?php if($total_notifications>0): ?>


<span class="badge bg-danger position-absolute top-0 start-100 translate-middle">


<?=$total_notifications?>


</span>


<?php endif; ?>



</a>


</div>



</header>









<?php if(isset($_GET['msg']) && $_GET['msg']=="updated"): ?>


<div class="alert alert-success">


<i class="fa-solid fa-circle-check"></i>


Booking status updated successfully.


</div>


<?php endif; ?>






<?php if(!empty($error)): ?>


<div class="alert alert-danger">


<i class="fa-solid fa-triangle-exclamation"></i>


<?=htmlspecialchars($error)?>


</div>


<?php endif; ?>









<!-- STATISTICS CARDS -->


<div class="row g-4 mb-4">





<div class="col-md-3">


<div class="stat-card">


<div class="d-flex align-items-center gap-3">


<div class="stat-icon bg-primary-subtle text-primary">


<i class="fa-solid fa-hotel"></i>


</div>


<div>


<h6 class="text-muted mb-1">

Hotels

</h6>


<h3 class="fw-bold mb-0">

<?=$total_hotels?>

</h3>


</div>


</div>


</div>


</div>









<div class="col-md-3">


<div class="stat-card">


<div class="d-flex align-items-center gap-3">


<div class="stat-icon bg-success-subtle text-success">


<i class="fa-solid fa-calendar-check"></i>


</div>


<div>


<h6 class="text-muted mb-1">

Bookings

</h6>


<h3 class="fw-bold mb-0">

<?=$total_bookings?>

</h3>


</div>


</div>


</div>


</div>









<div class="col-md-3">


<div class="stat-card">


<div class="d-flex align-items-center gap-3">


<div class="stat-icon bg-warning-subtle text-warning">


<i class="fa-solid fa-user-check"></i>


</div>


<div>


<h6 class="text-muted mb-1">

Active Guests

</h6>


<h3 class="fw-bold mb-0">

<?=$active_guests?>

</h3>


</div>


</div>


</div>


</div>









<div class="col-md-3">


<div class="stat-card">


<div class="d-flex align-items-center gap-3">


<div class="stat-icon bg-info-subtle text-info">


<i class="fa-solid fa-wallet"></i>


</div>


<div>


<h6 class="text-muted mb-1">

Revenue

</h6>


<h3 class="fw-bold mb-0">


<?=number_format($total_earnings,2)?>

</h3>


</div>


</div>


</div>


</div>




</div>









<!-- SEARCH FILTER -->


<div class="card-box">


<form method="GET"

class="row g-3">





<div class="col-md-6">


<input type="text"

name="search"

class="form-control"

placeholder="Search booking code, hotel, customer..."

value="<?=htmlspecialchars($search)?>">


</div>







<div class="col-md-3">


<select name="status"

class="form-select">


<option value="">

All Status

</option>



<option value="Pending"

<?=$status_filter=="Pending"?'selected':''?>>

Pending

</option>




<option value="Confirmed"

<?=$status_filter=="Confirmed"?'selected':''?>>

Confirmed

</option>




<option value="Checked Out"

<?=$status_filter=="Checked Out"?'selected':''?>>

Checked Out

</option>




<option value="Cancelled"

<?=$status_filter=="Cancelled"?'selected':''?>>

Cancelled

</option>



</select>


</div>








<div class="col-md-3">


<button class="btn btn-primary w-100">


<i class="fa-solid fa-filter"></i>

Filter


</button>


</div>



</form>


</div>









<!-- BOOKINGS TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>


Recent Bookings


</h5>







<div class="table-responsive">


<table class="table table-hover align-middle">


<thead class="table-light">


<tr>


<th>Code</th>

<th>Hotel</th>

<th>Customer</th>

<th>Date</th>

<th>Amount</th>

<th>Status</th>

<th>Action</th>


</tr>


</thead>





<tbody>


<?php if($bookings_result && mysqli_num_rows($bookings_result)>0): ?>



<?php while($b=mysqli_fetch_assoc($bookings_result)): ?>



<tr>


<td>


<strong class="text-primary">

<?=htmlspecialchars($b['booking_code'])?>

</strong>


</td>




<td>


<?=htmlspecialchars($b['hotel_name'] ?? '-')?>

</td>




<td>


<?=htmlspecialchars($b['customer_name'] ?? '-')?>


<br>


<small class="text-muted">

<?=htmlspecialchars($b['customer_email'] ?? '-')?>

</small>


</td>





<td>


<?=

!empty($b['check_in'])

?

date("d M Y",strtotime($b['check_in']))

:

"N/A"

?>


</td>





<td>


<strong class="text-success">


<?=number_format($b['total_amount'] ?? 0,2)?>

MMK


</strong>


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



<option value="Pending"

<?=$b['booking_status']=="Pending"?'selected':''?>>

Pending

</option>



<option value="Confirmed"

<?=$b['booking_status']=="Confirmed"?'selected':''?>>

Confirm

</option>



<option value="Checked Out"

<?=$b['booking_status']=="Checked Out"?'selected':''?>>

Check Out

</option>



<option value="Cancelled"

<?=$b['booking_status']=="Cancelled"?'selected':''?>>

Cancel

</option>



</select>


</form>



</td>


</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="7"

class="text-center text-muted py-4">


No booking records found.


</td>


</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>
<!-- ===========================
     DASHBOARD SUMMARY
=========================== -->


<div class="row g-4 mb-4">



<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-building fa-2x text-primary mb-3"></i>


<h6 class="text-muted">

Total Hotels

</h6>


<h3 class="fw-bold">

<?=number_format($total_hotels)?>

</h3>


</div>


</div>







<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-calendar-days fa-2x text-success mb-3"></i>


<h6 class="text-muted">

Total Reservations

</h6>


<h3 class="fw-bold">

<?=number_format($total_bookings)?>

</h3>


</div>


</div>







<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-money-bill-wave fa-2x text-warning mb-3"></i>


<h6 class="text-muted">

Total Revenue

</h6>


<h3 class="fw-bold">

<?=number_format($total_earnings,2)?>

MMK

</h3>


</div>


</div>



</div>









<!-- ===========================
     PAGINATION
=========================== -->


<?php if($total_pages > 1): ?>


<nav class="mt-4">


<ul class="pagination justify-content-center">





<li class="page-item <?=($page<=1)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


Previous


</a>


</li>









<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>









<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


Next


</a>


</li>





</ul>


</nav>


<?php endif; ?>









<!-- ===========================
     FOOTER
=========================== -->


<footer class="text-center text-muted py-4">


<small>


© <?=date('Y')?> Hotel Booking System V3 |


Hotel Partner Management Dashboard


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



<script>


/*
================================
AUTO HIDE ALERT MESSAGE
================================
*/


setTimeout(function(){


let alerts = document.querySelectorAll('.alert');


alerts.forEach(function(alert){


alert.style.display='none';


});


},4000);



</script>





</body>

</html>