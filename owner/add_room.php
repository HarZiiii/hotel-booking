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



/*
================================
IMAGE UPLOAD
================================
*/

function uploadRoomImage($file)
{

    if(
        !isset($file) ||
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



    if(!in_array($ext,$allowed))
    {
        return "";
    }



    if($file['size'] > 2 * 1024 * 1024)
    {
        return "";
    }



    $folder = "../assets/images/rooms/";



    if(!is_dir($folder))
    {
        mkdir(
            $folder,
            0755,
            true
        );
    }



    $filename =
        "room_".
        time().
        "_".
        bin2hex(random_bytes(5)).
        ".".$ext;



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
ADD ROOM
================================
*/


if(
$_SERVER['REQUEST_METHOD']=="POST"
&&
isset($_POST['add_room'])
)
{


    $hotel_id = intval($_POST['hotel_id']);



    /*
    CHECK OWNER HOTEL
    */


    $check = mysqli_prepare(
        $conn,

        "
        SELECT hotel_id
        FROM hotels
        WHERE hotel_id=?
        AND owner_id=?
        "
    );



    mysqli_stmt_bind_param(
        $check,
        "ii",
        $hotel_id,
        $owner_id
    );


    mysqli_stmt_execute($check);



    $result =
    mysqli_stmt_get_result($check);



    if(mysqli_num_rows($result)==0)
    {

        $error="Invalid hotel selected.";

    }

    else
    {



        $room_name =
        trim($_POST['room_name']);



        $room_type =
        $_POST['room_type'];



        $bed_type =
        $_POST['bed_type'];



        $room_size =
        trim($_POST['room_size']);



        $room_size_unit =
        $_POST['room_size_unit'];



        $max_adults =
        intval($_POST['max_adults']);



        $max_children =
        intval($_POST['max_children']);



        $total_rooms =
        intval($_POST['total_rooms']);



        $base_price =
        floatval($_POST['base_price']);



        $extra_bed_price =
        floatval($_POST['extra_bed_price']);



        $description =
        trim($_POST['room_description']);



        $status =
        $_POST['room_status'];




        /*
        MAIN IMAGE
        */


        $main_image="";



        if(isset($_FILES['room_image']))
        {

            $main_image =
            uploadRoomImage(
                $_FILES['room_image']
            );

        }







        /*
        INSERT ROOM
        */


        $stmt=mysqli_prepare(

            $conn,

            "
            INSERT INTO rooms

            (
            hotel_id,
            room_name,
            room_type,
            bed_type,
            room_size,
            room_size_unit,
            max_adults,
            max_children,
            total_rooms,
            base_price,
            extra_bed_price,
            room_description,
            room_status
            )

            VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?)

            "

        );





        mysqli_stmt_bind_param(

            $stmt,

            "isssssiiiddsss",

            $hotel_id,
            $room_name,
            $room_type,
            $bed_type,
            $room_size,
            $room_size_unit,
            $max_adults,
            $max_children,
            $total_rooms,
            $base_price,
            $extra_bed_price,
            $description,
            $status

        );




        if(mysqli_stmt_execute($stmt))
        {


            $room_id =
            mysqli_insert_id($conn);




            /*
            SAVE MAIN IMAGE
            */


            if($main_image!="")
            {


                $imgStmt=mysqli_prepare(

                    $conn,

                    "
                    INSERT INTO room_images

                    (
                    room_id,
                    image_path,
                    is_cover
                    )

                    VALUES(?,?,1)

                    "

                );



                mysqli_stmt_bind_param(

                    $imgStmt,

                    "is",

                    $room_id,

                    $main_image

                );



                mysqli_stmt_execute($imgStmt);


            }

            /*
            GALLERY UPLOAD
            */


            if(

                isset($_FILES['gallery'])

                &&

                !empty($_FILES['gallery']['name'][0])

            )
            {


                foreach(

                    $_FILES['gallery']['tmp_name']

                    as $key=>$tmp

                )
                {


                    $gallery = [

                        "name" =>
                        $_FILES['gallery']['name'][$key],

                        "tmp_name" =>
                        $tmp,


                        "size" =>
                        $_FILES['gallery']['size'][$key],


                        "error" =>
                        $_FILES['gallery']['error'][$key]

                    ];




                    $image =
                    uploadRoomImage($gallery);




                    if($image!="")
                    {


                        $gstmt=mysqli_prepare(

                            $conn,

                            "
                            INSERT INTO room_images

                            (
                            room_id,
                            image_path,
                            is_cover
                            )

                            VALUES(?,?,0)

                            "

                        );



                        mysqli_stmt_bind_param(

                            $gstmt,

                            "is",

                            $room_id,

                            $image

                        );



                        mysqli_stmt_execute($gstmt);



                    }



                }



            }






            header(

                "Location: manage_rooms.php?msg=room_added"

            );


            exit();



        }

        else
        {

            $error =
            "Failed to save room.";

        }




    }



}









/*
================================
FETCH OWNER HOTELS
================================
*/


$hotels=mysqli_prepare(

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

    $hotels,

    "i",

    $owner_id

);




mysqli_stmt_execute($hotels);



$hotel_list =
mysqli_stmt_get_result($hotels);



?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>
Add New Room | Hotel Partner Hub
</title>


<meta name="viewport" content="width=device-width, initial-scale=1.0">


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


.form-label{

font-weight:600;

}


.preview-image{

width:120px;

height:120px;

object-fit:cover;

border-radius:12px;

display:none;

margin-top:10px;

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

<i class="fa-solid fa-plus text-primary"></i>

Add New Room

</h3>


<p class="text-muted">

Create a new room for your hotel

</p>


</div>



<a href="manage_rooms.php"

class="btn btn-outline-secondary">

<i class="fa-solid fa-arrow-left"></i>

Back

</a>



</div>






<?php if($error!=""): ?>

<div class="alert alert-danger">

<?=$error?>

</div>

<?php endif; ?>






<div class="card-box">



<form method="POST"

enctype="multipart/form-data">


<input type="hidden"

name="add_room"

value="1">





<div class="row g-4">





<!-- HOTEL -->

<div class="col-md-6">


<label class="form-label">

Select Hotel

</label>



<select name="hotel_id"

class="form-select"

required>


<option value="">

Choose Hotel

</option>



<?php while($hotel=mysqli_fetch_assoc($hotel_list)): ?>


<option value="<?=$hotel['hotel_id']?>">

<?=htmlspecialchars($hotel['hotel_name'])?>

</option>


<?php endwhile; ?>


</select>


</div>






<!-- ROOM NAME -->


<div class="col-md-6">


<label class="form-label">

Room Name

</label>


<input type="text"

name="room_name"

class="form-control"

placeholder="Example: Deluxe Room"

required>


</div>








<!-- ROOM TYPE FIXED -->


<div class="col-md-4">


<label class="form-label">

Room Type

</label>


<select name="room_type"

class="form-select"

required>


<option value="Single">

Single

</option>


<option value="Double">

Double

</option>


<option value="Twin">

Twin

</option>


<option value="Triple">

Triple

</option>


<option value="Deluxe">

Deluxe

</option>


<option value="Suite">

Suite

</option>


<option value="Family">

Family

</option>


<option value="Executive">

Executive

</option>


</select>


</div>








<!-- BED TYPE FIXED -->


<div class="col-md-4">


<label class="form-label">

Bed Type

</label>


<select name="bed_type"

class="form-select"

required>


<option value="Single Bed">

Single Bed

</option>


<option value="Double Bed">

Double Bed

</option>


<option value="Queen Bed">

Queen Bed

</option>


<option value="King Bed">

King Bed

</option>


<option value="Twin Bed">

Twin Bed

</option>


<option value="Mixed">

Mixed

</option>


</select>


</div>







<!-- SIZE -->


<div class="col-md-4">


<label class="form-label">

Room Size

</label>


<input type="number"
step="0.01"
name="room_size"
class="form-control"
placeholder="30">
<select name="room_size_unit"
class="form-select">

<option value="sqm">
Square Meter
</option>

<option value="sqft">
Square Feet
</option>

</select>


</div>








<!-- ADULT -->


<div class="col-md-4">


<label class="form-label">

Max Adults

</label>


<input type="number"

name="max_adults"

class="form-control"

value="2"

min="1">


</div>








<!-- CHILD -->


<div class="col-md-4">


<label class="form-label">

Max Children

</label>


<input type="number"

name="max_children"

class="form-control"

value="0"

min="0">


</div>







<!-- TOTAL -->


<div class="col-md-4">


<label class="form-label">

Total Rooms

</label>


<input type="number"

name="total_rooms"

class="form-control"

value="1"

min="1">


</div>








<!-- PRICE -->


<div class="col-md-6">


<label class="form-label">

Base Price (MMK)

</label>


<input type="number"

name="base_price"

class="form-control"

required>


</div>







<div class="col-md-6">


<label class="form-label">

Extra Bed Price (MMK)

</label>


<input type="number"

name="extra_bed_price"

class="form-control"

value="0">


</div>








<!-- STATUS -->


<div class="col-md-6">


<label class="form-label">

Room Status

</label>


<select name="room_status"

class="form-select">


<option value="available">

Available

</option>


<option value="maintenance">

Maintenance

</option>


<option value="inactive">

Inactive

</option>


</select>


</div>








<!-- MAIN IMAGE -->


<div class="col-md-6">


<label class="form-label">

Main Room Image

</label>


<input type="file"

name="room_image"

class="form-control"

accept="image/*"

onchange="previewMain(this)">


<img id="imagePreview"

class="preview-image">


</div>








<!-- DESCRIPTION -->


<div class="col-12">


<label class="form-label">

Room Description

</label>


<textarea

name="room_description"

class="form-control"

rows="5"

placeholder="Describe this room..."></textarea>


</div>








<!-- GALLERY -->


<div class="col-12">


<label class="form-label">

Room Gallery Images

</label>


<input type="file"

name="gallery[]"

class="form-control"

multiple

accept="image/*">


<small class="text-muted">

You can select multiple images.

</small>


</div>






</div>







<div class="text-end mt-4">


<button class="btn btn-primary px-5">

<i class="fa-solid fa-save"></i>

Save Room

</button>


</div>






</form>


</div>


</div>









<script>


function previewMain(input)

{


if(input.files && input.files[0])

{


let reader = new FileReader();



reader.onload=function(e)

{


let img=document.getElementById("imagePreview");


img.src=e.target.result;


img.style.display="block";


}



reader.readAsDataURL(input.files[0]);


}



}



</script>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>

</html>