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

// SQL to create notifications table
$sql = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    owner_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'info', 'success', 'warning', 'danger'
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_owner ON notifications(owner_id, is_read);
";

// Execute the SQL
if ($conn->multi_query($sql)) {
    echo "<h1>Notifications table created successfully!</h1>";
    echo "<p>The notifications table has been added to the VolunteerHub database.</p>";
    echo "<p>You can now <a href='../public/index.php'>visit the website</a>.</p>";
} else {
    echo "Error creating notifications table: " . $conn->error;
}

$conn->close();
?>
