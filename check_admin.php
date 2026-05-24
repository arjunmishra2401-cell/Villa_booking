<?php
// check_admin.php
$conn = new mysqli("localhost", "root", "", "bookmyroom");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check table structure
$result = $conn->query("DESCRIBE admin");
echo "<h3>Admin Table Structure:</h3>";
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Check existing data
$result = $conn->query("SELECT * FROM admin");
echo "<h3>Existing Admin Records:</h3>";
if ($result->num_rows > 0) {
    echo "<table border='1'><tr>";
    // Headers
    while ($field = $result->fetch_field()) {
        echo "<th>" . htmlspecialchars($field->name) . "</th>";
    }
    echo "</tr>";
    
    // Data
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No admin records found.";
}

$conn->close();
?>