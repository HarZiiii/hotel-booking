<?php

require_once '../config/config.php';


/*
===========================================
1. SESSION START & ADMIN AUTHENTICATION
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
2. CSRF TOKEN GENERATION
===========================================
*/


if(empty($_SESSION['csrf_token'])){

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}




/*
===========================================
3. UPDATE BOOKING STATUS
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_booking_status'])
){


    $booking_id = intval($_POST['booking_id'] ?? 0);

    $status = trim($_POST['booking_status'] ?? '');



    $allowed_statuses = [

        'Pending',
        'Confirmed',
        'Checked In',
        'Checked Out',
        'Completed',
        'Cancelled',
        'Expired'

    ];




    if(
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ){

        die("Invalid CSRF Token");

    }





    if(
        $booking_id > 0 &&
        in_array($status,$allowed_statuses)
    ){



        /*
        ===========================
        UPDATE BOOKING
        ===========================
        */


        $update_sql = "

        UPDATE bookings

        SET booking_status = ?

        WHERE booking_id = ?

        ";



        $update_stmt = mysqli_prepare(
            $conn,
            $update_sql
        );



        mysqli_stmt_bind_param(

            $update_stmt,

            "si",

            $status,

            $booking_id

        );



        mysqli_stmt_execute($update_stmt);






        /*
        ===========================
        AUDIT LOG INSERT
        ===========================
        */


        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';




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
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )

        ";




        $action = "BOOKING_STATUS_UPDATED";

        $table = "bookings";



        $audit_stmt = mysqli_prepare(
            $conn,
            $audit_sql
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



        mysqli_stmt_execute($audit_stmt);




        header(
            "Location: bookings.php?msg=status_updated"
        );

        exit();



    }



}







/*
===========================================
4. SEARCH & FILTER
===========================================
*/


$search = trim($_GET['search'] ?? '');

$status_filter = trim($_GET['status'] ?? '');



$where = [];

$params = [];

$types = "";




if($search !== ''){


    $where[] = "

    (
        b.booking_code LIKE ?

        OR u.full_name LIKE ?

        OR u.email LIKE ?

        OR h.hotel_name LIKE ?

    )

    ";


    $keyword = "%".$search."%";


    for($i=0;$i<4;$i++){

        $params[] = $keyword;

    }


    $types .= "ssss";


}





if($status_filter !== ''){


    $where[] = "b.booking_status = ?";


    $params[] = $status_filter;


    $types .= "s";


}





$where_sql = "";


