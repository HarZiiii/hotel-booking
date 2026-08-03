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
3. SAFE QUERY COUNT FUNCTION
===========================================
*/


function getCount($conn,$sql){

    $result = mysqli_query($conn,$sql);


    if($result){

        $data = mysqli_fetch_assoc($result);

        return $data['total'] ?? 0;

    }


    return 0;

}







/*
===========================================
4. HOTEL APPROVAL / REJECTION
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['action_hotel'])
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





    $hotel_id = intval($_POST['hotel_id'] ?? 0);


    $status = $_POST['status_update'] ?? '';



    $allowed_status = [

        'approved',
        'rejected'

    ];





    if(
        $hotel_id > 0 &&
        in_array($status,$allowed_status)
    ){



        /*
        ===========================
        UPDATE HOTEL STATUS
        ===========================
        */


        $update_sql = "

        UPDATE hotels

        SET status = ?

        WHERE hotel_id = ?

        ";



        $stmt = mysqli_prepare(
            $conn,
            $update_sql
        );



        mysqli_stmt_bind_param(

            $stmt,

            "si",

            $status,

            $hotel_id

        );



        mysqli_stmt_execute($stmt);







        /*
        ===========================
        GET HOTEL INFORMATION
        ===========================
        */


        $hotel_stmt = mysqli_prepare(

            $conn,

            "

            SELECT hotel_name,owner_id

            FROM hotels

            WHERE hotel_id=?

            "

        );



        mysqli_stmt_bind_param(

            $hotel_stmt,

            "i",

            $hotel_id

        );



        mysqli_stmt_execute($hotel_stmt);



        $hotel_result =
        mysqli_stmt_get_result(
            $hotel_stmt
        );



        $hotel =
        mysqli_fetch_assoc(
            $hotel_result
        );







        if($hotel){



            if($status==="approved"){


                $message =
                "Congratulations! Your hotel '".$hotel['hotel_name']."' has been approved.";


            }else{


                $message =
                "Sorry! Your hotel '".$hotel['hotel_name']."' has been rejected. Please review your information.";


            }




            $title =
            "Hotel Review Completed";




            /*
            ===========================
            INSERT NOTIFICATION
            ===========================
            */


            $notification_sql = "

            INSERT INTO notifications

            (
                user_id,
                title,
                message,
                notification_type
            )

            VALUES
            (?,?,?,'System')

            ";



            $notification_stmt =
            mysqli_prepare(
                $conn,
                $notification_sql
            );



            mysqli_stmt_bind_param(

                $notification_stmt,

                "iss",

                $hotel['owner_id'],

                $title,

                $message

            );



            mysqli_stmt_execute(
                $notification_stmt
            );








            /*
            ===========================
            AUDIT LOG
            ===========================
            */


            $ip =
            $_SERVER['REMOTE_ADDR']
            ??
            '127.0.0.1';



            $agent =
            $_SERVER['HTTP_USER_AGENT']
            ??
            'Unknown';



            $action =
            "HOTEL_".strtoupper($status);



            $table =
            "hotels";





            $audit_sql = "

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

            ";



            $audit_stmt =
            mysqli_prepare(
                $conn,
                $audit_sql
            );



            mysqli_stmt_bind_param(

                $audit_stmt,

                "ississ",

                $admin_id,

                $action,

                $table,

                $hotel_id,

                $ip,

                $agent

            );



            mysqli_stmt_execute(
                $audit_stmt
            );



        }




        header(
            "Location: dashboard.php"
        );

        exit();



    }



}









/*
===========================================
5. DASHBOARD KPI COUNTERS
===========================================
*/


$total_users =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM users
    "
);



$total_customers =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM users
    WHERE role='customer'
    "
);



$total_owners =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM users
    WHERE role='owner'
    "
);



$total_hotels =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM hotels
    "
);



$pending_hotels =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM hotels
    WHERE status='pending'
    "
);



