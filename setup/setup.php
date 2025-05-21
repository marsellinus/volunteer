<?php
// Configuration
$host = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read SQL file - Use the full path to ensure the file is found
$sqlFilePath = __DIR__ . '/database.sql';

// Check if the file exists
if (!file_exists($sqlFilePath)) {
    die("Error: SQL file not found at: " . $sqlFilePath . 
        "<br>Please make sure the database.sql file exists in the setup directory.");
}

$sql = file_get_contents($sqlFilePath);

// Check if SQL content was properly loaded
if (empty($sql)) {
    die("Error: The SQL file is empty or could not be read properly.");
}

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "<h1>Database setup successful!</h1>";
    echo "<p>The VolunteerHub database has been created and populated with sample data.</p>";
    echo "<p>You can now <a href='../public/index.php'>visit the website</a>.</p>";
    
    echo "<h2>Test Login Credentials:</h2>";
    echo "<h3>User Accounts:</h3>";
    echo "<ul>";
    echo "<li>Email: john@example.com / Password: password</li>";
    echo "<li>Email: jane@example.com / Password: password</li>";
    echo "</ul>";
    
    echo "<h3>Owner Accounts:</h3>";
    echo "<ul>";
    echo "<li>Email: alex@ngo.org / Password: password</li>";
    echo "<li>Email: maria@example.org / Password: password</li>";
    echo "</ul>";
    
} else {
    echo "Error setting up database: " . $conn->error;
}

$conn->close();
?>
