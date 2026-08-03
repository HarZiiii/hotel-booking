<?php

ob_start();

require_once 'includes/header.php';
require_once 'includes/navbar.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$user_id = $_SESSION['user_id'];


if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header("Location:index.php");
    exit();
}


$booking_id = intval($_GET['booking_id']);



$stmt = mysqli_prepare($conn,
"
SELECT 
    b.*,
    h.hotel_name,
    h.city,
    h.hotel_id,
    h.owner_id

FROM bookings b

JOIN hotels h 
ON b.hotel_id = h.hotel_id

WHERE 
b.booking_id = ?
AND b.customer_id = ?

LIMIT 1

");


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $booking_id,
    $user_id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);



if(mysqli_num_rows($result)==0){

    echo "
    <div class='container my-5'>
        <div class='alert alert-danger text-center rounded-4 p-4'>
            Booking record not found.
        </div>
    </div>";

    require_once 'includes/footer.php';
    exit();

}



$booking = mysqli_fetch_assoc($result);



if(
$booking['booking_status']=="Confirmed" ||
$booking['booking_status']=="Completed"
){

    header(
    "Location:order_history.php?msg=already_paid"
    );

    exit();

}



$error_msg="";




if(
$_SERVER["REQUEST_METHOD"]=="POST"
&&
isset($_POST['pay_now'])

){


$payment_method =
mysqli_real_escape_string(
$conn,
$_POST['payment_method']
);



$total_amount =
floatval(
$booking['total_amount']
);



$owner_id =
intval(
$booking['owner_id']
);




/*
 Commission 10%
*/

$commission_rate = 10;

$commission_amount =
$total_amount * 0.10;


$owner_amount =
$total_amount - $commission_amount;




mysqli_begin_transaction($conn);



try{


/*
 Payment Insert
*/


$payment_status="Pending";


$transaction_id =
"TXN-".
strtoupper(
substr(
md5(
uniqid(mt_rand(),true)
),
0,
8
)
);



$stmt_pay=mysqli_prepare(
$conn,
"
INSERT INTO payments

(
booking_id,
payment_method,
transaction_id,
amount,
payment_status

)

VALUES
(?,?,?,?,?)

"

);



mysqli_stmt_bind_param(
$stmt_pay,
"issds",
$booking_id,
$payment_method,
$transaction_id,
$total_amount,
$payment_status
);



mysqli_stmt_execute($stmt_pay);





/*
 Commission Insert
*/


$commission_status="Pending";


$stmt_comm=mysqli_prepare(
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
commission_status

)

VALUES
(?,?,?,?,?,?,?)

"

);



mysqli_stmt_bind_param(
$stmt_comm,
"iidddds",

$booking_id,
$owner_id,
$total_amount,
$commission_rate,
$commission_amount,
$owner_amount,
$commission_status

);



mysqli_stmt_execute($stmt_comm);





/*
 Booking status keep Pending
*/


$status="Pending";


$stmt_book=mysqli_prepare(
$conn,

"
UPDATE bookings

SET booking_status=?

WHERE booking_id=?

"

);


mysqli_stmt_bind_param(
$stmt_book,
"si",
$status,
$booking_id
);


mysqli_stmt_execute($stmt_book);





/*
 Notification
*/


$message =
"Payment request submitted for booking "
.$booking['booking_code'];



$stmt_noti=mysqli_prepare(
$conn,

"
INSERT INTO notifications

(
user_id,
title,
message,
type,
notification_type

)

VALUES
(?,?,?,?,?)

"

);



$title="Payment Submitted";
$type="general";
$noti_type="Payment";



mysqli_stmt_bind_param(
$stmt_noti,
"issss",

$user_id,
$title,
$message,
$type,
$noti_type

);



mysqli_stmt_execute($stmt_noti);





mysqli_commit($conn);



header(
"Location:order_history.php?msg=payment_success"
);

exit();



}

catch(Exception $e){


mysqli_rollback($conn);


$error_msg =
"Payment processing failed.";

}



}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
Secure Payment
</title>


<meta name="viewport" content="width=device-width, initial-scale=1">


<link href="
https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css
" rel="stylesheet">


<link href="
https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css
" rel="stylesheet">



<style>


body{

font-family:'Poppins',sans-serif;

background:#f4f6f9;

}



.payment-card{

background:white;

border-radius:18px;

border:1px solid #eee;

box-shadow:
0 8px 30px rgba(0,0,0,.05);

}




.payment-method-option{

border:2px solid #e2e8f0;

border-radius:14px;

padding:18px;

cursor:pointer;

transition:.25s;

}



.payment-method-option:hover{

border-color:#2563eb;

background:#f8fafc;

}



.payment-method-option.active{

border-color:#2563eb;

background:#eff6ff;

}




.form-check-input{

cursor:pointer;

}



.form-check-input:focus{

border-color:#2563eb;

box-shadow:
0 0 0 .25rem rgba(37,99,235,.25);

}



.form-check-input:checked{

background-color:#2563eb;

border-color:#2563eb;

}



.btn-pay{

background:#2563eb;

color:white;

border:none;

width:100%;

padding:15px;

border-radius:12px;

font-weight:600;

}



.btn-pay:hover{

background:#1d4ed8;

}



</style>


</head>
<body>


