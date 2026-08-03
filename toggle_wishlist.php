<?php
// Session စတင်ရန်
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection ထည့်သွင်းရန် (သင့် project အပေါ်မူတည်၍ path ကို ပြင်ပေးပါ)
require_once 'config/config.php';

// JSON Response ပြန်ရန် header သတ်မှတ်ခြင်း
header('Content-Type: application/json');

// User ဝင်ထားခြင်း ရှိမရှိ စစ်ဆေးခြင်း
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'unauthorized', 'message' => 'Please login first.']);
    exit();
}

// POST ဖြင့် hotel_id ပါလာခြင်း ရှိမရှိ စစ်ဆေးခြင်း
if (isset($_POST['hotel_id'])) {
    $customer_id = $_SESSION['user_id']; // Session ထဲရှိ user_id ကို customer_id အဖြစ် သုံးသည်
    $hotel_id = mysqli_real_escape_string($conn, $_POST['hotel_id']);

    // ဤ customer သည် ဤ hotel ကို Wishlist ထဲ ထည့်ထားပြီးသား ဟုတ်မဟုတ် စစ်ဆေးခြင်း
    $check_query = "SELECT * FROM wishlists WHERE customer_id = '$customer_id' AND hotel_id = '$hotel_id'";
    $check_result = mysqli_query($conn, $check_query);

    if ($check_result && mysqli_num_rows($check_result) > 0) {
        // ရှိပြီးသားဆိုရင် Wishlist ထဲမှ ဖယ်ရှားမည် (DELETE)
        $delete_query = "DELETE FROM wishlists WHERE customer_id = '$customer_id' AND hotel_id = '$hotel_id'";
        if (mysqli_query($conn, $delete_query)) {
            echo json_encode(['status' => 'removed', 'message' => 'Removed from wishlist']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    } else {
        // မရှိသေးရင် Wishlist အသစ်ထည့်မည် (INSERT)
        $insert_query = "INSERT INTO wishlists (customer_id, hotel_id) VALUES ('$customer_id', '$hotel_id')";
        if (mysqli_query($conn, $insert_query)) {
            echo json_encode(['status' => 'added', 'message' => 'Added to wishlist']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>