if(count($where)>0){

    $where_sql = "WHERE ".implode(
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


$page = intval($_GET['page'] ?? 1);


if($page < 1){

    $page = 1;

}


$offset = ($page-1)*$limit;







/*
===========================================
6. TOTAL BOOKING COUNT
===========================================
*/


$count_sql = "

SELECT COUNT(*) AS total

FROM bookings b

LEFT JOIN users u

ON b.customer_id=u.user_id


LEFT JOIN hotels h

ON b.hotel_id=h.hotel_id


$where_sql

";



$count_stmt = mysqli_prepare(
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



mysqli_stmt_execute($count_stmt);


$count_result = mysqli_stmt_get_result(
    $count_stmt
);



$total_records = mysqli_fetch_assoc(
    $count_result
)['total'] ?? 0;



$total_pages = ceil(
    $total_records/$limit
);








/*
===========================================
7. FETCH BOOKINGS
===========================================
*/


$sql = "

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





$stmt = mysqli_prepare(
    $conn,
    $sql
);



$params[] = $limit;

$params[] = $offset;


$types .= "ii";




mysqli_stmt_bind_param(

    $stmt,

    $types,

    ...$params

);



mysqli_stmt_execute($stmt);



$bookings_query = mysqli_stmt_get_result(
    $stmt
);







/*
===========================================
8. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;


$notification_query = mysqli_query(

    $conn,

    "
    SELECT COUNT(*) AS total

    FROM notifications

    WHERE is_read=0

    "

);



if($notification_query){


    $notification_data =
    mysqli_fetch_assoc(
        $notification_query
    );


    $total_notifications =
    $notification_data['total'] ?? 0;


}


?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Booking Management | HBS V3 Admin</title>

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

height:100vh;

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

display:flex;

justify-content:space-between;

align-items:center;

box-shadow:0 3px 10px rgba(0,0,0,.05);

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


<link rel="stylesheet" href="../assets/css/admin-sidebar.css?v=2">
</head>



<body>


<div class="wrapper">



<!-- SIDEBAR -->

<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main-content">



<!-- TOP BAR -->

<div class="topbar">


<div>

<h4 class="fw-bold mb-1">

<i class="fa-solid fa-calendar-check text-primary"></i>

Booking Management

</h4>


<small class="text-muted">

Manage customer reservations

</small>


</div>




<div>


<a href="notifications.php"
class="btn btn-light rounded-circle position-relative">


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

<i class="fa fa-check-circle"></i>

Booking status updated successfully.

</div>


<?php endif; ?>








<!-- SEARCH -->


<div class="card-box">


<form method="GET" class="row g-3">


<div class="col-md-6">


<input type="text"
name="search"
class="form-control"
placeholder="Search booking, customer, hotel..."
value="<?=htmlspecialchars($search)?>">


</div>





<div class="col-md-4">


<select name="status"
class="form-select">


<option value="">

All Status

</option>



<?php

$status_list=[

'Pending',
'Confirmed',
'Checked In',
'Checked Out',
'Completed',
'Cancelled',
'Expired'

];


foreach($status_list as $st):

?>


<option value="<?=$st?>"
<?=$status_filter==$st?'selected':''?>>

<?=$st?>

</option>


<?php endforeach; ?>


</select>


</div>





<div class="col-md-2">


<button class="btn btn-primary w-100">

<i class="fa fa-filter"></i>

Filter

</button>


</div>


</form>


</div>








<!-- BOOKING TABLE -->


<div class="card-box">


<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">


<tr>

<th>Code</th>

<th>Customer</th>

<th>Hotel</th>

<th>Date</th>

<th>Amount</th>

<th>Payment</th>

<th>Status</th>


</tr>


</thead>




<tbody>



<?php if(mysqli_num_rows($bookings_query)>0): ?>



<?php while($b=mysqli_fetch_assoc($bookings_query)): ?>



<tr>



<td>

<span class="badge bg-dark">

<?=htmlspecialchars($b['booking_code'])?>

</span>


<br>

<small>

<?=

!empty($b['created_at'])

?

date('M d,Y',strtotime($b['created_at']))

:

'N/A'

?>

</small>


</td>





<td>


<strong>

<?=htmlspecialchars($b['customer_name'] ?? 'Unknown')?>

</strong>


<br>


<small>

<?=htmlspecialchars($b['customer_email'] ?? '-')?>

</small>


<br>


<small>

<?=htmlspecialchars($b['customer_phone'] ?? '-')?>

</small>


</td>







<td>


<strong>

<?=htmlspecialchars($b['hotel_name'] ?? '-')?>

</strong>


<br>


<small>

<?=htmlspecialchars($b['city'] ?? '-')?>

</small>


</td>







<td>


<strong class="text-success">

IN:

</strong>


<?=

!empty($b['check_in'])

?

date('M d,Y',strtotime($b['check_in']))

:

'N/A'

?>


<br>



<strong class="text-danger">

OUT:

</strong>


<?=

!empty($b['check_out'])

?

date('M d,Y',strtotime($b['check_out']))

:

'N/A'

?>


</td>







<td>


<strong class="text-primary">

<?=number_format($b['total_amount'] ?? 0,2)?>

MMK

</strong>


<br>


<small>

<?=$b['rooms_booked'] ?? 0?> Room(s)

</small>


</td>







<td>


<?php

$p_status=$b['payment_status'] ?? 'Pending';


switch($p_status){

case 'Paid':

$p_badge="bg-success";

break;


case 'Failed':

$p_badge="bg-danger";

break;


case 'Refunded':

$p_badge="bg-warning text-dark";

break;


default:

$p_badge="bg-secondary";

}


?>


<span class="badge <?=$p_badge?>">

<?=$p_status?>

</span>


<br>


<small>

<?=htmlspecialchars($b['payment_method'] ?? '-')?>

</small>


</td>







<td>


<form method="POST"
onsubmit="return confirm('Change booking status?');">


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



<?php foreach($status_list as $st): ?>


<option value="<?=$st?>"
<?=$b['booking_status']==$st?'selected':''?>>

<?=$st?>

</option>


<?php endforeach; ?>



</select>


</form>


</td>



</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="7"
class="text-center text-muted">

No bookings found.

</td>

</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>








<!-- PAGINATION -->


<nav>


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







<footer class="text-center text-muted">

© <?=date('Y')?> Hotel Booking System V3

</footer>



</main>


</div>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>