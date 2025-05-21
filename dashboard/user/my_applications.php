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
    <title>Aplikasi Saya - VolunteerHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#10283f',
                            50: '#e6eaee',
                            100: '#b3c1d1',
                            200: '#8097b4',
                            300: '#4d6d97',
                            400: '#1a437a',
                            500: '#10283f',
                            600: '#0e2438',
                            700: '#0b1c2c',
                            800: '#081520',
                            900: '#040e14',
                        }
                    },
                    fontFamily: {
                        sans: ['Helvetica', 'Arial', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 2px 10px rgba(0, 0, 0, 0.05)',
                        'hover': '0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
                    }
                }
            }
        }
    </script>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: 'Helvetica', Arial, sans-serif;
            background-color: #f9fafb;
        }
        .page-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
            min-height: calc(100vh - 150px);
            padding-bottom: 40px;
        }
        footer {
            margin-top: auto;
            width: 100%;
        }
        /* Removed the .bg-pattern class definition */
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
        }
        .badge {
            transition: all 0.2s ease;
        }
        .badge:hover {
            transform: scale(1.05);
        }
        .shine-effect {
            position: relative;
            overflow: hidden;
        }
        .shine-effect:after {
            content: '';
            position: absolute;
            top: -50%;
            left: -60%;
            width: 20%;
            height: 200%;
            opacity: 0;
            transform: rotate(30deg);
            background: rgba(255, 255, 255, 0.13);
            background: linear-gradient(
                to right, 
                rgba(255, 255, 255, 0.13) 0%,
                rgba(255, 255, 255, 0.13) 77%,
                rgba(255, 255, 255, 0.5) 92%,
                rgba(255, 255, 255, 0.0) 100%
            );
        }
        .shine-effect:hover:after {
            opacity: 1;
            left: 130%;
            transition: all 0.7s ease;
        }
        .application-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .application-card-inner {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .application-card-content {
            flex: 1;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 3rem; /* Ensure consistent height */
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Navigation -->
    <?php include '../../includes/header_user.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 w-full flex-grow pt-24 mt-8">
        <!-- Database Update Notification -->
        <?php if (!$columnsExist): ?>
        <div class="bg-amber-50 border-l-4 border-amber-400 text-amber-700 p-5 mb-8 rounded-xl shadow-soft flex items-start" role="alert">
            <div class="mr-4 flex-shrink-0 pt-1">
                <i class="fas fa-exclamation-triangle text-2xl text-amber-500"></i>
            </div>
            <div>
                <p class="font-semibold text-lg mb-1">Database Update Required</p>
                <p class="text-amber-700/90 mb-4">The certificate feature requires a database update. Please run the setup script: <code class="bg-amber-100 px-2 py-1 rounded font-mono text-sm">setup/update_applications_table.php</code></p>
                <a href="../../setup/update_applications_table.php" class="inline-flex items-center px-4 py-2 rounded-lg shadow-sm text-white bg-amber-500 hover:bg-amber-600 transition-all font-medium text-sm">
                    <i class="fas fa-cog mr-2"></i> Run Update Script
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2 flex items-center">
                    <i class="fas fa-clipboard-list mr-3 text-primary-500"></i>Aplikasi Saya
                </h1>
                <p class="text-gray-600">Daftar semua pendaftaran volunteer yang sudah Anda ajukan.</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="search.php" class="inline-flex items-center px-5 py-2.5 rounded-lg shadow-sm text-white bg-primary-500 hover:bg-primary-600 transition-all font-medium">
                    <i class="fas fa-search mr-2"></i> Cari Kegiatan Baru
                </a>
            </div>
        </div>
        
        <!-- Certificate Banner Section (if eligible) -->
        <?php if($eligible_count > 0): ?>
        <div class="mb-8">
            <div class="bg-gradient-to-r from-primary-500 to-primary-700 rounded-2xl shadow-lg overflow-hidden relative shine-effect">
                <!-- Removed the bg-pattern div here -->
                <div class="md:flex md:items-center md:justify-between relative z-10 p-6 sm:p-8">
                    <div class="flex-1">
                        <div class="flex items-center mb-3">
                            <span class="flex h-10 w-10 rounded-full bg-white/20 items-center justify-center mr-3">
                                <i class="fas fa-certificate text-white text-xl"></i>
                            </span>
                            <h3 class="text-2xl font-bold text-white">Piagam Tersedia</h3>
                        </div>
                        <p class="text-primary-100 max-w-2xl mb-4">
                            Anda memiliki <span class="font-semibold text-white"><?php echo $eligible_count; ?></span> piagam yang tersedia untuk kegiatan volunteer yang telah selesai. Unduh dan bagikan pencapaian Anda!
                        </p>
                        <a href="certificates.php" class="inline-flex items-center px-5 py-3 border border-transparent text-base font-medium rounded-lg shadow-md text-primary-800 bg-white hover:bg-gray-50 transition-all">
                            <i class="fas fa-award mr-2"></i> Lihat Piagam
                        </a>
                    </div>
                    <div class="hidden md:block">
                        <div class="relative w-32 h-32 flex-shrink-0">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-award text-white/10 text-8xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Applications List -->
        <div class="mb-8">
            <?php if($applications && $applications->num_rows > 0): ?>
            <div class="grid md:grid-cols-2 gap-6">
                <?php while($app = $applications->fetch_assoc()): 
                    // Determine status style
                    $status_color = 'gray';
                    $status_text = 'Menunggu';
                    $status_icon = 'clock';
                    $status_bg = 'bg-gray-100';
                    $status_text_color = 'text-gray-700';
                    $status_border = 'border-gray-200';
                    
                    if($app['status'] == 'approved') {
                        $status_color = 'green';
                        $status_text = 'Diterima';
                        $status_icon = 'check-circle';
                        $status_bg = 'bg-green-100';
                        $status_text_color = 'text-green-700';
                        $status_border = 'border-green-200';
                    } elseif($app['status'] == 'rejected') {
                        $status_color = 'red';
                        $status_text = 'Ditolak';
                        $status_icon = 'times-circle';
                        $status_bg = 'bg-red-100';
                        $status_text_color = 'text-red-700';
                        $status_border = 'border-red-200';
                    } elseif($app['status'] == 'pending') {
                        $status_color = 'amber';
                        $status_text = 'Menunggu';
                        $status_icon = 'clock';
                        $status_bg = 'bg-amber-100';
                        $status_text_color = 'text-amber-700';
                        $status_border = 'border-amber-200';
                    }
                    
                    // Check if event has passed and application was approved (eligible for certificate)
                    $event_passed = strtotime($app['event_date']) < time();
                    $certificate_eligible = $event_passed && $app['status'] == 'approved';
                    
                    // Calculate days remaining or days passed
                    $today = time();
                    $event_time = strtotime($app['event_date']);
                    $days_diff = round(($event_time - $today) / (60 * 60 * 24));
                    
                    // Get category icon
                    $category_icon = 'hands-helping';
                    switch(strtolower($app['category'])) {
                        case 'education': 
                        case 'pendidikan': 
                            $category_icon = 'book';
                            break;
                        case 'environment': 
                        case 'lingkungan': 
                            $category_icon = 'leaf';
                            break;
                        case 'health': 
                        case 'kesehatan': 
                            $category_icon = 'heartbeat';
                            break;
                        case 'social': 
                        case 'sosial': 
                            $category_icon = 'users';
                            break;
                        case 'disaster': 
                        case 'bencana': 
                            $category_icon = 'house-damage';
                            break;
                    }
                ?>
                <div class="h-full"> <!-- Wrapper div to maintain consistent height -->
                    <a href="view_application.php?id=<?php echo $app['id']; ?>" class="block h-full">
                        <div class="bg-white rounded-xl shadow-soft overflow-hidden border border-gray-100 transition-all card-hover application-card">
                            <div class="p-6 application-card-inner">
                                <div class="application-card-content">
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $status_bg; ?> <?php echo $status_text_color; ?> border <?php echo $status_border; ?> badge">
                                            <i class="fas fa-<?php echo $status_icon; ?> mr-1.5"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200 badge">
                                            <i class="fas fa-<?php echo $category_icon; ?> mr-1.5"></i>
                                            <?php echo htmlspecialchars($app['category']); ?>
                                        </span>
                                    </div>
                                    
                                    <h3 class="text-lg font-semibold text-gray-900 mb-3 line-clamp-2">
                                        <?php echo htmlspecialchars($app['title']); ?>
                                    </h3>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center text-sm text-gray-500">
                                            <i class="fas fa-map-marker-alt w-4 text-center mr-2 text-gray-400"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($app['location']); ?></span>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <i class="far fa-calendar w-4 text-center mr-2 text-gray-400"></i>
                                            <time datetime="<?php echo $app['event_date']; ?>">
                                                <?php echo date('d M Y', strtotime($app['event_date'])); ?>
                                            </time>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <i class="far fa-clock w-4 text-center mr-2 text-gray-400"></i>
                                            <span>Diajukan <?php echo date('d M Y', strtotime($app['applied_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-100">
                                    <?php if($certificate_eligible): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-primary-100 text-primary-700 border border-primary-200">
                                        <i class="fas fa-certificate mr-1.5"></i> Piagam Tersedia
                                    </span>
                                    <?php elseif($days_diff > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                        <i class="fas fa-calendar-day mr-1.5"></i> <?php echo $days_diff; ?> hari lagi
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                        <i class="fas fa-calendar-check mr-1.5"></i> Selesai
                                    </span>
                                    <?php endif; ?>
                                    
                                    <div class="text-primary-500 hover:text-primary-600 transition-colors">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="bg-white shadow-soft rounded-xl overflow-hidden border border-gray-100 py-12">
                <div class="px-4 py-6 sm:p-10 text-center">
                    <div class="rounded-full bg-primary-100 w-20 h-20 flex items-center justify-center mx-auto mb-6">
                        <i class="far fa-clipboard text-4xl text-primary-500"></i>
                    </div>
                    <h3 class="text-xl font-medium text-gray-900 mb-3">Anda belum mendaftar kegiatan volunteer</h3>
                    <p class="text-gray-500 mb-8 max-w-lg mx-auto">
                        Cari dan daftarkan diri Anda untuk kegiatan volunteer sekarang untuk membantu komunitas dan mengembangkan diri Anda.
                    </p>
                    <a href="search.php" class="inline-flex items-center px-5 py-3 border border-transparent text-base font-medium rounded-lg shadow-sm text-white bg-primary-500 hover:bg-primary-600 transition-all">
                        <i class="fas fa-search mr-2"></i> Cari Kegiatan
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tips Section -->
        <div class="rounded-xl bg-white border border-gray-100 shadow-soft overflow-hidden">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-lightbulb text-amber-500 mr-2"></i> Tips Volunteering
                </h3>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="flex space-x-3">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Persiapkan diri</h4>
                            <p class="mt-1 text-sm text-gray-500">Pastikan Anda memiliki waktu dan kemampuan yang sesuai dengan kegiatan</p>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Berinteraksi</h4>
                            <p class="mt-1 text-sm text-gray-500">Jalin hubungan dengan sesama volunteer dan organisasi</p>
                        </div>
                    </div>
                    <div class="flex space-x-3">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700">
                                <i class="fas fa-certificate"></i>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Dokumentasikan</h4>
                            <p class="mt-1 text-sm text-gray-500">Simpan piagam dan dokumentasi kegiatan untuk portofolio Anda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
      <?php include '../../includes/footer.php'; ?>
</body>
</html>