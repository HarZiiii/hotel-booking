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
3. AUDIT LOG FUNCTION
===========================================
*/


function insertAuditLog(

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
4. UPDATE USER STATUS
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['toggle_status'])
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
    intval(
        $_POST['user_id'] ?? 0
    );





    if($target_user_id == $admin_id){


        $error =
        "You cannot change your own admin status.";


    }

    else{



        $stmt = mysqli_prepare(

            $conn,

            "

            SELECT

            status

            FROM users

            WHERE user_id=?

            "

        );



        mysqli_stmt_bind_param(

            $stmt,

            "i",

            $target_user_id

        );


        mysqli_stmt_execute($stmt);



        $result =
        mysqli_stmt_get_result($stmt);



        $user =
        mysqli_fetch_assoc($result);





        if($user){



            $new_status =

            ($user['status']=="active")

            ?

            "blocked"

            :

            "active";






            $update = mysqli_prepare(

                $conn,

                "

                UPDATE users

                SET status=?

                WHERE user_id=?

                "

            );



            mysqli_stmt_bind_param(

                $update,

                "si",

                $new_status,

                $target_user_id

            );



            mysqli_stmt_execute($update);








            insertAuditLog(

                $conn,

                $admin_id,

                "USER_".strtoupper($new_status),

                "users",

                $target_user_id

            );








            /*
            Notification
            */


            $title =
            "Account Status Updated";


            $message =

            "Your account status has been changed to "

            .

            $new_status;



            $notify = mysqli_prepare(

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

                (?,?,?,'Account')

                "

            );



            mysqli_stmt_bind_param(

                $notify,

                "iss",

                $target_user_id,

                $title,

                $message

            );



            mysqli_stmt_execute($notify);





            header(
                "Location: users.php?msg=status_updated"
            );

            exit();


        }



    }


}









/*
===========================================
5. UPDATE USER ROLE
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['update_role'])
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



    $new_role =
    $_POST['role'] ?? '';





    $allowed_roles = [

        'customer',
        'owner',
        'admin'

    ];






    if(
        in_array(
            $new_role,
            $allowed_roles
        )
    ){



        if($target_user_id != $admin_id){



            $stmt = mysqli_prepare(

                $conn,

                "

                UPDATE users

                SET role=?

                WHERE user_id=?

                "

            );



            mysqli_stmt_bind_param(

                $stmt,

                "si",

                $new_role,

                $target_user_id

            );



            mysqli_stmt_execute($stmt);





            insertAuditLog(

                $conn,

                $admin_id,

                "USER_ROLE_CHANGED",

                "users",

                $target_user_id

            );





            header(
                "Location: users.php?msg=role_updated"
            );

            exit();



        }


    }



}









/*
===========================================
6. DELETE USER (SOFT DELETE)
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['delete_user'])
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





    if($target_user_id == $admin_id){


        $error =
        "You cannot delete your own account.";


    }

    else{



        $stmt = mysqli_prepare(

            $conn,

            "

            UPDATE users

            SET status='deleted'

            WHERE user_id=?

            "

        );



        mysqli_stmt_bind_param(

            $stmt,

            "i",

            $target_user_id

        );



        mysqli_stmt_execute($stmt);





        insertAuditLog(

            $conn,

            $admin_id,

            "USER_DELETED",

            "users",

            $target_user_id

        );





        header(
            "Location: users.php?msg=user_deleted"
        );

        exit();


    }


}









/*
===========================================
7. SEARCH FILTER + PAGINATION
===========================================
*/


$search =
trim(
    $_GET['search'] ?? ''
);



$role_filter =
trim(
    $_GET['role'] ?? ''
);





$where = [];

$params = [];

$types = "";







if($search !== ''){


    $where[] = "

    (

    full_name LIKE ?

    OR email LIKE ?

    OR phone LIKE ?

    )

    ";



    $keyword =
    "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;



    $types.="sss";


}







if($role_filter !== ''){


    $where[] =
    "role=?";


    $params[] =
    $role_filter;


    $types.="s";


}







$where_sql = "";



if(count($where)>0){


    $where_sql =
    "WHERE ".implode(
        " AND ",
        $where
    );


}









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









$count_stmt =
mysqli_prepare(

    $conn,

    "

    SELECT COUNT(*) total

    FROM users

    $where_sql

    "

);




if(!empty($params)){


    mysqli_stmt_bind_param(

        $count_stmt,

        $types,

        ...$params

    );


}



mysqli_stmt_execute($count_stmt);



$count_result =
mysqli_stmt_get_result($count_stmt);



$total_records =
mysqli_fetch_assoc($count_result)['total'] ?? 0;



$total_pages =
ceil(
    $total_records/$limit
);









/*
===========================================
8. FETCH USERS
===========================================
*/


$sql = "

SELECT *

FROM users

$where_sql

ORDER BY user_id DESC

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



mysqli_stmt_execute($stmt);



$users_query =
mysqli_stmt_get_result($stmt);









