<?php

require_once '../config/config.php';


if(session_status() === PHP_SESSION_NONE){

    session_start();

}



/*
====================================
ADMIN AUTH
====================================
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





/*
====================================
CSRF
====================================
*/


if(empty($_SESSION['csrf_token'])){

    $_SESSION['csrf_token']

    =

    bin2hex(random_bytes(32));

}






/*
====================================
PAY COMMISSION
====================================
*/


if(

$_SERVER['REQUEST_METHOD']=="POST"

&&

isset($_POST['pay_commission'])

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





    $commission_id=intval(

        $_POST['commission_id'] ?? 0

    );





    if($commission_id>0){



        $stmt=mysqli_prepare(

            $conn,

            "

            SELECT

            owner_id,

            commission_amount,

            commission_status


            FROM commissions


            WHERE commission_id=?


            "

        );



        mysqli_stmt_bind_param(

            $stmt,

            "i",

            $commission_id

        );



        mysqli_stmt_execute($stmt);



        $result=mysqli_stmt_get_result($stmt);



        $commission=mysqli_fetch_assoc($result);






        if(

            $commission

            &&

            $commission['commission_status']=="Pending"

        ){



            $update=mysqli_prepare(

                $conn,

                "

                UPDATE commissions

                SET commission_status='Paid'


                WHERE commission_id=?

                "

            );



            mysqli_stmt_bind_param(

                $update,

                "i",

                $commission_id

            );



            mysqli_stmt_execute($update);





            /*
            ============================
            OWNER NOTIFICATION
            ============================
            */


            $title="Commission Paid";


            $message=

            "Your commission payment of "

            .

            number_format(

                $commission['commission_amount'],

                2

            )

            .

            " MMK has been paid.";





            $notify=mysqli_prepare(

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

                (?,?,?,'System')

                "

            );



            mysqli_stmt_bind_param(

                $notify,

                "iss",

                $commission['owner_id'],

                $title,

                $message

            );



            mysqli_stmt_execute($notify);



        }



    }



    header(

        "Location: manage_commissions.php?msg=paid"

    );

    exit();

}

?>
<?php


/*
====================================
COMMISSION SUMMARY
====================================
*/


$stats_query=mysqli_query(

$conn,

"

SELECT


IFNULL(

SUM(booking_amount),

0

) AS total_sales,




IFNULL(

SUM(commission_amount),

0

) AS total_commission,





IFNULL(

SUM(

CASE

WHEN commission_status='Paid'

THEN commission_amount

ELSE 0

END

),

0

) AS paid_commission,





IFNULL(

SUM(

CASE

WHEN commission_status='Pending'

THEN commission_amount

ELSE 0

END

),

0

) AS pending_commission





FROM commissions



WHERE commission_status IN

(

'Pending',

'Paid'

)



"

);





$stats=mysqli_fetch_assoc(

$stats_query

);





$total_sales =

$stats['total_sales'] ?? 0;



$total_commission =

$stats['total_commission'] ?? 0;



$paid_commission =

$stats['paid_commission'] ?? 0;



$pending_commission =

$stats['pending_commission'] ?? 0;









/*
====================================
PAGINATION
====================================
*/


$limit=20;



$page=intval(

$_GET['page'] ?? 1

);



if($page < 1){

    $page=1;

}



$offset=

($page-1)*$limit;







$count_query=mysqli_query(

$conn,

"

SELECT COUNT(*) AS total

FROM commissions

WHERE commission_status IN

(

'Pending',

'Paid'

)

"

);



$total_records=

mysqli_fetch_assoc(

$count_query

)['total'] ?? 0;





$total_pages=

ceil(

$total_records/$limit

);









/*
====================================
FETCH COMMISSIONS
====================================
*/


$commission_stmt=mysqli_prepare(

$conn,

"

SELECT


c.*,


u.username,


u.email



FROM commissions c



LEFT JOIN users u


ON c.owner_id=u.user_id




WHERE c.commission_status IN

(

'Pending',

'Paid'

)



ORDER BY c.commission_id DESC



LIMIT ? OFFSET ?



"

);





mysqli_stmt_bind_param(

$commission_stmt,

"ii",

$limit,

$offset

);





mysqli_stmt_execute(

$commission_stmt

);





$commissions_query=

mysqli_stmt_get_result(

$commission_stmt

);



?>

<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<title>
Manage Commissions | HBS V3 Admin
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

font-family:'Poppins',sans-serif;

background:#f1f5f9;

margin:0;

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

left:0;

}



.brand{

padding:22px;

font-size:20px;

font-weight:700;

color:#38bdf8;

border-bottom:1px solid #334155;

}



.sidebar ul{

padding:0;

margin:0;

list-style:none;

}



