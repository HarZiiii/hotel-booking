<?php
// Core config ကိုခေါ်ယူပြီး Session စစ်ဆေးခြင်း
require_once 'config/config.php';

// Session Array တစ်ခုလုံးကို ဗလာလုပ်ခြင်း
$_SESSION = array();

// Session Cookie ကို Browser ထဲမှ ဖျက်ဆီးခြင်း
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session ကို တရားဝင် ဖျက်သိမ်းခြင်း
session_destroy();

// ပင်မစာမျက်နှာ သို့မဟုတ် Login Page သို့ ချက်ချင်း ပြန်လည်ပို့ဆောင်ခြင်း
header("Location: login.php");
exit();
?>