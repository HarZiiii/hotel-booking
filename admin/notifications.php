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



$msg = "";

$error = "";





/*
===========================================
3. MARK NOTIFICATION READ ACTION
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['mark_action'])
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




    /*
    ===========================
    MARK ALL READ
    ===========================
    */


    if($_POST['mark_action']=="all_read"){



        $stmt = mysqli_prepare(

            $conn,

            "

            UPDATE notifications

            SET is_read = 1

            WHERE is_read = 0

            "

        );


        mysqli_stmt_execute($stmt);



        header(
            "Location: notifications.php?msg=all_read"
        );

        exit();


    }






    /*
    ===========================
    MARK SINGLE READ
    ===========================
    */


    if(
        $_POST['mark_action']=="single_read"
        &&
        isset($_POST['notification_id'])
    ){


        $notification_id =
        intval($_POST['notification_id']);



        $stmt = mysqli_prepare(

            $conn,

            "

            UPDATE notifications

            SET is_read = 1

            WHERE notification_id = ?

            "

        );



        mysqli_stmt_bind_param(

            $stmt,

            "i",

            $notification_id

        );



        mysqli_stmt_execute($stmt);



        header(
            "Location: notifications.php?msg=read"
        );


        exit();


    }



}









/*
===========================================
4. SEND NOTIFICATION
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['send_notification'])
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




    $target_user_id =
    intval($_POST['user_id'] ?? 0);



    $title =
    trim($_POST['title'] ?? '');



    $message =
    trim($_POST['message'] ?? '');



    $type =
    trim($_POST['type'] ?? 'system');





    if(
        empty($title)
        ||
        empty($message)
    ){


        $error =
        "Please fill all required fields.";


    }

    else{



        /*
        ===========================
        BROADCAST ALL USERS
        ===========================
        */


        if($target_user_id == 0){



            $users_query = mysqli_query(

                $conn,

                "

                SELECT user_id

                FROM users

                "

            );



            while(
                $user =
                mysqli_fetch_assoc($users_query)
            ){



                $insert_stmt = mysqli_prepare(

                    $conn,

                    "

                    INSERT INTO notifications

                    (

                    user_id,

                    title,

                    message,

                    type,

                    is_read,

                    created_at

                    )


                    VALUES

                    (?,?,?,?,0,NOW())


                    "

                );



                mysqli_stmt_bind_param(

                    $insert_stmt,

                    "isss",

                    $user['user_id'],

                    $title,

                    $message,

                    $type

                );



                mysqli_stmt_execute(
                    $insert_stmt
                );


            }




        }

        else{



            /*
            ===========================
            SINGLE USER NOTIFICATION
            ===========================
            */


            $insert_stmt = mysqli_prepare(

                $conn,

                "

                INSERT INTO notifications

                (

                user_id,

                title,

                message,

                type,

                is_read,

                created_at

                )


                VALUES

                (?,?,?,?,0,NOW())


                "

            );



            mysqli_stmt_bind_param(

                $insert_stmt,

                "isss",

                $target_user_id,

                $title,

                $message,

                $type

            );



            mysqli_stmt_execute(
                $insert_stmt
            );


        }







        /*
        ===========================
        AUDIT LOG
        ===========================
        */


        $log_id =
        mysqli_insert_id($conn);



        $action =
        "NOTIFICATION_SENT";


        $table =
        "notifications";


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

            $log_id,

            $ip,

            $agent

        );



        mysqli_stmt_execute(
            $audit_stmt
        );




        header(
            "Location: notifications.php?msg=sent"
        );


        exit();


    }



}










/*
===========================================
5. SEARCH & FILTER
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




$where = [];

$params = [];

$types = "";






if($search !== ''){


    $where[] = "

    (

    n.title LIKE ?

    OR n.message LIKE ?

    OR u.full_name LIKE ?

    OR u.email LIKE ?

    )

    ";



    $keyword =
    "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;



    $types.="ssss";


}






if($status_filter=="unread"){


    $where[]="n.is_read=0";


}



elseif($status_filter=="read"){


    $where[]="n.is_read=1";


}





$where_sql="";


if(count($where)>0){


    $where_sql =
    "WHERE ".implode(
        " AND ",
        $where
    );


}







/*
===========================================
6. PAGINATION
===========================================
*/


