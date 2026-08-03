<?php

require_once '../config/config.php';

/*
===========================================
1. SESSION & ADMIN AUTHENTICATION
===========================================
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login.php");
    exit();
}


$admin_id = $_SESSION['user_id'];


/*
===========================================
2. SEARCH & FILTER INPUT
===========================================
*/

$search        = trim($_GET['search'] ?? '');
$action_filter = trim($_GET['action_type'] ?? '');
$table_filter  = trim($_GET['table_name'] ?? '');



/*
===========================================
3. PAGINATION SETTINGS
===========================================
*/

$limit = 20;

$page = isset($_GET['page']) 
        ? (int)$_GET['page'] 
        : 1;

if($page < 1){
    $page = 1;
}

$offset = ($page - 1) * $limit;



/*
===========================================
4. BUILD FILTER QUERY
===========================================
*/


$where = [];
$params = [];
$types = "";


if($search !== ''){

    $where[] = "
    (
        a.action LIKE ?
        OR a.table_name LIKE ?
        OR a.ip_address LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
    )
    ";

    $keyword = "%".$search."%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "sssss";
}



if($action_filter !== ''){

    $where[] = "a.action = ?";

    $params[] = $action_filter;

    $types .= "s";
}



if($table_filter !== ''){

    $where[] = "a.table_name = ?";

    $params[] = $table_filter;

    $types .= "s";
}



$where_sql = "";

if(count($where) > 0){

    $where_sql = "WHERE " . implode(" AND ", $where);

}



/*
===========================================
5. COUNT TOTAL RECORDS
===========================================
*/


$count_sql = "

SELECT COUNT(*) AS total

FROM audit_logs a

LEFT JOIN users u 
ON a.user_id = u.user_id

$where_sql

";


$count_stmt = mysqli_prepare($conn,$count_sql);


if(!empty($params)){

    mysqli_stmt_bind_param(
        $count_stmt,
        $types,
        ...$params
    );

}


mysqli_stmt_execute($count_stmt);


$count_result = mysqli_stmt_get_result($count_stmt);


$total_records = mysqli_fetch_assoc($count_result)['total'] ?? 0;



$total_pages = ceil($total_records / $limit);



/*
===========================================
6. FETCH AUDIT LOG DATA
===========================================
*/


$sql = "

SELECT 

a.*,

u.full_name AS actor_name,

u.email AS actor_email,

u.role AS actor_role


FROM audit_logs a


LEFT JOIN users u

ON a.user_id = u.user_id


$where_sql


ORDER BY a.log_id DESC


LIMIT ? OFFSET ?

";



$stmt = mysqli_prepare($conn,$sql);



$params[] = $limit;
$params[] = $offset;


$types .= "ii";



mysqli_stmt_bind_param(

    $stmt,

    $types,

    ...$params

);



mysqli_stmt_execute($stmt);



$logs_query = mysqli_stmt_get_result($stmt);



/*
===========================================
7. ACTION FILTER LIST
===========================================
*/


$actions_list = mysqli_query(
    $conn,
    "
    SELECT DISTINCT action 
    FROM audit_logs
    ORDER BY action ASC
    "
);



/*
===========================================
8. TABLE FILTER LIST
===========================================
*/


$tables_list = mysqli_query(
    $conn,
    "
    SELECT DISTINCT table_name
    FROM audit_logs
    ORDER BY table_name ASC
    "
);



/*
===========================================
9. NOTIFICATION COUNT
===========================================
*/


$total_notifications = 0;


$notification_query = mysqli_query(

    $conn,

    "
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE is_read = 0
    "

);



if($notification_query){

    $notification_data = mysqli_fetch_assoc($notification_query);

    $total_notifications = 
        $notification_data['total'] ?? 0;

}


?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Audit Logs | HBS V3 Admin</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link 
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
rel="stylesheet">


<link 
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
rel="stylesheet">


<link 
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">



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

height:100vh;

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

display:block;

padding:12px 20px;

color:#94a3b8;

text-decoration:none;

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



.agent{

font-size:11px;

max-width:250px;

overflow:hidden;

white-space:nowrap;

text-overflow:ellipsis;

}




