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


function insertOwnerAuditLog(

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



    $booking_status =
    trim(
        $_POST['booking_status'] ?? ''
    );






    $allowed_status = [

        'Pending',
        'Confirmed',
        'Checked In',
        'Checked Out',
        'Cancelled',
        'Completed'

        ];







    if(
        $booking_id > 0
        &&
        in_array(
            $booking_status,
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


            b.customer_id,

            b.booking_code,

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



        mysqli_stmt_execute($check_stmt);



        $check_result =
        mysqli_stmt_get_result(
            $check_stmt
        );



        $booking =
        mysqli_fetch_assoc(
            $check_result
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

                $booking_status,

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
                "Booking Status Updated";



                $message =

                "Your booking "

                .

                ($booking['booking_code'] ?? '')

                .

                " at "

                .

                ($booking['hotel_name'] ?? 'hotel')

                .

                " is now "

                .

                $booking_status;






                $notify_stmt = mysqli_prepare(

                    $conn,

                    "

                    INSERT INTO notifications

                    (

                    user_id,

                    title,

                    message,

                    notification_type

                    )


                    VALUES

                    (?,?,?,'Booking')

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


            insertOwnerAuditLog(

                $conn,

                $owner_id,

                "BOOKING_STATUS_UPDATED",

                "bookings",

                $booking_id

            );







            header(
                "Location: bookings.php?msg=status_updated"
            );

            exit();



        }


    }


}









/*
===========================================
5. SEARCH FILTER
===========================================
*/


$search =
trim(
    $_GET['search'] ?? ''
);





$where = [];

$params = [];

$types = "";







if($search !== ''){


    $where[] = "

    (

    b.booking_code LIKE ?

    OR u.full_name LIKE ?

    OR u.email LIKE ?

    )

    ";



    $keyword =
    "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;



    $types .= "sss";


}






$where_sql = "";



if(count($where)>0){


    $where_sql =

    "AND ".implode(

        " AND ",

        $where

    );


}









/*
===========================================
6. PAGINATION
===========================================
*/


$limit = 20;


$page =
intval(
    $_GET['page'] ?? 1
);



if($page < 1){

    $page = 1;

}



$offset =
($page-1)*$limit;









/*
===========================================
7. COUNT BOOKINGS
===========================================
*/


$count_sql = "

SELECT COUNT(*) total


FROM bookings b



LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id



LEFT JOIN users u

ON b.customer_id=u.user_id



WHERE h.owner_id=?


$where_sql


";




$count_stmt =
mysqli_prepare(

    $conn,

    $count_sql

);



$count_params = [];

$count_types = "i";


$count_params[] = $owner_id;





foreach($params as $p){

    $count_params[]=$p;

    $count_types.="s";

}





mysqli_stmt_bind_param(

    $count_stmt,

    $count_types,

    ...$count_params

);



mysqli_stmt_execute($count_stmt);



$count_result =
mysqli_stmt_get_result($count_stmt);



$total_records =
mysqli_fetch_assoc(
    $count_result
)['total'] ?? 0;



$total_pages =
ceil(
    $total_records/$limit
);









/*
===========================================
8. FETCH BOOKINGS
===========================================
*/


$sql = "

SELECT


b.*,


h.hotel_name,


u.full_name AS customer_name,


u.email AS customer_email



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




$stmt =
mysqli_prepare(

    $conn,

    $sql

);




$final_params = [];

$final_types = "i";



$final_params[]=$owner_id;




foreach($params as $p){

    $final_params[]=$p;

    $final_types.="s";

}



$final_params[]=$limit;

$final_params[]=$offset;


$final_types.="ii";




mysqli_stmt_bind_param(

    $stmt,

    $final_types,

    ...$final_params

);




mysqli_stmt_execute($stmt);



$bookings_query =
mysqli_stmt_get_result($stmt);









/*
===========================================
9. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;



$notification_stmt = mysqli_prepare(

$conn,

"

SELECT COUNT(*) total

FROM notifications

WHERE user_id=?

AND is_read=0

"

);


mysqli_stmt_bind_param(

$notification_stmt,

"i",

$owner_id

);


mysqli_stmt_execute($notification_stmt);


$notification_result =
mysqli_stmt_get_result($notification_stmt);



$data=mysqli_fetch_assoc($notification_result);


$total_notifications=$data['total'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Guest Bookings | Hotel Partner Hub</title>

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

box-shadow:0 2px 12px rgba(0,0,0,.04);

margin-bottom:25px;

display:flex;

justify-content:space-between;

align-items:center;

}





.card-box{

background:white;

border-radius:12px;

padding:22px;

box-shadow:0 2px 10px rgba(0,0,0,.03);

border:1px solid #eef2f6;

margin-bottom:25px;

}




.table td{

vertical-align:middle;

}



.status-badge{

font-size:12px;

padding:6px 10px;

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









<!-- ===========================
     MAIN CONTENT
=========================== -->


<main class="main-content">






<!-- TOP BAR -->


<header class="topbar">


<div>


<h4 class="fw-bold mb-1">


<i class="fa-solid fa-calendar-check text-primary me-2"></i>


Guest Reservations


</h4>



<small class="text-muted">

Track and manage customer bookings across your hotels

</small>



</div>






<div>


<a href="notifications.php"

class="btn btn-light position-relative rounded-circle">


<i class="fa-solid fa-bell"></i>




<?php if($total_notifications > 0): ?>


<span class="badge bg-danger position-absolute top-0 start-100 translate-middle">


<?=$total_notifications?>


</span>


<?php endif; ?>



</a>


</div>



</header>









<?php if(isset($_GET['msg']) && $_GET['msg']=="status_updated"): ?>


<div class="alert alert-success">


<i class="fa-solid fa-circle-check"></i>


Booking status updated successfully.


</div>


<?php endif; ?>









<!-- SEARCH -->


<div class="card-box">


<form method="GET"

action="bookings.php"

class="row g-3">





<div class="col-md-9">


<div class="input-group">


<span class="input-group-text bg-light">


<i class="fa-solid fa-magnifying-glass"></i>


</span>



<input type="text"

name="search"

class="form-control"

placeholder="Search booking code, customer name, email..."

value="<?=htmlspecialchars($search)?>">


</div>


</div>







<div class="col-md-3">


<button type="submit"

class="btn btn-primary w-100">


<i class="fa-solid fa-filter me-1"></i>


Search Records


</button>


</div>



</form>


</div>









<!-- BOOKING TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>


Guest Booking Records


</h5>







<div class="table-responsive">


<table class="table table-hover align-middle">



<thead class="table-light">


<tr>


<th>Code</th>

<th>Hotel</th>

<th>Customer</th>

<th>Stay Period</th>

<th>Total Bill</th>

<th>Status</th>

<th>Action</th>


</tr>


</thead>






<tbody>



<?php if($bookings_query && mysqli_num_rows($bookings_query)>0): ?>



<?php while($b=mysqli_fetch_assoc($bookings_query)): ?>



<tr>




<td>


<strong class="text-primary font-monospace">


<?=htmlspecialchars($b['booking_code'])?>


</strong>


</td>







<td>


<strong>


<?=htmlspecialchars($b['hotel_name'] ?? 'Unknown')?>


</strong>


</td>







<td>


<?=htmlspecialchars($b['customer_name'] ?? 'Unknown')?>


<br>


<small class="text-muted">


<?=htmlspecialchars($b['customer_email'] ?? '-')?>


</small>


</td>








<td>


<small>


<?=

!empty($b['check_in'])

?

date("d M Y",strtotime($b['check_in']))

:

"N/A"

?>


<br>


to


<br>


<?=

!empty($b['check_out'])

?

date("d M Y",strtotime($b['check_out']))

:

"N/A"

?>



</small>


</td>







<td>


<strong class="text-success">


<?=number_format($b['total_amount'] ?? 0,2)?>


MMK


</strong>


</td>








<td>



<?php


$status =

$b['booking_status'] ?? 'Pending';



if($status=="Confirmed"){

$badge="success";

}

elseif($status=="Cancelled"){

$badge="danger";

}

elseif($status=="Completed"){

$badge="primary";

}

elseif($status=="Checked In"){

$badge="info";

}

elseif($status=="Checked Out"){

$badge="secondary";

}

else{

$badge="warning";

}



?>



<span class="badge bg-<?=$badge?> status-badge">


<?=$status?>


</span>



</td>









<td>



<form method="POST"

onsubmit="return confirm('Update booking status?');">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="booking_id"

value="<?=$b['booking_id']?>">



<input type="hidden"

name="update_booking_status"

value="1">






<select name="booking_status"

class="form-select form-select-sm"

onchange="this.form.submit()">



<option value="Pending"

<?=$status=="Pending"?'selected':''?>>

Pending

</option>





<option value="Confirmed"

<?=$status=="Confirmed"?'selected':''?>>

Confirmed

</option>



<option value="Checked In"

<?=$status=="Checked In"?'selected':''?>>

Checked In

</option>

<option value="Checked Out"

<?=$status=="Checked Out"?'selected':''?>>

Checked Out

</option>


<option value="Completed"

<?=$status=="Completed"?'selected':''?>>

Completed

</option>





<option value="Cancelled"

<?=$status=="Cancelled"?'selected':''?>>

Cancelled

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
     BOOKING SUMMARY
=========================== -->


<div class="row g-3 mb-4">



<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-calendar-check fa-2x text-primary mb-2"></i>


<h6 class="text-muted">

Total Bookings

</h6>


<h3 class="fw-bold">

<?=number_format($total_records)?>

</h3>


</div>


</div>







<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-file-lines fa-2x text-success mb-2"></i>


<h6 class="text-muted">

Total Pages

</h6>


<h3 class="fw-bold">

<?=number_format($total_pages)?>

</h3>


</div>


</div>







<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-list fa-2x text-warning mb-2"></i>


<h6 class="text-muted">

Current Page

</h6>


<h3 class="fw-bold">

<?=$page?>

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

href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>








<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>">


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


Hotel Partner Booking Management System


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>