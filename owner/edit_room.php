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
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'owner'
){

    header("Location: ../login.php");
    exit();

}



$owner_id = $_SESSION['user_id'];



$error = "";

$success = "";





/*
================================
ROOM ID
================================
*/


$room_id = intval($_GET['id'] ?? 0);



if($room_id <= 0){

    header("Location: manage_rooms.php");

    exit();

}









/*
================================
IMAGE UPLOAD FUNCTION
================================
*/


function uploadRoomImage($file){


    if(
        !isset($file)
        ||
        $file['error'] !== UPLOAD_ERR_OK
    ){

        return "";

    }




    $allowed = [

        "jpg",
        "jpeg",
        "png",
        "webp"

    ];



    $ext = strtolower(

        pathinfo(

            $file['name'],

            PATHINFO_EXTENSION

        )

    );



    if(!in_array($ext,$allowed)){

        return "";

    }




    if($file['size'] > 2 * 1024 * 1024){

        return "";

    }






    $folder="../assets/images/rooms/";



    if(!is_dir($folder)){

        mkdir(

            $folder,

            0755,

            true

        );

    }






    $filename =

    "room_"

    .time()

    ."_"

    .bin2hex(random_bytes(5))

    .".".$ext;







    if(

        move_uploaded_file(

            $file['tmp_name'],

            $folder.$filename

        )

    ){

        return $filename;

    }



    return "";

}









/*
================================
FETCH ROOM DATA
================================
*/


$stmt = mysqli_prepare(

    $conn,

    "

    SELECT

    r.*,

    h.hotel_name


    FROM rooms r


    INNER JOIN hotels h


    ON r.hotel_id=h.hotel_id



    WHERE r.room_id=?

    AND h.owner_id=?


    "

);





mysqli_stmt_bind_param(

    $stmt,

    "ii",

    $room_id,

    $owner_id

);





mysqli_stmt_execute($stmt);



$result = mysqli_stmt_get_result($stmt);



$room = mysqli_fetch_assoc($result);





if(!$room){

    die("Room not found or unauthorized access.");

}









/*
================================
UPDATE ROOM
================================
*/


if(

$_SERVER['REQUEST_METHOD']=="POST"

&&

isset($_POST['update_room'])

){





$room_name = trim($_POST['room_name']);

$room_type = trim($_POST['room_type']);

$bed_type = trim($_POST['bed_type']);

$room_size = trim($_POST['room_size']);

$max_adults = intval($_POST['max_adults']);

$max_children = intval($_POST['max_children']);

$total_rooms = intval($_POST['total_rooms']);

$base_price = floatval($_POST['base_price']);

$extra_bed_price = floatval($_POST['extra_bed_price']);

$description = trim($_POST['room_description']);

$status = $_POST['room_status'];





$new_image = $room['room_image'];





if(isset($_FILES['room_image'])){


    $upload = uploadRoomImage(

        $_FILES['room_image']

    );



    if($upload!=""){


        $new_image=$upload;


    }


}







$update = mysqli_prepare(

    $conn,

    "

    UPDATE rooms


    SET


    room_name=?,

    room_type=?,

    bed_type=?,

    room_size=?,

    max_adults=?,

    max_children=?,

    total_rooms=?,

    base_price=?,

    extra_bed_price=?,

    room_description=?,

    room_status=?,

    room_image=?


    WHERE room_id=?


    "

);






mysqli_stmt_bind_param(

    $update,

    "ssssiiiddsssi",

    $room_name,

    $room_type,

    $bed_type,

    $room_size,

    $max_adults,

    $max_children,

    $total_rooms,

    $base_price,

    $extra_bed_price,

    $description,

    $status,

    $new_image,

    $room_id

);






if(mysqli_stmt_execute($update)){



    /*
    ============================
    ADD NEW GALLERY IMAGES
    ============================
    */


    if(

        isset($_FILES['gallery'])

        &&

        !empty($_FILES['gallery']['name'][0])

    ){



        foreach(

            $_FILES['gallery']['tmp_name']

            as $key=>$tmp

        ){



            $gallery=[


                "name"=>

                $_FILES['gallery']['name'][$key],


                "tmp_name"=>$tmp,


                "size"=>

                $_FILES['gallery']['size'][$key],


                "error"=>

                $_FILES['gallery']['error'][$key]


            ];




            $img=uploadRoomImage($gallery);




            if($img!=""){



                $g=mysqli_prepare(

                    $conn,

                    "

                    INSERT INTO room_images

                    (

                    room_id,

                    image_path

                    )

                    VALUES(?,?)

                    "

                );





                mysqli_stmt_bind_param(

                    $g,

                    "is",

                    $room_id,

                    $img

                );





                mysqli_stmt_execute($g);



            }



        }



    }






    header(

        "Location: edit_room.php?id=".$room_id."&msg=updated"

    );


    exit();



}



}



/*
================================
FETCH ROOM GALLERY
================================
*/

$gallery_stmt = mysqli_prepare(

    $conn,

    "

    SELECT *

    FROM room_images

    WHERE room_id=?

    "

);



mysqli_stmt_bind_param(

    $gallery_stmt,

    "i",

    $room_id

);



mysqli_stmt_execute($gallery_stmt);



$gallery_query =

mysqli_stmt_get_result($gallery_stmt);



?>






<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<title>

Edit Room | Hotel Partner Hub

</title>


<meta name="viewport"

content="width=device-width, initial-scale=1.0">





<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"

rel="stylesheet">



<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"

rel="stylesheet">



<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"

rel="stylesheet">





<style>


body{

font-family:'Poppins',sans-serif;

background:#f4f6f9;

}