$limit = 20;


$page =
intval(
    $_GET['page'] ?? 1
);


if($page < 1){

    $page=1;

}


$offset =
($page-1)*$limit;








/*
===========================================
7. TOTAL COUNT
===========================================
*/


$count_sql = "

SELECT COUNT(*) total

FROM notifications n

LEFT JOIN users u

ON n.user_id=u.user_id


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
8. FETCH NOTIFICATIONS
===========================================
*/


$sql = "

SELECT


n.*,


u.full_name AS recipient_name,


u.email AS recipient_email,


u.role AS recipient_role



FROM notifications n



LEFT JOIN users u

ON n.user_id=u.user_id



$where_sql



ORDER BY n.notification_id DESC



LIMIT ? OFFSET ?

";



$stmt =
mysqli_prepare(
    $conn,
    $sql
);



$params[]=$limit;

$params[]=$offset;


$types.="ii";




mysqli_stmt_bind_param(

    $stmt,

    $types,

    ...$params

);



mysqli_stmt_execute(
    $stmt
);



$notifications_query =
mysqli_stmt_get_result(
    $stmt
);






/*
===========================================
9. USERS LIST FOR SEND MODAL
===========================================
*/


$users_list =
mysqli_query(

$conn,

"

SELECT

user_id,

full_name,

email,

role


FROM users


ORDER BY full_name ASC


"

);






/*
===========================================
10. UNREAD COUNTER
===========================================
*/


$total_unread = 0;



$unread_query =
mysqli_query(

$conn,

"

SELECT COUNT(*) total

FROM notifications

WHERE is_read=0


"

);



if($unread_query){


$data =
mysqli_fetch_assoc(
    $unread_query
);



$total_unread =
$data['total'] ?? 0;


}



?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Notification Management | HBS V3 Admin</title>

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



.unread-row{

background:#f0f9ff;

font-weight:500;

}