/* MOBILE */

@media(max-width:768px){


.sidebar{

position:relative;

width:100%;

height:auto;

}


.main-content{

margin-left:0;

width:100%;

}



.wrapper{

display:block;

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



<div class="topbar">


<div>

<h4 class="fw-bold mb-1">

<i class="fa-solid fa-clock-rotate-left"></i>

Audit Logs

</h4>


<small class="text-muted">

Track admin activities and system changes

</small>


</div>




<div>


<a href="notifications.php"
class="btn btn-light rounded-circle position-relative">


<i class="fa-solid fa-bell"></i>


<?php if($total_notifications>0): ?>

<span class="badge bg-danger position-absolute top-0 start-100 translate-middle">

<?= $total_notifications ?>

</span>

<?php endif; ?>


</a>


</div>



</div>






<!-- FILTER -->


<div class="card-box">


<form method="GET" class="row g-3">


<div class="col-md-4">

<input 
type="text"
name="search"
class="form-control"
placeholder="Search..."
value="<?=htmlspecialchars($search)?>">

</div>



<div class="col-md-3">


<select name="action_type"
class="form-select">


<option value="">
All Actions
</option>



<?php while($a=mysqli_fetch_assoc($actions_list)): ?>


<option value="<?=$a['action']?>"
<?=($action_filter==$a['action'])?'selected':''?>>


<?=htmlspecialchars($a['action'])?>


</option>


<?php endwhile; ?>


</select>


</div>





<div class="col-md-3">


<select name="table_name"
class="form-select">


<option value="">
All Tables
</option>


<?php while($t=mysqli_fetch_assoc($tables_list)): ?>


<option value="<?=$t['table_name']?>"
<?=($table_filter==$t['table_name'])?'selected':''?>>


<?=htmlspecialchars($t['table_name'])?>


</option>


<?php endwhile; ?>


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






<!-- TABLE -->


<div class="card-box">


<div class="table-responsive">


<table class="table table-hover">


<thead class="table-light">


<tr>

<th>ID</th>

<th>User</th>

<th>Action</th>

<th>Table</th>

<th>IP</th>

<th>Date</th>

</tr>


</thead>



<tbody>



<?php if(mysqli_num_rows($logs_query)>0): ?>


<?php while($log=mysqli_fetch_assoc($logs_query)): ?>



<tr>


<td>

#<?=$log['log_id']?>

</td>



<td>


<strong>

<?=htmlspecialchars($log['actor_name'] ?? 'System')?>

</strong>


<br>


<small>

<?=htmlspecialchars($log['actor_email'] ?? '-')?>

</small>


</td>




<td>


<span class="badge bg-dark">

<?=htmlspecialchars($log['action'])?>

</span>


</td>




<td>

<?=htmlspecialchars($log['table_name'] ?? '-')?>

<br>

<small>

ID:
<?=$log['record_id'] ?? '-'?>

</small>


</td>




<td>


<?=htmlspecialchars($log['ip_address'] ?? '-')?>


<br>


<small class="agent">

<?=htmlspecialchars($log['user_agent'] ?? '-')?>

</small>


</td>




<td>

<?=date(
"Y-m-d H:i",
strtotime($log['created_at'])
)?>

</td>



</tr>



<?php endwhile; ?>


<?php else: ?>


<tr>

<td colspan="6"
class="text-center text-muted">

No Audit Logs Found

</td>

</tr>


<?php endif; ?>



</tbody>


</table>


</div>


</div>







<!-- PAGINATION -->


<nav>


<ul class="pagination justify-content-center">


<?php for($i=1;$i<=$total_pages;$i++): ?>


<li class="page-item <?=($page==$i)?'active':''?>">


<a class="page-link"

href="?page=<?=$i?>&search=<?=$search?>&action_type=<?=$action_filter?>&table_name=<?=$table_filter?>">


<?=$i?>


</a>


</li>


<?php endfor; ?>


</ul>


</nav>






<footer class="text-center text-muted">

© <?=date('Y')?> Hotel Booking System V3

</footer>



</main>


</div>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>