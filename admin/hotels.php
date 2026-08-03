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
3. HOTEL STATUS UPDATE
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_hotel_status'])
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

    $status = $_POST['status'] ?? '';



    $allowed_status = [

        'approved',
        'rejected',
        'pending'

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



        $update_stmt = mysqli_prepare(
            $conn,
            $update_sql
        );



        mysqli_stmt_bind_param(

            $update_stmt,

            "si",

            $status,

            $hotel_id

        );



        mysqli_stmt_execute(
            $update_stmt
        );








        /*
        ===========================
        GET HOTEL OWNER DATA
        ===========================
        */


        $hotel_stmt = mysqli_prepare(

            $conn,

            "

            SELECT

            hotel_name,

            owner_id


            FROM hotels


            WHERE hotel_id = ?

            "

        );



        mysqli_stmt_bind_param(

            $hotel_stmt,

            "i",

            $hotel_id

        );



        mysqli_stmt_execute(
            $hotel_stmt
        );



        $hotel_result =
        mysqli_stmt_get_result(
            $hotel_stmt
        );



        $hotel =
        mysqli_fetch_assoc(
            $hotel_result
        );







        if($hotel){



            $title =
            "Hotel Status Updated";



            $message =

            "Your hotel '".$hotel['hotel_name']."' status has been updated to ".$status.".";







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
            INSERT AUDIT LOG
            ===========================
            */


            $action =

            "HOTEL_".strtoupper($status);



            $table_name =

            "hotels";



            $ip_address =

            $_SERVER['REMOTE_ADDR']
            ??
            '127.0.0.1';



            $user_agent =

            $_SERVER['HTTP_USER_AGENT']
            ??
            'Unknown';







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

                $table_name,

                $hotel_id,

                $ip_address,

                $user_agent

            );



            mysqli_stmt_execute(
                $audit_stmt
            );



        }




        header(
            "Location: hotels.php?msg=status_updated"
        );


        exit();



    }



}








/*
===========================================
4. SEARCH & FILTER
===========================================
*/


$search = trim(
    $_GET['search'] ?? ''
);


$status_filter = trim(
    $_GET['status'] ?? ''
);



$where = [];

$params = [];

$types = "";





if($search !== ''){


    $where[] = "

    (

    h.hotel_name LIKE ?

    OR h.city LIKE ?

    OR u.full_name LIKE ?

    )

    ";



    $keyword = "%".$search."%";



    $params[] = $keyword;

    $params[] = $keyword;

    $params[] = $keyword;


    $types .= "sss";

}





