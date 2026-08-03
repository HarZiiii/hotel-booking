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
3. APPROVE COMMISSION ACTION
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['approve_commission'])
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



    $commission_id =
    intval($_POST['commission_id'] ?? 0);




    if($commission_id > 0){



        /*
        ===========================
        GET COMMISSION DATA
        ===========================
        */


        $commission_stmt = mysqli_prepare(

            $conn,

            "
            SELECT

            c.owner_id,

            c.commission_amount

            FROM commissions c

            WHERE c.commission_id = ?

            "

        );


        mysqli_stmt_bind_param(

            $commission_stmt,

            "i",

            $commission_id

        );


        mysqli_stmt_execute(
            $commission_stmt
        );


        $commission_result =
        mysqli_stmt_get_result(
            $commission_stmt
        );


        $commission =
        mysqli_fetch_assoc(
            $commission_result
        );







        if($commission){



            /*
            ===========================
            UPDATE COMMISSION STATUS
            ===========================
            */


            $update_stmt = mysqli_prepare(

                $conn,

                "
                UPDATE commissions

                SET commission_status='Paid'

                WHERE commission_id=?

                "

            );


            mysqli_stmt_bind_param(

                $update_stmt,

                "i",

                $commission_id

            );


            mysqli_stmt_execute(
                $update_stmt
            );








            /*
            ===========================
            OWNER NOTIFICATION
            ===========================
            */


            $title =
            "Commission Payment Confirmed";



            $message =

            "Your commission payment of "
            .
            number_format(
                $commission['commission_amount'],
                2
            )
            .
            " MMK has been marked as paid by admin.";



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
                (?,?,?,'System')

                "

            );


            mysqli_stmt_bind_param(

                $notify_stmt,

                "iss",

                $commission['owner_id'],

                $title,

                $message

            );


            mysqli_stmt_execute(
                $notify_stmt
            );







            /*
            ===========================
            AUDIT LOG
            ===========================
            */


            $action =
            "COMMISSION_PAID";


            $table =
            "commissions";


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

                $commission_id,

                $ip,

                $agent

            );



            mysqli_stmt_execute(
                $audit_stmt
            );



        }



        header(
            "Location: manage_commissions.php?msg=paid"
        );

        exit();


    }


}







/*
===========================================
4. PAGINATION
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
5. COMMISSION SUMMARY
===========================================
*/


$stats_query = mysqli_query(

$conn,

"

SELECT


IFNULL(SUM(booking_amount),0)
AS total_sales,


IFNULL(SUM(commission_amount),0)
AS total_commission,


IFNULL(

SUM(

CASE

WHEN commission_status='Paid'

THEN commission_amount

ELSE 0

END

)

,0)

AS approved_commission,



IFNULL(

SUM(

CASE

WHEN commission_status!='Paid'

OR commission_status IS NULL

THEN commission_amount

ELSE 0

END

)

,0)

AS pending_commission



FROM commissions


"

);



$stats =
mysqli_fetch_assoc(
    $stats_query
);



$total_sales =
$stats['total_sales'] ?? 0;


$total_commission =
$stats['total_commission'] ?? 0;


$approved_commission =
$stats['approved_commission'] ?? 0;


$pending_commission =
$stats['pending_commission'] ?? 0;








/*
===========================================
6. TOTAL COMMISSION COUNT
===========================================
*/


$count_query = mysqli_query(

$conn,

"

SELECT COUNT(*) total

FROM commissions

"

);



$total_records =
mysqli_fetch_assoc(
    $count_query
)['total'] ?? 0;



$total_pages =
ceil(
    $total_records/$limit
);








/*
===========================================
7. FETCH COMMISSION DATA
===========================================
*/


$commissions_stmt = mysqli_prepare(

$conn,

"

SELECT


c.*,


u.username,


u.email



FROM commissions c



LEFT JOIN users u


ON c.owner_id=u.user_id



ORDER BY c.commission_id DESC



LIMIT ? OFFSET ?


"

);



mysqli_stmt_bind_param(

$commissions_stmt,

"ii",

$limit,

$offset

);



mysqli_stmt_execute(
    $commissions_stmt
);



$commissions_query =

mysqli_stmt_get_result(
    $commissions_stmt
);



?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Manage Commissions | HBS V3 Admin</title>

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

background:#0f172a;

color:white;

position:fixed;

top:0;

bottom:0;

overflow-y:auto;

}



