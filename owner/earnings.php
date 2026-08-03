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

$error_msg = "";








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
4. COMMISSION PAYMENT SUBMISSION
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
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






    $commission_amount =

    floatval(

        $_POST['commission_amount'] ?? 0

    );






    if($commission_amount <= 0){

        $error_msg =
        "Invalid commission amount.";

    }

    else{



        /*
        ===========================
        FILE UPLOAD VALIDATION
        ===========================
        */


        $allowed_extensions = [

            'jpg',
            'jpeg',
            'png',
            'webp'

        ];



        $max_size =

        2 * 1024 * 1024;



        $slip_img = "";






        if(
            isset($_FILES['payment_slip'])
            &&
            $_FILES['payment_slip']['error']
            ===
            UPLOAD_ERR_OK
        ){



            $file_tmp =

            $_FILES['payment_slip']['tmp_name'];



            $file_size =

            $_FILES['payment_slip']['size'];



            $original_name =

            $_FILES['payment_slip']['name'];



            $extension =

            strtolower(

                pathinfo(

                    $original_name,

                    PATHINFO_EXTENSION

                )

            );






            $mime =

            mime_content_type(

                $file_tmp

            );





            $allowed_mime = [

                'image/jpeg',
                'image/png',
                'image/webp'

            ];







            if(

                !in_array(

                    $extension,

                    $allowed_extensions

                )

                ||

                !in_array(

                    $mime,

                    $allowed_mime

                )

                ||

                $file_size > $max_size

            ){


                $error_msg =

                "Invalid payment slip file.";

            }

            else{



                $upload_dir =

                "../assets/images/slips/";



                if(!is_dir($upload_dir)){


                    mkdir(

                        $upload_dir,

                        0755,

                        true

                    );


                }





                $new_name =

                time()

                .

                "_"

                .

                bin2hex(

                    random_bytes(5)

                )

                .

                "."

                .

                $extension;






                if(
                    move_uploaded_file(

                        $file_tmp,

                        $upload_dir.$new_name

                    )
                ){


                    $slip_img = $new_name;


                }


            }


        }

        else{


            $error_msg =

            "Please upload payment slip.";


        }









        /*
        ===========================
        CREATE COMMISSION RECORD
        ===========================
        */



        if(
            empty($error_msg)
            &&
            !empty($slip_img)
        ){





            $booking_stmt = mysqli_prepare(

                $conn,

                "

                SELECT


                b.booking_id,

                b.total_amount



                FROM bookings b



                LEFT JOIN hotels h

                ON b.hotel_id=h.hotel_id



                WHERE

                h.owner_id=?

                AND

                b.booking_status

                IN

                ('Confirmed','Checked Out')



                AND b.booking_id NOT IN

                (

                SELECT booking_id

                FROM commissions

                WHERE owner_id=?

                )


                "

            );





            mysqli_stmt_bind_param(

                $booking_stmt,

                "ii",

                $owner_id,

                $owner_id

            );



            mysqli_stmt_execute(

                $booking_stmt

            );



            $booking_result =

            mysqli_stmt_get_result(

                $booking_stmt

            );







            while(

                $booking =

                mysqli_fetch_assoc(

                    $booking_result

                )

            ){



                $booking_id =

                $booking['booking_id'];



                $booking_amount =

                $booking['total_amount'];



                $commission_rate =

                10.00;



                $commission_value =

                $booking_amount * 0.10;



                $owner_amount =

                $booking_amount

                -

                $commission_value;








                $insert_stmt = mysqli_prepare(

                    $conn,

                    "

                    INSERT INTO commissions

                    (

                    booking_id,

                    owner_id,

                    booking_amount,

                    commission_rate,

                    commission_amount,

                    owner_amount,

                    commission_status,

                    payment_slip

                    )


                    VALUES

                    (?,?,?,?,?,?, 'Pending', ?)


                    "

                );





                mysqli_stmt_bind_param(

                    $insert_stmt,

                    "iidddds",

                    $booking_id,

                    $owner_id,

                    $booking_amount,

                    $commission_rate,

                    $commission_value,

                    $owner_amount,

                    $slip_img

                );



                mysqli_stmt_execute(

                    $insert_stmt

                );



            }









            /*
            ===========================
            NOTIFY ADMIN
            ===========================
            */


            $admin_notify = mysqli_query(

                $conn,

                "

                SELECT user_id

                FROM users

                WHERE role='admin'

                LIMIT 1

                "

            );




            if($admin_notify){


                $admin =

                mysqli_fetch_assoc(

                    $admin_notify

                );




                if($admin){



                    $title =

                    "Commission Payment Submitted";



                    $message =

                    "Hotel owner submitted commission payment slip.";





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

                        (?,?,?,'Commission')


                        "

                    );





                    mysqli_stmt_bind_param(

                        $notify_stmt,

                        "iss",

                        $admin['user_id'],

                        $title,

                        $message

                    );



                    mysqli_stmt_execute(

                        $notify_stmt

                    );



                }


            }







            insertOwnerAuditLog(

                $conn,

                $owner_id,

                "COMMISSION_PAYMENT_SUBMITTED",

                "commissions",

                0

            );







            header(

                "Location: earnings.php?msg=success"

            );


            exit();


        }



    }



}









