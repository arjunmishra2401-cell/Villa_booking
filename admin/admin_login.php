<?php
session_start();
error_reporting(0);

// Connect to database
$conn = mysqli_connect("localhost", "root", "", "villa_booking") or die("Database & Server Error");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Check admin credentials
    $admin_query = "SELECT * FROM admin WHERE name = ?";
    $stmt = mysqli_prepare($conn, $admin_query);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $admin_result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($admin_result) > 0) {
        $admin = mysqli_fetch_assoc($admin_result);
        
        // Verify admin password
        if (password_verify($password, $admin['password'])) {
            // Set admin session
            $_SESSION['admin'] = $admin['name'];
            $_SESSION['username'] = $admin['name'];
            $_SESSION['last_activity'] = time();
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid admin credentials!";
        }
    } else {
        $error = "Admin not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Book My Stay</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .login-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .login-btn:hover {
            background-color: #555;
        }
        
        .error {
            color: red;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 5px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #333;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        
        <?php if(!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Admin Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">Login as Admin</button>
        </form>
        
        <div class="back-link">
            <a href="../login.php">← Back to User Login</a>
            <br>
            <a href="../index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>