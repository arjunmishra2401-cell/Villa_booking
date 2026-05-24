<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'includes/database.php';

// 1. AUTH CHECK (Must be before any HTML output)
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$message = ""; 

// 2. HANDLE BOOKING CANCELLATION (If booking_id is present)
if (isset($_GET['booking_id'])) {
    $bid = $_GET['booking_id'];
    $deleteQuery = "DELETE FROM villa_booking WHERE booking_id = ?";
    $stmtDel = $conn->prepare($deleteQuery);
    $stmtDel->bind_param("i", $bid);
    
    if ($stmtDel->execute()) {
        echo "<script>alert('Booking Cancelled Successfully'); window.location.href='mybooking.php';</script>";
        exit();
    } else {
        $message = "Error cancelling booking.";
    }
    $stmtDel->close();
}

// 3. HANDLE VILLA BOOKING LOGIC
if (isset($_GET['villa_id'])) {
    $villaId = $_GET['villa_id'];

    // Fetch Villa Details
    $query = "SELECT * FROM villas1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $villaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $villa = $result->fetch_assoc();

    if (!$villa) {
        die("Error: Villa not found.");
    }

    $guestCapacity = $villa['guest_capacity'];

    // Fetch User Details securely
    $queryUser = "SELECT * FROM user WHERE username = ?";
    $stmtUser = $conn->prepare($queryUser);
    $stmtUser->bind_param("s", $username);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    $user = $resultUser->fetch_assoc();
    $name = $user['fname'] . ' ' . $user['lname'];

    // 4. HANDLE PAYMENT FORM SUBMISSION (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Collect form data
        $checkInDate = $_POST['check_in_date'];
        $checkOutDate = $_POST['check_out_date'];
        $adults = intval($_POST['adults']);
        $children = intval($_POST['children']);
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $pricePerNight = $villa['price'];
        
        $currentDate = date('Y-m-d');
        
        // Validations
        if ($checkInDate < $currentDate || $checkOutDate < $currentDate) {
            die("Error: Dates cannot be in the past.");
        }

        if ($checkOutDate <= $checkInDate) {
            die("Error: Check-out date must be after check-in date.");
        }
    
        $totalGuests = $adults + $children;
        if ($totalGuests > $guestCapacity) {
            echo "<script>alert('Error: Total guests ($totalGuests) exceed capacity of $guestCapacity.'); window.history.back();</script>";
            exit();
        }

        // Calculate Cost
        $startDate = new DateTime($checkInDate);
        $endDate = new DateTime($checkOutDate);
        $nights = $endDate->diff($startDate)->days;
        $totalPrice = $nights * $pricePerNight;
        $amount = $totalPrice * 100; // Convert to paise

        // Check Availability
        $availabilityQuery = "SELECT COUNT(*) AS booking_count 
                              FROM villa_booking 
                              WHERE vid = ? 
                              AND (
                                  (checkin <= ? AND checkout >= ?) OR 
                                  (checkin <= ? AND checkout >= ?) OR 
                                  (checkin >= ? AND checkout <= ?)
                              )";
        $stmtAvail = $conn->prepare($availabilityQuery);
        $stmtAvail->bind_param("issssss", $villaId, $checkInDate, $checkInDate, $checkOutDate, $checkOutDate, $checkInDate, $checkOutDate);
        $stmtAvail->execute();
        $resultAvail = $stmtAvail->get_result();
        $availability = $resultAvail->fetch_assoc();

        if ($availability['booking_count'] >= 5) {
             echo "<script>alert('This Villa is fully booked for the selected dates.'); window.location.href = 'book2.php?villa_id=$villaId';</script>";
             exit();
        }

        // --- RAZORPAY API INTEGRATION START ---
        $apiKey = 'rzp_test_LWmY7qihZ255G1';
        $apiSecret = 'LvudH59jJCQBC389xoS5zAOW';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.razorpay.com/v1/orders",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $amount,
                'currency' => 'INR',
                'payment_capture' => 1
            ]),
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_USERPWD => $apiKey . ':' . $apiSecret,
            CURLOPT_SSL_VERIFYPEER => false // FIX FOR LOCALHOST SSL ISSUES
        ]);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $curlError = curl_error($ch);
        }
        
        curl_close($ch);
        $responseArray = json_decode($response, true);

        if (isset($responseArray['id'])) {
            $orderId = $responseArray['id'];
            $initialStatus = 'PENDING';

            // Insert booking details into the database
            // FIX: Added 'payment_id' and 'status' columns
            $insertQuery = "INSERT INTO villa_booking 
                            (username, fullname, vid, vname, vprice, checkin, checkout, adult, child, userphno, useremail, order_id, payment_id, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtInsert = $conn->prepare($insertQuery);
            
            // FIX: Added two 's' types at the end for payment_id and status
            $stmtInsert->bind_param(
                "ssissssiiissss",
                $username,
                $name,
                $villaId,
                $villa['name'],
                $totalPrice,
                $checkInDate,
                $checkOutDate,
                $adults,
                $children,
                $phone,
                $email,
                $orderId,
                $initialStatus,
                $initialStatus
            );
            $stmtInsert->execute();
            $_SESSION['amount'] = $amount;

            // Render Razorpay Checkout
            ?>
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
                var options = {
                    "key": "<?= $apiKey ?>",
                    "amount": "<?= $_SESSION['amount'] ?>",
                    "currency": "INR",
                    "name": "Book My Stay",
                    "description": "Villa Booking Payment",
                    "order_id": "<?= $orderId ?>",
                    "handler": function (response) {
                        var form = document.createElement("form");
                        form.method = "POST";
                        form.action = "verify_payment2.php";

                        var fields = {
                            "razorpay_payment_id": response.razorpay_payment_id,
                            "razorpay_order_id": response.razorpay_order_id,
                            "razorpay_signature": response.razorpay_signature
                        };

                        for (var key in fields) {
                            var input = document.createElement("input");
                            input.type = "hidden";
                            input.name = key;
                            input.value = fields[key];
                            form.appendChild(input);
                        }
                        document.body.appendChild(form);
                        form.submit();
                    },
                    "prefill": {
                        "name": "<?= $name ?>",
                        "email": "<?= $email ?>",
                        "contact": "<?= $phone ?>"
                    },
                    "theme": { "color": "#3399cc" },
                    "modal": {
                        "ondismiss": function() { window.location.href = "mybooking.php"; }
                    }
                };
                var rzp1 = new Razorpay(options);
                rzp1.open();
            </script>
            <?php
            exit(); 
        } else {
            // DETAILED ERROR REPORTING
            echo "<h3>Something went wrong connecting to Razorpay!</h3>";
            echo "<p><strong>Debug Details:</strong></p>";
            if (isset($curlError)) {
                echo "<p>Connection Error: " . htmlspecialchars($curlError) . "</p>";
            }
            echo "<pre>";
            print_r($responseArray); 
            echo "</pre>";
            die("Error generating Razorpay Order ID. Please check the details above.");
        }
    }
} else {
    // If no booking_id and no villa_id, invalid request
    header("Location: index.php");
    exit();
}

