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
SELECT HOTEL FILTER
================================
*/


$selected_hotel =

isset($_GET['hotel_id'])

?

intval($_GET['hotel_id'])

:

0;








/*
================================
FETCH OWNER HOTELS
================================
*/


$hotel_stmt=mysqli_prepare(

$conn,

"

SELECT

hotel_id,

hotel_name


FROM hotels


WHERE owner_id=?


ORDER BY hotel_name ASC

"

);



mysqli_stmt_bind_param(

$hotel_stmt,

"i",

$owner_id

);



mysqli_stmt_execute($hotel_stmt);



$owner_hotels=mysqli_stmt_get_result(

$hotel_stmt

);









/*
================================
FETCH ROOMS
================================
*/


$sql="

SELECT


r.*,


h.hotel_name,



(

SELECT

ri.image_path


FROM room_images ri


WHERE ri.room_id=r.room_id


AND ri.is_cover=1


ORDER BY ri.room_image_id ASC


LIMIT 1


) AS room_image




FROM rooms r




INNER JOIN hotels h


ON r.hotel_id=h.hotel_id





WHERE h.owner_id=?


";






$params=[

$owner_id

];


$types="i";





if($selected_hotel > 0){


$sql.="

AND r.hotel_id=?

";


$types.="i";


$params[]=$selected_hotel;


}






$sql.="

ORDER BY r.room_id DESC

";






$stmt=mysqli_prepare(

$conn,

$sql

);






mysqli_stmt_bind_param(

$stmt,

$types,

...$params

);





mysqli_stmt_execute($stmt);



$rooms_query=mysqli_stmt_get_result(

$stmt

);



?>
<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<title>
Room Inventory | Hotel Partner Hub
</title>



<meta name="viewport"

content="width=device-width, initial-scale=1.0">



<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"

rel="stylesheet">



<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"

rel="stylesheet">



<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"

rel="stylesheet">



<link href="../assets/css/owner.css"

rel="stylesheet">





<style>


body{

font-family:'Poppins',sans-serif;

background:#f4f6f9;

}





.main-content{

margin-left:260px;

padding:30px;

}





.topbar{

background:white;

padding:20px;

border-radius:15px;

box-shadow:0 3px 15px rgba(0,0,0,.05);

margin-bottom:25px;

}





.card-box{

background:white;

padding:25px;

border-radius:15px;

box-shadow:0 3px 15px rgba(0,0,0,.05);

}





.room-image{

width:65px;

height:65px;

object-fit:cover;

border-radius:10px;

}





</style>



</head>





<body>



<?php include '../includes/owner_sidebar.php'; ?>






<div class="main-content">





<div class="topbar d-flex justify-content-between align-items-center">



<div>


<h3 class="fw-bold">


<i class="fa-solid fa-bed text-primary"></i>


Room Inventory


</h3>



<p class="text-muted mb-0">


Manage your hotel rooms and availability


</p>



</div>







<a href="add_room.php?hotel_id=<?=$selected_hotel?>"
class="btn btn-primary">


<i class="fa-solid fa-plus"></i>


Add New Room


</a>




</div>









<!-- FILTER -->



<div class="card-box mb-4">



<form method="GET">



<div class="row g-3 align-items-end">





<div class="col-md-8">



<label class="form-label fw-semibold">


Select Hotel


</label>






<select name="hotel_id"

class="form-select"

onchange="this.form.submit()">





<option value="">


All Hotels


</option>







<?php while($hotel=mysqli_fetch_assoc($owner_hotels)): ?>





<option value="<?=$hotel['hotel_id']?>"



<?=

$selected_hotel == $hotel['hotel_id']

?

'selected'

:

''

?>

>



<?=htmlspecialchars($hotel['hotel_name'])?>


</option>






<?php endwhile; ?>





</select>



</div>







<div class="col-md-4">



<a href="manage_rooms.php"

class="btn btn-outline-secondary w-100">


<i class="fa-solid fa-rotate"></i>


Reset Filter


</a>



</div>





</div>



</form>



</div>









<!-- ROOM TABLE -->



<div class="card-box">





<div class="table-responsive">



<table class="table table-hover align-middle">





<thead class="table-light">



<tr>


<th>

Image

</th>


<th>

Hotel

</th>


<th>

Room

</th>


<th>

Capacity

</th>


<th>

Price

</th>


<th>

Status

</th>


<th>

Action

</th>



</tr>



</thead>









<tbody>






<?php if(mysqli_num_rows($rooms_query)>0): ?>





<?php while($room=mysqli_fetch_assoc($rooms_query)): ?>






<tr>






<td>





<?php if(!empty($room['room_image'])): ?>





<img src="../assets/images/rooms/<?=htmlspecialchars($room['room_image'])?>"

class="room-image"

onerror="this.src='../assets/images/default_room.jpg'">





<?php else: ?>





<i class="fa-solid fa-bed fa-2x text-secondary"></i>





<?php endif; ?>






</td>









<td>



<strong>


<?=htmlspecialchars($room['hotel_name'])?>


</strong>



</td>









<td>




<strong>


<?=htmlspecialchars($room['room_name'])?>


</strong>



<br>



<small class="text-muted">


<?=htmlspecialchars($room['room_type'])?>


</small>




<br>


<small>


<?=htmlspecialchars($room['bed_type'])?>


</small>




</td>









<td>




<i class="fa-solid fa-user"></i>


<?=intval($room['max_adults'])?> Adults



<br>



<i class="fa-solid fa-child"></i>


<?=$room['max_children']?> Children




</td>









<td>



<strong class="text-success">


<?=number_format($room['base_price'],2)?>


MMK


</strong>



<br>


<small>


Extra Bed:


<?=number_format($room['extra_bed_price'],2)?>


</small>



</td>









<td>





<?php if($room['room_status']=="available"): ?>



<span class="badge bg-success">


Available


</span>




<?php elseif($room['room_status']=="maintenance"): ?>



<span class="badge bg-warning text-dark">


Maintenance


</span>




<?php else: ?>



<span class="badge bg-secondary">


Inactive


</span>




<?php endif; ?>





</td>
<td>


<a href="edit_room.php?room_id=<?=$room['room_id']?>"

class="btn btn-sm btn-outline-primary">


<i class="fa-solid fa-pen"></i>


Edit


</a>





<a href="delete_room.php?room_id=<?=$room['room_id']?>"

class="btn btn-sm btn-outline-danger"

onclick="return confirm('Are you sure you want to delete this room?');">


<i class="fa-solid fa-trash"></i>


</a>



</td>





</tr>





<?php endwhile; ?>






<?php else: ?>



<tr>


<td colspan="7"

class="text-center py-5 text-muted">



<i class="fa-solid fa-bed fa-3x mb-3"></i>



<h5>

No Rooms Found

</h5>




<p>

You have not added any rooms yet.

</p>




<a href="add_room.php?hotel_id=<?=$selected_hotel?>">


<i class="fa-solid fa-plus"></i>


Add First Room


</a>




</td>


</tr>





<?php endif; ?>






</tbody>


</table>



</div>


</div>







<footer class="text-center text-muted py-4">


<small>


© <?=date('Y')?> Hotel Partner Hub

|

Room Management


</small>


</footer>






</div>









<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>


</html>