<?php
session_start();
require 'includes/database.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ===========================
   ENABLE ERROR REPORTING (IMPORTANT)
=========================== */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===========================
   RAZORPAY TEST KEYS
=========================== */
$apiKey    = 'YOUR_RAZORPAY_KEY';
$apiSecret = 'YOUR_RAZORPAY_SECRET';

/* ===========================
   CHECK LOGIN
=========================== */
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

/* ===========================
   VALIDATE BOOKING ID
=========================== */
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    die("Invalid booking ID.");
}

$bookingId = intval($_GET['booking_id']);
$username  = $_SESSION['username'];

/* ===========================
   FETCH BOOKING
=========================== */
$query = "SELECT * FROM resort_booking 
          WHERE booking_id = ? 
          AND username = ? 
          AND status = 'paid'";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $bookingId, $username);
$stmt->execute();
$result  = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Booking not found or already cancelled.");
}

/* ===========================
   CHECK CANCELLATION POLICY
=========================== */
$checkinDate = new DateTime($booking['checkin']);
$currentDate = new DateTime();

$interval = $currentDate->diff($checkinDate);
$isPast   = $currentDate > $checkinDate;

if ($interval->days <= 1 || $isPast) {
    echo "<script>
        alert('Cancellation not allowed within 1 day of check-in.');
        window.location.href='mybooking.php';
    </script>";
    exit();
}

/* ===========================
   CALCULATE REFUND (10% DEDUCT)
=========================== */
$totalAmount       = floatval($booking['rprice']);
$refundAmount      = round($totalAmount * 0.90, 2);
$refundAmountPaise = intval($refundAmount * 100);

$paymentId = $booking['payment_id'];

if (empty($paymentId)) {
    die("Payment ID missing.");
}

/* ===========================
   PROCESS RAZORPAY REFUND
=========================== */
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.razorpay.com/v1/payments/$paymentId/refund",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['amount' => $refundAmountPaise]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_USERPWD => "$apiKey:$apiSecret",
]);

$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error      = curl_error($ch);
curl_close($ch);

if ($error) {
    die("Refund Error: " . htmlspecialchars($error));
}

$responseArray = json_decode($response, true);

if ($httpStatus != 200 || !isset($responseArray['id'])) {
    $err = $responseArray['error']['description'] ?? "Refund failed.";
    die("Razorpay Error: " . htmlspecialchars($err));
}

/* ===========================
   START DATABASE TRANSACTION
=========================== */
$conn->begin_transaction();

try {

    // Update resort booking
    $updateQuery = "UPDATE resort_booking SET status = 'canceled' WHERE booking_id = ?";
    $updateStmt  = $conn->prepare($updateQuery);
    $updateStmt->bind_param("i", $bookingId);
    $updateStmt->execute();

    // Cancel cab if exists
    $cabQuery = "UPDATE cab_booking_resort 
                 SET status = 'canceled' 
                 WHERE resort_booking_id = ?";
    $cabStmt = $conn->prepare($cabQuery);
    $cabStmt->bind_param("i", $bookingId);
    $cabStmt->execute();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    die("Database Error: " . $e->getMessage());
}

/* ===========================
   SEND EMAIL
=========================== */
sendCancellationEmail(
    $booking['useremail'],
    $username,
    $booking['rname'],
    $refundAmount,
    $booking['checkin']
);

/* ===========================
   SUCCESS MESSAGE
=========================== */
echo "<script>
    alert('Booking Cancelled Successfully! Refund ₹$refundAmount processed.');
    window.location.href='mybooking.php';
</script>";

$conn->close();


/* ===========================
   EMAIL FUNCTION
=========================== */
function sendCancellationEmail($to, $username, $hotelName, $refundAmount, $checkinDate)
{
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_email@gmail.com';
        $mail->Password   = 'your_app_password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('your_email@gmail.com', 'Book My Stay');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Booking Cancellation & Refund';

        $mail->Body = "
            <h2>Booking Cancelled</h2>
            <p>Dear <strong>$username</strong>,</p>
            <p>Your booking at <strong>$hotelName</strong> has been cancelled.</p>
            <p><strong>Refund Amount:</strong> ₹$refundAmount</p>
            <p><strong>Check-in Date:</strong> $checkinDate</p>
            <p>Thank you for using Book My Stay.</p>
        ";

        $mail->send();

    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
    }
}
?>