// 5. RENDER THE BOOKING FORM (GET Request)
include 'includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Book Villa</title>
    <link rel="stylesheet" href="payform.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { font-family: Arial, sans-serif; }
        .payform { margin-top: 110px; }
        form { max-width: 600px; padding: 20px; border: 1px solid #ccc; border-radius: 10px; background-color: #f9f9f9; margin: 0 auto; }
        input, select, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        input[readonly], select[readonly] { background-color: #e9ecef; }
    </style>
</head>
<body>
    
    <form method="POST" class="payform">
        <h2>Booking Details for <?= htmlspecialchars($villa['name']); ?></h2>
        
        <label>Username:</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_SESSION['username']); ?>" readonly>

        <label>Full Name:</label>
        <input type="text" name="name" value="<?= htmlspecialchars($name); ?>" readonly>

        <label>Villa Name:</label>
        <input type="text" value="<?= htmlspecialchars($villa['name']); ?>" readonly>

        <label>Price Per Night:</label>
        <input type="text" value="₹<?= number_format($villa['price'], 2); ?>" readonly>

        <label>Phone Number:</label>
        <input type="tel" name="phone" pattern="[0-9]{10}" value="<?= htmlspecialchars($user['phno']); ?>" required>

        <label>Email Address:</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>

        <label>Check-in Date:</label>
        <input type="date" id="check_in_date" name="check_in_date" min="<?= date('Y-m-d'); ?>" required>

        <label>Check-out Date:</label>
        <input type="date" id="check_out_date" name="check_out_date" min="<?= date('Y-m-d'); ?>" required>

        <label>Adults:</label>
        <input type="number" id="adults" name="adults" min="1" required>

        <label>Children:</label>
        <input type="number" id="children" name="children" min="0" value="0" required>

        <button type="submit">Pay Now</button>
    </form>

    <script>
        document.getElementById('check_in_date').addEventListener('change', function () {
            document.getElementById('check_out_date').setAttribute('min', this.value);
        });

        const adultsInput = document.getElementById("adults");
        const childrenInput = document.getElementById("children");
        const guestCapacity = <?= $guestCapacity; ?>;

        function validateGuests() {
            const a = parseInt(adultsInput.value) || 0;
            const c = parseInt(childrenInput.value) || 0;
            if ((a + c) > guestCapacity) {
                alert(`Total guests cannot exceed ${guestCapacity}.`);
                adultsInput.value = ""; 
                return false;
            }
        }
        adultsInput.addEventListener("change", validateGuests);
        childrenInput.addEventListener("change", validateGuests);
    </script>

</body>
</html>

<?php include 'includes/footer.php'; ?>