<?php

require_once '../config/config.php';


if(session_status() === PHP_SESSION_NONE){

    session_start();

}



/*
================================
OWNER AUTH
================================
*/


if(

    !isset($_SESSION['user_id'])

    ||

    $_SESSION['role'] !== 'owner'

){

    header("Location: ../login.php");

    exit();

}



$owner_id = $_SESSION['user_id'];





/*
================================
CSRF CHECK
================================
*/


if(

    $_SERVER['REQUEST_METHOD'] !== 'POST'

    ||

    !isset($_POST['pay_commission'])

){

    header("Location: earnings.php");

    exit();

}




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








/*
================================
COMMISSION AMOUNT
================================
*/


$commission_amount = floatval(

    $_POST['commission_amount'] ?? 0

);



if($commission_amount <= 0){

    header(

        "Location: earnings.php?error=invalid_amount"

    );

    exit();

}







/*
================================
UPLOAD PAYMENT SLIP
================================
*/


$payment_slip = "";




if(

    !isset($_FILES['payment_slip'])

    ||

    $_FILES['payment_slip']['error']

    !==

    UPLOAD_ERR_OK

){

    header(

        "Location: earnings.php?error=no_slip"

    );

    exit();

}





$file=$_FILES['payment_slip'];



$allowed_ext=[

    "jpg",
    "jpeg",
    "png",
    "webp"

];



$ext=strtolower(

    pathinfo(

        $file['name'],

        PATHINFO_EXTENSION

    )

);



$mime=mime_content_type(

    $file['tmp_name']

);



$allowed_mime=[

    "image/jpeg",
    "image/png",
    "image/webp"

];




if(

    !in_array($ext,$allowed_ext)

    ||

    !in_array($mime,$allowed_mime)

    ||

    $file['size'] > 2*1024*1024

){

    header(

        "Location: earnings.php?error=invalid_file"

    );

    exit();

}






$upload_dir="../assets/images/slips/";



if(!is_dir($upload_dir)){


    mkdir(

        $upload_dir,

        0755,

        true

    );


}




$new_name=

"slip_"

.time()

."_"

.bin2hex(random_bytes(5))

."."

.$ext;




if(

    move_uploaded_file(

        $file['tmp_name'],

        $upload_dir.$new_name

    )

){

    $payment_slip=$new_name;

}

else{

    header(

        "Location: earnings.php?error=upload_failed"

    );

    exit();

}

/*
================================
CREATE COMMISSION RECORDS
================================
*/


$booking_stmt = mysqli_prepare(

$conn,

"

SELECT


b.booking_id,

b.total_amount



FROM bookings b



INNER JOIN hotels h

ON b.hotel_id=h.hotel_id




WHERE h.owner_id=?



AND b.booking_status IN

(

'Confirmed',

'Checked Out'

)



AND b.booking_id NOT IN

(

SELECT booking_id

FROM commissions

WHERE owner_id=?

)



ORDER BY b.booking_id ASC


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



$booking_result = mysqli_stmt_get_result(

$booking_stmt

);






$insert_count = 0;






while(

$booking=mysqli_fetch_assoc(

$booking_result

)

){



    $booking_id = $booking['booking_id'];



    $booking_amount =

    floatval(

        $booking['total_amount']

    );




    $commission_rate = 10.00;



    $commission_value =

    $booking_amount * 0.10;



    $owner_amount =

    $booking_amount

    -

    $commission_value;







    $insert_stmt=mysqli_prepare(

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

        $payment_slip

    );







    if(mysqli_stmt_execute($insert_stmt)){


        $insert_count++;


    }



}






/*
================================
IF NO BOOKING FOUND
================================
*/


if($insert_count==0){


    header(

        "Location: earnings.php?error=no_booking"

    );


    exit();


}








/*
================================
NOTIFY ADMIN
================================
*/


$admin_stmt=mysqli_prepare(

$conn,

"

SELECT user_id

FROM users

WHERE role='admin'

LIMIT 1

"

);



mysqli_stmt_execute(

$admin_stmt

);



$admin_result=mysqli_stmt_get_result(

$admin_stmt

);



$admin=mysqli_fetch_assoc(

$admin_result

);





if($admin){



$title="Commission Payment Submitted";



$message=

"Owner submitted commission payment slip. Please review payment.";





$notify_stmt=mysqli_prepare(

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






/*
================================
AUDIT LOG
================================
*/


$ip=$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';



$agent=$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';





$audit_stmt=mysqli_prepare(

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





$action="COMMISSION_PAYMENT_SUBMITTED";

$table="commissions";

$record_id=0;





mysqli_stmt_bind_param(

$audit_stmt,

"ississ",

$owner_id,

$action,

$table,

$record_id,

$ip,

$agent

);





mysqli_stmt_execute(

$audit_stmt

);







/*
================================
SUCCESS REDIRECT
================================
*/


header(

"Location: earnings.php?msg=commission_submitted"

);


exit();


?>