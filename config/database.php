<?php
// Database Configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "volunteer_db";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . 
        "<br>Make sure you have set up the database by running the setup.php script.");
}

// Set charset
$conn->set_charset("utf8mb4");
?>