/*
===========================================
5. TOTAL REVENUE
===========================================
*/


$revenue_stmt = mysqli_prepare(

    $conn,

    "

    SELECT

    SUM(b.total_amount) grand_total



    FROM bookings b



    LEFT JOIN hotels h

    ON b.hotel_id=h.hotel_id



    WHERE h.owner_id=?



    AND b.booking_status

    IN

    ('Confirmed','Checked Out')


    "

);



mysqli_stmt_bind_param(

    $revenue_stmt,

    "i",

    $owner_id

);



mysqli_stmt_execute(

    $revenue_stmt

);



$revenue_result =

mysqli_stmt_get_result(

    $revenue_stmt

);



$grand_total =

mysqli_fetch_assoc(

    $revenue_result

)['grand_total'] ?? 0;









/*
===========================================
6. COMMISSION CALCULATION
===========================================
*/


$total_commission =

$grand_total * 0.10;






$paid_stmt = mysqli_prepare(

$conn,

"

SELECT

SUM(commission_amount) paid_total

FROM commissions

WHERE owner_id=?

AND commission_status IN
('Pending','Paid')

"

);



mysqli_stmt_bind_param(

    $paid_stmt,

    "i",

    $owner_id

);



mysqli_stmt_execute(

    $paid_stmt

);



$paid_result =

mysqli_stmt_get_result(

    $paid_stmt

);



$already_submitted =

mysqli_fetch_assoc(

    $paid_result

)['paid_total'] ?? 0;





$remaining_commission =

max(

    0,

    $total_commission - $already_submitted

);




$net_owner_earnings =

$grand_total - $total_commission;









/*
===========================================
7. HOTEL EARNINGS BREAKDOWN
===========================================
*/


$earnings_stmt = mysqli_prepare(

    $conn,

    "

    SELECT


    h.hotel_name,


    COUNT(b.booking_id) total_bookings,


    SUM(

    CASE

    WHEN b.booking_status IN

    ('Confirmed','Checked Out')

    THEN b.total_amount

    ELSE 0

    END

    ) total_revenue



    FROM hotels h



    LEFT JOIN bookings b

    ON h.hotel_id=b.hotel_id



    WHERE h.owner_id=?



    GROUP BY h.hotel_id


    "

);



mysqli_stmt_bind_param(

    $earnings_stmt,

    "i",

    $owner_id

);



mysqli_stmt_execute(

    $earnings_stmt

);



$earnings_query =

mysqli_stmt_get_result(

    $earnings_stmt

);



?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Earnings & Commission | Hotel Partner Hub</title>

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

background:#fff;

padding:18px 25px;

border-radius:12px;

box-shadow:0 2px 12px rgba(0,0,0,.04);

margin-bottom:25px;

display:flex;

justify-content:space-between;

align-items:center;

}



.card-box{

background:#fff;

border-radius:12px;

padding:22px;

box-shadow:0 2px 10px rgba(0,0,0,.03);

border:1px solid #eef2f6;

margin-bottom:25px;

}



.finance-card{

padding:25px;

border-radius:15px;

color:white;

height:100%;

}