$approved_hotels =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM hotels
    WHERE status='approved'
    "
);



$total_rooms =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM rooms
    "
);



$total_bookings =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM bookings
    "
);



$today_bookings =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM bookings
    WHERE DATE(created_at)=CURDATE()
    "
);





/*
===========================================
6. REVENUE & PAYMENT
===========================================
*/


$revenue_query = mysqli_query(

$conn,

"
SELECT IFNULL(SUM(total_amount),0) total

FROM bookings

WHERE booking_status IN
(
'Completed',
'Checked Out'
)

"

);



$total_revenue =
$revenue_query
?
(mysqli_fetch_assoc($revenue_query)['total'] ?? 0)
:
0;





$payment_query = mysqli_query(

$conn,

"
SELECT IFNULL(SUM(amount),0) total

FROM payments

WHERE payment_status='Paid'

"

);



$total_payments =
$payment_query
?
(mysqli_fetch_assoc($payment_query)['total'] ?? 0)
:
0;






$total_reviews =
getCount(
    $conn,
    "
    SELECT COUNT(*) total
    FROM reviews
    "
);



$total_notifications =
getCount(
$conn,
"
SELECT COUNT(*) total

FROM notifications

WHERE user_id=$admin_id

AND is_read=0
"
);







/*
===========================================
7. RECENT DATA FETCH
===========================================
*/


$recentBookings = mysqli_query(

$conn,

"

SELECT

b.booking_code,

b.booking_id,

b.total_amount,

b.booking_status,

b.check_in,

b.check_out,


u.full_name,


h.hotel_name


FROM bookings b


LEFT JOIN users u

ON b.customer_id=u.user_id



LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id



ORDER BY b.booking_id DESC


LIMIT 5

"

);





$recentPayments = mysqli_query(

$conn,

"

SELECT

p.payment_id,

p.amount,

p.payment_status,

p.created_at,


u.full_name,


b.booking_code,


b.booking_id


FROM payments p


LEFT JOIN bookings b

ON p.booking_id=b.booking_id



LEFT JOIN users u

ON b.customer_id=u.user_id



ORDER BY p.payment_id DESC


LIMIT 5

"

);





$pendingHotels = mysqli_query(

$conn,

"

SELECT

h.*,

u.full_name AS owner_name


FROM hotels h


LEFT JOIN users u

ON h.owner_id=u.user_id


WHERE h.status='pending'


ORDER BY h.created_at DESC


LIMIT 5

"

);





$latestUsers = mysqli_query(

$conn,

"

SELECT

user_id,

full_name,

email,

role,

status,

created_at


FROM users


ORDER BY created_at DESC


LIMIT 5

"

);





$recentReviews = mysqli_query(

$conn,

"

SELECT

r.rating,

r.comment,

r.created_at,


u.full_name,


h.hotel_name


FROM reviews r


LEFT JOIN users u

ON r.customer_id=u.user_id


LEFT JOIN hotels h

ON r.hotel_id=h.hotel_id



ORDER BY r.review_id DESC


LIMIT 5

"

);





$recentNotifications = mysqli_query(

$conn,

"

SELECT *

FROM notifications

ORDER BY notification_id DESC

LIMIT 5

"

);





$recentLogs = mysqli_query(

$conn,

"

SELECT

a.*,

u.full_name


FROM audit_logs a


LEFT JOIN users u

ON a.user_id=u.user_id


ORDER BY a.log_id DESC


LIMIT 5

"

);


?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Admin Dashboard | Hotel Booking System V3</title>

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



/* SIDEBAR */

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

display:block;

padding:12px 20px;

color:#94a3b8;

text-decoration:none;

}



.sidebar a:hover,
.sidebar .active a{

background:#0f172a;

color:#38bdf8;

border-left:4px solid #38bdf8;

}




/* MAIN */

.main-content{

margin-left:260px;

width:calc(100% - 260px);

padding:25px;

}



