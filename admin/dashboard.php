<?php
// START SESSION SAFELY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CHECK ADMIN LOGIN
if (!isset($_SESSION['admin'])) {
    header("Location: index.php");
    exit();
}

// SESSION TIMEOUT (10 minutes)
$timeout_duration = 600;

if (isset($_SESSION['last_activity'])) {
    if ((time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: index.php?timeout=1");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// DATABASE CONNECTION
$conn = mysqli_connect("localhost", "root", "", "villa_booking");
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// REUSABLE COUNT FUNCTION
function getCount($conn, $table) {
    $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM $table");
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

// FETCH COUNTS
$hotelBookings  = getCount($conn, "hotel_booking");
$villaBookings  = getCount($conn, "villa_booking");
$resortBookings = getCount($conn, "resort_booking");
$totalBookings  = $hotelBookings + $villaBookings + $resortBookings;

$totalUsers     = getCount($conn, "user");
$userQueries    = getCount($conn, "user_queries");
$totalAdmin     = getCount($conn, "admin");

$cabHotel       = getCount($conn, "cab_booking_hotel");
$cabResort      = getCount($conn, "cab_booking_resort");
$totalCab       = $cabHotel + $cabResort;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', sans-serif;
    display: flex;
    min-height: 100vh;
    background: #f5f6fa;
}

/* SIDEBAR */
.sidebar {
    width: 230px;
    background: #1e272e;
    color: #fff;
    padding: 20px;
    height: 100vh;
    position: fixed;
    overflow-y: auto;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 20px;
}

.sidebar a,
.dropdown-btn {
    display: block;
    width: 100%;
    padding: 10px;
    color: #fff;
    text-decoration: none;
    border: none;
    background: none;
    text-align: left;
    cursor: pointer;
    border-radius: 5px;
    margin-bottom: 5px;
}

.sidebar a:hover,
.dropdown-btn:hover {
    background: #485460;
}

.dropdown-btn::after {
    content: " ▼";
    float: right;
}

.dropdown-container {
    display: none;
    padding-left: 15px;
    background: #2f3640;
}

/* MAIN CONTENT */
.main-content {
    margin-left: 230px;
    padding: 25px;
    width: 100%;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-size: 24px;
}

.logout-btn {
    padding: 8px 15px;
    background: #e84118;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
}

.logout-btn:hover {
    background: #c23616;
}

/* CARDS */
.card-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 20px;
}

.card {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    text-align: center;
    transition: 0.3s;
}

.card:hover {
    transform: translateY(-5px);
}

.card h3 {
    margin-bottom: 15px;
    font-size: 18px;
}

.card .count {
    font-size: 32px;
    font-weight: bold;
}

/* COLORS */
.hotel { border-top: 4px solid #0984e3; }
.villa { border-top: 4px solid #00b894; }
.resort { border-top: 4px solid #fdcb6e; }
.total { border-top: 4px solid #6c5ce7; }
.users { border-top: 4px solid #636e72; }
.query { border-top: 4px solid #e17055; }
.cab { border-top: 4px solid #00cec9; }

/* RESPONSIVE */
@media(max-width: 768px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }
    .main-content {
        margin-left: 0;
    }
}
</style>
</head>

<body>

<div class="sidebar">
    <h2>Book My Stay</h2>
    <a href="dashboard.php">Dashboard</a>

    <button class="dropdown-btn">Hotels</button>
    <div class="dropdown-container">
        <a href="dashboard.php?page=hotel.php">Hotel</a>
        <a href="dashboard.php?page=hotelrooms.php">Rooms</a>
        <a href="dashboard.php?page=hotelbooking.php">Bookings</a>
    </div>

    <button class="dropdown-btn">Resorts</button>
    <div class="dropdown-container">
        <a href="dashboard.php?page=resort.php">Resort</a>
        <a href="dashboard.php?page=resortbooking.php">Booking</a>
    </div>

    <button class="dropdown-btn">Villas</button>
    <div class="dropdown-container">
        <a href="dashboard.php?page=villa.php">Villa</a>
        <a href="dashboard.php?page=villabooking.php">Booking</a>
    </div>

    <a href="dashboard.php?page=user.php">Users</a>
    <a href="dashboard.php?page=manager.php">Manager</a>
    <a href="dashboard.php?page=admin.php">Admin</a>
</div>

<div class="main-content">
    <div class="header">
        <h1>Admin Dashboard</h1>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

<?php
if (isset($_GET['page'])) {

    $allowedPages = [
        'manager.php','hotel.php','hotelrooms.php','hotelbooking.php',
        'resort.php','resortbooking.php','villa.php','villabooking.php',
        'user.php','admin.php'
    ];

    if (in_array($_GET['page'], $allowedPages)) {
        include($_GET['page']);
    } else {
        echo "<p>Invalid page selected.</p>";
    }

} else {
?>

<div class="card-container">

    <div class="card hotel">
        <h3>Hotel Bookings</h3>
        <div class="count"><?php echo $hotelBookings; ?></div>
    </div>

    <div class="card villa">
        <h3>Villa Bookings</h3>
        <div class="count"><?php echo $villaBookings; ?></div>
    </div>

    <div class="card resort">
        <h3>Resort Bookings</h3>
        <div class="count"><?php echo $resortBookings; ?></div>
    </div>

    <div class="card total">
        <h3>Total Bookings</h3>
        <div class="count"><?php echo $totalBookings; ?></div>
    </div>

    <div class="card cab">
        <h3>Total Cab Bookings</h3>
        <div class="count"><?php echo $totalCab; ?></div>
    </div>

    <div class="card users">
        <h3>Total Users</h3>
        <div class="count"><?php echo $totalUsers; ?></div>
    </div>

    <div class="card query">
        <h3>User Queries</h3>
        <div class="count"><?php echo $userQueries; ?></div>
    </div>

    <div class="card users">
        <h3>Total Admins</h3>
        <div class="count"><?php echo $totalAdmin; ?></div>
    </div>
    <div style="margin-top:40px; background:#fff; padding:20px; border-radius:10px;">
         <h3 style="margin-bottom:20px;">Bookings Overview</h3>
         <canvas id="bookingChart" height="120px"></canvas>
    </div>

    </div>

<?php } ?>

</div>

<script>
document.querySelectorAll(".dropdown-btn").forEach(btn => {
    btn.addEventListener("click", function () {
        let content = this.nextElementSibling;
        content.style.display = content.style.display === "block" ? "none" : "block";
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const hotelBookings = <?php echo $hotelBookings; ?>;
const villaBookings = <?php echo $villaBookings; ?>;
const resortBookings = <?php echo $resortBookings; ?>;

const ctx = document.getElementById('bookingChart').getContext('2d');

const bookingChart = new Chart(ctx, {
    type: 'bar', // You can change to 'pie' or 'doughnut'
    data: {
        labels: ['Hotels', 'Villas', 'Resorts'],
        datasets: [{
            label: 'Total Bookings',
            data: [hotelBookings, villaBookings, resortBookings],
            backgroundColor: [
                '#0984e3',
                '#00b894',
                '#fdcb6e'
            ],
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
</body>
</html>