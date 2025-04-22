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

// Handle mark as read
if(isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    markNotificationAsRead($_GET['mark_read'], $conn);
    header("Location: notifications.php");
    exit;
}

// Handle mark all as read
if(isset($_GET['mark_all_read'])) {
    markAllOwnerNotificationsAsRead($owner_id, $conn);
    header("Location: notifications.php");
    exit;
}

// Get notifications
try {
    $notifications = getOwnerNotifications($owner_id, $conn, 50);
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $notifications = [];
    $unread_count = 0;
}

// Set page title
$page_title = 'Notifikasi - VolunteerHub';

// Include the header
include '../../includes/header_owner.php';
?>

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
                    Anda akan menerima notifikasi tentang pendaftaran kegiatan volunteer disini.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
