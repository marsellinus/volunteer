<?php
// Configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "volunteer_db";

// Connect to the database
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sample data
$categories = ['Community Service', 'Education', 'Environment', 'Health', 'Arts & Culture'];
$locations = ['Jakarta', 'Bandung', 'Surabaya', 'Medan', 'Yogyakarta'];
$activities = [
    [
        'title' => 'Beach Cleanup',
        'description' => 'Join us in cleaning up the beach to protect marine life and promote a cleaner environment.',
        'category' => 'Environment',
        'location' => 'Bali',
        'event_date' => date('Y-m-d', strtotime('+1 week')),
        'application_deadline' => date('Y-m-d', strtotime('+3 days')),
        'required_skills' => 'None',
        'is_featured' => 1
    ],
    [
        'title' => 'Tree Planting',
        'description' => 'Help us plant trees to combat deforestation and improve air quality.',
        'category' => 'Environment',
        'location' => 'Bandung',
        'event_date' => date('Y-m-d', strtotime('+2 weeks')),
        'application_deadline' => date('Y-m-d', strtotime('+1 week')),
        'required_skills' => 'None',
        'is_featured' => 0
    ]
];

// Insert sample activities
foreach ($activities as $activity) {
    // Add some randomized elements
    $random_category = $activity['category'] ?? $categories[array_rand($categories)];
    $random_location = $activity['location'] ?? $locations[array_rand($locations)];
    
    $stmt = $conn->prepare("INSERT INTO volunteer_activities (owner_id, title, description, category, location, event_date, application_deadline, required_skills, is_featured, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("issssssis", 
        $owner_id, 
        $activity['title'], 
        $activity['description'], 
        $random_category, 
        $random_location, 
        $activity['event_date'], 
        $activity['application_deadline'], 
        $activity['required_skills'], 
        $activity['is_featured']
    );
    
    $stmt->execute();
}

echo "<p>Created " . count($activities) . " sample volunteer activities</p>";

// Add one completed activity in the past if none exists
$check = $conn->query("SELECT COUNT(*) as count FROM volunteer_activities WHERE event_date < CURDATE()");
$result = $check->fetch_assoc();

if ($result['count'] == 0) {
    // Get the owner ID
    $owner_result = $conn->query("SELECT owner_id FROM owners LIMIT 1");
    $owner = $owner_result->fetch_assoc();
    $owner_id = $owner['owner_id'];
    
    // Add a past activity
    $past_activity = [
        'title' => 'City Park Renovation',
        'description' => "Help us renovate the local park with new plants, benches, and playground equipment.\n\nThis was a successful community event where volunteers helped transform our local park.",
        'category' => 'Community Service',
        'location' => 'Jakarta',
        'event_date' => date('Y-m-d', strtotime('-2 weeks')),
        'application_deadline' => date('Y-m-d', strtotime('-1 month')),
        'required_skills' => 'None',
        'is_featured' => 0
    ];
    
    $stmt = $conn->prepare("INSERT INTO volunteer_activities (owner_id, title, description, category, location, event_date, application_deadline, required_skills, is_featured, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param("issssssis", 
        $owner_id, 
        $past_activity['title'], 
        $past_activity['description'], 
        $past_activity['category'], 
        $past_activity['location'], 
        $past_activity['event_date'], 
        $past_activity['application_deadline'], 
        $past_activity['required_skills'], 
        $past_activity['is_featured']
    );
    
    $stmt->execute();
    
    // Get the activity ID of the past event
    $activity_id = $conn->insert_id;
    
    // Get the sample user
    $user_result = $conn->query("SELECT user_id FROM users LIMIT 1");
    $user = $user_result->fetch_assoc();
    $user_id = $user['user_id'];
    
    // Create an approved application for the past activity
    $conn->query("INSERT INTO applications (user_id, activity_id, message, status, applied_at, certificate_generated) 
                 VALUES ($user_id, $activity_id, 'I would love to help with this project!', 'approved', '".date('Y-m-d H:i:s', strtotime('-1 month'))."', 0)");
    
    echo "<p>Added a completed activity and application for testing certificates</p>";
}

echo "<p>Data seeding completed! <a href='../dashboard/user/search.php'>Visit the user dashboard</a> or <a href='../dashboard/owner/index.php'>visit the owner dashboard</a>.</p>";

echo "<p>User login: volunteer@example.com / password123<br>Owner login: admin@example.com / password123</p>";

$conn->close();
?>