.sidebar{

width:260px;

height:100vh;

position:fixed;

left:0;

top:0;

background:#0f172a;

color:white;

}



.brand{

padding:20px;

font-size:19px;

font-weight:700;

color:#38bdf8;

}



.sidebar a{

display:block;

padding:13px 20px;

color:#cbd5e1;

text-decoration:none;

}



.sidebar a:hover{

background:#1e293b;

color:#38bdf8;

}



.main-content{

margin-left:260px;

padding:30px;

}



.card-box{

background:white;

padding:25px;

border-radius:15px;

box-shadow:0 3px 15px rgba(0,0,0,.05);

}



.preview-img{

width:160px;

height:120px;

object-fit:cover;

border-radius:12px;

}



.gallery-img{

width:120px;

height:100px;

object-fit:cover;

border-radius:10px;

}




</style>
<link href="../assets/css/owner.css" rel="stylesheet">

</head>




<body>








<?php include '../includes/owner_sidebar.php'; ?>









<div class="main-content">






<div class="d-flex justify-content-between align-items-center mb-4">



<div>


<h3 class="fw-bold">


<i class="fa-solid fa-pen text-primary"></i>


Edit Room Details


</h3>



<p class="text-muted">


<?=$room['hotel_name']?>


</p>


</div>







<a href="manage_rooms.php"

class="btn btn-outline-secondary">


<i class="fa-solid fa-arrow-left"></i>


Back


</a>



</div>









<?php if(isset($_GET['msg'])): ?>


<div class="alert alert-success">


Room updated successfully.


</div>


<?php endif; ?>









<div class="card-box">






<form method="POST"

enctype="multipart/form-data">





<input type="hidden"

name="update_room"

value="1">







<div class="row g-4">





<div class="col-md-6">


<label class="form-label fw-bold">

Room Name

</label>



<input type="text"

name="room_name"

class="form-control"

value="<?=htmlspecialchars($room['room_name'])?>"

required>


</div>







<div class="col-md-6">


<label class="form-label fw-bold">

Room Type

</label>



<input type="text"

name="room_type"

class="form-control"

value="<?=htmlspecialchars($room['room_type'])?>">


</div>







<div class="col-md-4">


<label class="form-label">

Bed Type

</label>


<input type="text"

name="bed_type"

class="form-control"

value="<?=htmlspecialchars($room['bed_type'])?>">


</div>







<div class="col-md-4">


<label class="form-label">

Room Size

</label>


<input type="text"

name="room_size"

class="form-control"

value="<?=htmlspecialchars($room['room_size'])?>">


</div>







<div class="col-md-4">


<label class="form-label">

Total Rooms

</label>


<input type="number"

name="total_rooms"

class="form-control"

value="<?=$room['total_rooms']?>">


</div>







<div class="col-md-4">


<label>

Max Adults

</label>


<input type="number"

name="max_adults"

class="form-control"

value="<?=$room['max_adults']?>">


</div>







<div class="col-md-4">


<label>

Max Children

</label>


<input type="number"

name="max_children"

class="form-control"

value="<?=$room['max_children']?>">


</div>







<div class="col-md-4">


<label>

Status

</label>



<select name="room_status"

class="form-select">


<option value="available"

<?=$room['room_status']=="available"?"selected":""?>>

Available

</option>



<option value="maintenance"

<?=$room['room_status']=="maintenance"?"selected":""?>>

Maintenance

</option>



<option value="inactive"

<?=$room['room_status']=="inactive"?"selected":""?>>

Inactive

</option>



</select>


</div>









<div class="col-md-6">


<label>

Base Price

</label>



<input type="number"

name="base_price"

class="form-control"

value="<?=$room['base_price']?>">


</div>







<div class="col-md-6">


<label>

Extra Bed Price

</label>



<input type="number"

name="extra_bed_price"

class="form-control"

value="<?=$room['extra_bed_price']?>">


</div>









<div class="col-12">


<label>

Room Description

</label>



<textarea

name="room_description"

class="form-control"

rows="5"><?=htmlspecialchars($room['room_description'])?></textarea>


</div>









<div class="col-md-6">


<label class="fw-bold">

Change Main Image

</label>



<input type="file"

name="room_image"

class="form-control"

accept="image/*">


</div>








<div class="col-md-6">


<label>

Current Image

</label>


<br>



<?php if(!empty($room['room_image'])): ?>


<img src="../assets/images/rooms/<?=$room['room_image']?>"

class="preview-img">


<?php else: ?>


<span class="text-muted">

No Image

</span>


<?php endif; ?>



</div>









<div class="col-12">


<label class="fw-bold">

Add More Gallery Images

</label>



<input type="file"

name="gallery[]"

class="form-control"

multiple

accept="image/*">


</div>







</div>







<button class="btn btn-success mt-4 px-5">


<i class="fa-solid fa-save"></i>


Update Room


</button>






</form>



</div>








<!-- GALLERY -->



<div class="card-box mt-4">



<h5 class="fw-bold mb-3">


<i class="fa-solid fa-images"></i>


Room Gallery


</h5>




<div class="row g-3">



<?php if(mysqli_num_rows($gallery_query)>0): ?>



<?php while($img=mysqli_fetch_assoc($gallery_query)): ?>



<div class="col-md-3">


<img src="../assets/images/rooms/<?=htmlspecialchars($img['image_path'])?>"

class="gallery-img">


</div>



<?php endwhile; ?>



<?php else: ?>


<p class="text-muted">

No gallery images.

</p>



<?php endif; ?>



</div>



</div>






</div>








<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


</body>


</html>