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

    !isset($_GET['room_id'])

){

    header("Location: manage_rooms.php");

    exit();

}




$room_id = intval($_GET['room_id']);



if($room_id <= 0){

    header("Location: manage_rooms.php");

    exit();

}







/*
================================
VERIFY ROOM OWNER
================================
*/


$stmt=mysqli_prepare(

$conn,

"

SELECT

r.room_id


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



$result=mysqli_stmt_get_result($stmt);



if(mysqli_num_rows($result)==0){


    header(

        "Location: manage_rooms.php?error=unauthorized"

    );


    exit();

}







/*
================================
GET ROOM IMAGES
================================
*/


$image_stmt=mysqli_prepare(

$conn,

"

SELECT

image_path


FROM room_images


WHERE room_id=?

"

);



mysqli_stmt_bind_param(

$image_stmt,

"i",

$room_id

);



mysqli_stmt_execute($image_stmt);



$images=mysqli_stmt_get_result(

$image_stmt

);






/*
================================
DELETE PHYSICAL IMAGES
================================
*/


$image_folder="../assets/images/rooms/";



while(

$image=mysqli_fetch_assoc($images)

){


    $file=$image_folder.$image['image_path'];



    if(

        file_exists($file)

    ){

        unlink($file);

    }


}









/*
================================
DELETE IMAGE RECORDS
================================
*/


$delete_img=mysqli_prepare(

$conn,

"

DELETE FROM room_images

WHERE room_id=?

"

);



mysqli_stmt_bind_param(

$delete_img,

"i",

$room_id

);



mysqli_stmt_execute($delete_img);










/*
================================
DELETE ROOM
================================
*/


$delete_room=mysqli_prepare(

$conn,

"

DELETE FROM rooms

WHERE room_id=?

"

);



mysqli_stmt_bind_param(

$delete_room,

"i",

$room_id

);






if(mysqli_stmt_execute($delete_room)){





/*
================================
AUDIT LOG
================================
*/


if(function_exists('insertOwnerAuditLog')){


    insertOwnerAuditLog(

        $conn,

        $owner_id,

        "DELETE_ROOM",

        "rooms",

        $room_id

    );


}




header(

"Location: manage_rooms.php?msg=room_deleted"

);


exit();



}
else{


header(

"Location: manage_rooms.php?error=delete_failed"

);


exit();


}


?>