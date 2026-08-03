<?php
// 1. Session ကို အပေါ်ဆုံးကနေ ဦးစွာ စတင်ရပါမယ်
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database connection config ကို ချိတ်ဆက်ပါ
require_once 'config/config.php';

// အကယ်၍ User က Login ဝင်ထားပြီးသားဖြစ်ပါက သက်ဆိုင်ရာ Dashboard သို့ ပြန်ပို့ရန်
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') { 
        header("Location: admin/dashboard.php"); 
    } elseif ($_SESSION['role'] === 'owner') { 
        header("Location: owner/dashboard.php"); 
    } else { 
        header("Location: index.php"); 
    }
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_email = mysqli_real_escape_string($conn, trim($_POST['username_email']));
    $password = trim($_POST['password']); // user ရိုက်ထည့်လိုက်သော password

    // Username သို့မဟုတ် Email တစ်ခုခုဖြင့် စစ်ဆေးပြီး Login ခွင့်ပေးခြင်း
    $query = "SELECT * FROM users WHERE username='$username_email' OR email='$username_email' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Blocked ဖြစ်နေသော User များကို တားဆီးခြင်း
        if ($user['status'] === 'blocked' || $user['status'] === 'inactive') {
            $error = "Your account has been deactivated or blocked.";
        } 
        // DB ထဲက Plain-text နှင့် အသုံးပြုသူရိုက်ထည့်လိုက်သော password ကို တိုက်ရိုက် တိုက်စစ်ခြင်း
        elseif ($password === $user['password']) {
            
            // Session Variables များ သတ်မှတ်သိမ်းဆည်းခြင်း
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Last Login Time ကို Database တွင် Update လုပ်ခြင်း
            $user_id = $user['user_id'];
            mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE user_id = '$user_id'");
            
            // Role အလိုက် သက်ဆိုင်ရာ Dashboard သို့ လမ်းညွှန်ခြင်း
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
            } elseif ($user['role'] === 'owner') {
                header("Location: owner/dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found with that username or email!";
    }
}

// 3. UI Template Layout များကို Session & Redirect logic ပြီးမှ ခေါ်သုံးရပါမယ်
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container" style="max-width: 450px; margin-top: 70px; margin-bottom: 70px;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #eaeded;">
        <h2 style="text-align: center; color: #2c3e50; margin-top: 0;">Sign In</h2>
        <p style="color: #7f8c8d; text-align: center; margin-bottom: 25px; font-size: 14px;">Welcome back! Please login to your account.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-size: 14px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #34495e; font-weight: bold; font-size: 14px;">Username or Email</label>
                <input type="text" name="username_email" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #cccccc; border-radius: 4px; box-sizing: border-box;" required autocomplete="username">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; color: #34495e; font-weight: bold; font-size: 14px;">Password</label>
                <input type="password" name="password" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #cccccc; border-radius: 4px; box-sizing: border-box;" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block" style="width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 16px;">Login</button>
        </form>
        
        <p style="margin-top: 20px; text-align: center; font-size: 14px; margin-bottom: 0;">Don't have an account? <a href="register.php" style="color: #3498db; text-decoration: none; font-weight: bold;">Register here</a></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>