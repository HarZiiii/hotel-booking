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
$success = "";






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
3. CREATE NEW OWNER
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['create_owner'])
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






    $full_name =
    trim($_POST['full_name'] ?? '');



    $email =
    trim($_POST['email'] ?? '');



    $phone =
    trim($_POST['phone'] ?? '');



    $password =
    $_POST['password'] ?? '';






    if(
        empty($full_name) ||
        empty($email) ||
        empty($password)
    ){


        $error =
        "Please fill all required fields.";


    }

    elseif(
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ){


        $error =
        "Invalid email address.";


    }

    elseif(strlen($password) < 8){


        $error =
        "Password must be at least 8 characters.";


    }

    else{



        /*
        ===========================
        CHECK EMAIL
        ===========================
        */


        $check_stmt = mysqli_prepare(

            $conn,

            "
            SELECT user_id

            FROM users

            WHERE email = ?

            "

        );



        mysqli_stmt_bind_param(

            $check_stmt,

            "s",

            $email

        );



        mysqli_stmt_execute(
            $check_stmt
        );



        $check_result =
        mysqli_stmt_get_result(
            $check_stmt
        );





        if(mysqli_num_rows($check_result)>0){


            $error =
            "Email address is already registered.";


        }

        else{



            // Owner accounts are admin-created only. Build the required user fields
            // so this form stays compatible with the users table schema.
            $name_parts = preg_split('/\s+/', $full_name, 2);
            $first_name = $name_parts[0] ?? 'Hotel';
            $last_name = $name_parts[1] ?? 'Owner';
            $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', strstr($email, '@', true) ?: 'hotelowner'));
            if($base_username === '') $base_username = 'hotelowner';
            $username = $base_username;
            $suffix = 1;
            while(true){
                $ucheck = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username=? LIMIT 1");
                mysqli_stmt_bind_param($ucheck, "s", $username);
                mysqli_stmt_execute($ucheck);
                if(mysqli_num_rows(mysqli_stmt_get_result($ucheck)) === 0) break;
                $username = $base_username . (++$suffix);
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $address = 'Not provided';
            $city = 'Not provided';
            $country = 'Myanmar';
            $role = 'owner';






            /*
            ===========================
            INSERT OWNER ACCOUNT
            ===========================
            */


            $insert_stmt = mysqli_prepare(

                $conn,

                "

                INSERT INTO users
                (username, full_name, first_name, last_name, email, phone, password, role, address, city, country, status, created_at)
                VALUES
                (?,?,?,?,?,?,?,?,?,?,?,'active',NOW())


                "

            );



            mysqli_stmt_bind_param(
                $insert_stmt,
                "sssssssssss",
                $username,
                $full_name,
                $first_name,
                $last_name,
                $email,
                $phone,
                $hashed_password,
                $role,
                $address,
                $city,
                $country
            );





            if(mysqli_stmt_execute($insert_stmt)){



                $new_owner_id =
                mysqli_insert_id($conn);







                /*
                ===========================
                AUDIT LOG
                ===========================
                */


                insertAuditLog(
                    $conn,
                    $admin_id,
                    "OWNER_CREATED",
                    "users",
                    $new_owner_id
                );





                header(
                    "Location: owners.php?msg=owner_created"
                );


                exit();



            }

            else{


                $error =
                "Failed to create owner account.";


            }


        }


    }



}









/*
===========================================
4. OWNER STATUS UPDATE
===========================================
*/


