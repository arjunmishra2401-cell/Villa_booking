<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'includes/database.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =======================
   SIGNUP LOGIC
======================= */
if (isset($_POST['signup'])) {

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $fname    = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname    = mysqli_real_escape_string($conn, $_POST['lname']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $phno     = mysqli_real_escape_string($conn, $_POST['phno']);
    $add      = mysqli_real_escape_string($conn, $_POST['add']);
    $dob      = mysqli_real_escape_string($conn, $_POST['dob']);
    $password = $_POST['password'];
    $cpassword= $_POST['cpassword'];

    /* ===== Password Validation ===== */
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/', $password)) {
        echo "<script>alert('Password must contain uppercase, lowercase, number & special character'); window.location='signup.php';</script>";
        exit();
    }

    if ($password !== $cpassword) {
        echo "<script>alert('Passwords do not match'); window.location='signup.php';</script>";
        exit();
    }

    $hashedpassword = password_hash($password, PASSWORD_BCRYPT);

    /* ===== Duplicate Check ===== */
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE username=? OR email=? OR phno=?");
    $stmt->bind_param("sss", $username, $email, $phno);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Username, Email or Phone already exists'); window.location='signup.php';</script>";
        exit();
    }

    /* ===== OTP ===== */
    $otp = rand(100000, 999999);

    $_SESSION['signup_otp'] = $otp;
    $_SESSION['signup_otp_time'] = time();
    $_SESSION['signup_data'] = [
    'username' => $username,
    'fname'    => $fname,
    'lname'    => $lname,
    'email'    => $email,
    'phno'     => $phno,
    'add'      => $add,
    'dob'      => $dob,
    'password' => $hashedpassword
];

    sendSignupOTP($email, $otp);

    header("Location: verify_signup_otp.php");
    exit();
}

/* =======================
   OTP MAIL FUNCTION
======================= */
function sendSignupOTP($to, $otp) {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bookmystayonline@gmail.com';
        $mail->Password   = 'sftm blky plvr lyvc'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('bookmystayonline@gmail.com', 'Book My Stay');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'OTP Verification - Book My Stay';
        $mail->Body    = "<h2>Your OTP is: $otp</h2><p>Valid for 10 minutes.</p>";

        $mail->send();

    } catch (Exception $e) {
        die("Mailer Error: {$mail->ErrorInfo}");
    }
}
?>

<?php include 'includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Book My Stay</title>
    <link rel="stylesheet" href="assets/css/ls.css">
</head>

<body>
<div class="auth-container">
    <h2>Sign Up</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="text" name="fname" placeholder="First Name" required>
        <input type="text" name="lname" placeholder="Last Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="phno" placeholder="Phone Number" required>
        <input type="text" name="add" placeholder="Address" required>
        <input type="date" name="dob" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="cpassword" placeholder="Confirm Password" required>
        <button type="submit" name="signup">Sign Up</button>
    </form>
</div>
</body>
</html>

<?php include 'includes/footer.php'; ?>
