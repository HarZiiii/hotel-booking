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
3. UPDATE ROOM STATUS
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_room_status'])
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






    $room_id =
    intval(
        $_POST['room_id'] ?? 0
    );



    $status =
    trim(
        $_POST['status'] ?? ''
    );





    $allowed_status = [

        'available',
        'booked',
        'maintenance'

    ];








    if(
        $room_id > 0
        &&
        in_array(
            $status,
            $allowed_status
        )
    ){



        /*
        ===========================
        GET ROOM OWNER DATA
        ===========================
        */


        $room_stmt = mysqli_prepare(

            $conn,

            "

            SELECT


            r.room_number,

            r.hotel_id,


            h.hotel_name,


            h.owner_id



            FROM rooms r



            LEFT JOIN hotels h

            ON r.hotel_id=h.hotel_id



            WHERE r.room_id=?


            "

        );



        mysqli_stmt_bind_param(

            $room_stmt,

            "i",

            $room_id

        );



        mysqli_stmt_execute(
            $room_stmt
        );



        $room_result =
        mysqli_stmt_get_result(
            $room_stmt
        );



        $room =
        mysqli_fetch_assoc(
            $room_result
        );








        if($room){



            /*
            ===========================
            UPDATE ROOM STATUS
            ===========================
            */


            $update_stmt = mysqli_prepare(

                $conn,

                "

                UPDATE rooms

                SET status=?

                WHERE room_id=?


                "

            );



            mysqli_stmt_bind_param(

                $update_stmt,

                "si",

                $status,

                $room_id

            );



            mysqli_stmt_execute(
                $update_stmt
            );









            /*
            ===========================
            OWNER NOTIFICATION
            ===========================
            */


            if(!empty($room['owner_id'])){



                $title =
                "Room Status Updated";



                $message =

                "Room "

                .

                ($room['room_number'] ?? '')

                .

                " in "

                .

                ($room['hotel_name'] ?? 'hotel')

                .

                " status changed to "

                .

                $status;



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

                    (?,?,?,'Room')


                    "

                );



                mysqli_stmt_bind_param(

                    $notify_stmt,

                    "iss",

                    $room['owner_id'],

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
            "ROOM_STATUS_UPDATED";



            $table =
            "rooms";



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

                $room_id,

                $ip,

                $agent

            );



            mysqli_stmt_execute(
                $audit_stmt
            );



        }



        header(
            "Location: rooms.php?msg=status_updated"
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



$hotel_filter =
intval(
    $_GET['hotel_id'] ?? 0
);



$status_filter =
trim(
    $_GET['status'] ?? ''
);






$where = [];

$params = [];

$types = "";








if($search !== ''){


    $where[] = "

    (

    r.room_number LIKE ?

    OR r.room_type LIKE ?

    OR h.hotel_name LIKE ?

    )

    ";



    $keyword =
    "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;


    $types .= "sss";


}







if($hotel_filter > 0){


    $where[] =
    "r.hotel_id=?";


    $params[] =
    $hotel_filter;


    $types .= "i";


}







if($status_filter !== ''){


    $where[] =
    "r.status=?";


    $params[] =
    $status_filter;


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
6. COUNT ROOMS
===========================================
*/


$count_sql = "

SELECT COUNT(*) total


FROM rooms r



LEFT JOIN hotels h

ON r.hotel_id=h.hotel_id



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
7. FETCH ROOMS
===========================================
*/


$sql = "

SELECT


r.*,


h.hotel_name,


h.city



FROM rooms r



LEFT JOIN hotels h

ON r.hotel_id=h.hotel_id



$where_sql



ORDER BY r.room_id DESC



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



$rooms_query =
mysqli_stmt_get_result(
    $stmt
);









/*
===========================================
8. HOTEL LIST FILTER
===========================================
*/


$hotels_list =
mysqli_query(

$conn,

"

SELECT

hotel_id,

hotel_name


FROM hotels


ORDER BY hotel_name ASC


"

);









/*
===========================================
9. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;



$notification_result =
mysqli_query(

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

<title>Room Management | HBS V3 Admin</title>

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



.room-type{

font-size:12px;

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




<li class="active">

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





<!-- TOP BAR -->


<div class="topbar">


<div>


<h4 class="fw-bold mb-1">


<i class="fa-solid fa-bed text-info"></i>

Room Management


</h4>


<small class="text-muted">

Manage room availability and inventory

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


Room status updated successfully.


</div>


<?php endif; ?>









<!-- FILTER SECTION -->


<div class="card-box">


<form method="GET"

class="row g-3">





<div class="col-md-4">


<input type="text"

name="search"

class="form-control"

placeholder="Search room number, type, hotel..."

value="<?=htmlspecialchars($search)?>">


</div>







<div class="col-md-3">


<select name="hotel_id"

class="form-select">


<option value="0">

All Hotels

</option>



<?php if($hotels_list): ?>


<?php while($hotel=mysqli_fetch_assoc($hotels_list)): ?>


<option value="<?=$hotel['hotel_id']?>"

<?=$hotel_filter==$hotel['hotel_id']?'selected':''?>>



<?=htmlspecialchars($hotel['hotel_name'])?>



</option>


<?php endwhile; ?>


<?php endif; ?>


</select>


</div>







<div class="col-md-3">


<select name="status"

class="form-select">


<option value="">

All Status

</option>



<option value="available"

<?=$status_filter=="available"?'selected':''?>>

Available

</option>



<option value="booked"

<?=$status_filter=="booked"?'selected':''?>>

Booked

</option>



<option value="maintenance"

<?=$status_filter=="maintenance"?'selected':''?>>

Maintenance

</option>



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









<!-- ROOM TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>

Room Inventory


</h5>







<div class="table-responsive">


<table class="table table-hover align-middle">



<thead class="table-light">


<tr>


<th>ID</th>

<th>Hotel</th>

<th>Room</th>

<th>Price</th>

<th>Capacity</th>

<th>Status</th>


</tr>


</thead>




<tbody>



<?php if($rooms_query && mysqli_num_rows($rooms_query)>0): ?>


<?php while($room=mysqli_fetch_assoc($rooms_query)): ?>



<?php


$price =
$room['base_price']
??
$room['price_per_night']
??
$room['price']
??
0;



$status =
$room['status']
??
'available';



?>




<tr>



<td>

#<?=$room['room_id']?>

</td>







<td>


<strong>

<?=htmlspecialchars($room['hotel_name'] ?? 'Unassigned')?>

</strong>


<br>


<small class="text-muted">

<?=htmlspecialchars($room['city'] ?? '-')?>

</small>


</td>








<td>


<strong>

Room <?=htmlspecialchars($room['room_number'] ?? '-')?>

</strong>


<br>


<span class="badge bg-light text-dark border room-type">


<?=htmlspecialchars($room['room_type'] ?? 'Standard')?>

</span>


</td>








<td>


<span class="text-success fw-bold">


<?=number_format($price,2)?>


</span>


</td>







<td>


<i class="fa-solid fa-user"></i>


<?=

$room['max_occupancy']

??

$room['capacity']

??

2

?>


Persons


</td>







<td>



<form method="POST"

onsubmit="return confirm('Update room status?');">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="room_id"

value="<?=$room['room_id']?>">



<input type="hidden"

name="update_room_status"

value="1">






<select name="status"

class="form-select form-select-sm"

onchange="this.form.submit()">



<option value="available"

<?=$status=="available"?'selected':''?>>

Available

</option>




<option value="booked"

<?=$status=="booked"?'selected':''?>>

Booked

</option>





<option value="maintenance"

<?=$status=="maintenance"?'selected':''?>>

Maintenance

</option>



</select>


</form>



</td>




</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="6"

class="text-center text-muted py-4">


No rooms found.


</td>


</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     ROOM SUMMARY
=========================== -->


<div class="row g-3 mb-4">



<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-bed fa-2x text-info mb-2"></i>


<h6 class="text-muted">

Total Rooms

</h6>


<h3 class="fw-bold">

<?=number_format($total_records)?>

</h3>


</div>


</div>







<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-file-lines fa-2x text-primary mb-2"></i>


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


<i class="fa-solid fa-list-check fa-2x text-success mb-2"></i>


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

href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&hotel_id=<?=$hotel_filter?>&status=<?=urlencode($status_filter)?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&hotel_id=<?=$hotel_filter?>&status=<?=urlencode($status_filter)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>








<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&hotel_id=<?=$hotel_filter?>&status=<?=urlencode($status_filter)?>">


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


Secure Room Management System


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>