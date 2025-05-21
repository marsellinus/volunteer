<?php
// Include notifications functions if not already included
if(!function_exists('getUserUnreadNotificationsCount')) {
    include_once '../../includes/notifications.php';
}

// Check for unread notifications
$unread_count = 0;
if(isset($_SESSION['user_id'])) {
    $unread_count = getUserUnreadNotificationsCount($_SESSION['user_id'], $conn);
}
?>
<!-- Navigation -->
<nav class="bg-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <h1 class="text-xl font-bold text-[#10283f]">VolunteerHub</h1>
            </div>
            
            <!-- Centered Navigation Items -->
            <div class="flex items-center justify-center flex-1">
                <div class="flex space-x-4">
                    <a href="search.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'bg-gray-100' : ''; ?>">
                        Cari
                    </a>
                    <a href="my_applications.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'my_applications.php' ? 'bg-gray-100' : ''; ?>">
                        Lamaran
                    </a>
                    <a href="certificates.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'bg-gray-100' : ''; ?>">
                        Piagam
                    </a>
                    <a href="notifications.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-gray-100' : ''; ?> relative">
                        Notifikasi
                        <?php if($unread_count > 0): ?>
                        <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                            <?php echo $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-gray-100' : ''; ?>">
                        Profil
                    </a>
                </div>
            </div>
            
            <!-- User Info and Logout -->
            <div class="flex items-center">
                <span class="text-[#10283f] mr-4">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../../auth/logout.php" class="bg-[#10283f] hover:bg-[#10283f]/80 text-white px-3 py-2 rounded-full text-sm font-medium transition-colors duration-300 transform hover:-translate-y-0.5">
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>