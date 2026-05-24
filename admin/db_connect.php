<?php
$conn = new mysqli("localhost", "root", "", "villa_booking");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>