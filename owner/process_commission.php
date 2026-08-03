<?php

require_once '../config/config.php';


if(session_status() === PHP_SESSION_NONE){
    session_start();
}



if(
    !isset($_SESSION['user_id'])
    ||
    $_SESSION['role'] !== 'owner'
){

    header("Location: ../login.php");
    exit();

}



if(
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['pay_commission'])
){




    $owner_id = $_SESSION['user_id'];



    $amount = floatval(
        $_POST['commission_amount'] ?? 0
    );



    if($amount <= 0){

        header(
            "Location: earnings.php?error=invalid_amount"
        );

        exit();

    }







    /*
    ============================
    PAYMENT SLIP UPLOAD
    ============================
    */


    $slip_img = "";



    if(
        isset($_FILES['payment_slip'])
        &&
        $_FILES['payment_slip']['error']
        === UPLOAD_ERR_OK
    ){



        $file = $_FILES['payment_slip'];



        $allowed_ext = [

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





        if(
            in_array($ext,$allowed_ext)
            &&
            $file['size'] <= 2*1024*1024
        ){



            $upload_dir =

            "../assets/images/slips/";



            if(!is_dir($upload_dir)){

                mkdir(
                    $upload_dir,
                    0755,
                    true
                );

            }




            $file_name =

            "slip_"
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
                    $upload_dir.$file_name
                )
            ){

                $slip_img = $file_name;

            }



        }


    }








    if($slip_img !== ""){



        $stmt = $conn->prepare(

            "

            INSERT INTO commissions

            (

            owner_id,

            amount,

            payment_slip,

            status,

            created_at

            )

            VALUES

            (?,?,?,'Pending',NOW())


            "

        );





        $stmt->bind_param(

            "ids",

            $owner_id,

            $amount,

            $slip_img

        );





        if($stmt->execute()){


            header(

                "Location: earnings.php?msg=commission_submitted"

            );


            exit();


        }



    }





    header(

        "Location: earnings.php?error=failed"

    );


    exit();


}

?>