.sidebar li a{

display:flex;

align-items:center;

gap:12px;

padding:14px 20px;

color:#94a3b8;

text-decoration:none;

}



.sidebar li a:hover,

.sidebar li.active a{

background:#0f172a;

color:#38bdf8;

border-left:4px solid #38bdf8;

}





/* MAIN */

.main-content{

margin-left:260px;

padding:35px;

width:calc(100% - 260px);

}





.title{

font-weight:700;

margin-bottom:25px;

}





.card-box{

background:white;

border-radius:18px;

padding:25px;

box-shadow:0 5px 20px rgba(0,0,0,.05);

}





.stat-card{

border-radius:18px;

padding:25px;

color:white;

}



.slip-img{

width:60px;

height:60px;

object-fit:cover;

border-radius:8px;

}



</style>


</head>






<body>






<div class="wrapper">





<!-- ==========================
SIDEBAR
========================== -->


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





<li class="active">

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





<h2 class="title">

<i class="fa-solid fa-hand-holding-dollar text-success"></i>

Manage Commissions

</h2>








<?php if(isset($_GET['msg']) && $_GET['msg']=="paid"): ?>


<div class="alert alert-success">

Commission marked as Paid successfully.

</div>


<?php endif; ?>









<!-- SUMMARY -->

<div class="row g-4 mb-4">



<div class="col-md-3">

<div class="stat-card bg-primary">


<h6>Total Sales</h6>


<h4>

<?=number_format($total_sales,2)?>

MMK

</h4>


</div>

</div>






<div class="col-md-3">

<div class="stat-card bg-success">


<h6>Total Commission</h6>


<h4>

<?=number_format($total_commission,2)?>

MMK

</h4>


</div>

</div>






<div class="col-md-3">

<div class="stat-card bg-info">


<h6>Paid</h6>


<h4>

<?=number_format($paid_commission,2)?>

MMK

</h4>


</div>

</div>






<div class="col-md-3">

<div class="stat-card bg-warning text-dark">


<h6>Pending</h6>


<h4>

<?=number_format($pending_commission,2)?>

MMK

</h4>


</div>

</div>



</div>









<div class="card-box">



<div class="table-responsive">



<table class="table table-hover align-middle">



<thead class="table-light">


<tr>

<th>ID</th>

<th>Owner</th>

<th>Email</th>

<th>Booking Amount</th>

<th>Commission</th>

<th>Slip</th>

<th>Status</th>

<th>Date</th>

<th>Action</th>


</tr>


</thead>





<tbody>





<?php if(mysqli_num_rows($commissions_query)>0): ?>





<?php while($row=mysqli_fetch_assoc($commissions_query)): ?>

<tr>



<td>

#<?=$row['commission_id']?>

</td>




<td>

<?=htmlspecialchars($row['username'] ?? 'Unknown')?>

</td>




<td>

<?=htmlspecialchars($row['email'] ?? '-')?>

</td>




<td>

<?=number_format($row['booking_amount'] ?? 0,2)?>

MMK

</td>




<td>

<strong class="text-success">

<?=number_format($row['commission_amount'] ?? 0,2)?>

MMK

</strong>

</td>




<td>



<?php if(!empty($row['payment_slip'])): ?>


<a href="../assets/images/slips/<?=htmlspecialchars($row['payment_slip'])?>"
target="_blank">


<img src="../assets/images/slips/<?=htmlspecialchars($row['payment_slip'])?>"
class="slip-img">


</a>



<?php else: ?>


<span class="text-muted">

No Slip

</span>



<?php endif; ?>


</td>




<td>


<?php if($row['commission_status']=="Paid"): ?>


<span class="badge bg-success">

Paid

</span>


<?php else: ?>


<span class="badge bg-warning text-dark">

Pending

</span>


<?php endif; ?>


</td>





<td>

<?=date("d M Y",strtotime($row['created_at']))?>

</td>





<td>



<?php if(

$row['commission_status']=="Pending"

&&

!empty($row['payment_slip'])

): ?>



<form method="POST">


<input type="hidden"
name="csrf_token"
value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"
name="commission_id"
value="<?=$row['commission_id']?>">



<button class="btn btn-success btn-sm"
name="pay_commission"
onclick="return confirm('Confirm payment?');">


<i class="fa-solid fa-check"></i>

Pay


</button>


</form>



<?php elseif($row['commission_status']=="Paid"): ?>


<span class="text-success">

<i class="fa-solid fa-circle-check"></i>

Completed

</span>



<?php else: ?>


<span class="text-muted">

Waiting Slip

</span>


<?php endif; ?>



</td>


</tr>


<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="9"
class="text-center py-5 text-muted">

No commission records found.

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
href="?page=<?=$i?>">

<?=$i?>

</a>


</li>


<?php endfor; ?>


</ul>


</nav>


<?php endif; ?>






</main>




</div>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>