<div class="container my-5" style="max-width:720px;">


    <div class="text-center mb-4">

        <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-pill mb-3">

            <i class="fa-solid fa-lock me-1"></i>

            Secure Checkout Gateway

        </span>


        <h2 class="fw-bold">

            Complete Your Payment

        </h2>


        <p class="text-muted">

            Select your preferred payment method to secure your reservation.

        </p>


    </div>




<?php if(!empty($error_msg)): ?>

<div class="alert alert-danger rounded-3 shadow-sm">

<i class="fa-solid fa-circle-exclamation me-2"></i>

<?= htmlspecialchars($error_msg); ?>

</div>

<?php endif; ?>





<div class="payment-card p-4 p-md-5">



<!-- Booking Summary -->

<div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">


<div>

<small class="text-muted">

Booking Reference

</small>


<h5 class="fw-bold mb-0">

<?= htmlspecialchars($booking['booking_code']); ?>

</h5>


</div>




<div class="text-end">

<small class="text-muted">

Total Amount

</small>


<h3 class="text-success fw-bold mb-0">


<?= number_format($booking['total_amount']); ?>

<span class="fs-6 text-muted">

MMK

</span>


</h3>


</div>


</div>







<form method="POST">



<h5 class="fw-bold mb-3">

Select Payment Method

</h5>





<div class="d-flex flex-column gap-3 mb-4">





<!-- KBZ Pay -->

<div class="payment-method-option active">


<div class="form-check">


<input

class="form-check-input"

type="radio"

name="payment_method"

id="kbzpay"

value="KBZPay"

checked>


<label

class="form-check-label fw-semibold"

for="kbzpay">


<i class="fa-solid fa-mobile-screen-button text-primary me-2"></i>

KBZPay


</label>


</div>


<small class="text-muted ms-4">

Fast and secure mobile wallet payment.

</small>


</div>






<!-- Wave Pay -->

<div class="payment-method-option">


<div class="form-check">


<input

class="form-check-input"

type="radio"

name="payment_method"

id="wavepay"

value="WavePay">



<label

class="form-check-label fw-semibold"

for="wavepay">


<i class="fa-solid fa-mobile-screen-button text-warning me-2"></i>

WavePay


</label>


</div>


<small class="text-muted ms-4">

Easy transfer via Wave Money account.

</small>


</div>








<!-- AYA Pay -->


<div class="payment-method-option">


<div class="form-check">


<input

class="form-check-input"

type="radio"

name="payment_method"

id="ayapay"

value="AYA Pay">


<label

class="form-check-label fw-semibold"

for="ayapay">


<i class="fa-solid fa-building-columns text-info me-2"></i>

AYA Pay


</label>


</div>


<small class="text-muted ms-4">

Direct payment via AYA mobile banking.

</small>


</div>







<!-- CB Pay -->


<div class="payment-method-option">


<div class="form-check">


<input

class="form-check-input"

type="radio"

name="payment_method"

id="cbpay"

value="CB Pay">



<label

class="form-check-label fw-semibold"

for="cbpay">


<i class="fa-solid fa-building-columns text-success me-2"></i>

CB Pay


</label>


</div>


<small class="text-muted ms-4">

Direct payment via CB mobile banking.

</small>


</div>








<!-- Bank Transfer -->


<div class="payment-method-option">


<div class="form-check">


<input

class="form-check-input"

type="radio"

name="payment_method"

id="bank"

value="Bank Transfer">


<label

class="form-check-label fw-semibold"

for="bank">


<i class="fa-solid fa-landmark text-secondary me-2"></i>

Bank Transfer


</label>


</div>


<small class="text-muted ms-4">

Traditional bank or online transfer.

</small>


</div>








<!-- Credit Card -->


<div class="payment-method-option">


<div class="form-check">


<input

class="form-check-input"

type="radio"

name="payment_method"

id="creditcard"

value="Credit Card">


<label

class="form-check-label fw-semibold"

for="creditcard">


<i class="fa-solid fa-credit-card text-danger me-2"></i>

Credit Card


</label>


</div>


<small class="text-muted ms-4">

Visa / Mastercard secure gateway.

</small>


</div>






</div>








<button

type="submit"

name="pay_now"

class="btn-pay">


<i class="fa-solid fa-shield-halved me-2"></i>


Confirm Payment

(

<?= number_format($booking['total_amount']); ?>

MMK

)


</button>



</form>




</div>







<div class="text-center mt-4">


<a href="order_history.php"

class="text-decoration-none text-muted">


<i class="fa-solid fa-clock-rotate-left me-1"></i>


Pay Later & View Order History


</a>


</div>





</div>







<script src="
https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js
"></script>





<script>


// Payment card focus fix

document.querySelectorAll(
'.payment-method-option'
)
.forEach(card=>{


card.addEventListener(
'click',
function(e){


let radio =
this.querySelector(
'input[type="radio"]'
);



radio.checked=true;



document
.querySelectorAll(
'.payment-method-option'
)
.forEach(item=>{

item.classList.remove(
'active'
);

});



this.classList.add(
'active'
);



}

);


});





// Radio direct click support

document.querySelectorAll(
'.form-check-input'
)
.forEach(radio=>{


radio.addEventListener(
'change',
function(){


document
.querySelectorAll(
'.payment-method-option'
)
.forEach(item=>{

item.classList.remove(
'active'
);

});



this.closest(
'.payment-method-option'
)
.classList.add(
'active'
);



}

);



});



</script>




</body>


</html>




<?php

require_once 'includes/footer.php';

?>