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

// SQL to create search history table
$sql = "
CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_term VARCHAR(255) NOT NULL,
    search_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_search_history_user ON search_history(user_id, search_date);
";

// Execute the SQL
if ($conn->multi_query($sql)) {
    echo "<h1>Search history table created successfully!</h1>";
    echo "<p>The search history table has been added to the VolunteerHub database to enable better recommendations.</p>";
    echo "<p>You can now <a href='../public/index.php'>visit the website</a>.</p>";
} else {
    echo "Error creating search history table: " . $conn->error;
}

$conn->close();
?>
