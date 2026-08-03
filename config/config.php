<?php
// 1. Session အမြဲတမ်း Open ဖြစ်နေစေရန် စတင်ခြင်း
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Connection Configurations
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "hotel_booking_system_v3"; // သင်ပြောင်းထားတဲ့ v3 နာမည်အတိုင်း

// Connection တည်ဆောက်ခြင်း
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Connection Error တက်ရင် ချက်ချင်းပြသပြီး ရပ်တန့်ရန်
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// 3. Font တွေ၊ စာသားတွေ မြန်မာစာအပါအဝင် မပျက်စီးအောင် UTF-8 Set လုပ်ခြင်း
mysqli_set_charset($conn, "utf8mb4");

// 4. Global URL Paths များ (လိုအပ်ပါက ဤနေရာတွင် Constants များသတ်မှတ်နိုင်သည်)
define('BASE_URL', 'http://localhost/hotel-booking/');
?>