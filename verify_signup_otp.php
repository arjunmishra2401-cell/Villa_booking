<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'includes/database.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   HARD STOP IF SESSION BAD
========================= */
if (
    !isset($_SESSION['signup_otp']) ||
    !isset($_SESSION['signup_otp_time']) ||
    !isset($_SESSION['signup_data'])
) {
    header("Location: signup.php");
    exit();
}

$email = $_SESSION['signup_data']['email'];

/* =========================
   OTP ATTEMPT COUNTER
========================= */
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

/* =========================
   VERIFY OTP
========================= */
if (isset($_POST['verify_otp'])) {

    $entered_otp = trim($_POST['otp']);

    /* OTP expiry: 10 minutes */
    if (time() - $_SESSION['signup_otp_time'] > 600) {
        session_unset();
        session_destroy();
        header("Location: signup.php");
        exit();
    }

    /* Max attempts */
    if ($_SESSION['otp_attempts'] >= 5) {
        session_unset();
        session_destroy();
        header("Location: signup.php");
        exit();
    }

    if ($entered_otp === (string)$_SESSION['signup_otp']) {

        $u = $_SESSION['signup_data'];

        /* INSERT USER (PREPARED STATEMENT) */
        $stmt = $conn->prepare(
            "INSERT INTO user (username, fname, lname, email, phno, address, dob, password)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssssssss",
            $u['username'],
            $u['fname'],
            $u['lname'],
            $u['email'],
            $u['phno'],
            $u['add'],
            $u['dob'],
            $u['password']
        );

        if ($stmt->execute()) {

            /* CLEAN SESSION */
            session_regenerate_id(true);
            session_unset();
            session_destroy();

            header("Location: login.php");
            exit();

        } else {
            error_log("DB Insert Error: " . $stmt->error);
        }

    } else {
        $_SESSION['otp_attempts']++;
    }
}

/* =========================
   RESEND OTP (30s COOLDOWN)
========================= */
if (isset($_POST['resend_otp'])) {

    if (
        isset($_SESSION['last_otp_request']) &&
        time() - $_SESSION['last_otp_request'] < 30
    ) {
        // silent fail
    } else {
        $_SESSION['signup_otp'] = random_int(100000, 999999);
        $_SESSION['signup_otp_time'] = time();
        $_SESSION['last_otp_request'] = time();
        $_SESSION['otp_attempts'] = 0;

        sendSignupOTP($email, $_SESSION['signup_otp']);
    }
}

/* =========================
   OTP MAIL FUNCTION
========================= */
function sendSignupOTP($to, $otp) {

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bookmystayonline@gmail.com';
        $mail->Password   = 'APP_PASSWORD_HERE';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('bookmystayonline@gmail.com', 'Book My Stay');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'OTP Verification';
        $mail->Body    = "<h2>Your OTP: {$otp}</h2><p>Valid for 10 minutes</p>";

        $mail->send();

    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }
}
?>

<?php include 'includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP</title>
    <link rel="stylesheet" href="assets/css/ls.css">
</head>
<body>

<div class="auth-container">
    <h2>Verify OTP</h2>

    <form method="post">
        <input type="text" name="otp" placeholder="Enter OTP" required>
        <button type="submit" name="verify_otp">Verify</button>
    </form>

    <form method="post">
        <button type="submit" name="resend_otp">Resend OTP</button>
    </form>
</div>

</body>
</html>

<?php include 'includes/footer.php'; ?>
