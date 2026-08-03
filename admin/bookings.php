<?php

require_once '../config/config.php';


if(session_status() === PHP_SESSION_NONE){

    session_start();

}



/*
===========================================
ADMIN AUTH
===========================================
*/


if(

    !isset($_SESSION['user_id'])

    ||

    !isset($_SESSION['role'])

    ||

    $_SESSION['role'] !== 'admin'

){

    header("Location: ../login.php");

    exit();

}



$admin_id=$_SESSION['user_id'];





/*
===========================================
CSRF TOKEN
===========================================
*/


if(empty($_SESSION['csrf_token'])){

    $_SESSION['csrf_token']

    =

    bin2hex(random_bytes(32));

}







/*
===========================================
UPDATE BOOKING STATUS
===========================================
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





    $booking_id=intval(

        $_POST['booking_id'] ?? 0

    );



    $new_status=trim(

        $_POST['booking_status'] ?? ''

    );





    $allowed_statuses=[


        'Pending',

        'Confirmed',

        'Checked In',

        'Checked Out',

        'Completed',

        'Cancelled',

        'Expired'


    ];





    if(

        $booking_id>0

        &&

        in_array(

            $new_status,

            $allowed_statuses

        )

    ){



        /*
        ==========================
        GET OLD BOOKING DATA
        ==========================
        */


        $get_stmt=mysqli_prepare(

            $conn,

            "

            SELECT

            booking_status,

            customer_id,

            booking_code


            FROM bookings


            WHERE booking_id=?


            "

        );



        mysqli_stmt_bind_param(

            $get_stmt,

            "i",

            $booking_id

        );



        mysqli_stmt_execute($get_stmt);



        $result=mysqli_stmt_get_result(

            $get_stmt

        );



        $booking=mysqli_fetch_assoc(

            $result

        );






        if($booking){



            /*
            ==========================
            UPDATE BOOKING STATUS
            ==========================
            */


            $update_stmt=mysqli_prepare(

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
            ==========================
            CUSTOMER NOTIFICATION
            ==========================
            */


            if(

                $booking['customer_id']

            ){



                $title = "Booking Status Updated";



                $message =

                "Your booking "

                .

                $booking['booking_code']

                .

                " status has been changed to "

                .

                $new_status

                .

                ".";





                $notify_stmt=mysqli_prepare(

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
            ==========================
            AUDIT LOG
            ==========================
            */


            $ip_address=$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';


            $user_agent=$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';



            $action=

            "BOOKING_STATUS_UPDATED : "

            .

            $booking['booking_status']

            .

            " -> "

            .

            $new_status;





            $audit_stmt=mysqli_prepare(

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




            /*
            ==========================
            AUDIT LOG
            ==========================
            */


            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';


            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';



            $action =

            "BOOKING_STATUS_UPDATED : "

            .

            $booking['booking_status']

            .

            " -> "

            .

            $new_status;




            $table = "bookings";




            $audit_stmt=mysqli_prepare(

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

                $audit_stmt,

                "ississ",

                $admin_id,

                $action,

                $table,

                $booking_id,

                $ip_address,

                $user_agent

            );





            mysqli_stmt_execute(

                $audit_stmt

            );





            mysqli_stmt_execute(

                $audit_stmt

            );



        }





        header(

            "Location: bookings.php?msg=status_updated"

        );


        exit();



    }


}








/*
===========================================
SEARCH & FILTER
===========================================
*/


$search = trim(

    $_GET['search'] ?? ''

);



$status_filter = trim(

    $_GET['status'] ?? ''

);





$where=[];


$params=[];


$types="";





if($search!=""){



    $where[]="

    (

    b.booking_code LIKE ?

    OR u.full_name LIKE ?

    OR u.email LIKE ?

    OR h.hotel_name LIKE ?

    )

    ";



    $keyword="%".$search."%";



    for($i=0;$i<4;$i++){

        $params[]=$keyword;

    }



    $types.="ssss";


}





if($status_filter!=""){



    $where[]="b.booking_status=?";



    $params[]=$status_filter;



    $types.="s";



}







$where_sql="";



if(count($where)>0){


    $where_sql=

    "WHERE "

    .

    implode(

        " AND ",

        $where

    );


}








/*
===========================================
PAGINATION
===========================================
*/


$limit=20;



$page=intval(

    $_GET['page'] ?? 1

);



if($page<1){

    $page=1;

}



$offset=

($page-1)*$limit;







$count_stmt=mysqli_prepare(

    $conn,

    "

    SELECT COUNT(*) total


    FROM bookings b



    LEFT JOIN users u

    ON b.customer_id=u.user_id



    LEFT JOIN hotels h

    ON b.hotel_id=h.hotel_id



    $where_sql


    "

);






if(!empty($params)){



    mysqli_stmt_bind_param(

        $count_stmt,

        $types,

        ...$params

    );


}





mysqli_stmt_execute(

    $count_stmt

);





$count_result=mysqli_stmt_get_result(

    $count_stmt

);





$total_records=

mysqli_fetch_assoc(

    $count_result

)['total'] ?? 0;





$total_pages=

ceil(

    $total_records/$limit

);








/*
===========================================
FETCH BOOKINGS
===========================================
*/


$sql="


SELECT


b.*,



u.full_name AS customer_name,

u.email AS customer_email,

u.phone AS customer_phone,



h.hotel_name,

h.city,



p.payment_status,

p.payment_method



FROM bookings b



LEFT JOIN users u

ON b.customer_id=u.user_id



LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id



LEFT JOIN payments p

ON b.booking_id=p.booking_id



$where_sql



ORDER BY b.booking_id DESC



LIMIT ?

OFFSET ?



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





mysqli_stmt_execute(

    $stmt

);





$bookings_query=mysqli_stmt_get_result(

    $stmt

);





?>
<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<title>

Booking Management | HBS V3 Admin

</title>



<meta name="viewport"

content="width=device-width, initial-scale=1.0">



<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"

rel="stylesheet">



<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"

rel="stylesheet">



<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"

rel="stylesheet">



<style>


*{

box-sizing:border-box;

}



body{

margin:0;

font-family:'Poppins',sans-serif;

background:#f1f5f9;

}



.wrapper{

display:flex;

min-height:100vh;

}





.sidebar{

width:260px;

background:#1e293b;

position:fixed;

top:0;

bottom:0;

left:0;

color:white;

}



.brand{

padding:22px;

font-size:20px;

font-weight:700;

color:#38bdf8;

border-bottom:1px solid #334155;

}



.sidebar ul{

list-style:none;

padding:0;

}



.sidebar li a{

display:flex;

gap:12px;

align-items:center;

padding:13px 20px;

color:#94a3b8;

text-decoration:none;

}



.sidebar li a:hover,

.sidebar li.active a{

background:#0f172a;

color:#38bdf8;

border-left:4px solid #38bdf8;

}





.main-content{

margin-left:260px;

padding:30px;

width:calc(100% - 260px);

}





.card-box{

background:white;

padding:25px;

border-radius:18px;

box-shadow:0 4px 15px rgba(0,0,0,.05);

}





.booking-code{

font-weight:600;

color:#2563eb;

}



</style>


</head>






<body>






<div class="wrapper">





<!-- =====================
SIDEBAR
===================== -->


<aside class="sidebar">



<div class="brand">

<i class="fa-solid fa-hotel"></i>

HBS V3 Admin

</div>





<ul>



<li>

<a href="dashboard.php">

<i class="fa-solid fa-chart-line"></i>

Dashboard

</a>

</li>




<li>

<a href="manage_commissions.php">

<i class="fa-solid fa-money-bill-transfer"></i>

Commission

</a>

</li>




<li>

<a href="users.php">

<i class="fa-solid fa-users"></i>

Users

</a>

</li>





<li>

<a href="owners.php">

<i class="fa-solid fa-user-tie"></i>

Hotel Owners

</a>

</li>





<li>

<a href="hotels.php">

<i class="fa-solid fa-hotel"></i>

Hotels

</a>

</li>





<li>

<a href="rooms.php">

<i class="fa-solid fa-bed"></i>

Rooms

</a>

</li>





<li class="active">

<a href="bookings.php">

<i class="fa-solid fa-calendar-check"></i>

Bookings

</a>

</li>





<li>

<a href="payments.php">

<i class="fa-solid fa-credit-card"></i>

Payments

</a>

</li>





<li>

<a href="reviews.php">

<i class="fa-solid fa-star"></i>

Reviews

</a>

</li>





<li>

<a href="notifications.php">

<i class="fa-solid fa-bell"></i>

Notifications

</a>

</li>





<li>

<a href="audit_logs.php">

<i class="fa-solid fa-clock-rotate-left"></i>

Audit Logs

</a>

</li>





<li>

<a href="../logout.php">

<i class="fa-solid fa-right-from-bracket"></i>

Logout

</a>

</li>



</ul>


</aside>









<main class="main-content">






<h3 class="fw-bold mb-4">


<i class="fa-solid fa-calendar-check text-primary"></i>


Booking Management


</h3>








<?php if(isset($_GET['msg']) && $_GET['msg']=="status_updated"): ?>


<div class="alert alert-success">

Booking status updated successfully.

</div>


<?php endif; ?>









<!-- SEARCH FILTER -->


<div class="card-box mb-4">



<form method="GET">



<div class="row g-3">



<div class="col-md-5">


<input type="text"

name="search"

class="form-control"

placeholder="Search booking code, customer, hotel"

value="<?=htmlspecialchars($search)?>">


</div>







<div class="col-md-4">


<select name="status"

class="form-select">


<option value="">

All Status

</option>



<?php foreach([

'Pending',

'Confirmed',

'Checked In',

'Checked Out',

'Completed',

'Cancelled',

'Expired'

] as $status): ?>


<option value="<?=$status?>"

<?=$status_filter==$status?'selected':''?>>

<?=$status?>

</option>


<?php endforeach; ?>



</select>


</div>






<div class="col-md-3">


<button class="btn btn-primary w-100">

<i class="fa-solid fa-search"></i>

Search

</button>


</div>





</div>


</form>


</div>









<!-- TABLE -->


<div class="card-box">



<div class="table-responsive">



<table class="table table-hover align-middle">



<thead class="table-light">


<tr>


<th>

Code

</th>


<th>

Customer

</th>


<th>

Hotel

</th>


<th>

Date

</th>


<th>

Rooms

</th>


<th>

Amount

</th>


<th>

Payment

</th>


<th>

Status

</th>


<th>

Action

</th>



</tr>


</thead>







<tbody>






<?php if(mysqli_num_rows($bookings_query)>0): ?>





<?php while($b=mysqli_fetch_assoc($bookings_query)): ?>





<tr>



<td>


<span class="booking-code">

<?=$b['booking_code']?>

</span>


</td>





<td>


<strong>

<?=htmlspecialchars($b['customer_name'] ?? 'Unknown')?>

</strong>


<br>

<small>

<?=htmlspecialchars($b['customer_email'] ?? '')?>

</small>


</td>







<td>


<?=htmlspecialchars($b['hotel_name'] ?? '-')?>

<br>

<small>

<?=htmlspecialchars($b['city'] ?? '')?>

</small>


</td>








<td>


<?=$b['check_in']?>

<br>

to

<br>

<?=$b['check_out']?>


</td>








<td>


<?=$b['rooms_booked']?>


</td>







<td>


<strong class="text-success">

<?=number_format($b['total_amount'],2)?>

MMK

</strong>


</td>







<td>


<?php if($b['payment_status']=="Paid"): ?>


<span class="badge bg-success">

Paid

</span>


<?php elseif($b['payment_status']=="Failed"): ?>


<span class="badge bg-danger">

Failed

</span>


<?php elseif($b['payment_status']=="Refunded"): ?>


<span class="badge bg-secondary">

Refunded

</span>


<?php else: ?>


<span class="badge bg-warning text-dark">

Pending

</span>


<?php endif; ?>


</td>








<td>



<form method="POST">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="booking_id"

value="<?=$b['booking_id']?>">





<select name="booking_status"

class="form-select form-select-sm">


<?php foreach([

'Pending',

'Confirmed',

'Checked In',

'Checked Out',

'Completed',

'Cancelled',

'Expired'

] as $status): ?>


<option value="<?=$status?>"

<?=$b['booking_status']==$status?'selected':''?>>

<?=$status?>

</option>


<?php endforeach; ?>


</select>



</td>







<td>



<button class="btn btn-sm btn-primary"

name="update_booking_status">


Save


</button>


</form>



</td>





</tr>






<?php endwhile; ?>






<?php else: ?>


<tr>

<td colspan="9"

class="text-center py-5 text-muted">


No booking records found.


</td>

</tr>


<?php endif; ?>






</tbody>


</table>


</div>


</div>









<!-- PAGINATION -->


<?php if($total_pages>1): ?>


<nav class="mt-4">


<ul class="pagination justify-content-center">



<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=$page==$i?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">

<?=$i?>

</a>


</li>


<?php endfor; ?>



</ul>


</nav>


<?php endif; ?>







<footer class="text-center text-muted py-4">


<small>

© <?=date('Y')?> HBS V3 Admin

</small>


</footer>






</main>




</div>







<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>


</html>