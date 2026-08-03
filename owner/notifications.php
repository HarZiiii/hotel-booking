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



$owner_id=$_SESSION['user_id'];





/*
================================
CSRF TOKEN
================================
*/


if(empty($_SESSION['csrf_token'])){


    $_SESSION['csrf_token']=

    bin2hex(random_bytes(32));


}







/*
================================
MARK READ
================================
*/


if(

$_SERVER['REQUEST_METHOD']=="POST"

&&

isset($_POST['mark_read'])

){



if(

!hash_equals(

$_SESSION['csrf_token'],

$_POST['csrf_token']

)

){

die("Invalid CSRF");

}




$notification_id=intval($_POST['notification_id']);




$stmt=mysqli_prepare(

$conn,

"

UPDATE notifications

SET is_read=1

WHERE notification_id=?

AND user_id=?

"

);



mysqli_stmt_bind_param(

$stmt,

"ii",

$notification_id,

$owner_id

);



mysqli_stmt_execute($stmt);



header(

"Location: notifications.php"

);

exit();


}







/*
================================
DELETE NOTIFICATION
================================
*/


if(

$_SERVER['REQUEST_METHOD']=="POST"

&&

isset($_POST['delete_notification'])

){



if(

!hash_equals(

$_SESSION['csrf_token'],

$_POST['csrf_token']

)

){

die("Invalid CSRF");

}



$notification_id=intval($_POST['notification_id']);




$stmt=mysqli_prepare(

$conn,

"

DELETE FROM notifications

WHERE notification_id=?

AND user_id=?

"

);



mysqli_stmt_bind_param(

$stmt,

"ii",

$notification_id,

$owner_id

);



mysqli_stmt_execute($stmt);



header(

"Location: notifications.php"

);

exit();


}







/*
================================
FETCH NOTIFICATIONS
================================
*/


$page=max(

1,

intval($_GET['page'] ?? 1)

);


$limit=10;


$offset=($page-1)*$limit;





$stmt=mysqli_prepare(

$conn,

"

SELECT *

FROM notifications

WHERE user_id=?

ORDER BY created_at DESC

LIMIT ? OFFSET ?

"

);



mysqli_stmt_bind_param(

$stmt,

"iii",

$owner_id,

$limit,

$offset

);



mysqli_stmt_execute($stmt);



$notifications=mysqli_stmt_get_result($stmt);



?>



<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<title>

Owner Notifications | Hotel Booking System

</title>



<meta name="viewport"

content="width=device-width, initial-scale=1.0">



<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"

rel="stylesheet">



<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"

rel="stylesheet">



<link href="../assets/css/owner.css"

rel="stylesheet">



<style>


body{

background:#f4f6f9;

font-family:'Poppins',sans-serif;

}



.main-content{

margin-left:260px;

padding:30px;

}



.card-box{

background:white;

border-radius:15px;

padding:25px;

box-shadow:0 3px 15px rgba(0,0,0,.05);

}



.notification-card{

border-left:5px solid #38bdf8;

padding:18px;

border-radius:12px;

background:#fff;

margin-bottom:15px;

}



.unread{

background:#eff6ff;

border-left-color:#2563eb;

}



.time{

font-size:13px;

color:#64748b;

}



</style>


</head>




<body>



<?php include '../includes/owner_sidebar.php'; ?>





<div class="main-content">






<div class="d-flex justify-content-between align-items-center mb-4">


<div>


<h3 class="fw-bold">


<i class="fa-solid fa-bell text-primary"></i>


Notifications


</h3>



<p class="text-muted mb-0">

Manage your account notifications

</p>


</div>



</div>









<div class="card-box">







<?php if(mysqli_num_rows($notifications)>0): ?>





<?php while($n=mysqli_fetch_assoc($notifications)): ?>





<div class="notification-card

<?=

$n['is_read']==0

?

'unread'

:

''

?>

">





<div class="d-flex justify-content-between">





<div>



<h5 class="fw-bold">


<?=htmlspecialchars($n['title'])?>


</h5>



<p class="mb-2">


<?=htmlspecialchars($n['message'])?>


</p>





<span class="time">


<i class="fa-regular fa-clock"></i>


<?=

date(

"d M Y H:i",

strtotime($n['created_at'])

)

?>


</span>




</div>







<div class="text-end">





<?php if($n['is_read']==0): ?>



<span class="badge bg-primary mb-2">


Unread

</span>



<?php else: ?>


<span class="badge bg-secondary mb-2">


Read

</span>



<?php endif; ?>









<div class="mt-2">





<?php if($n['is_read']==0): ?>



<form method="POST"

class="d-inline">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="notification_id"

value="<?=$n['notification_id']?>">



<button

name="mark_read"

class="btn btn-sm btn-success">


<i class="fa-solid fa-check"></i>


Read


</button>


</form>



<?php endif; ?>









<form method="POST"

class="d-inline"

onsubmit="return confirm('Delete this notification?');">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="notification_id"

value="<?=$n['notification_id']?>">



<button

name="delete_notification"

class="btn btn-sm btn-danger">


<i class="fa-solid fa-trash"></i>


</button>


</form>







</div>




</div>




</div>







</div>







<?php endwhile; ?>





<?php else: ?>




<div class="text-center py-5 text-muted">


<i class="fa-solid fa-bell-slash fa-3x mb-3"></i>



<h5>

No Notifications

</h5>


<p>

You don't have any notifications yet.

</p>


</div>





<?php endif; ?>







</div>









<!-- PAGINATION -->





<?php


$count_stmt=mysqli_prepare(

$conn,

"

SELECT COUNT(*) total

FROM notifications

WHERE user_id=?

"

);



mysqli_stmt_bind_param(

$count_stmt,

"i",

$owner_id

);



mysqli_stmt_execute($count_stmt);



$count_result=mysqli_stmt_get_result($count_stmt);



$total=mysqli_fetch_assoc($count_result)['total'];



$total_pages=ceil(

$total/$limit

);



?>









<?php if($total_pages>1): ?>



<nav class="mt-4">


<ul class="pagination justify-content-center">





<?php for($i=1;$i<=$total_pages;$i++): ?>




<li class="page-item

<?=

$page==$i

?

'active'

:

''

?>

">



<a class="page-link"

href="?page=<?=$i?>">


<?=$i?>


</a>


</li>





<?php endfor; ?>






</ul>


</nav>



<?php endif; ?>









<footer class="text-center text-muted py-4">


<small>


© <?=date('Y')?> Hotel Booking System


</small>


</footer>







</div>






<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>


</html>