if($status_filter !== ''){


    $where[] = "h.status = ?";


    $params[] = $status_filter;


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


$page = intval(
    $_GET['page'] ?? 1
);



if($page < 1){

    $page = 1;

}


$offset =

($page-1) * $limit;








/*
===========================================
6. TOTAL HOTEL COUNT
===========================================
*/


$count_sql = "

SELECT COUNT(DISTINCT h.hotel_id) AS total


FROM hotels h


LEFT JOIN users u

ON h.owner_id=u.user_id


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
7. FETCH HOTELS
===========================================
*/


$sql = "

SELECT


h.*,


u.full_name AS owner_name,


u.email AS owner_email,


COUNT(r.room_id) AS total_rooms



FROM hotels h



LEFT JOIN users u

ON h.owner_id=u.user_id



LEFT JOIN rooms r

ON h.hotel_id=r.hotel_id



$where_sql



GROUP BY h.hotel_id



ORDER BY h.hotel_id DESC



LIMIT ? OFFSET ?


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



mysqli_stmt_execute(
    $stmt
);



$hotels_query =

mysqli_stmt_get_result(
    $stmt
);








/*
===========================================
8. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;



$notification_count = mysqli_query(

$conn,

"

SELECT COUNT(*) AS total

FROM notifications

WHERE is_read=0

"

);



if($notification_count){


$data =
mysqli_fetch_assoc(
    $notification_count
);


$total_notifications =
$data['total'] ?? 0;


}


?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Hotels Management | HBS V3 Admin</title>

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

font-size:14px;

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

background:#fff;

padding:18px 25px;

border-radius:12px;

box-shadow:0 3px 10px rgba(0,0,0,.05);

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:25px;

}



.card-box{

background:#fff;

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



<!-- TOPBAR -->


<header class="topbar">


<div>


<h4 class="fw-bold mb-1">

<i class="fa-solid fa-hotel text-success"></i>

Hotels Management

</h4>


<small class="text-muted">

Review and manage registered hotels

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







<?php if(isset($_GET['msg']) && $_GET['msg']=="status_updated"): ?>


<div class="alert alert-success">

<i class="fa-solid fa-circle-check"></i>

Hotel status updated successfully.

</div>


<?php endif; ?>








<!-- SEARCH FILTER -->


<div class="card-box">


<form method="GET"
class="row g-3">



<div class="col-md-5">


<input type="text"

name="search"

class="form-control"

placeholder="Search hotel, city, owner..."

value="<?=htmlspecialchars($search)?>">


</div>





<div class="col-md-4">


<select name="status"
class="form-select">


<option value="">

All Status

</option>



<option value="approved"
<?=$status_filter=="approved"?'selected':''?>>

Approved

</option>



<option value="pending"
<?=$status_filter=="pending"?'selected':''?>>

Pending

</option>



<option value="rejected"
<?=$status_filter=="rejected"?'selected':''?>>

Rejected

</option>



</select>


</div>





<div class="col-md-3">


<button class="btn btn-primary w-100">

<i class="fa fa-filter"></i>

Filter

</button>


</div>



</form>


</div>








<!-- HOTEL TABLE -->


<div class="card-box">


<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">


<tr>


<th>ID</th>

<th>Hotel</th>

<th>Owner</th>

<th>Location</th>

<th>Rooms</th>

<th>Status</th>

<th>Action</th>


</tr>


</thead>





<tbody>



<?php if($hotels_query && mysqli_num_rows($hotels_query)>0): ?>



<?php while($hotel=mysqli_fetch_assoc($hotels_query)): ?>


<tr>



<td>

#<?=$hotel['hotel_id']?>

</td>





<td>


<strong>

<?=htmlspecialchars($hotel['hotel_name'])?>

</strong>


<br>


<small class="text-warning">

⭐

<?=number_format($hotel['star_rating'] ?? 0,1)?>

</small>


</td>







<td>


<strong>

<?=htmlspecialchars($hotel['owner_name'] ?? 'Unknown')?>

</strong>


<br>


<small>

<?=htmlspecialchars($hotel['owner_email'] ?? '-')?>

</small>


</td>







<td>


<i class="fa-solid fa-location-dot text-danger"></i>


<?=htmlspecialchars($hotel['city'] ?? 'N/A')?>


</td>







<td>


<a href="rooms.php?hotel_id=<?=$hotel['hotel_id']?>"

class="badge bg-secondary text-decoration-none">


<i class="fa-solid fa-bed"></i>


<?=$hotel['total_rooms']?> Room(s)


</a>


</td>








<td>


<?php


$status=$hotel['status'];


if($status=="approved"){

$badge="success";

}

elseif($status=="rejected"){

$badge="danger";

}

else{

$badge="warning";

}



?>


<span class="badge bg-<?=$badge?> mb-2">


<?=ucfirst($status)?>

</span>






<form method="POST"

onsubmit="return confirm('Change hotel status?');">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="hotel_id"

value="<?=$hotel['hotel_id']?>">


<input type="hidden"

name="update_hotel_status"

value="1">



<select name="status"

class="form-select form-select-sm"

onchange="this.form.submit()">



<option value="approved"

<?=$status=="approved"?'selected':''?>>

Approved

</option>



<option value="pending"

<?=$status=="pending"?'selected':''?>>

Pending

</option>



<option value="rejected"

<?=$status=="rejected"?'selected':''?>>

Rejected

</option>



</select>


</form>



</td>








<td>


<a href="rooms.php?hotel_id=<?=$hotel['hotel_id']?>"

class="btn btn-sm btn-outline-primary">


<i class="fa-solid fa-bed"></i>

Rooms


</a>


</td>



</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>


<td colspan="7"

class="text-center text-muted py-4">


No hotels found.


</td>


</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
        <!-- ===========================
             PAGINATION
        ============================ -->

        <?php if($total_pages > 1): ?>

        <nav class="mt-4">

            <ul class="pagination justify-content-center">


                <!-- Previous Button -->

                <li class="page-item <?=($page <= 1)?'disabled':''?>">

                    <a class="page-link"
                    href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">

                        Previous

                    </a>

                </li>



                <!-- Page Numbers -->

                <?php for($i=1; $i <= $total_pages; $i++): ?>


                <li class="page-item <?=($page==$i)?'active':''?>">


                    <a class="page-link"

                    href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


                    <?=$i?>


                    </a>


                </li>


                <?php endfor; ?>




                <!-- Next Button -->

                <li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


                    <a class="page-link"

                    href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


                    Next


                    </a>


                </li>


            </ul>


        </nav>


        <?php endif; ?>








        <!-- FOOTER -->

        <footer class="text-center text-muted pt-3 pb-2">


            <small>

            © <?=date('Y')?> Hotel Booking System V3 |

            Secure Admin Management Terminal


            </small>


        </footer>





    </main>


</div>







<!-- BOOTSTRAP JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>