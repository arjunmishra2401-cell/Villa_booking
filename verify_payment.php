<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure no output is sent before the script processes
ob_start();

// Include required files
require 'includes/fpdf/fpdf.php';
require 'vendor/autoload.php'; 
require 'includes/database.php'; // Database connection

// Error reporting (Useful for debugging, disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$keySecret = 'LvudH59jJCQBC389xoS5zAOW'; // Razorpay Secret Key

// Collect and sanitize POST data
$paymentId = isset($_POST['razorpay_payment_id']) ? strip_tags($_POST['razorpay_payment_id']) : null;
$orderId   = isset($_POST['razorpay_order_id']) ? strip_tags($_POST['razorpay_order_id']) : null;
$signature = isset($_POST['razorpay_signature']) ? strip_tags($_POST['razorpay_signature']) : null;

if ($paymentId && $orderId && $signature) {
    
    // 1. Verify Razorpay Signature
    $generatedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);

    if (hash_equals($generatedSignature, $signature)) {
        try {
            // 2. Update Payment Status in Database
            $updateQuery = "UPDATE hotel_booking SET status='paid', payment_id=? WHERE order_id=?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ss", $paymentId, $orderId);

            if ($stmt->execute()) {
                
                // 3. Fetch Booking Details (Single Query)
                $query = "SELECT * FROM hotel_booking WHERE order_id=?";
                $stmt2 = $conn->prepare($query);
                $stmt2->bind_param("s", $orderId);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $booking = $result->fetch_assoc();

                if ($booking) {
                    // Extract needed details for Email/PDF
                    $username = $booking['username'];
                    $email = $booking['useremail'];
                    $hotelName = $booking['hname'];
                    
                    // 4. Generate PDF Invoice (Pass data array, don't query again)
                    $invoicePath = generateInvoicePDF($booking, $paymentId);

                    // 5. Send Email
                    $emailSent = sendPaymentConfirmationEmail($email, $username, $hotelName, $paymentId, $orderId, $invoicePath);

                    // 6. Success Response
                    if ($emailSent) {
                        echo "<script>
                            alert('Payment verification successful. Confirmation email sent.');
                            setTimeout(function() { 
                                if (confirm('Do you want to book a cab (Free Service)?')) {
                                    window.location.href = 'cab_hotel.php?hotel_booking_id=" . urlencode($booking['booking_id']) . "'; 
                                } else {
                                    window.location.href = 'mybooking.php';
                                }
                            }, 1000);
                        </script>";
                    } else {
                        // Payment worked, but email failed
                        echo "<script>
                            alert('Payment successful, but we could not send the confirmation email.');
                            window.location.href = 'mybooking.php';
                        </script>";
                    }
                } else {
                    throw new Exception("Booking not found after update.");
                }
            } else {
                throw new Exception("Database update failed.");
            }
        } catch (Exception $e) {
            // Log error and show generic message
            error_log("Payment Error: " . $e->getMessage());
            echo "<script>alert('An error occurred while processing your booking. Please contact support.'); window.location.href = 'mybooking.php';</script>";
        }
    } else {
        echo "<script>
            alert('Payment verification failed: Invalid signature.');
            setTimeout(function() { window.location.href = 'hotels.php'; }, 2000);
        </script>";
    }
} else {
    echo "<script>
        alert('Payment verification failed: Missing payment details.');
        setTimeout(function() { window.location.href = 'hotels.php'; }, 2000);
    </script>";
}

// ---------------------------------------------------------
// Helper Functions
// ---------------------------------------------------------

function generateInvoicePDF($booking, $paymentId) {
    // Ensure invoices directory exists
    $dir = 'invoices';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdf = new FPDF();
    $pdf->AddPage();

    // Header Styling
    $pdf->SetFillColor(64, 64, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 16);

    // Header
    $pdf->Cell(0, 12, 'Book My Stay', 0, 1, 'C', true);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Hotel Invoice', 0, 1, 'C', true);
    $pdf->Ln(10);

    // Reset text color for content
    $pdf->SetTextColor(0, 0, 0);

    // Table Header
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(230, 230, 230); // Light Gray
    $pdf->Cell(50, 10, 'Field', 1, 0, 'C', true);
    $pdf->Cell(140, 10, 'Details', 1, 1, 'C', true);

    // Table Content
    $pdf->SetFont('Arial', '', 12);
    
    $rows = [
        'Username'    => $booking['username'],
        'Full Name'   => $booking['fullname'],
        'Booking ID'  => $booking['booking_id'],
        'Order ID'    => $booking['order_id'],
        'Payment ID'  => $paymentId, // Use the verified payment ID
        'Hotel'       => $booking['hname'],
        'Room Type'   => $booking['rname'],
        'Check-in'    => date('d-M-Y', strtotime($booking['checkin'])),
        'Check-out'   => date('d-M-Y', strtotime($booking['checkout'])),
        'Guests'      => $booking['adult'] . ' Adults, ' . $booking['child'] . ' Children',
        'Phone No.'   => $booking['userphno'],
        'Email'       => $booking['useremail'],
        'Total Price' => 'Rs. ' . number_format($booking['hprice'], 2),
        'Status'      => 'Paid',
    ];

    $fill = false; // Alternate row colors
    foreach ($rows as $field => $detail) {
        // Very Light Gray for alternate rows
        $pdf->SetFillColor(245, 245, 245); 
        $pdf->Cell(50, 10, $field, 1, 0, 'L', $fill);
        $pdf->Cell(140, 10, $detail, 1, 1, 'L', $fill);
        $fill = !$fill; // Toggle fill color
    }

    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->SetFillColor(64, 64, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, 'Thank you for choosing Book My Stay!', 0, 1, 'C', true);

    // Save PDF
    $filePath = $dir . "/Invoice_" . $booking['order_id'] . ".pdf";
    $pdf->Output('F', $filePath);

    return $filePath;
}

function sendPaymentConfirmationEmail($to, $username, $hotelName, $paymentId, $orderId, $invoicePath) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bookmystayonline@gmail.com'; 
        $mail->Password   = 'sftm blky plvr lyvc'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('bookmystayonline@gmail.com', 'Book My Stay');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmation - Book My Stay';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #F37254;'>Payment Successful!</h2>
                <p>Dear <strong>$username</strong>,</p>
                <p>Your booking for <strong>$hotelName</strong> has been confirmed.</p>
                <p><strong>Payment ID:</strong> $paymentId<br>
                <strong>Order ID:</strong> $orderId</p>
                <p>Please find your invoice attached.</p>
                <p>We look forward to hosting you!</p>
            </div>
        ";

        // Attachment
        if (file_exists($invoicePath)) {
            $mail->addAttachment($invoicePath, 'Invoice.pdf');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>