<?php

require_once '../config/config.php';


/*
===========================================
1. SESSION & ADMIN AUTHENTICATION
===========================================
*/

if(session_status() === PHP_SESSION_NONE){

    session_start();

}



if(
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
){

    header("Location: ../login.php");
    exit();

}



$admin_id = $_SESSION['user_id'];

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
3. UPDATE PAYMENT STATUS
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_payment_status'])
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





    $payment_id =
    intval(
        $_POST['payment_id'] ?? 0
    );



    $payment_status =
    trim(
        $_POST['payment_status'] ?? ''
    );





    $allowed_statuses = [

        'Pending',
        'Paid',
        'Failed',
        'Refunded'

    ];







    if(
        $payment_id > 0
        &&
        in_array(
            $payment_status,
            $allowed_statuses
        )
    ){



        /*
        ===========================
        GET PAYMENT DATA
        ===========================
        */


        $payment_stmt = mysqli_prepare(

            $conn,

            "

            SELECT

            p.amount,

            p.booking_id,

            b.customer_id,

            b.booking_code


            FROM payments p


            LEFT JOIN bookings b

            ON p.booking_id=b.booking_id


            WHERE p.payment_id=?


            "

        );



        mysqli_stmt_bind_param(

            $payment_stmt,

            "i",

            $payment_id

        );



        mysqli_stmt_execute(
            $payment_stmt
        );



        $payment_result =
        mysqli_stmt_get_result(
            $payment_stmt
        );



        $payment =
        mysqli_fetch_assoc(
            $payment_result
        );








        if($payment){



            /*
            ===========================
            UPDATE PAYMENT
            ===========================
            */


            if($payment_status === "Paid"){



                $update_stmt = mysqli_prepare(

                    $conn,

                    "

                    UPDATE payments

                    SET

                    payment_status=?,

                    paid_at=NOW()


                    WHERE payment_id=?


                    "

                );


            }

            else{



                $update_stmt = mysqli_prepare(

                    $conn,

                    "

                    UPDATE payments

                    SET

                    payment_status=?


                    WHERE payment_id=?


                    "

                );


            }






            mysqli_stmt_bind_param(

                $update_stmt,

                "si",

                $payment_status,

                $payment_id

            );



            mysqli_stmt_execute(
                $update_stmt
            );










            /*
            ===========================
            CUSTOMER NOTIFICATION
            ===========================
            */


            if(!empty($payment['customer_id'])){


                $title =
                "Payment Status Updated";



                $message =

                "Your payment for booking "

                .

                ($payment['booking_code'] ?? '')

                .

                " has been updated to "

                .

                $payment_status;



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

                    (?,?,?,'Payment')


                    "

                );



                mysqli_stmt_bind_param(

                    $notify_stmt,

                    "iss",

                    $payment['customer_id'],

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



            $action =
            "PAYMENT_STATUS_UPDATED";



            $table =
            "payments";



            $ip =
            $_SERVER['REMOTE_ADDR']
            ??
            '127.0.0.1';



            $agent =
            $_SERVER['HTTP_USER_AGENT']
            ??
            'Unknown';





            $audit_stmt = mysqli_prepare(

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

                $payment_id,

                $ip,

                $agent

            );



            mysqli_stmt_execute(
                $audit_stmt
            );



        }




        header(
            "Location: payments.php?msg=status_updated"
        );


        exit();


    }



}










/*
===========================================
4. SEARCH & FILTER
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



$method_filter =
trim(
    $_GET['method'] ?? ''
);





$where = [];

$params = [];

$types = "";







if($search !== ''){


    $where[] = "

    (

    p.transaction_id LIKE ?

    OR b.booking_code LIKE ?

    OR u.full_name LIKE ?

    OR u.email LIKE ?

    )

    ";



    $keyword =
    "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;


    $types .= "ssss";


}







if($status_filter !== ''){


    $where[] =
    "p.payment_status=?";


    $params[] =
    $status_filter;


    $types .= "s";


}







if($method_filter !== ''){


    $where[] =
    "p.payment_method=?";


    $params[] =
    $method_filter;


    $types .= "s";


}





$where_sql = "";



if(count($where)>0){


    $where_sql =
    "WHERE ".implode(
        " AND ",
        $where
    );


}










/*
===========================================
5. PAGINATION
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
6. COUNT PAYMENTS
===========================================
*/


