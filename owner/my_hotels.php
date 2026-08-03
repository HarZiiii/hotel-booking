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

$error = "";





/*
================================
UPLOAD HOTEL IMAGE
================================
*/


function uploadHotelImage($file)

{


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





    if($file['size'] > 3 * 1024 * 1024){

        return "";

    }






    $folder = "../assets/images/";




    if(!is_dir($folder)){

        mkdir(

            $folder,

            0755,

            true

        );

    }





    $filename =

    "hotel_"

    .

    time()

    .

    "_"

    .

    bin2hex(random_bytes(5))

    .

    "."

    .

    $ext;





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
ADD HOTEL
================================
*/


if(

    $_SERVER['REQUEST_METHOD']=="POST"

    &&

    isset($_POST['add_hotel'])

)

{



    if(!isset($_POST['agree_terms'])){


        $error =

        "You must accept partner agreement.";


    }

    else
    {


        $hotel_name =

        trim($_POST['hotel_name']);



        $hotel_email =

        trim($_POST['email']);



        $hotel_phone =

        trim($_POST['phone']);



        $city =

        trim($_POST['city']);



        $address =

        trim($_POST['location']);



        $description =

        trim($_POST['description']);



        $star_rating =

        floatval($_POST['rating']);



        $check_in_time =

        $_POST['check_in_time'];



        $check_out_time =

        $_POST['check_out_time'];



        $status =

        $_POST['status'];





        /*
        IMAGE UPLOAD FIRST
        */


        $hotel_image = "";



        if(isset($_FILES['image'])){


            $hotel_image =

            uploadHotelImage(

                $_FILES['image']

            );


        }






        /*
        INSERT HOTEL
        */


        $stmt = mysqli_prepare(

            $conn,

            "

            INSERT INTO hotels

            (

            owner_id,

            hotel_name,

            description,

            address,

            city,

            hotel_phone,

            hotel_email,

            star_rating,

            check_in_time,

            check_out_time,

            status

            )

            VALUES

            (?,?,?,?,?,?,?,?,?,?,?)

            "

        );





        mysqli_stmt_bind_param(

            $stmt,

            "issssssssss",

            $owner_id,

            $hotel_name,

            $description,

            $address,

            $city,

            $hotel_phone,

            $hotel_email,

            $star_rating,

            $check_in_time,

            $check_out_time,

            $status

        );







        if(mysqli_stmt_execute($stmt))

        {


            $hotel_id =

            mysqli_insert_id($conn);







            /*
            SAVE COVER IMAGE
            */


            if($hotel_image!="")

            {


                $img_stmt = mysqli_prepare(

                    $conn,

                    "

                    INSERT INTO hotel_images

                    (

                    hotel_id,

                    image_path,

                    is_cover

                    )

                    VALUES(?,?,1)

                    "

                );





                mysqli_stmt_bind_param(

                    $img_stmt,

                    "is",

                    $hotel_id,

                    $hotel_image

                );





                mysqli_stmt_execute($img_stmt);


            }






            /*
            SAVE FACILITIES
            */


            if(

                isset($_POST['facilities'])

                &&

                is_array($_POST['facilities'])

            )

            {


                foreach(

                    $_POST['facilities']

                    as $facility_id

                )

                {


                    $facility_stmt = mysqli_prepare(

                        $conn,

                        "

                        INSERT INTO hotel_facilities

                        (

                        hotel_id,

                        facility_id

                        )

                        VALUES(?,?)

                        "

                    );





                    mysqli_stmt_bind_param(

                        $facility_stmt,

                        "ii",

                        $hotel_id,

                        $facility_id

                    );





                    mysqli_stmt_execute($facility_stmt);


                }


            }








            /*
            OWNER AGREEMENT
            */


            $commission_rate = 10;

            $accepted = 1;

            $accepted_date = date(

                "Y-m-d H:i:s"

            );

            $created_by = $owner_id;






            $agreement_stmt = mysqli_prepare(

                $conn,

                "

                INSERT INTO owner_agreements

                (

                owner_id,

                commission_rate,

                accepted,

                accepted_date,

                created_by

                )

                VALUES(?,?,?,?,?)

                "

            );







            mysqli_stmt_bind_param(

                $agreement_stmt,

                "idiis",

                $owner_id,

                $commission_rate,

                $accepted,

                $accepted_date,

                $created_by

            );







            mysqli_stmt_execute($agreement_stmt);






            header(

                "Location: my_hotels.php?msg=added"

            );


            exit();



        }

        else
        {


            $error = mysqli_error($conn);


        }



    }


}

/*
================================
FETCH OWNER HOTELS
================================
*/


$hotel_stmt = mysqli_prepare(

    $conn,

    "

    SELECT


    h.*,



    (

        SELECT 

        hi.image_path


        FROM hotel_images hi


        WHERE hi.hotel_id=h.hotel_id


        AND hi.is_cover=1


        LIMIT 1


    ) AS hotel_image



    FROM hotels h



    WHERE h.owner_id=?



    ORDER BY h.hotel_id DESC



    "

);




mysqli_stmt_bind_param(

    $hotel_stmt,

    "i",

    $owner_id

);



mysqli_stmt_execute($hotel_stmt);



$hotels_query = mysqli_stmt_get_result(

    $hotel_stmt

);



?>





<!DOCTYPE html>

<html lang="en">


<head>


<meta charset="UTF-8">


<title>

My Hotels | Hotel Partner Hub

</title>


<meta name="viewport" content="width=device-width, initial-scale=1.0">



<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"

rel="stylesheet">



<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"

rel="stylesheet">



<link href="../assets/css/owner.css"

rel="stylesheet">



<style>


body{

font-family:Poppins,sans-serif;

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

box-shadow:0 3px 12px rgba(0,0,0,.05);

margin-bottom:25px;

}



.hotel-card{

background:white;

border-radius:15px;

overflow:hidden;

box-shadow:0 3px 15px rgba(0,0,0,.08);

height:100%;

}



.hotel-img{

width:100%;

height:190px;

object-fit:cover;

}



.hotel-body{

padding:20px;

}



</style>


</head>





<body>




<?php include '../includes/owner_sidebar.php'; ?>





<div class="main-content">






<div class="topbar d-flex justify-content-between align-items-center">


<div>


<h3 class="fw-bold">


<i class="fa-solid fa-hotel text-primary"></i>

My Hotels


</h3>


<p class="text-muted mb-0">

Manage your registered properties

</p>


</div>




<button class="btn btn-primary"

data-bs-toggle="modal"

data-bs-target="#addHotelModal">


<i class="fa-solid fa-plus"></i>

Add Hotel


</button>


</div>








<?php if(isset($_GET['msg']) && $_GET['msg']=="added"): ?>


<div class="alert alert-success">


<i class="fa-solid fa-circle-check"></i>

Hotel added successfully.


</div>


<?php endif; ?>






<?php if($error!=""): ?>


<div class="alert alert-danger">


<?=htmlspecialchars($error)?>


</div>


<?php endif; ?>










<div class="row g-4">






<?php if(mysqli_num_rows($hotels_query)>0): ?>




<?php while($hotel=mysqli_fetch_assoc($hotels_query)): ?>





<?php


$cover_image =

!empty($hotel['hotel_image'])

?

$hotel['hotel_image']

:

"default_hotel.jpg";





$status =

$hotel['status'] ?? "pending";



?>








<div class="col-md-6 col-lg-4">



<div class="hotel-card">






<img src="../assets/images/<?=htmlspecialchars($cover_image)?>"

class="hotel-img"

onerror="this.src='../assets/images/default_hotel.jpg'">








<div class="hotel-body">






<div class="d-flex justify-content-between align-items-center">



<h5 class="fw-bold">


<?=htmlspecialchars($hotel['hotel_name'])?>


</h5>





<span class="badge bg-warning text-dark">


<i class="fa-solid fa-star"></i>


<?=number_format(

$hotel['star_rating'] ?? 0,

1

)?>


</span>



</div>








<p class="text-muted">


<i class="fa-solid fa-location-dot text-danger"></i>


<?=htmlspecialchars(

$hotel['address']

)?>



</p>









<p class="small text-secondary">


<?=htmlspecialchars(

substr(

$hotel['description'],

0,

120

)

)?>


...</p>









<div class="mb-3">



<?php if($status=="approved"): ?>


<span class="badge bg-success">

Approved

</span>



<?php elseif($status=="pending"): ?>


<span class="badge bg-warning text-dark">

Pending

</span>



<?php elseif($status=="rejected"): ?>


<span class="badge bg-danger">

Rejected

</span>



<?php else: ?>


<span class="badge bg-secondary">

Inactive

</span>



<?php endif; ?>



</div>








<a href="manage_rooms.php?hotel_id=<?=$hotel['hotel_id']?>"

class="btn btn-outline-primary w-100">


<i class="fa-solid fa-bed"></i>


Manage Rooms


</a>






</div>


</div>


</div>







<?php endwhile; ?>





<?php else: ?>




<div class="col-12">


<div class="alert alert-info text-center">


<i class="fa-solid fa-hotel fa-2x"></i>


<h5 class="mt-3">

No Hotels Found

</h5>


<p>

Add your first hotel property.

</p>



</div>


</div>





<?php endif; ?>







</div>
<!-- ADD HOTEL MODAL -->


<div class="modal fade"

id="addHotelModal">


<div class="modal-dialog modal-lg">


<div class="modal-content">



<form method="POST"

enctype="multipart/form-data">



<input type="hidden"

name="add_hotel"

value="1">





<div class="modal-header">


<h5 class="modal-title fw-bold">


<i class="fa-solid fa-building-circle-check text-primary"></i>

Add New Hotel


</h5>


<button type="button"

class="btn-close"

data-bs-dismiss="modal">

</button>


</div>







<div class="modal-body">



<div class="row g-3">






<!-- HOTEL NAME -->


<div class="col-md-6">


<label class="form-label">

Hotel Name

</label>


<input type="text"

name="hotel_name"

class="form-control"

required>


</div>








<!-- CITY -->


<div class="col-md-6">


<label class="form-label">

City

</label>


<input type="text"

name="city"

class="form-control"

required>


</div>








<!-- EMAIL -->


<div class="col-md-6">


<label class="form-label">

Hotel Email

</label>


<input type="email"

name="email"

class="form-control">


</div>








<!-- PHONE -->


<div class="col-md-6">


<label class="form-label">

Hotel Phone

</label>


<input type="text"

name="phone"

class="form-control">


</div>









<!-- ADDRESS -->


<div class="col-md-8">


<label class="form-label">

Address

</label>


<input type="text"

name="location"

class="form-control"

required>


</div>







<!-- RATING -->


<div class="col-md-4">


<label class="form-label">

Star Rating

</label>


<input type="number"

name="rating"

class="form-control"

step="0.1"

min="0"

max="5"

value="0">


</div>








<!-- CHECK IN -->


<div class="col-md-6">


<label class="form-label">

Check In Time

</label>


<input type="time"

name="check_in_time"

class="form-control"

value="14:00">


</div>








<!-- CHECK OUT -->


<div class="col-md-6">


<label class="form-label">

Check Out Time

</label>


<input type="time"

name="check_out_time"

class="form-control"

value="12:00">


</div>









<!-- FACILITIES -->


<div class="col-12">


<label class="form-label fw-bold">

Facilities

</label>



<div class="border rounded p-3">



<div class="row">



<?php


$facility_result=mysqli_query(

$conn,

"

SELECT *

FROM facilities

ORDER BY facility_name ASC

"

);



if(

$facility_result

&&

mysqli_num_rows($facility_result)>0

):

?>


<?php while($facility=mysqli_fetch_assoc($facility_result)): ?>



<div class="col-md-4 mb-2">


<div class="form-check">


<input class="form-check-input"

type="checkbox"

name="facilities[]"

value="<?=$facility['facility_id']?>"

id="facility<?=$facility['facility_id']?>">



<label class="form-check-label"

for="facility<?=$facility['facility_id']?>">


<?=htmlspecialchars($facility['facility_name'])?>


</label>



</div>


</div>



<?php endwhile; ?>



<?php else: ?>


<div class="text-muted">

No facilities found.

</div>



<?php endif; ?>



</div>



</div>


</div>








<!-- DESCRIPTION -->


<div class="col-12">


<label class="form-label">

Description

</label>


<textarea

name="description"

class="form-control"

rows="4"

placeholder="Hotel description"></textarea>


</div>









<!-- IMAGE -->


<div class="col-md-6">


<label class="form-label">

Cover Image

</label>


<input type="file"

name="image"

class="form-control"

accept="image/*">


</div>








<!-- STATUS -->


<div class="col-md-6">


<label class="form-label">

Status

</label>


<select name="status"

class="form-select">


<option value="pending">

Pending

</option>


<option value="approved">

Approved

</option>


<option value="inactive">

Inactive

</option>


<option value="rejected">

Rejected

</option>



</select>


</div>








<!-- AGREEMENT -->


<div class="col-12">


<div class="form-check mt-3">


<input class="form-check-input"

type="checkbox"

name="agree_terms"

id="agree_terms"

required>


<label class="form-check-label"

for="agree_terms">


I agree to partner terms and conditions.


<a href="#"

data-bs-toggle="modal"

data-bs-target="#termsModal">

View Terms

</a>


</label>


</div>


</div>







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


<i class="fa-solid fa-save"></i>

Save Hotel


</button>



</div>



</form>



</div>


</div>


</div>









<!-- TERMS MODAL -->


<div class="modal fade"

id="termsModal">


<div class="modal-dialog modal-lg">


<div class="modal-content">



<div class="modal-header bg-dark text-white">


<h5 class="modal-title">

Partner Terms & Conditions

</h5>


<button type="button"

class="btn-close btn-close-white"

data-bs-dismiss="modal">

</button>


</div>






<div class="modal-body">


<h6>

1. Hotel Information

</h6>


<p>

Owner must provide correct hotel details,
room information and pricing.

</p>



<h6>

2. Booking Responsibility

</h6>


<p>

Owner is responsible for confirmed bookings
and guest services.

</p>



<h6>

3. Agreement

</h6>


<p>

System stores agreement acceptance for
verification purposes.

</p>



</div>






<div class="modal-footer">


<button type="button"

class="btn btn-primary"

data-bs-dismiss="modal"


onclick="document.getElementById('agree_terms').checked=true;">


I Agree


</button>



</div>


</div>


</div>


</div>








<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>



</body>


</html>