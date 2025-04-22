<?php
include_once '../../config/database.php';
include_once '../../includes/notifications.php';
session_start();

// Check if owner is logged in
if(!isset($_SESSION['owner_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$owner_name = $_SESSION['owner_name'];

// Get unread notifications count
try {
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Set page title
$page_title = 'Buat Kegiatan Baru - VolunteerHub';

// Include the header
include '../../includes/header_owner.php';

$success_message = '';
$error_message = '';

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = $_POST['location'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $application_deadline = $_POST['application_deadline'] ?? '';
    $required_skills = $_POST['required_skills'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate required fields
    if(empty($title) || empty($category) || empty($location) || empty($event_date) || empty($application_deadline) || empty($description)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Check if event date is after application deadline
        if(strtotime($event_date) <= strtotime($application_deadline)) {
            $error_message = "Event date must be after application deadline.";
        } else {
            // Insert the new activity
            $stmt = $conn->prepare("INSERT INTO volunteer_activities 
                                (owner_id, title, category, location, event_date, application_deadline, required_skills, description, is_featured, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                
            $stmt->bind_param("issssssis", $owner_id, $title, $category, $location, $event_date, $application_deadline, $required_skills, $description, $is_featured);
            
            if($stmt->execute()) {
                $success_message = "Volunteer activity created successfully!";
            } else {
                $error_message = "Error creating volunteer activity. Please try again.";
            }
        }
    }
}

// Get list of categories for dropdown (from existing activities)
$categories = $conn->query("SELECT DISTINCT category FROM volunteer_activities ORDER BY category");
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-bold text-gray-900">Create New Volunteer Activity</h2>
    <p class="mt-1 text-sm text-gray-600">Fill in the details below to create a new volunteer opportunity.</p>
    
    <?php if($success_message): ?>
        <div class="mt-6 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        <?php echo $success_message; ?>
                    </p>
                    <div class="mt-4">
                        <div class="-mx-2 -my-1.5 flex">
                            <a href="manage_activities.php" class="bg-green-50 px-2 py-1.5 rounded-md text-sm font-medium text-green-800 hover:bg-green-100">
                                View All Activities
                            </a>
                            <button type="button" onclick="location.reload();" class="ml-3 bg-green-50 px-2 py-1.5 rounded-md text-sm font-medium text-green-800 hover:bg-green-100">
                                Create Another
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="mt-6 rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">
                        <?php echo $error_message; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-6 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <form action="create_activity.php" method="POST">
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-6">
                    <label for="title" class="block text-sm font-medium text-gray-700">
                        Title *
                    </label>
                    <div class="mt-1">
                        <input type="text" name="title" id="title" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
                
                <div class="sm:col-span-3">
                    <label for="category" class="block text-sm font-medium text-gray-700">
                        Category *
                    </label>
                    <div class="mt-1">
                        <select id="category" name="category" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select a category</option>
                            <?php if($categories && $categories->num_rows > 0): ?>
                                <?php while($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                            <option value="Education">Education</option>
                            <option value="Environment">Environment</option>
                            <option value="Health">Health</option>
                            <option value="Community Service">Community Service</option>
                            <option value="Animal Welfare">Animal Welfare</option>
                            <option value="Arts & Culture">Arts & Culture</option>
                            <option value="Disaster Relief">Disaster Relief</option>
                            <option value="Human Rights">Human Rights</option>
                            <option value="Sports">Sports</option>
                            <option value="Technology">Technology</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="location" class="block text-sm font-medium text-gray-700">
                        Location *
                    </label>
                    <div class="mt-1">
                        <input type="text" name="location" id="location" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="event_date" class="block text-sm font-medium text-gray-700">
                        Event Date *
                    </label>
                    <div class="mt-1">
                        <input type="date" name="event_date" id="event_date" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="application_deadline" class="block text-sm font-medium text-gray-700">
                        Application Deadline *
                    </label>
                    <div class="mt-1">
                        <input type="date" name="application_deadline" id="application_deadline" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-6">
                    <label for="required_skills" class="block text-sm font-medium text-gray-700">
                        Required Skills
                    </label>
                    <div class="mt-1">
                        <input type="text" name="required_skills" id="required_skills" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="e.g., Communication, Teamwork, Foreign Language">
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Comma separated list of skills required for this volunteer opportunity.</p>
                </div>

                <div class="sm:col-span-6">
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Description *
                    </label>
                    <div class="mt-1">
                        <textarea id="description" name="description" rows="5" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Detailed description of the volunteer opportunity including responsibilities, benefits, and any other relevant information.</p>
                </div>

                <div class="sm:col-span-6">
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="is_featured" name="is_featured" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_featured" class="font-medium text-gray-700">Feature this activity</label>
                            <p class="text-gray-500">Featured activities appear on the homepage and get more visibility.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="window.history.back()" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </button>
                <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Create Activity
                </button>
            </div>
        </form>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
