<?php
include_once '../../config/database.php';
include_once '../../includes/notifications.php'; // Add this line
session_start();

// Check if owner is logged in
if(!isset($_SESSION['owner_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$owner_name = $_SESSION['owner_name'];

// Get unread notifications count with error handling
try {
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity</title>
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-indigo-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-white">VolunteerHub</h1>
                        </div>
                        <div class="ml-6 flex items-center space-x-4">
                            <a href="index.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-home mr-1"></i> Dashboard
                            </a>
                            <a href="create_activity.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700">
                                <i class="fas fa-plus-circle mr-1"></i> Buat Kegiatan
                            </a>
                            <a href="manage_activities.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tasks mr-1"></i> Kelola Kegiatan
                            </a>
                            <a href="notifications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium relative">
                                <i class="fas fa-bell mr-1"></i> Notifikasi
                                <?php if($unread_count > 0): ?>
                                <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                    <?php echo $unread_count; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-user mr-1"></i> Profil
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($owner_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Edit Activity</h2>
            <!-- Form for editing activity -->
            <form action="update_activity.php" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div class="mb-4">
                    <label for="activity_name" class="block text-gray-700 text-sm font-bold mb-2">Activity Name:</label>
                    <input type="text" id="activity_name" name="activity_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label for="activity_date" class="block text-gray-700 text-sm font-bold mb-2">Activity Date:</label>
                    <input type="date" id="activity_date" name="activity_date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                </div>
                <div class="mb-4">
                    <label for="activity_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                    <textarea id="activity_description" name="activity_description" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="4" required></textarea>
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Update Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>