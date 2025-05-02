<?php
// Configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "volunteer_db";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to add certificate columns to applications table
$sql = "ALTER TABLE applications 
        ADD COLUMN certificate_generated TINYINT(1) DEFAULT 0 AFTER status,
        ADD COLUMN certificate_date DATE DEFAULT NULL AFTER certificate_generated";

// Execute the SQL
if ($conn->query($sql)) {
    echo "<h1>Applications table updated successfully!</h1>";
    echo "<p>The certificate columns have been added to the applications table.</p>";
    echo "<p>You can now <a href='../public/index.php'>visit the website</a>.</p>";
} else {
    echo "Error updating applications table: " . $conn->error;
}

$conn->close();
?>
