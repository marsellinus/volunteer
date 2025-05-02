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

// Get user's applied activities
$applications = $conn->query("SELECT a.*, va.title, va.category, va.location, va.event_date, va.application_deadline 
                            FROM applications a 
                            JOIN volunteer_activities va ON a.activity_id = va.id 
                            WHERE a.user_id = $user_id 
                            ORDER BY a.applied_at DESC");

// Get unread notifications count with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Check if certificate columns exist - with error handling
$columnsExist = true;
try {
    $result = $conn->query("SHOW COLUMNS FROM applications LIKE 'certificate_generated'");
    $columnsExist = ($result->num_rows > 0);
} catch (Exception $e) {
    $columnsExist = false;
}

// Check if user has any completed and approved applications eligible for certificates
// Handle case where the query might fail due to missing columns
$eligible_count = 0;
try {
    $eligible_query = $conn->query("
        SELECT COUNT(*) as count
        FROM applications a 
        JOIN volunteer_activities va ON a.activity_id = va.id 
        WHERE a.user_id = $user_id 
        AND a.status = 'approved'
        AND va.event_date < CURDATE()
    ");
    
    if ($eligible_query) {
        $eligible_result = $eligible_query->fetch_assoc();
        if ($eligible_result) {
            $eligible_count = $eligible_result['count'];
        }
    }
} catch (Exception $e) {
    $eligible_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lamaran Saya - VolunteerHub</title>
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
                            <a href="my_applications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700">
                                <i class="fas fa-clipboard-list mr-1"></i> Lamaran
                            </a>
                            <a href="certificates.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-certificate mr-1"></i> Piagam
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
                        <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($user_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Database Update Notification -->
            <?php if (!$columnsExist): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">Database Update Required</p>
                <p>The certificate feature requires a database update. Please run the setup script: <code>setup/update_applications_table.php</code></p>
                <p class="mt-3">
                    <a href="../../setup/update_applications_table.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded inline-block mt-2">Run Update Script</a>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Page Title -->
            <div class="px-4 sm:px-0">
                <h2 class="text-2xl font-bold text-gray-900">Lamaran Saya</h2>
                <p class="mt-1 text-sm text-gray-600">Daftar semua pendaftaran volunteer yang sudah Anda ajukan.</p>
            </div>
            
            <!-- Applications List -->
            <div class="mt-6 px-4 sm:px-0">
                <?php if($applications && $applications->num_rows > 0): ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul role="list" class="divide-y divide-gray-200">
                        <?php while($app = $applications->fetch_assoc()): 
                            // Determine status color
                            $status_color = 'gray';
                            $status_text = 'Menunggu';
                            $status_icon = 'clock';
                            
                            if($app['status'] == 'approved') {
                                $status_color = 'green';
                                $status_text = 'Diterima';
                                $status_icon = 'check-circle';
                            } elseif($app['status'] == 'rejected') {
                                $status_color = 'red';
                                $status_text = 'Ditolak';
                                $status_icon = 'times-circle';
                            } elseif($app['status'] == 'pending') {
                                $status_color = 'yellow';
                                $status_text = 'Menunggu';
                                $status_icon = 'clock';
                            }
                            
                            // Check if event has passed and application was approved (eligible for certificate)
                            $event_passed = strtotime($app['event_date']) < time();
                            $certificate_eligible = $event_passed && $app['status'] == 'approved';
                        ?>
                        <li>
                            <a href="view_application.php?id=<?php echo $app['id']; ?>" class="block hover:bg-gray-50">
                                <div class="px-4 py-4 sm:px-6">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-medium text-indigo-600 truncate">
                                            <?php echo htmlspecialchars($app['title']); ?>
                                        </p>
                                        <div class="ml-2 flex-shrink-0 flex">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800">
                                                <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-2 sm:flex sm:justify-between">
                                        <div class="sm:flex">
                                            <p class="flex items-center text-sm text-gray-500">
                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                                </svg>
                                                <?php echo htmlspecialchars($app['location']); ?>
                                            </p>
                                            <p class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0 sm:ml-6">
                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                </svg>
                                                <?php echo date('d M Y', strtotime($app['event_date'])); ?>
                                            </p>
                                        </div>
                                        <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                            <div class="flex items-center">
                                                <?php if($certificate_eligible): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-green-100 text-green-800 mr-2">
                                                        <i class="fas fa-certificate mr-1"></i> Piagam Tersedia
                                                    </span>
                                                <?php endif; ?>
                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                                </svg>
                                                Diajukan pada <?php echo date('d M Y', strtotime($app['applied_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php else: ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Anda belum mendaftar kegiatan volunteer</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Cari dan daftarkan diri Anda untuk kegiatan volunteer sekarang.
                        </p>
                        <div class="mt-6">
                            <a href="search.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                <i class="fas fa-search mr-2"></i> Cari Kegiatan
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Certificate Section for Eligible Applications -->
            <?php if($eligible_count > 0): ?>
            <div class="mt-8 px-4 sm:px-0">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-md p-6 text-white">
                    <div class="md:flex md:items-center md:justify-between">
                        <div>
                            <h3 class="text-lg font-medium">Piagam Tersedia</h3>
                            <p class="mt-1 text-sm text-indigo-100">
                                Anda memiliki piagam yang tersedia untuk kegiatan volunteer yang telah selesai.
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <a href="certificates.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-white hover:bg-indigo-50">
                                <i class="fas fa-certificate mr-2"></i> Lihat Piagam
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 mt-12">
            <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div class="md:flex md:items-center md:justify-between">
                    <div class="flex justify-center md:order-2">
                        <p class="text-center text-gray-400">&copy; 2023 VolunteerHub. All rights reserved.</p>
                    </div>
                    <div class="mt-8 md:mt-0 md:order-1">
                        <p class="text-center text-base text-gray-400">Made with ❤️ for community impact</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
