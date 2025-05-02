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
<nav class="bg-indigo-600 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <h1 class="text-xl font-bold text-white">VolunteerHub</h1>
                </div>
                <div class="ml-6 flex items-center space-x-4">
                    <a href="search.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'search.php' ? 'bg-indigo-700' : ''; ?>">
                        <i class="fas fa-search mr-1"></i> Cari
                    </a>
                    <a href="my_applications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'my_applications.php' ? 'bg-indigo-700' : ''; ?>">
                        <i class="fas fa-clipboard-list mr-1"></i> Lamaran
                    </a>
                    <a href="certificates.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'bg-indigo-700' : ''; ?>">
                        <i class="fas fa-certificate mr-1"></i> Piagam
                    </a>
                    <a href="notifications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'bg-indigo-700' : ''; ?> relative">
                        <i class="fas fa-bell mr-1"></i> Notifikasi
                        <?php if($unread_count > 0): ?>
                        <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                            <?php echo $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-indigo-700' : ''; ?>">
                        <i class="fas fa-user mr-1"></i> Profil
                    </a>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>