if(
    $_SERVER['REQUEST_METHOD']==='POST'
    &&
    isset($_POST['toggle_owner_status'])
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




    $owner_id =
    intval(
        $_POST['owner_id'] ?? 0
    );




    if($owner_id > 0){



        $status_stmt = mysqli_prepare(

            $conn,

            "

            SELECT status

            FROM users

            WHERE user_id=?

            AND role='owner'

            "

        );



        mysqli_stmt_bind_param(

            $status_stmt,

            "i",

            $owner_id

        );



        mysqli_stmt_execute(
            $status_stmt
        );



        $status_result =
        mysqli_stmt_get_result(
            $status_stmt
        );



        $owner =
        mysqli_fetch_assoc(
            $status_result
        );






        if($owner){



            $new_status =

            ($owner['status']=="active")

            ?

            "blocked"

            :

            "active";







            $update_stmt = mysqli_prepare(

                $conn,

                "

                UPDATE users

                SET status=?

                WHERE user_id=?

                "

            );



            mysqli_stmt_bind_param(

                $update_stmt,

                "si",

                $new_status,

                $owner_id

            );



            mysqli_stmt_execute(
                $update_stmt
            );








            insertAuditLog(

                $conn,

                $admin_id,

                "OWNER_STATUS_".strtoupper($new_status),

                "users",

                $owner_id

            );








            header(
                "Location: owners.php?msg=status_updated"
            );


            exit();


        }


    }


}









/*
===========================================
5. AUDIT LOG FUNCTION
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
6. SEARCH & PAGINATION
===========================================
*/


$search =
trim(
    $_GET['search'] ?? ''
);



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






$where = "

WHERE u.role='owner'

";


$params = [];

$types = "";





if($search !== ''){


    $where .= "

    AND

    (

    u.full_name LIKE ?

    OR u.email LIKE ?

    OR u.phone LIKE ?

    )

    ";



    $keyword =
    "%".$search."%";



    $params[]=$keyword;

    $params[]=$keyword;

    $params[]=$keyword;


    $types .= "sss";


}








/*
===========================================
7. COUNT OWNERS
===========================================
*/


$count_sql = "

SELECT COUNT(*) total

FROM users u

$where

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
8. FETCH OWNERS
===========================================
*/


$sql = "

SELECT

u.*,

COUNT(h.hotel_id) AS total_hotels


FROM users u


LEFT JOIN hotels h

ON u.user_id=h.owner_id



$where



GROUP BY u.user_id



ORDER BY u.user_id DESC



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



mysqli_stmt_execute($stmt);



$owners_query =
mysqli_stmt_get_result(
    $stmt
);






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

<title>Hotel Owners Management | HBS V3 Admin</title>

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



<!-- TOPBAR -->


<div class="topbar">


<div>


<h4 class="fw-bold mb-1">

<i class="fa-solid fa-user-tie text-warning"></i>

Hotel Owners Management

</h4>


<small class="text-muted">

Manage owner accounts and hotel ownership

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


if($_GET['msg']=="owner_created"){

echo "New owner account created successfully.";

}


elseif($_GET['msg']=="status_updated"){

echo "Owner status updated successfully.";

}


?>


</div>


<?php endif; ?>









<!-- SEARCH + ADD -->


<div class="card-box">


<div class="row g-3 align-items-center">


<div class="col-md-8">


<form method="GET"

class="d-flex gap-2">


<input type="text"

name="search"

class="form-control"

placeholder="Search owner name, email, phone..."

value="<?=htmlspecialchars($search)?>">



<button class="btn btn-primary">


<i class="fa fa-search"></i>

Search


</button>



<a href="owners.php"

class="btn btn-outline-secondary">

Reset

</a>


</form>


</div>







<div class="col-md-4 text-md-end">


<button class="btn btn-success"

data-bs-toggle="modal"

data-bs-target="#addOwnerModal">


<i class="fa-solid fa-user-plus"></i>

Add Owner


</button>


</div>


</div>


</div>









<!-- OWNER TABLE -->


<div class="card-box">


<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">


<tr>

<th>ID</th>

<th>Owner</th>

<th>Contact</th>

<th>Hotels</th>

<th>Status</th>

<th>Created</th>

<th>Action</th>


</tr>


</thead>



<tbody>



<?php if($owners_query && mysqli_num_rows($owners_query)>0): ?>



<?php while($owner=mysqli_fetch_assoc($owners_query)): ?>


<tr>