.brand{

padding:20px;

font-size:19px;

font-weight:700;

color:#38bdf8;

border-bottom:1px solid #1e293b;

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

background:#1e293b;

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

padding:18px 25px;

border-radius:12px;

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



.stat-card{

border-radius:12px;

padding:22px;

color:white;

height:100%;

}



.slip-thumb{

width:55px;

height:55px;

object-fit:cover;

border-radius:8px;

border:1px solid #ddd;

cursor:pointer;

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


}



</style>



<link rel="stylesheet" href="../assets/css/admin-sidebar.css?v=2">
</head>



<body>



<div class="wrapper">



<!-- SIDEBAR -->


<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main-content">



<!-- HEADER -->


<div class="topbar">


<h4 class="fw-bold mb-0">

<i class="fa-solid fa-hand-holding-dollar text-primary"></i>

Owner Commission Requests

</h4>



</div>








<?php if(isset($_GET['msg']) && $_GET['msg']=="paid"): ?>


<div class="alert alert-success">

<i class="fa-solid fa-circle-check"></i>

Commission marked as paid successfully.

</div>


<?php endif; ?>









<!-- SUMMARY CARDS -->


<div class="row g-3 mb-4">



<div class="col-md-3">

<div class="stat-card bg-primary">


<small>

Total Booking Volume

</small>


<h4 class="fw-bold mt-2">

<?=number_format($total_sales,2)?>

MMK

</h4>


</div>

</div>






<div class="col-md-3">

<div class="stat-card bg-success">


<small>

Total Commission

</small>


<h4 class="fw-bold mt-2">

<?=number_format($total_commission,2)?>

MMK

</h4>


</div>

</div>







<div class="col-md-3">

<div class="stat-card bg-info">


<small>

Paid Commission

</small>


<h4 class="fw-bold mt-2">

<?=number_format($approved_commission,2)?>

MMK

</h4>


</div>

</div>







<div class="col-md-3">

<div class="stat-card bg-warning text-dark">


<small>

Pending Commission

</small>


<h4 class="fw-bold mt-2">

<?=number_format($pending_commission,2)?>

MMK

</h4>


</div>

</div>




</div>









<!-- COMMISSION TABLE -->


<div class="card-box">



<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>

Commission Payment Submissions


</h5>





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



<?php if($commissions_query && mysqli_num_rows($commissions_query)>0): ?>



<?php while($row=mysqli_fetch_assoc($commissions_query)): ?>



<tr>



<td>

#<?=$row['commission_id']?>

</td>




<td>


<strong>

<?=htmlspecialchars($row['username'] ?? 'Unknown')?>

</strong>


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


<?php if(

!empty($row['payment_slip'])

&&

file_exists(
'../assets/images/slips/'.$row['payment_slip']
)

): ?>


<a href="../assets/images/slips/<?=htmlspecialchars($row['payment_slip'])?>"
target="_blank">


<img src="../assets/images/slips/<?=htmlspecialchars($row['payment_slip'])?>"

class="slip-thumb">


</a>



<?php else: ?>


<span class="text-muted">

No Image

</span>



<?php endif; ?>


</td>







<td>



<?php if(

($row['commission_status'] ?? '')==="Paid"

): ?>


<span class="badge bg-success">

Approved

</span>



<?php else: ?>


<span class="badge bg-warning text-dark">

Pending

</span>



<?php endif; ?>



</td>







<td>


<small>

<?=

!empty($row['created_at'])

?

date(
"d M Y",
strtotime($row['created_at'])
)

:

"N/A"

?>

</small>


</td>








<td>



<?php if(

($row['commission_status'] ?? '')!=="Paid"

): ?>



<form method="POST"

onsubmit="return confirm('Approve this commission payment?');">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="commission_id"

value="<?=$row['commission_id']?>">



<button type="submit"

name="approve_commission"

class="btn btn-sm btn-primary">


<i class="fa-solid fa-check"></i>

Approve


</button>


</form>



<?php else: ?>


<span class="text-success small">

<i class="fa-solid fa-circle-check"></i>

Completed

</span>



<?php endif; ?>



</td>





</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="9"

class="text-center text-muted py-4">

No commission records found.

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


                <!-- Previous -->

                <li class="page-item <?=($page <= 1)?'disabled':''?>">


                    <a class="page-link"

                    href="?page=<?=($page-1)?>">


                    Previous


                    </a>


                </li>





                <!-- Page Numbers -->


                <?php for($i=1;$i<=$total_pages;$i++): ?>


                <li class="page-item <?=($page==$i)?'active':''?>">


                    <a class="page-link"

                    href="?page=<?=$i?>">


                    <?=$i?>


                    </a>


                </li>


                <?php endfor; ?>







                <!-- Next -->


                <li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


                    <a class="page-link"

                    href="?page=<?=($page+1)?>">


                    Next


                    </a>


                </li>



            </ul>


        </nav>


        <?php endif; ?>







        <!-- FOOTER -->


        <footer class="text-center text-muted py-4">


            <small>

            © <?=date('Y')?> Hotel Booking System V3 |

            Secure Commission Management System


            </small>


        </footer>




    </main>


</div>







<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>