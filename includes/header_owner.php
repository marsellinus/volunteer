<?php
// Include notifications functions if not already included
if(!function_exists('getOwnerUnreadNotificationsCount')) {
    include_once '../../includes/notifications.php';
}

// Check if owner is logged in
if(!isset($_SESSION['owner_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$owner_name = $_SESSION['owner_name'];

// Get current page filename for highlighting the active menu item
$current_page = basename($_SERVER['PHP_SELF']);

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
    <title><?php echo isset($page_title) ? $page_title : 'VolunteerHub'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php if (isset($extra_head)) echo $extra_head; ?>
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
                            <a href="index.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo ($current_page == 'index.php') ? 'bg-indigo-700' : ''; ?>">
                                <i class="fas fa-home mr-1"></i> Dashboard
                            </a>
                            <a href="create_activity.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo ($current_page == 'create_activity.php' || $current_page == 'edit_activity.php') ? 'bg-indigo-700' : ''; ?>">
                                <i class="fas fa-plus-circle mr-1"></i> Buat Kegiatan
                            </a>
                            <a href="manage_activities.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo (in_array($current_page, ['manage_activities.php', 'view_applications.php', 'generate_certificates.php'])) ? 'bg-indigo-700' : ''; ?>">
                                <i class="fas fa-tasks mr-1"></i> Kelola Kegiatan
                            </a>
                            <a href="notifications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo ($current_page == 'notifications.php') ? 'bg-indigo-700' : ''; ?> relative">
                                <i class="fas fa-bell mr-1"></i> Notifikasi
                                <?php if($unread_count > 0): ?>
                                <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                    <?php echo $unread_count; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo ($current_page == 'profile.php') ? 'bg-indigo-700' : ''; ?>">
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