/*
===========================================
9. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;



$result =
mysqli_query(

$conn,

"

SELECT COUNT(*) total

FROM notifications

WHERE is_read=0

"

);



if($result){


$data =
mysqli_fetch_assoc($result);


$total_notifications =
$data['total'] ?? 0;


}



?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>User Management | HBS V3 Admin</title>

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






<!-- TOP BAR -->


<div class="topbar">


<div>


<h4 class="fw-bold mb-1">


<i class="fa-solid fa-users text-primary"></i>

User Management


</h4>


<small class="text-muted">

Manage users, roles and account access


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









<!-- ALERT -->


<?php if(!empty($error)): ?>


<div class="alert alert-danger">


<i class="fa-solid fa-circle-exclamation"></i>


<?=htmlspecialchars($error)?>


</div>


<?php endif; ?>








<?php if(isset($_GET['msg'])): ?>


<div class="alert alert-success">


<i class="fa-solid fa-circle-check"></i>



<?php


if($_GET['msg']=="status_updated"){

echo "User status updated successfully.";

}


elseif($_GET['msg']=="role_updated"){

echo "User role updated successfully.";

}


elseif($_GET['msg']=="user_deleted"){

echo "User deleted successfully.";

}


?>



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

placeholder="Search name, email, phone..."

value="<?=htmlspecialchars($search)?>">


</div>






<div class="col-md-4">


<select name="role"

class="form-select">


<option value="">

All Roles

</option>




<option value="customer"

<?=$role_filter=="customer"?'selected':''?>>

Customer

</option>





<option value="owner"

<?=$role_filter=="owner"?'selected':''?>>

Owner

</option>





<option value="admin"

<?=$role_filter=="admin"?'selected':''?>>

Admin

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









<!-- USERS TABLE -->


<div class="card-box">


<h5 class="fw-bold mb-3">


<i class="fa-solid fa-list text-primary"></i>

System Users


</h5>






<div class="table-responsive">


<table class="table table-hover align-middle">



<thead class="table-light">


<tr>


<th>ID</th>

<th>User Information</th>

<th>Role</th>

<th>Status</th>

<th>Joined</th>

<th>Action</th>


</tr>


</thead>





<tbody>



<?php if($users_query && mysqli_num_rows($users_query)>0): ?>



<?php while($u=mysqli_fetch_assoc($users_query)): ?>



<tr>



<td>

#<?=$u['user_id']?>

</td>








<td>


<strong>

<?=htmlspecialchars($u['full_name'])?>

</strong>


<br>


<small>

<i class="fa fa-envelope"></i>

<?=htmlspecialchars($u['email'])?>

</small>



<br>


<?php if(!empty($u['phone'])): ?>


<small>

<i class="fa fa-phone"></i>

<?=htmlspecialchars($u['phone'])?>

</small>


<?php endif; ?>


</td>







<td>



<form method="POST">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">



<input type="hidden"

name="user_id"

value="<?=$u['user_id']?>">



<input type="hidden"

name="update_role"

value="1">





<select name="role"

class="form-select form-select-sm"

onchange="this.form.submit()"

<?=($u['user_id']==$admin_id)?'disabled':''?>>



<option value="customer"

<?=$u['role']=="customer"?'selected':''?>>

Customer

</option>





<option value="owner"

<?=$u['role']=="owner"?'selected':''?>>

Owner

</option>





<option value="admin"

<?=$u['role']=="admin"?'selected':''?>>

Admin

</option>



</select>


</form>



</td>









<td>



<?php if($u['status']=="active"): ?>


<span class="badge bg-success">

<i class="fa fa-check"></i>

Active

</span>



<?php elseif($u['status']=="deleted"): ?>


<span class="badge bg-secondary">

Deleted

</span>



<?php else: ?>


<span class="badge bg-danger">

Blocked

</span>


<?php endif; ?>


</td>







<td>


<?=

!empty($u['created_at'])

?

date(
"d M Y",
strtotime($u['created_at'])
)

:

"N/A"

?>


</td>








<td>



<?php if($u['user_id'] != $admin_id): ?>



<form method="POST"

class="d-inline"

onsubmit="return confirm('Change user status?');">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="user_id"

value="<?=$u['user_id']?>">


<input type="hidden"

name="toggle_status"

value="1">



<button class="btn btn-sm btn-outline-warning">


<i class="fa fa-ban"></i>


</button>


</form>






<form method="POST"

class="d-inline"

onsubmit="return confirm('Delete this user?');">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="user_id"

value="<?=$u['user_id']?>">


<input type="hidden"

name="delete_user"

value="1">



<button class="btn btn-sm btn-outline-danger">


<i class="fa fa-trash"></i>


</button>


</form>





<?php else: ?>


<span class="badge bg-light text-dark">

Current Admin

</span>


<?php endif; ?>



</td>




</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>

<td colspan="6"

class="text-center text-muted">

No users found.

</td>


</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     USER SUMMARY
=========================== -->


<div class="row g-3 mb-4">



<div class="col-md-4">


<div class="card-box text-center">


<i class="fa-solid fa-users fa-2x text-primary mb-2"></i>


<h6 class="text-muted">

Total Users

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


<i class="fa-solid fa-user-shield fa-2x text-warning mb-2"></i>


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

href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>&role=<?=urlencode($role_filter)?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=urlencode($search)?>&role=<?=urlencode($role_filter)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>








<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"

href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>&role=<?=urlencode($role_filter)?>">


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


Secure User Management System


</small>


</footer>







</main>


</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>