.topbar{

background:white;

padding:20px;

border-radius:12px;

box-shadow:0 3px 10px rgba(0,0,0,.05);

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:25px;

}



.card-box,
.kpi-card,
.action-card{

background:white;

border-radius:12px;

padding:20px;

box-shadow:0 3px 10px rgba(0,0,0,.04);

border:1px solid #eef2f6;

}




.kpi-card{

display:flex;

align-items:center;

gap:15px;

}



.kpi-icon{

width:50px;

height:50px;

border-radius:10px;

display:flex;

align-items:center;

justify-content:center;

color:white;

font-size:22px;

}



.action-card{

text-decoration:none;

color:#334155;

display:flex;

align-items:center;

gap:10px;

font-weight:500;

}



.action-card:hover{

color:#0284c7;

transform:translateY(-2px);

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


<li class="active">

<a href="dashboard.php">

<i class="fa-solid fa-chart-line"></i>
Dashboard

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



<!-- TOPBAR -->


<div class="topbar">


<div>

<h4 class="fw-bold mb-1">

<i class="fa-solid fa-chart-pie text-primary"></i>

Dashboard

</h4>


<small class="text-muted">

Welcome,

<?=htmlspecialchars($_SESSION['full_name'] ?? 'Administrator')?>


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







<!-- QUICK ACTIONS -->


<div class="row g-3 mb-4">


<div class="col-md-2">

<a href="users.php"
class="action-card">

<i class="fa-solid fa-users text-primary"></i>

Users

</a>

</div>



<div class="col-md-2">

<a href="hotels.php"
class="action-card">

<i class="fa-solid fa-hotel text-success"></i>

Hotels

</a>

</div>



<div class="col-md-2">

<a href="bookings.php"
class="action-card">

<i class="fa-solid fa-calendar text-warning"></i>

Bookings

</a>

</div>



<div class="col-md-2">

<a href="payments.php"
class="action-card">

<i class="fa-solid fa-credit-card text-danger"></i>

Payments

</a>

</div>



<div class="col-md-2">

<a href="manage_commissions.php"
class="action-card">

<i class="fa-solid fa-chart-column"></i>

Commissions

</a>

</div>



<div class="col-md-2">

<a href="audit_logs.php"
class="action-card">

<i class="fa-solid fa-clock"></i>

Logs

</a>

</div>


</div>







<!-- KPI CARDS -->


<div class="row g-3 mb-4">


<div class="col-md-3">

<div class="kpi-card">


<div class="kpi-icon bg-primary">

<i class="fa fa-users"></i>

</div>


<div>

<small>Total Users</small>

<h3>

<?=number_format($total_users)?>

</h3>

</div>


</div>

</div>





<div class="col-md-3">

<div class="kpi-card">


<div class="kpi-icon bg-success">

<i class="fa fa-hotel"></i>

</div>


<div>

<small>Approved Hotels</small>

<h3>

<?=number_format($approved_hotels)?>

</h3>

</div>


</div>

</div>






<div class="col-md-3">

<div class="kpi-card">


<div class="kpi-icon bg-warning">

<i class="fa fa-hourglass"></i>

</div>


<div>

<small>Pending Hotels</small>

<h3>

<?=number_format($pending_hotels)?>

</h3>

</div>


</div>

</div>






<div class="col-md-3">

<div class="kpi-card">


<div class="kpi-icon bg-danger">

<i class="fa fa-calendar"></i>

</div>


<div>

<small>Total Bookings</small>

<h3>

<?=number_format($total_bookings)?>

</h3>

</div>


</div>

</div>


</div>







<!-- HOTEL APPROVAL -->


<div class="card-box mb-4">


<h5 class="fw-bold mb-3">

<i class="fa-solid fa-hotel text-warning"></i>

Pending Hotel Approvals

</h5>



<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">

<tr>

<th>ID</th>

<th>Hotel</th>

<th>Owner</th>

<th>City</th>

<th>Action</th>


</tr>


</thead>


<tbody>



<?php while($hotel=mysqli_fetch_assoc($pendingHotels)): ?>


<tr>


<td>#<?=$hotel['hotel_id']?></td>


<td>

<?=htmlspecialchars($hotel['hotel_name'])?>

</td>


<td>

<?=htmlspecialchars($hotel['owner_name'] ?? '-')?>

</td>


<td>

<?=htmlspecialchars($hotel['city'] ?? '-')?>

</td>



<td>


<form method="POST"
onsubmit="return confirm('Confirm hotel status change?');">


<input type="hidden"
name="csrf_token"
value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"
name="hotel_id"
value="<?=$hotel['hotel_id']?>">


<input type="hidden"
name="action_hotel"
value="1">



<button class="btn btn-success btn-sm"
name="status_update"
value="approved">

Approve

</button>



<button class="btn btn-danger btn-sm"
name="status_update"
value="rejected">

Reject

</button>


</form>


</td>


</tr>


<?php endwhile; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     RECENT BOOKINGS
=========================== -->

<div class="card-box mb-4">


<div class="d-flex justify-content-between align-items-center mb-3">

<h5 class="fw-bold m-0">

<i class="fa-solid fa-calendar-check text-primary"></i>

Latest Bookings

</h5>


<a href="bookings.php"
class="btn btn-sm btn-outline-primary">

View All

</a>


</div>




<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">

<tr>

<th>Code</th>

<th>Customer</th>

<th>Hotel</th>

<th>Check In</th>

<th>Check Out</th>

<th>Amount</th>

<th>Status</th>

</tr>

</thead>



<tbody>


<?php if($recentBookings && mysqli_num_rows($recentBookings)>0): ?>


<?php while($b=mysqli_fetch_assoc($recentBookings)): ?>


<tr>


<td>

<code>

<?=htmlspecialchars($b['booking_code'] ?? '-')?>

</code>

</td>



<td>

<?=htmlspecialchars($b['full_name'] ?? 'Unknown')?>

</td>



<td>

<?=htmlspecialchars($b['hotel_name'] ?? 'Unknown')?>

</td>



<td>

<?=

!empty($b['check_in'])

?

date(
"d M Y",
strtotime($b['check_in'])
)

:

"N/A"

?>

</td>



<td>

<?=

!empty($b['check_out'])

?

date(
"d M Y",
strtotime($b['check_out'])
)

:

"N/A"

?>

</td>



<td>

<strong>

<?=number_format(
$b['total_amount'] ?? 0,
2
)?> MMK

</strong>

</td>



<td>


<?php

$status =
strtolower(
$b['booking_status'] ?? ''
);


if($status=="confirmed"){

$badge="success";

}

elseif($status=="pending"){

$badge="warning";

}

elseif($status=="cancelled"){

$badge="danger";

}

else{

$badge="secondary";

}


?>


<span class="badge bg-<?=$badge?>">

<?=ucfirst($status)?>

</span>


</td>



</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="7"
class="text-center text-muted">

No booking records found.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>








<!-- ===========================
     RECENT PAYMENTS
=========================== -->


<div class="row g-3 mb-4">



<div class="col-md-6">


<div class="card-box h-100">


<h5 class="fw-bold mb-3">

<i class="fa-solid fa-credit-card text-success"></i>

Recent Payments

</h5>




<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">

<tr>

<th>Booking</th>

<th>Amount</th>

<th>Status</th>

</tr>


</thead>



<tbody>



<?php if($recentPayments && mysqli_num_rows($recentPayments)>0): ?>


<?php while($p=mysqli_fetch_assoc($recentPayments)): ?>


<tr>


<td>

#<?=htmlspecialchars($p['booking_id'] ?? '-')?>

<br>

<small>

<?=

!empty($p['created_at'])

?

date(
"d M Y",
strtotime($p['created_at'])
)

:

''

?>

</small>


</td>



<td>

<strong>

<?=number_format(
$p['amount'] ?? 0,
2
)?> MMK

</strong>


</td>



<td>


<?php

$payment_status =
strtolower(
$p['payment_status'] ?? ''
);


if($payment_status=="paid"){

    $payment_badge="success";

}
elseif($payment_status=="pending"){

    $payment_badge="warning";

}
elseif($payment_status=="failed"){

    $payment_badge="danger";

}
elseif($payment_status=="refunded"){

    $payment_badge="secondary";

}
else{

    $payment_badge="dark";

}


?>


<span class="badge bg-<?=$payment_badge?>">

<?=ucfirst($payment_status)?>

</span>


</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="3"
class="text-center text-muted">

No payment data.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>



</div>


</div>









<!-- ===========================
     LATEST USERS
=========================== -->


<div class="col-md-6">


<div class="card-box h-100">


<h5 class="fw-bold mb-3">

<i class="fa-solid fa-users text-primary"></i>

Latest Users

</h5>



<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">

<tr>

<th>Name</th>

<th>Email</th>

<th>Role</th>

</tr>


</thead>



<tbody>


<?php if($latestUsers && mysqli_num_rows($latestUsers)>0): ?>


<?php while($u=mysqli_fetch_assoc($latestUsers)): ?>


<tr>


<td>

<?=htmlspecialchars($u['full_name'])?>

</td>



<td>

<?=htmlspecialchars($u['email'])?>

</td>



<td>


<span class="badge bg-primary">

<?=ucfirst($u['role'])?>

</span>


</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="3"
class="text-center text-muted">

No users found.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>










<!-- ===========================
     REVIEWS & AUDIT LOGS
=========================== -->


<div class="row g-3">



<div class="col-md-6">


<div class="card-box">


<h5 class="fw-bold mb-3">

<i class="fa-solid fa-star text-warning"></i>

Latest Reviews

</h5>



<?php if($recentReviews && mysqli_num_rows($recentReviews)>0): ?>


<?php while($r=mysqli_fetch_assoc($recentReviews)): ?>


<div class="border-bottom pb-2 mb-2">


<strong>

<?=htmlspecialchars(
$r['full_name'] ?? 'User'
)?>

</strong>


<br>


<small>

<?=htmlspecialchars(
$r['hotel_name'] ?? ''
)?>

</small>



<div class="text-warning">

<?php

for(
$i=1;
$i<=5;
$i++
){

echo

($i <= ($r['rating'] ?? 0))

?

"★"

:

"☆";

}

?>

</div>


<p class="small text-muted mb-0">

<?=htmlspecialchars(
$r['comment'] ?? ''
)?>

</p>


</div>


<?php endwhile; ?>


<?php else: ?>


<p class="text-muted">

No reviews available.

</p>


<?php endif; ?>


</div>


</div>







<div class="col-md-6">


<div class="card-box">


<h5 class="fw-bold mb-3">

<i class="fa-solid fa-clock-rotate-left"></i>

Recent Audit Logs

</h5>



<div class="table-responsive">


<table class="table table-sm">


<thead class="table-light">

<tr>

<th>User</th>

<th>Action</th>

<th>Date</th>

</tr>


</thead>



<tbody>


<?php if($recentLogs && mysqli_num_rows($recentLogs)>0): ?>


<?php while($log=mysqli_fetch_assoc($recentLogs)): ?>


<tr>


<td>

<?=htmlspecialchars(
$log['full_name'] ?? 'System'
)?>

</td>



<td>

<span class="badge bg-dark">

<?=htmlspecialchars(
$log['action']
)?>

</span>


</td>



<td>

<?=

!empty($log['created_at'])

?

date(
"d M Y",
strtotime($log['created_at'])
)

:

''

?>

</td>


</tr>


<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="3"
class="text-center text-muted">

No logs found.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>







<footer class="text-center text-muted py-4">


<small>

© <?=date('Y')?> Hotel Booking System V3 |

Secure Admin Management Terminal

</small>


</footer>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>