.table td{

vertical-align:middle;

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









<main class="main-content">







<!-- TOP BAR -->


<header class="topbar">


<div>


<h4 class="fw-bold mb-1">


<i class="fa-solid fa-wallet text-primary me-2"></i>


Earnings & Commission Report


</h4>



<small class="text-muted">

Financial breakdown and platform fee settlements

</small>


</div>





<div>


<a href="notifications.php"

class="btn btn-light rounded-circle">


<i class="fa-solid fa-bell"></i>


</a>


</div>



</header>









<!-- ALERTS -->


<?php if(isset($_GET['msg']) && $_GET['msg']=="success"): ?>


<div class="alert alert-success">


<i class="fa-solid fa-circle-check me-2"></i>


Commission payment slip submitted successfully.


</div>


<?php endif; ?>








<?php if(!empty($error_msg)): ?>


<div class="alert alert-danger">


<i class="fa-solid fa-triangle-exclamation me-2"></i>


<?=htmlspecialchars($error_msg)?>


</div>


<?php endif; ?>









<!-- FINANCE CARDS -->


<div class="row g-4 mb-4">





<div class="col-md-4">


<div class="finance-card bg-success">


<small class="fw-semibold">

TOTAL SALES REVENUE

</small>


<h3 class="fw-bold mt-2 mb-0">


<?=number_format($grand_total,2)?>

MMK


</h3>


</div>


</div>







<div class="col-md-4">


<div class="finance-card bg-warning text-dark">


<small class="fw-semibold">

REMAINING COMMISSION (10%)

</small>


<h3 class="fw-bold mt-2 mb-0">


<?=number_format($remaining_commission,2)?>

MMK


</h3>


</div>


</div>







<div class="col-md-4">


<div class="finance-card bg-primary">


<small class="fw-semibold">

NET OWNER EARNINGS

</small>


<h3 class="fw-bold mt-2 mb-0">


<?=number_format($net_owner_earnings,2)?>

MMK


</h3>


</div>


</div>




</div>









<!-- COMMISSION PAYMENT -->


<?php if($remaining_commission > 0): ?>


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-money-bill-transfer text-primary me-2"></i>


Pay Commission to Admin


</h5>



<p class="text-muted small">


Platform commission (10%) payment slip ကို upload ပြုလုပ်ပါ။ Admin မှ စစ်ဆေးပြီး approve ပြုလုပ်ပါမည်။


</p>







<form action="earnings.php"

method="POST"

enctype="multipart/form-data"

class="row g-3">





<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">





<div class="col-md-4">


<label class="form-label fw-semibold">


Commission Amount (MMK)


</label>



<input type="number"

name="commission_amount"

class="form-control"

value="<?=$remaining_commission?>"

readonly>


</div>







<div class="col-md-4">


<label class="form-label fw-semibold">


Payment Slip


<span class="text-danger">*</span>


</label>


<input type="file"

name="payment_slip"

class="form-control"

accept=".jpg,.jpeg,.png,.webp"

required>


</div>







<div class="col-md-4 d-flex align-items-end">


<button type="submit"

name="pay_commission"

class="btn btn-success w-100">


<i class="fa-solid fa-paper-plane me-1"></i>


Submit Payment


</button>


</div>



</form>


</div>







<?php else: ?>


<div class="alert alert-info">


<i class="fa-solid fa-circle-info me-2"></i>


All commissions have been submitted.


</div>


<?php endif; ?>









<!-- HOTEL BREAKDOWN TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-chart-column text-primary me-2"></i>


Revenue Breakdown by Hotel Property


</h5>







<div class="table-responsive">


<table class="table table-hover align-middle mb-0">


<thead class="table-light">


<tr>


<th>Hotel Name</th>

<th>Total Reservations</th>

<th>Gross Revenue</th>

<th>Commission (10%)</th>

<th>Net Income</th>


</tr>


</thead>




<tbody>




<?php if($earnings_query && mysqli_num_rows($earnings_query)>0): ?>



<?php while($e=mysqli_fetch_assoc($earnings_query)): ?>


<?php

$hotel_revenue =
$e['total_revenue'] ?? 0;


$hotel_commission =
$hotel_revenue * 0.10;


$hotel_net =
$hotel_revenue - $hotel_commission;


?>



<tr>


<td class="fw-bold">


<?=htmlspecialchars($e['hotel_name'])?>


</td>



<td>


<?=number_format($e['total_bookings'])?>


Bookings


</td>



<td class="text-success fw-bold">


<?=number_format($hotel_revenue,2)?>

MMK


</td>



<td class="text-danger fw-bold">


<?=number_format($hotel_commission,2)?>

MMK


</td>



<td class="text-primary fw-bold">


<?=number_format($hotel_net,2)?>

MMK


</td>



</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>


<td colspan="5"

class="text-center text-muted py-4">


No earnings data available.


</td>


</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     COMMISSION PAYMENT HISTORY
=========================== -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>


Commission Payment History


</h5>







<div class="table-responsive">


<table class="table table-hover align-middle">



<thead class="table-light">


<tr>


<th>No</th>

<th>Booking ID</th>

<th>Booking Amount</th>

<th>Commission</th>

<th>Status</th>

<th>Payment Slip</th>

<th>Date</th>


</tr>


</thead>





<tbody>



<?php


$commission_history_stmt = mysqli_prepare(

$conn,

"

SELECT


c.*,


b.booking_code



FROM commissions c



LEFT JOIN bookings b


ON c.booking_id=b.booking_id



WHERE c.owner_id=?



ORDER BY c.commission_id DESC



"

);



mysqli_stmt_bind_param(

$commission_history_stmt,

"i",

$owner_id

);



mysqli_stmt_execute(

$commission_history_stmt

);



$commission_history = mysqli_stmt_get_result(

$commission_history_stmt

);



?>







<?php if($commission_history && mysqli_num_rows($commission_history)>0): ?>





<?php 

$no = 1;

while($c=mysqli_fetch_assoc($commission_history)): 

?>



<tr>



<td>

<?=$no++?>

</td>







<td>


<strong>

<?=htmlspecialchars($c['booking_code'] ?? '-')?>

</strong>


</td>








<td>


<?=number_format($c['booking_amount'] ?? 0,2)?>

MMK


</td>







<td class="text-danger fw-bold">


<?=number_format($c['commission_amount'] ?? 0,2)?>

MMK


</td>







<td>



<?php


$status = $c['commission_status'] ?? 'Pending';


if($status=="Paid"){

    $badge="success";

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



<?php if(!empty($c['payment_slip'])): ?>


<a href="../assets/images/slips/<?=htmlspecialchars($c['payment_slip'])?>"

target="_blank"

class="btn btn-sm btn-outline-primary">


<i class="fa-solid fa-image"></i>


View Slip


</a>


<?php else: ?>


<span class="text-muted">


No File


</span>


<?php endif; ?>


</td>







<td>


<?=

!empty($c['created_at'])

?

date(

"d M Y",

strtotime($c['created_at'])

)

:

"N/A"

?>


</td>





</tr>



<?php endwhile; ?>







<?php else: ?>



<tr>


<td colspan="7"

class="text-center text-muted py-4">


No commission payment history found.


</td>


</tr>



<?php endif; ?>




</tbody>



</table>



</div>



</div>









<!-- ===========================
     SUMMARY FOOTER CARDS
=========================== -->


<div class="row g-4 mb-4">





<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-chart-line fa-2x text-success mb-2"></i>


<h6 class="text-muted">


Gross Sales


</h6>


<h4 class="fw-bold">


<?=number_format($grand_total,2)?>

MMK


</h4>


</div>


</div>








<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-percent fa-2x text-danger mb-2"></i>


<h6 class="text-muted">


Platform Commission


</h6>


<h4 class="fw-bold">


<?=number_format($total_commission,2)?>

MMK


</h4>


</div>


</div>








<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-wallet fa-2x text-primary mb-2"></i>


<h6 class="text-muted">


Owner Net Earnings


</h6>


<h4 class="fw-bold">


<?=number_format($net_owner_earnings,2)?>

MMK


</h4>


</div>


</div>




</div>









<!-- ===========================
     FOOTER
=========================== -->


<footer class="text-center text-muted py-4">


<small>


© <?=date('Y')?> Hotel Booking System V3 |


Owner Financial Management System


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>




<script>


/*
================================
AUTO HIDE ALERT
================================
*/


setTimeout(function(){


document.querySelectorAll('.alert')

.forEach(function(alert){


alert.style.display='none';


});


},4000);



</script>






</body>

</html>