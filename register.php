<?php
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $first_name = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $full_name = $first_name . " " . $last_name;
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $date_of_birth = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $country = mysqli_real_escape_string($conn, trim($_POST['country']));
    $postal_code = mysqli_real_escape_string($conn, trim($_POST['postal_code']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    // Validation Check
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Username သို့မဟုတ် Email သို့မဟုတ် Phone ရှိပြီးသားလား စစ်ဆေးခြင်း
        $check_query = "SELECT * FROM users WHERE username='$username' OR email='$email' OR phone='$phone' LIMIT 1";
        $result = mysqli_query($conn, $check_query);
        $user = mysqli_fetch_assoc($result);

        if ($user) {
            if ($user['username'] === $username) { $error = "Username already exists!"; }
            elseif ($user['email'] === $email) { $error = "Email already exists!"; }
            elseif ($user['phone'] === $phone) { $error = "Phone number already exists!"; }
        } else {
            // Password ကို လုံခြုံအောင် Hashing လုပ်ခြင်း
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Database ထဲသို့ Profile အချက်အလက်အစုံထည့်ခြင်း
            $query = "INSERT INTO users (username, full_name, first_name, last_name, email, phone, password, role, gender, date_of_birth, address, city, country, postal_code, status) 
                      VALUES ('$username', '$full_name', '$first_name', '$last_name', '$email', '$phone', '$hashed_password', '$role', '$gender', '$date_of_birth', '$address', '$city', '$country', '$postal_code', 'active')";
            
            if (mysqli_query($conn, $query)) {
                $new_user_id = mysqli_insert_id($conn);
                
                // အကယ်၍ Register လုပ်သူသည် Owner ဖြစ်ပါက သူ့အတွက် Wallet တစ်ခုပါ တခါတည်း ဆောက်ပေးခြင်း
                if ($role === 'owner') {
                    mysqli_query($conn, "INSERT INTO wallets (owner_id, balance) VALUES ('$new_user_id', 0.00)");
                }
                
                $success = "Registration successful! You can now <a href='login.php'>Login</a>.";
            } else {
                $error = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>

<div class="container" style="max-width: 600px;">
    <h2>Create an Account</h2>
    <p style="color: #7f8c8d; margin-bottom: 20px;">Please fill in your profile details to register.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label>Register As</label>
            <select name="role" class="form-control" required>
                <option value="customer">Customer (Guest)</option>
                <option value="owner">Hotel Owner</option>
            </select>
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required>
            </div>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control" required>
        </div>
        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>Gender</label>
                <select name="gender" class="form-control" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" required>
            </div>
        </div>
        <div class="form-group">
            <label>Street Address</label>
            <textarea name="address" class="form-control" rows="2" required></textarea>
        </div>
        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>City</label>
                <input type="text" name="city" class="form-control" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Country</label>
                <input type="text" name="country" class="form-control" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Postal Code</label>
                <input type="text" name="postal_code" class="form-control">
            </div>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
    <p style="margin-top: 15px; text-align: center;">Already have an account? <a href="login.php">Login here</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>