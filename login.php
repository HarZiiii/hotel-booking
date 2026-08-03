<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
if(isset($_SESSION['user_id'])){header('Location: '.($_SESSION['role']==='admin'?'admin/dashboard.php':($_SESSION['role']==='owner'?'owner/dashboard.php':'index.php')));exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $identity=trim($_POST['username_email']??'');$password=$_POST['password']??'';
  $stmt=mysqli_prepare($conn,"SELECT user_id,username,full_name,password,role,status FROM users WHERE username=? OR email=? LIMIT 1");mysqli_stmt_bind_param($stmt,'ss',$identity,$identity);mysqli_stmt_execute($stmt);$user=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
  if(!$user){$error='We could not find an account with those details.';}
  elseif(in_array($user['status'],['blocked','inactive'],true)){$error='This account is currently unavailable. Please contact support.';}
  else{
    $stored=(string)$user['password'];
    $info=password_get_info($stored);
    $isHash=isset($info['algoName']) && $info['algoName']!=='unknown';
    $valid=$isHash ? password_verify($password,$stored) : hash_equals($stored,(string)$password);
    if($valid){
      // Upgrade legacy plain-text passwords and rehash old hashes automatically.
      if(!$isHash || password_needs_rehash($stored,PASSWORD_DEFAULT)){
        $newHash=password_hash($password,PASSWORD_DEFAULT);
        $up=mysqli_prepare($conn,'UPDATE users SET password=?,last_login=NOW() WHERE user_id=?');
        mysqli_stmt_bind_param($up,'si',$newHash,$user['user_id']);
      }else{
        $up=mysqli_prepare($conn,'UPDATE users SET last_login=NOW() WHERE user_id=?');
        mysqli_stmt_bind_param($up,'i',$user['user_id']);
      }
      mysqli_stmt_execute($up);
      session_regenerate_id(true);
      $_SESSION['user_id']=$user['user_id'];$_SESSION['username']=$user['username'];$_SESSION['full_name']=$user['full_name'];$_SESSION['role']=$user['role'];
      header('Location: '.($user['role']==='admin'?'admin/dashboard.php':($user['role']==='owner'?'owner/dashboard.php':'index.php')));exit;
    }
    $error='Incorrect password. Please try again.';
  }
}
require_once 'includes/header.php';require_once 'includes/navbar.php';?>
<section class="auth-shell"><div class="auth-card">
<aside class="auth-side"><div><div class="hbs-eyebrow mb-4"><i class="fa-solid fa-hotel"></i> StayFlow Booking</div><h2>Welcome back to smarter stays.</h2><p>Guests, hotel partners, and administrators use one secure sign-in with role-based access.</p></div><div class="auth-points"><div class="auth-point"><i class="fa-solid fa-circle-check"></i><span>Manage bookings from one account</span></div><div class="auth-point"><i class="fa-solid fa-shield-halved"></i><span>Modern password verification and safer sessions</span></div><div class="auth-point"><i class="fa-solid fa-chart-line"></i><span>Owners and admins go directly to their dashboard</span></div></div></aside>
<div class="auth-form"><div class="mb-4"><span class="text-primary fw-bold small">SIGN IN</span><h1 class="mt-1">Access your account</h1><p class="text-muted small">Use your username or email and password.</p></div><?php if($error):?><div class="alert alert-danger py-2 small"><i class="fa-solid fa-circle-exclamation me-2"></i><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST" action="login.php"><div class="mb-3"><label class="form-label">Username or email</label><div class="input-group"><span class="input-group-text bg-white border-end-0"><i class="fa-regular fa-user"></i></span><input class="form-control border-start-0" name="username_email" required autocomplete="username" value="<?=htmlspecialchars($_POST['username_email']??'')?>" placeholder="you@example.com"></div></div><div class="mb-3"><label class="form-label">Password</label><div class="input-group"><span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-lock"></i></span><input id="loginPassword" class="form-control border-start-0 border-end-0" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"><button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('loginPassword');p.type=p.type==='password'?'text':'password'"><i class="fa-regular fa-eye"></i></button></div></div><button class="btn btn-primary w-100 fw-bold mt-2" type="submit">Sign in <i class="fa-solid fa-arrow-right ms-1"></i></button></form><p class="auth-note text-center mt-4 mb-0">New to StayFlow? <a class="fw-bold text-decoration-none" href="register.php">Create an account</a></p></div>
</div></section><?php require_once 'includes/footer.php';?>