$count_sql = "

SELECT COUNT(*) total


FROM payments p


LEFT JOIN bookings b

ON p.booking_id=b.booking_id


LEFT JOIN users u

ON b.customer_id=u.user_id


LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id



$where_sql


";



$count_stmt =
mysqli_prepare(
    $conn,
    $count_sql
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



$count_result =
mysqli_stmt_get_result(
    $count_stmt
);



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
7. FETCH PAYMENTS
===========================================
*/


$sql = "

SELECT


p.*,


b.booking_code,


b.total_amount AS booking_total,


u.full_name AS customer_name,


u.email AS customer_email,


h.hotel_name



FROM payments p



LEFT JOIN bookings b

ON p.booking_id=b.booking_id



LEFT JOIN users u

ON b.customer_id=u.user_id



LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id



$where_sql



ORDER BY p.payment_id DESC



LIMIT ? OFFSET ?


";



$stmt =
mysqli_prepare(
    $conn,
    $sql
);



$params[]=$limit;

$params[]=$offset;


$types .= "ii";



mysqli_stmt_bind_param(

    $stmt,

    $types,

    ...$params

);



mysqli_stmt_execute(
    $stmt
);



$payments_query =

mysqli_stmt_get_result(
    $stmt
);








/*
===========================================
8. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;



$notification_result = mysqli_query(

$conn,

"

SELECT COUNT(*) total

FROM notifications

WHERE is_read=0


"

);



if($notification_result){


$data =
mysqli_fetch_assoc(
    $notification_result
);


$total_notifications =
$data['total'] ?? 0;


}



?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Payment Management | HBS V3 Admin</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">


<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">


<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">



<style>


body{

font-family:'Poppins',sans-serif;

background:#f4f6f9;

}



.wrapper{

display:flex;

min-height:100vh;

}





.sidebar{

width:260px;

background:#1e293b;

color:white;

position:fixed;

top:0;

bottom:0;

overflow-y:auto;

}



.brand{

padding:20px;

font-size:20px;

font-weight:700;

color:#38bdf8;

border-bottom:1px solid #334155;

}



.sidebar a{

display:flex;

align-items:center;

gap:12px;

padding:12px 20px;

color:#94a3b8;

text-decoration:none;

font-size:14px;

}



.sidebar a:hover,
.sidebar .active a{

background:#0f172a;

color:#38bdf8;

border-left:4px solid #38bdf8;

}







.main-content{

margin-left:260px;

width:calc(100% - 260px);

padding:25px;

}



.topbar{

background:white;

padding:18px 25px;

border-radius:12px;

box-shadow:0 3px 10px rgba(0,0,0,.05);

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:25px;

}




.card-box{

background:white;

padding:22px;

border-radius:12px;

box-shadow:0 3px 10px rgba(0,0,0,.04);

margin-bottom:25px;

}



.table td{

vertical-align:middle;

}



@media(max-width:768px){


.sidebar{

position:relative;

width:100%;

height:auto;

}


.wrapper{

display:block;

}



.main-content{

margin-left:0;

width:100%;

}


.topbar{

flex-direction:column;

gap:15px;

}



}


</style>


</head>



<body>



<div class="wrapper">





<!-- SIDEBAR -->


<aside class="sidebar">


<div class="brand">

<i class="fa-solid fa-hotel"></i>

HBS V3 Admin

</div>




<ul class="list-unstyled">



<li>

<a href="dashboard.php">

<i class="fa-solid fa-chart-line"></i>

Dashboard

</a>

</li>




<li>

<a href="manage_commissions.php">

<i class="fa-solid fa-hand-holding-dollar"></i>

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




<li>

<a href="bookings.php">

<i class="fa-solid fa-calendar-check"></i>

Bookings

</a>

</li>




<li class="active">

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





<!-- TOP BAR -->


<div class="topbar">


<div>


<h4 class="fw-bold mb-1">


<i class="fa-solid fa-credit-card text-success"></i>

Payment Management


</h4>


<small class="text-muted">

Manage customer payment transactions

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



</div>









<?php if(isset($_GET['msg']) && $_GET['msg']=="status_updated"): ?>


<div class="alert alert-success">


<i class="fa-solid fa-circle-check"></i>


Payment status updated successfully.


</div>


<?php endif; ?>









<!-- FILTER -->


<div class="card-box">


<form method="GET"

class="row g-3">





<div class="col-md-4">


<input type="text"

name="search"

class="form-control"

placeholder="Search transaction, booking, customer..."

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



<option value="Paid"

<?=$status_filter=="Paid"?'selected':''?>>

Paid

</option>



<option value="Failed"

<?=$status_filter=="Failed"?'selected':''?>>

Failed

</option>



<option value="Refunded"

<?=$status_filter=="Refunded"?'selected':''?>>

Refunded

</option>


</select>


</div>








<div class="col-md-3">


<input type="text"

name="method"

class="form-control"

placeholder="Payment Method"

value="<?=htmlspecialchars($method_filter)?>">


</div>








<div class="col-md-2">


<button class="btn btn-primary w-100">


<i class="fa fa-filter"></i>


Filter


</button>


</div>



</form>


</div>









<!-- PAYMENT TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>

Payment Transactions


</h5>






<div class="table-responsive">


<table class="table table-hover">



<thead class="table-light">


<tr>


<th>ID</th>

<th>Customer</th>

<th>Booking</th>

<th>Hotel</th>

<th>Amount</th>

<th>Method</th>

<th>Status</th>

<th>Date</th>

<th>Action</th>


</tr>


</thead>





<tbody>



<?php if($payments_query && mysqli_num_rows($payments_query)>0): ?>


<?php while($p=mysqli_fetch_assoc($payments_query)): ?>


<tr>



<td>

#<?=$p['payment_id']?>

</td>






<td>


<strong>

<?=htmlspecialchars($p['customer_name'] ?? 'Unknown')?>

</strong>


<br>


<small>

<?=htmlspecialchars($p['customer_email'] ?? '-')?>

</small>


</td>







<td>


<code>

<?=htmlspecialchars($p['booking_code'] ?? '-')?>

</code>


</td>








<td>

<?=htmlspecialchars($p['hotel_name'] ?? '-')?>

</td>








<td>


<strong class="text-success">

<?=number_format($p['amount'] ?? 0,2)?>

MMK

</strong>


</td>








<td>


<?=htmlspecialchars($p['payment_method'] ?? '-')?>


</td>








<td>


<?php


$status = strtolower(
$p['payment_status'] ?? ''
);


if($status=="paid"){

$badge="success";

}

elseif($status=="pending"){

$badge="warning";

}

elseif($status=="failed"){

$badge="danger";

}

elseif($status=="refunded"){

$badge="info";

}

else{

$badge="secondary";

}


?>


<span class="badge bg-<?=$badge?>">


<?=ucfirst($status)?>


</span>



</td>








<td>


<?=

!empty($p['created_at'])

?

date(
"d M Y",
strtotime($p['created_at'])
)

:

"N/A"

?>


</td>







<td>



<form method="POST"

onsubmit="return confirm('Update payment status?');">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="payment_id"

value="<?=$p['payment_id']?>">



<input type="hidden"

name="update_payment_status"

value="1">





<select name="payment_status"

class="form-select form-select-sm"

onchange="this.form.submit()">



<option value="Pending"

<?=$p['payment_status']=="Pending"?'selected':''?>>

Pending

</option>



<option value="Paid"

<?=$p['payment_status']=="Paid"?'selected':''?>>

Paid

</option>



<option value="Failed"

<?=$p['payment_status']=="Failed"?'selected':''?>>

Failed

</option>



<option value="Refunded"

<?=$p['payment_status']=="Refunded"?'selected':''?>>

Refunded

</option>



</select>



</form>


</td>






</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="9"

class="text-center text-muted py-4">


No payment records found.


</td>

</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     PAYMENT SUMMARY
=========================== -->


<div class="row g-3 mb-4">



<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-money-bill-transfer fa-2x text-success mb-2"></i>


<h6 class="text-muted">

Current Payment Records

</h6>


<h3 class="fw-bold">

<?=number_format($total_records)?>

</h3>


</div>


</div>







<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-file-invoice-dollar fa-2x text-primary mb-2"></i>


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


<i class="fa-solid fa-clock fa-2x text-warning mb-2"></i>


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

href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>&method=<?=urlencode($method_filter)?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>&method=<?=urlencode($method_filter)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>








<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>&method=<?=urlencode($method_filter)?>">


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


Secure Payment Management System


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>




</body>

</html>