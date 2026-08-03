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
3. UPDATE REVIEW STATUS
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_review_status'])
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






    $review_id =
    intval(
        $_POST['review_id'] ?? 0
    );



    $review_status =
    trim(
        $_POST['review_status'] ?? ''
    );





    $allowed_statuses = [

        'Pending',
        'Approved',
        'Hidden'

    ];







    if(
        $review_id > 0
        &&
        in_array(
            $review_status,
            $allowed_statuses
        )
    ){



        /*
        ===========================
        GET REVIEW DATA
        ===========================
        */


        $review_stmt = mysqli_prepare(

            $conn,

            "

            SELECT

            r.customer_id,

            r.title,

            h.hotel_name


            FROM reviews r


            LEFT JOIN hotels h

            ON r.hotel_id=h.hotel_id


            WHERE r.review_id=?


            "

        );



        mysqli_stmt_bind_param(

            $review_stmt,

            "i",

            $review_id

        );



        mysqli_stmt_execute(
            $review_stmt
        );



        $review_result =
        mysqli_stmt_get_result(
            $review_stmt
        );



        $review =
        mysqli_fetch_assoc(
            $review_result
        );








        if($review){



            /*
            ===========================
            UPDATE REVIEW STATUS
            ===========================
            */


            $update_stmt = mysqli_prepare(

                $conn,

                "

                UPDATE reviews

                SET review_status=?

                WHERE review_id=?


                "

            );



            mysqli_stmt_bind_param(

                $update_stmt,

                "si",

                $review_status,

                $review_id

            );



            mysqli_stmt_execute(
                $update_stmt
            );









            /*
            ===========================
            CUSTOMER NOTIFICATION
            ===========================
            */


            if(!empty($review['customer_id'])){


                $title =
                "Review Status Updated";



                $message =

                "Your review for "

                .

                ($review['hotel_name'] ?? 'hotel')

                .

                " has been updated to "

                .

                $review_status;



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

                    (?,?,?,'Review')


                    "

                );



                mysqli_stmt_bind_param(

                    $notify_stmt,

                    "iss",

                    $review['customer_id'],

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
            "REVIEW_STATUS_".strtoupper($review_status);



            $table_name =
            "reviews";



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

                $table_name,

                $review_id,

                $ip,

                $agent

            );



            mysqli_stmt_execute(
                $audit_stmt
            );



        }




        header(
            "Location: reviews.php?msg=status_updated"
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



$rating_filter =
intval(
    $_GET['rating'] ?? 0
);






$where = [];

$params = [];

$types = "";







if($search !== ''){


    $where[] = "

    (

    r.title LIKE ?

    OR r.comment LIKE ?

    OR u.full_name LIKE ?

    OR h.hotel_name LIKE ?

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
    "r.review_status=?";


    $params[] =
    $status_filter;


    $types .= "s";


}







if($rating_filter > 0){


    $where[] =
    "r.rating=?";


    $params[] =
    $rating_filter;


    $types .= "i";


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
6. COUNT REVIEWS
===========================================
*/


$count_sql = "

SELECT COUNT(*) total


FROM reviews r


LEFT JOIN users u

ON r.customer_id=u.user_id



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
7. FETCH REVIEWS
===========================================
*/


$sql = "

SELECT


r.*,


u.full_name AS customer_name,


u.email AS customer_email,


h.hotel_name,


h.city,


b.booking_code



FROM reviews r



LEFT JOIN users u

ON r.customer_id=u.user_id



LEFT JOIN hotels h

ON r.hotel_id=h.hotel_id



LEFT JOIN bookings b

ON r.booking_id=b.booking_id



$where_sql



ORDER BY r.review_id DESC



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



$reviews_query =
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

<title>Review Management | HBS V3 Admin</title>

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




.review-comment{

max-width:350px;

white-space:normal;

}



.star{

color:#f59e0b;

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


<i class="fa-solid fa-star text-warning"></i>

Review Management


</h4>


<small class="text-muted">

Manage customer reviews and moderation

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


Review status updated successfully.


</div>


<?php endif; ?>









<!-- FILTER -->


<div class="card-box">


<form method="GET"

class="row g-3">






<div class="col-md-5">


<input type="text"

name="search"

class="form-control"

placeholder="Search review, customer, hotel..."

value="<?=htmlspecialchars($search)?>">


</div>








<div class="col-md-2">


<select name="rating"

class="form-select">


<option value="">

All Rating

</option>



<?php for($i=5;$i>=1;$i--): ?>


<option value="<?=$i?>"

<?=$rating_filter==$i?'selected':''?>>


<?=$i?> Star


</option>


<?php endfor; ?>



</select>


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




<option value="Approved"

<?=$status_filter=="Approved"?'selected':''?>>

Approved

</option>





<option value="Hidden"

<?=$status_filter=="Hidden"?'selected':''?>>

Hidden

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









<!-- REVIEW TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>

Customer Reviews


</h5>







<div class="table-responsive">


<table class="table table-hover">



<thead class="table-light">


<tr>


<th>ID</th>

<th>Customer</th>

<th>Hotel</th>

<th>Rating</th>

<th>Review</th>

<th>Booking</th>

<th>Status</th>

<th>Date</th>

<th>Action</th>


</tr>


</thead>





<tbody>




<?php if($reviews_query && mysqli_num_rows($reviews_query)>0): ?>


<?php while($r=mysqli_fetch_assoc($reviews_query)): ?>


<tr>



<td>

#<?=$r['review_id']?>

</td>







<td>


<strong>

<?=htmlspecialchars($r['customer_name'] ?? 'Unknown')?>

</strong>


<br>


<small>

<?=htmlspecialchars($r['customer_email'] ?? '-')?>

</small>


</td>








<td>


<?=htmlspecialchars($r['hotel_name'] ?? '-')?>


<br>


<small class="text-muted">

<?=htmlspecialchars($r['city'] ?? '')?>

</small>


</td>







<td>



<?php

$rating =
intval($r['rating'] ?? 0);


for($i=1;$i<=5;$i++): ?>


<i class="fa fa-star star <?=($i <= $rating)?'':'text-muted'?>"></i>


<?php endfor; ?>



</td>








<td class="review-comment">


<strong>

<?=htmlspecialchars($r['title'] ?? '')?>

</strong>


<br>


<small>

<?=htmlspecialchars($r['comment'] ?? '')?>

</small>


</td>







<td>


<?=htmlspecialchars($r['booking_code'] ?? '-')?>


</td>








<td>



<?php


$status =
$r['review_status'] ?? 'Pending';



if($status=="Approved"){

$badge="success";

}

elseif($status=="Hidden"){

$badge="danger";

}

else{

$badge="warning";

}



?>



<span class="badge bg-<?=$badge?>">


<?=$status?>


</span>



</td>








<td>


<?=

!empty($r['created_at'])

?

date(
"d M Y",
strtotime($r['created_at'])
)

:

"N/A"

?>


</td>







<td>



<form method="POST"

onsubmit="return confirm('Update review status?');">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="review_id"

value="<?=$r['review_id']?>">



<input type="hidden"

name="update_review_status"

value="1">






<select name="review_status"

class="form-select form-select-sm"

onchange="this.form.submit()">



<option value="Pending"

<?=$status=="Pending"?'selected':''?>>

Pending

</option>




<option value="Approved"

<?=$status=="Approved"?'selected':''?>>

Approved

</option>





<option value="Hidden"

<?=$status=="Hidden"?'selected':''?>>

Hidden

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


No reviews found.


</td>

</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     REVIEW SUMMARY
=========================== -->


<div class="row g-3 mb-4">



<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-comments fa-2x text-primary mb-2"></i>


<h6 class="text-muted">

Total Reviews

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


<i class="fa-solid fa-star fa-2x text-warning mb-2"></i>


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

href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>&rating=<?=$rating_filter?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>&rating=<?=$rating_filter?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>








<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>&rating=<?=$rating_filter?>">


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


Secure Review Management System


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>