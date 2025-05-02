<?php
include_once '../../config/database.php';
include_once '../../includes/notifications.php';
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle mark as read
if(isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationAsRead($_GET['mark_read'], $conn);
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read
if(isset($_GET['mark_all_read'])) {
    markAllUserNotificationsAsRead($user_id, $conn);
    header("Location: notifications.php");
    exit;
}

// Get notifications
$notifications = getUserNotifications($user_id, $conn, 50);
$unreadCount = getUserUnreadNotificationsCount($user_id, $conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - VolunteerHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
                            <a href="search.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-search mr-1"></i> Cari
                            </a>
                            <a href="my_applications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-clipboard-list mr-1"></i> Lamaran
                            </a>
                            <a href="certificates.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-certificate mr-1"></i> Piagam
                            </a>
                            <a href="notifications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700 relative">
                                <i class="fas fa-bell mr-1"></i> Notifikasi
                                <?php if($unreadCount > 0): ?>
                                <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                    <?php echo $unreadCount; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-user mr-1"></i> Profil
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($user_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Notifikasi</h1>
                <?php if(count($notifications) > 0): ?>
                <a href="notifications.php?mark_all_read=1" class="text-sm text-indigo-600 hover:text-indigo-900">
                    <i class="fas fa-check-double mr-1"></i> Tandai semua telah dibaca
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Notifications List -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <?php if(count($notifications) > 0): ?>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach($notifications as $notification): ?>
                            <li class="<?php echo $notification['is_read'] ? 'bg-white' : 'bg-indigo-50'; ?> hover:bg-gray-50 transition-colors duration-150">
                                <div class="px-4 py-4 sm:px-6">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 pt-0.5">
                                            <?php if($notification['type'] == 'success'): ?>
                                                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                            <?php elseif($notification['type'] == 'warning'): ?>
                                                <i class="fas fa-exclamation-triangle text-yellow-500 text-lg"></i>
                                            <?php elseif($notification['type'] == 'danger'): ?>
                                                <i class="fas fa-times-circle text-red-500 text-lg"></i>
                                            <?php else: ?>
                                                <i class="fas fa-info-circle text-blue-500 text-lg"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <div class="flex justify-between">
                                                <p class="text-sm font-medium text-indigo-600">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                    <?php if(!$notification['is_read']): ?>
                                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                            Baru
                                                        </span>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    <?php 
                                                        $time_ago = time() - strtotime($notification['created_at']);
                                                        if($time_ago < 60) {
                                                            echo "Baru saja";
                                                        } elseif($time_ago < 3600) {
                                                            echo floor($time_ago / 60) . " menit yang lalu";
                                                        } elseif($time_ago < 86400) {
                                                            echo floor($time_ago / 3600) . " jam yang lalu";
                                                        } else {
                                                            echo date('d M Y H:i', strtotime($notification['created_at']));
                                                        }
                                                    ?>
                                                </p>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-700">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <div class="mt-2 text-sm flex">
                                                <?php if($notification['link']): ?>
                                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="mr-4 font-medium text-indigo-600 hover:text-indigo-500">
                                                        Lihat Detail <span aria-hidden="true">&rarr;</span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if(!$notification['is_read']): ?>
                                                    <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="font-medium text-gray-500 hover:text-gray-700">
                                                        Tandai telah dibaca
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-bell-slash text-gray-400 text-5xl mb-4"></i>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada notifikasi</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Anda akan menerima notifikasi tentang kegiatan volunteer yang diikuti disini.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
