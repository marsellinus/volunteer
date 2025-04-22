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
                                            <span class="mt-2 text-xs text-gray-500">
                                                <?php echo date('d M Y', strtotime($app['event_date'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-between items-center">
                                        <div class="text-sm text-gray-600">
                                            <i class="far fa-clock mr-1 text-gray-400"></i> Diajukan pada <?php echo date('d M Y', strtotime($app['applied_at'])); ?>
                                        </div>
                                        <div>
                                            <?php if($certificate_eligible): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-indigo-100 text-indigo-800 mr-2">
                                                    <i class="fas fa-certificate mr-1"></i> Piagam Tersedia
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-4 py-16 sm:p-6 text-center">
                        <div class="rounded-full bg-indigo-100 w-20 h-20 flex items-center justify-center mx-auto mb-4">
                            <svg class="h-10 w-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-medium text-gray-900 mb-2">Anda belum mendaftar kegiatan volunteer</h3>
                        <p class="text-gray-500 mb-6 max-w-md mx-auto">
                            Cari dan daftarkan diri Anda untuk kegiatan volunteer sekarang untuk membantu komunitas dan mengembangkan diri.
                        </p>
                        <a href="search.php" class="inline-flex items-center px-5 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150">
                            <i class="fas fa-search mr-2"></i> Cari Kegiatan
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Certificate Section for Eligible Applications -->
            <?php if($eligible_count > 0): ?>
            <div class="mt-10 px-4 sm:px-0">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl shadow-xl p-6 text-white relative overflow-hidden">
                    <div class="absolute inset-0 bg-pattern opacity-10"></div>
                    <div class="md:flex md:items-center md:justify-between relative z-10">
                        <div>
                            <h3 class="text-xl font-bold flex items-center">
                                <i class="fas fa-certificate mr-2"></i> Piagam Tersedia
                            </h3>
                            <p class="mt-2 text-lg text-indigo-100">
                                Anda memiliki <?php echo $eligible_count; ?> piagam yang tersedia untuk kegiatan volunteer yang telah selesai
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <a href="certificates.php" class="inline-flex items-center px-5 py-3 border border-transparent text-base font-medium rounded-md shadow-md text-indigo-700 bg-white hover:bg-indigo-50 transition duration-150">
                                <i class="fas fa-award mr-2"></i> Lihat Piagam
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <?php include '../../includes/footer.php'; ?>

        <style>
        .bg-pattern {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath fill-rule='evenodd' d='M0 0h40v40H0V0zm40 40h40v40H40V40zm0-40h2l-2 2V0zm0 4l4-4h2l-6 6V4zm0 4l8-8h2L40 10V8zm0 4L52 0h2L40 14v-2zm0 4L56 0h2L40 18v-2zm0 4L60 0h2L40 22v-2zm0 4L64 0h2L40 26v-2zm0 4L68 0h2L40 30v-2zm0 4L72 0h2L40 34v-2zm0 4L76 0h2L40 38v-2zm0 4L80 0v2L42 40h-2zm4 0L80 4v2L46 40h-2zm4 0L80 8v2L50 40h-2zm4 0l28-28v2L54 40h-2zm4 0l24-24v2L58 40h-2zm4 0l20-20v2L62 40h-2zm4 0l16-16v2L66 40h-2zm4 0l12-12v2L70 40h-2zm4 0l8-8v2l-6 6h-2zm4 0l4-4v2l-2 2h-2z'/%3E%3C/g%3E%3C/svg%3E");
        }
        </style>
    </div>
</body>
</html>
