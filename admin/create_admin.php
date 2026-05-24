<?php
// create_admin.php - Run once then DELETE

error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "villa_booking";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Admin credentials
$admin_username = "admin";
$admin_password = "admin123"; // Change this!

// Hash the password
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// Check if admin already exists
$check_sql = "SELECT id FROM admin WHERE name = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Admin account already exists!<br>";
    echo "<a href='admin_login.php'>Go to Admin Login</a>";
} else {
    // Create the admin (without email column)
    $insert_sql = "INSERT INTO admin (name, password) VALUES (?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ss", $admin_username, $hashed_password);
    
    if ($stmt->execute()) {
        echo "✅ Admin account created successfully!<br>";
        echo "Username: <strong>$admin_username</strong><br>";
        echo "Password: <strong>$admin_password</strong><br>";
        echo "<br><a href='admin_login.php'>Login Now</a>";
        echo "<br><br><strong style='color: red;'>IMPORTANT: Delete this file immediately!</strong>";
    } else {
        echo "❌ Error creating admin: " . $conn->error;
    }
}

$stmt->close();
$conn->close();
?>