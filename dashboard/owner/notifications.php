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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - VolunteerHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#10283f',
                        pageBg: '#ffffff'
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style>
        body, html {
            background-color:rgb(255, 255, 255);
        }
        .notification-item {
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-unread {
            background-color: rgba(16, 40, 63, 0.05);
            border-left: 3px solid #10283f;
        }
        .badge-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.25rem center;
            background-repeat: no-repeat;
            background-size: 1rem 1rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
    </style>
</head>
<body class="bg-pageBg flex flex-col min-h-screen">
    <div class="flex-grow">
        <!-- Navigation -->
        <?php include '../../includes/header_owner.php'; ?>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 pt-20 mt-10">
            <!-- Page Header -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Notifikasi</h1>
                    <?php if($unread_count > 0): ?>
                        <span class="badge-pulse ml-3 px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary text-white">
                            <?php echo $unread_count; ?> baru
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if(count($notifications) > 0): ?>
                <a href="notifications.php?mark_all_read=1" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm leading-4 font-medium rounded-md text-primary hover:bg-primary/5 transition-colors duration-150">
                    <i class="fas fa-check-double mr-1.5"></i> Tandai semua dibaca
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Notifications List -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <?php if(count($notifications) > 0): ?>
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 flex justify-between items-center">
                        <div class="flex space-x-4">
                            <button class="text-primary font-medium text-sm focus:outline-none border-b-2 border-primary pb-1">
                                Semua
                            </button>
                            <button class="text-gray-500 hover:text-primary font-medium text-sm focus:outline-none hover:border-b-2 hover:border-primary pb-1">
                                Belum dibaca
                            </button>
                        </div>
                        <div class="flex items-center text-sm text-gray-500">
                            <span class="hidden sm:inline">Urutkan: </span>
                            <select class="ml-1 bg-transparent border-0 text-gray-500 pr-6 focus:outline-none focus:ring-0 text-sm font-medium">
                                <option>Terbaru</option>
                                <option>Terlama</option>
                            </select>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        <?php foreach($notifications as $notification): ?>
                            <li class="<?php echo $notification['is_read'] ? 'notification-item' : 'notification-unread notification-item'; ?> transition-all duration-200">
                                <div class="px-4 py-4 sm:px-6">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 pt-1">
                                            <?php if($notification['type'] == 'success'): ?>
                                                <div class="p-2 rounded-md bg-green-100">
                                                    <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                                </div>
                                            <?php elseif($notification['type'] == 'warning'): ?>
                                                <div class="p-2 rounded-md bg-yellow-100">
                                                    <i class="fas fa-exclamation-triangle text-yellow-500 text-lg"></i>
                                                </div>
                                            <?php elseif($notification['type'] == 'danger'): ?>
                                                <div class="p-2 rounded-md bg-red-100">
                                                    <i class="fas fa-times-circle text-red-500 text-lg"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="p-2 rounded-md bg-primary/10">
                                                    <i class="fas fa-info-circle text-primary text-lg"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <div class="flex justify-between">
                                                <div>
                                                    <p class="text-sm font-semibold text-primary">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <?php if(!$notification['is_read']): ?>
                                                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                                                Baru
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="flex items-center">
                                                    <p class="text-xs text-gray-500 flex items-center">
                                                        <i class="far fa-clock mr-1 text-gray-400"></i>
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
                                            </div>
                                            <p class="mt-1 text-sm text-gray-700">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <div class="mt-2 text-sm flex justify-between items-center">
                                                <div>
                                                    <?php if($notification['link']): ?>
                                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="inline-flex items-center text-primary hover:text-primary/80 font-medium">
                                                            Lihat Detail <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if(!$notification['is_read']): ?>
                                                    <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="text-gray-500 hover:text-gray-700 font-medium text-xs">
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
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100">
                            <i class="fas fa-bell-slash text-gray-400 text-xl"></i>
                        </div>
                        <h3 class="mt-3 text-sm font-medium text-gray-900">Tidak ada notifikasi</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">
                            Anda akan menerima notifikasi tentang pendaftaran kegiatan volunteer disini.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Filter buttons functionality could be added here
                    const filterButtons = document.querySelectorAll('.bg-gray-50 button');
                    if (filterButtons.length) {
                        filterButtons.forEach(button => {
                            button.addEventListener('click', function() {
                                // Reset all buttons
                                filterButtons.forEach(btn => {
                                    btn.classList.remove('text-primary', 'border-b-2', 'border-primary');
                                    btn.classList.add('text-gray-500');
                                });
                                
                                // Activate clicked button
                                this.classList.remove('text-gray-500');
                                this.classList.add('text-primary', 'border-b-2', 'border-primary');
                                
                                // Filter functionality would be implemented here
                            });
                        });
                    }
                });

                // Handle dropdown toggle
                document.addEventListener('DOMContentLoaded', function() {
                    const dropdownButton = document.querySelector('.dropdown button');
                    const dropdownMenu = document.querySelector('.dropdown-menu');
                    
                    if (dropdownButton && dropdownMenu) {
                        dropdownButton.addEventListener('click', function() {
                            dropdownMenu.classList.toggle('hidden');
                        });
                        
                        // Close dropdown when clicking outside
                        document.addEventListener('click', function(event) {
                            if (!event.target.closest('.dropdown')) {
                                dropdownMenu.classList.add('hidden');
                            }
                        });
                    }
                });
            </script>
        </main>
    </div>
    
    <footer class="bg-pageBg border-t border-gray-200">
        <?php include '../../includes/footer.php'; ?>
    </footer>
</body>
</html>