<td>

#<?=$owner['user_id']?>

</td>





<td>


<strong>

<?=htmlspecialchars($owner['full_name'])?>

</strong>


<br>


<span class="badge bg-warning text-dark">

Hotel Owner

</span>


</td>






<td>


<i class="fa fa-envelope text-primary"></i>

<?=htmlspecialchars($owner['email'])?>


<br>


<i class="fa fa-phone text-success"></i>

<?=htmlspecialchars($owner['phone'] ?? '-')?>


</td>







<td>


<a href="hotels.php?owner_id=<?=$owner['user_id']?>"

class="badge bg-info text-dark text-decoration-none">


<i class="fa-solid fa-hotel"></i>


<?=$owner['total_hotels']?> Hotel(s)


</a>


</td>







<td>



<?php if($owner['status']=="active"): ?>


<span class="badge bg-success">

Active

</span>



<?php else: ?>


<span class="badge bg-danger">

Blocked

</span>



<?php endif; ?>


</td>








<td>


<?=

!empty($owner['created_at'])

?

date(
"d M Y",
strtotime($owner['created_at'])
)

:

"N/A"

?>


</td>








<td>



<form method="POST"

onsubmit="return confirm('Change owner status?');">



<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="owner_id"

value="<?=$owner['user_id']?>">



<input type="hidden"

name="toggle_owner_status"

value="1">



<?php if($owner['status']=="active"): ?>


<button class="btn btn-sm btn-outline-danger">


<i class="fa fa-ban"></i>

Ban


</button>



<?php else: ?>


<button class="btn btn-sm btn-outline-success">


<i class="fa fa-check"></i>

Activate


</button>



<?php endif; ?>



</form>


</td>


</tr>



<?php endwhile; ?>



<?php else: ?>


<tr>


<td colspan="7"

class="text-center text-muted">

No hotel owners found.

</td>


</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>
<!-- ===========================
     ADD OWNER MODAL
=========================== -->


<div class="modal fade"

id="addOwnerModal"

tabindex="-1">


<div class="modal-dialog">


<div class="modal-content">



<form method="POST">



<div class="modal-header">


<h5 class="modal-title fw-bold">

<i class="fa-solid fa-user-plus text-success"></i>

Add New Hotel Owner

</h5>



<button type="button"

class="btn-close"

data-bs-dismiss="modal">

</button>


</div>







<div class="modal-body">


<input type="hidden"

name="csrf_token"

value="<?=$_SESSION['csrf_token']?>">


<input type="hidden"

name="create_owner"

value="1">





<div class="mb-3">


<label class="form-label">

Full Name

</label>



<input type="text"

name="full_name"

class="form-control"

placeholder="Enter owner name"

required>


</div>







<div class="mb-3">


<label class="form-label">

Email Address

</label>



<input type="email"

name="email"

class="form-control"

placeholder="owner@example.com"

required>


</div>







<div class="mb-3">


<label class="form-label">

Phone Number

</label>



<input type="text"

name="phone"

class="form-control"

placeholder="09xxxxxxxxx">


</div>







<div class="mb-3">


<label class="form-label">

Password

</label>



<input type="password"

name="password"

class="form-control"

placeholder="Minimum 6 characters"

required>


</div>



</div>







<div class="modal-footer">


<button type="button"

class="btn btn-secondary"

data-bs-dismiss="modal">


Cancel


</button>




<button type="submit"

class="btn btn-success">


<i class="fa-solid fa-save"></i>

Create Owner


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


href="?page=<?=($page-1)?>&search=<?=urlencode($search)?>">


Previous


</a>


</li>








<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"


href="?page=<?=$i?>&search=<?=urlencode($search)?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>









<li class="page-item <?=($page >= $total_pages)?'disabled':''?>">


<a class="page-link"


href="?page=<?=($page+1)?>&search=<?=urlencode($search)?>">


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


Secure Admin Owner Management System


</small>


</footer>







</main>


</div>








<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>