.message-box{

max-width:400px;

white-space:normal;

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


<div class="topbar">


<div>


<h4 class="fw-bold mb-1">

<i class="fa-solid fa-bell text-primary"></i>

Notification Center

</h4>


<small class="text-muted">

Manage system alerts and user notifications

</small>


</div>





<button class="btn btn-primary"

data-bs-toggle="modal"

data-bs-target="#sendModal">


<i class="fa-solid fa-paper-plane"></i>

Send Notification


</button>



</div>







<!-- ALERT MESSAGE -->


<?php if(isset($_GET['msg'])): ?>


<?php if($_GET['msg']=="sent"): ?>


<div class="alert alert-success">

<i class="fa-solid fa-circle-check"></i>

Notification sent successfully.

</div>



<?php elseif($_GET['msg']=="all_read"): ?>


<div class="alert alert-info">

All notifications marked as read.

</div>



<?php elseif($_GET['msg']=="read"): ?>


<div class="alert alert-info">

Notification marked as read.

</div>



<?php endif; ?>


<?php endif; ?>







<?php if(!empty($error)): ?>


<div class="alert alert-danger">

<?=htmlspecialchars($error)?>

</div>


<?php endif; ?>








<!-- SEARCH FILTER -->


<div class="card-box">


<form method="GET"

class="row g-3">


<div class="col-md-6">


<input type="text"

name="search"

class="form-control"

placeholder="Search notification..."

value="<?=htmlspecialchars($search)?>">


</div>





<div class="col-md-3">


<select name="status"

class="form-select">


<option value="">

All Status

</option>


<option value="unread"

<?=$status_filter=="unread"?'selected':''?>>

Unread

</option>



<option value="read"

<?=$status_filter=="read"?'selected':''?>>

Read

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









<!-- MARK ALL READ -->


<?php if($total_unread>0): ?>


<div class="card-box">


<form method="POST">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="mark_action"

value="all_read">



<button class="btn btn-outline-primary">


<i class="fa-solid fa-check-double"></i>


Mark All Read

(<?=$total_unread?>)


</button>


</form>


</div>


<?php endif; ?>









<!-- NOTIFICATION TABLE -->


<div class="card-box">


<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">


<tr>

<th>Type</th>

<th>Recipient</th>

<th>Content</th>

<th>Date</th>

<th>Status</th>

<th>Action</th>


</tr>


</thead>




<tbody>



<?php if($notifications_query && mysqli_num_rows($notifications_query)>0): ?>


<?php while($n=mysqli_fetch_assoc($notifications_query)): ?>



<tr class="<?=$n['is_read']==0?'unread-row':''?>">



<td>


<?php


switch($n['type'] ?? 'system'){


case 'booking':

$type_badge="bg-primary";

break;



case 'payment':

$type_badge="bg-success";

break;



case 'alert':

$type_badge="bg-danger";

break;



default:

$type_badge="bg-warning text-dark";


}



?>


<span class="badge <?=$type_badge?>">

<?=htmlspecialchars($n['type'] ?? 'system')?>

</span>


</td>







<td>


<strong>

<?=htmlspecialchars($n['recipient_name'] ?? 'System')?>

</strong>


<br>


<small>

<?=htmlspecialchars($n['recipient_email'] ?? '-')?>

</small>


</td>







<td class="message-box">


<strong>

<?=htmlspecialchars($n['title'])?>

</strong>


<br>


<small class="text-muted">

<?=htmlspecialchars($n['message'])?>

</small>


</td>







<td>


<?=

!empty($n['created_at'])

?

date(
"d M Y H:i",
strtotime($n['created_at'])
)

:

"N/A"

?>


</td>







<td>


<?php if($n['is_read']==0): ?>


<span class="badge bg-danger">

Unread

</span>


<?php else: ?>


<span class="badge bg-secondary">

Read

</span>


<?php endif; ?>


</td>







<td>



<?php if($n['is_read']==0): ?>


<form method="POST">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="notification_id"

value="<?=$n['notification_id']?>">



<input type="hidden"

name="mark_action"

value="single_read">



<button class="btn btn-sm btn-success">

<i class="fa fa-check"></i>

</button>



</form>


<?php endif; ?>


</td>



</tr>



<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="6"

class="text-center text-muted">

No notifications found.

</td>

</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     SEND NOTIFICATION MODAL
=========================== -->


<div class="modal fade"

id="sendModal"

tabindex="-1">


<div class="modal-dialog">


<div class="modal-content">



<div class="modal-header">


<h5 class="modal-title">

<i class="fa-solid fa-paper-plane"></i>

Send Notification

</h5>


<button type="button"

class="btn-close"

data-bs-dismiss="modal">

</button>


</div>





<form method="POST">



<div class="modal-body">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="send_notification"

value="1">






<div class="mb-3">


<label class="form-label">

Send To

</label>



<select name="user_id"

class="form-select"

required>


<option value="0">

Broadcast to All Users

</option>



<?php while($user=mysqli_fetch_assoc($users_list)): ?>


<option value="<?=$user['user_id']?>">


<?=htmlspecialchars($user['full_name'])?>

-

<?=htmlspecialchars($user['email'])?>



(<?=htmlspecialchars($user['role'])?>)


</option>


<?php endwhile; ?>


</select>


</div>








<div class="mb-3">


<label class="form-label">

Notification Type

</label>



<select name="type"

class="form-select">


<option value="system">

System

</option>


<option value="booking">

Booking

</option>


<option value="payment">

Payment

</option>


<option value="alert">

Alert

</option>


</select>


</div>








<div class="mb-3">


<label class="form-label">

Title

</label>


<input type="text"

name="title"

class="form-control"

required>


</div>








<div class="mb-3">


<label class="form-label">

Message

</label>



<textarea name="message"

class="form-control"

rows="4"

required></textarea>


</div>





</div>







<div class="modal-footer">


<button type="button"

class="btn btn-secondary"

data-bs-dismiss="modal">


Cancel


</button>



<button type="submit"

class="btn btn-primary">


<i class="fa-solid fa-paper-plane"></i>

Send


</button>


</div>





</form>



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


href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"


href="?page=<?=$i?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>







<li class="page-item <?=($page>=$total_pages)?'disabled':''?>">


<a class="page-link"


href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&status=<?=urlencode($status_filter)?>">


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


Admin Notification Management System


</small>


</footer>







</main>


</div>








<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>