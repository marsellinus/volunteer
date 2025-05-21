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

$page_title = 'Dashboard Pengelola - VolunteerHub';

// Get statistics
$stats = [
    'active_activities' => 0,
    'total_activities' => 0,
    'pending_applications' => 0,
    'total_applications' => 0
];

// Get active activities count
try {
    $active_query = $conn->query("SELECT COUNT(*) as count FROM volunteer_activities WHERE owner_id = $owner_id AND application_deadline >= CURDATE()");
    if($result = $active_query->fetch_assoc()) {
        $stats['active_activities'] = $result['count'];
    }
} catch (Exception $e) {
    // Handle silently
}

// Get total activities count
try {
    $total_query = $conn->query("SELECT COUNT(*) as count FROM volunteer_activities WHERE owner_id = $owner_id");
    if($result = $total_query->fetch_assoc()) {
        $stats['total_activities'] = $result['count'];
    }
} catch (Exception $e) {
    // Handle silently
}

// Get pending applications count
try {
    $pending_query = $conn->query("
        SELECT COUNT(*) as count FROM applications a 
        JOIN volunteer_activities va ON a.activity_id = va.id
        WHERE va.owner_id = $owner_id AND a.status = 'pending'
    ");
    if($result = $pending_query->fetch_assoc()) {
        $stats['pending_applications'] = $result['count'];
    }
} catch (Exception $e) {
    // Handle silently
}

// Get total applications count
try {
    $applications_query = $conn->query("
        SELECT COUNT(*) as count FROM applications a 
        JOIN volunteer_activities va ON a.activity_id = va.id
        WHERE va.owner_id = $owner_id
    ");
    if($result = $applications_query->fetch_assoc()) {
        $stats['total_applications'] = $result['count'];
    }
} catch (Exception $e) {
    // Handle silently
}

// Get latest activities
try {
    $latest_activities = $conn->query("
        SELECT va.*, 
               (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count
        FROM volunteer_activities va 
        WHERE va.owner_id = $owner_id
        ORDER BY va.created_at DESC
        LIMIT 5
    ");
} catch (Exception $e) {
    $latest_activities = null;
}

// Get latest applications
try {
    $latest_applications = $conn->query("
        SELECT a.*, va.title, u.name as user_name
        FROM applications a
        JOIN volunteer_activities va ON a.activity_id = va.id
        JOIN users u ON a.user_id = u.user_id
        WHERE va.owner_id = $owner_id
        ORDER BY a.applied_at DESC
        LIMIT 5
    ");
} catch (Exception $e) {
    $latest_applications = null;
}

// Get unread notifications count with error handling - using try/catch for all notifications-related code
$unread_count = 0;
try {
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if($table_check && $table_check->num_rows > 0) {
        // Table exists, get unread count
        $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
    } else {
        // Table doesn't exist, create it
        $create_table = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            owner_id INT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE CASCADE
        )";
        $conn->query($create_table);
        
        // Create indexes
        $conn->query("CREATE INDEX idx_notifications_user ON notifications(user_id, is_read)");
        $conn->query("CREATE INDEX idx_notifications_owner ON notifications(owner_id, is_read)");
    }
} catch (Exception $e) {
    // Just keep the default value of 0
}

// Include the header
include_once '../../includes/header_owner.php';
?>

<!-- Add a CSS style block for the page background -->
<style>
body {
    background-color: #e5e5e5;
}
.wave-container {
    position: absolute;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.wave {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 200%;
    height: 100px;
    background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23FFFFFF"/></svg>');
    background-size: 1200px 100px;
    animation: wave 15s linear infinite;
}

.wave1 {
    animation-delay: 0s;
    animation-duration: 20s;
    z-index: 1;
    opacity: 0.7;
    bottom: 0;
}

.wave2 {
    animation-delay: -5s;
    animation-duration: 25s;
    z-index: 2;
    opacity: 0.5;
    bottom: 10px;
}

.wave3 {
    animation-delay: -10s;
    animation-duration: 15s;
    z-index: 3;
    opacity: 0.3;
    bottom: 20px;
}

@keyframes wave {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}
</style>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 pt-20 mt-10">
    <!-- Database Setup Notification (if notifications table doesn't exist) -->
    <?php if(!isset($table_check) || $table_check->num_rows == 0): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-md shadow-sm">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Notifikasi tidak tersedia.</strong> Database perlu diperbarui.
                </p>
                <p class="text-sm text-yellow-700 mt-2">
                    <a href="../../setup/create_notifications_table.php" class="font-medium underline text-yellow-700 hover:text-yellow-600 transition duration-150">
                        Klik disini untuk memperbarui database
                    </a>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

  
   
<!-- Welcome Section - Modern with realistic JS wave animation and dynamic greeting -->
    <div class="bg-gradient-to-r from-[#10283f] to-[#1a3b58] rounded-xl shadow-xl px-8 py-10 mb-8 text-white overflow-hidden relative">
    <!-- Enhanced JS Animated wave background -->
    <canvas id="waveCanvas" class="absolute inset-0 w-full h-full opacity-30"></canvas>
    
    <div class="relative z-10 flex justify-between items-start">
        <!-- Left content area -->
        <div>
        <!-- Dynamic greeting based on time with icon -->
        <div class="flex items-center mb-1">
            <div id="greetingContainer" class="flex items-center">
            <div id="greetingIcon" class="text-yellow-300 mr-2 text-2xl"></div>
            <div id="greetingText" class="text-xl font-medium text-gray-100"></div>
            </div>
        </div>
        
        <h1 class="text-3xl md:text-4xl font-bold mb-2"><?php echo htmlspecialchars($owner_name); ?>!</h1>
        
        <p class="text-gray-100 text-lg">Kelola kegiatan volunteer Anda dan temukan relawan yang tepat.</p>
        
        <div class="mt-8 flex flex-wrap gap-4">
            <a href="create_activity.php" class="inline-flex items-center px-5 py-3 border border-transparent text-sm font-medium rounded-lg bg-white text-[#10283f] hover:bg-gray-100 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
            <i class="fas fa-plus-circle mr-2"></i> Buat Kegiatan Baru
            </a>
            <a href="manage_activities.php" class="inline-flex items-center px-5 py-3 border border-white text-sm font-medium rounded-lg text-white hover:bg-white hover:text-[#10283f] transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
            <i class="fas fa-tasks mr-2"></i> Kelola Kegiatan
            </a>
        </div>
        </div>
        
        <!-- Right time display - moved to right and centered vertically -->
        <div class="backdrop-blur-md bg-white bg-opacity-20 rounded-lg px-3 py-1 shadow-lg border border-white border-opacity-20 self-center">
        <div id="currentTime" class="text-sm font-medium text-white flex items-center"></div>
        </div>
    </div>
    </div>

    
    <!-- Stats Cards - Enhanced with shadow and hover effects -->
    <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Active Activities -->
        <div class="bg-white overflow-hidden shadow-lg rounded-xl hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1">
            <div class="px-6 py-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-[#10283f] rounded-lg p-4 shadow-md">
                        <i class="fas fa-calendar-check text-white text-lg"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Kegiatan Aktif
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-3xl font-semibold text-gray-900">
                                    <?php echo $stats['active_activities']; ?>
                                </div>
                                <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                    <span class="text-xs text-gray-400">dari <?php echo $stats['total_activities']; ?> total</span>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4">
                <a href="manage_activities.php" class="text-sm font-medium text-[#10283f] hover:text-[#1a3b58] transition duration-150 flex items-center">
                    Lihat Detail <i class="fas fa-arrow-right text-xs ml-2"></i>
                </a>
            </div>
        </div>
        
        <!-- Pending Applications -->
        <div class="bg-white overflow-hidden shadow-lg rounded-xl hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1">
            <div class="px-6 py-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-lg p-4 shadow-md">
                        <i class="fas fa-hourglass-half text-white text-lg"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Pendaftaran Menunggu
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-3xl font-semibold text-gray-900">
                                    <?php echo $stats['pending_applications']; ?>
                                </div>
                                <div class="ml-2 flex items-baseline text-sm font-semibold">
                                    <span class="text-xs text-gray-400">perlu ditinjau</span>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4">
                <a href="manage_activities.php" class="text-sm font-medium text-[#10283f] hover:text-[#1a3b58] transition duration-150 flex items-center">
                    Tinjau Semua <i class="fas fa-arrow-right text-xs ml-2"></i>
                </a>
            </div>
        </div>
        
        <!-- Total Applications -->
        <div class="bg-white overflow-hidden shadow-lg rounded-xl hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1">
            <div class="px-6 py-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-500 rounded-lg p-4 shadow-md">
                        <i class="fas fa-users text-white text-lg"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Total Pendaftaran
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-3xl font-semibold text-gray-900">
                                    <?php echo $stats['total_applications']; ?>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4">
                <span class="text-sm text-gray-500">
                    Dari semua kegiatan
                </span>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white overflow-hidden shadow-lg rounded-xl hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1">
            <div class="px-6 py-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-2">Aksi Cepat</h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500">
                    <p>Kelola kegiatan & pendaftar dengan cepat</p>
                </div>
                <div class="mt-5 flex flex-col gap-2">
                    <a href="create_activity.php" class="inline-flex items-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-[#10283f] hover:bg-[#1a3b58] transition-all duration-200 focus:outline-none w-full justify-center">
                        <i class="fas fa-plus-circle mr-2"></i> Kegiatan Baru
                    </a>
                    <a href="manage_activities.php?filter=pending" class="inline-flex items-center px-4 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200 focus:outline-none w-full justify-center">
                        <i class="fas fa-clipboard-check mr-2"></i> Tinjau Pendaftaran
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities & Applications - More modern with enhanced shadows and rounded corners -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <!-- Recent Activities -->
        <div class="bg-white shadow-lg sm:rounded-xl overflow-hidden hover:shadow-xl transition-all duration-200">
            <div class="px-6 py-5 flex justify-between items-center">
                <h2 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                    <i class="fas fa-calendar-alt text-[#10283f] mr-2"></i>
                    Kegiatan Terbaru
                </h2>
                <a href="manage_activities.php" class="text-sm text-[#10283f] hover:text-[#1a3b58] transition duration-150 flex items-center">
                    Lihat Semua
                    <i class="fas fa-chevron-right ml-1 text-xs"></i>
                </a>
            </div>
            <div class="border-t border-gray-200 px-6 py-5">
                <?php if($latest_activities && $latest_activities->num_rows > 0): ?>
                <ul role="list" class="divide-y divide-gray-200">
                    <?php while($activity = $latest_activities->fetch_assoc()): ?>
                    <li class="py-4 hover:bg-gray-50 rounded-lg px-2 transition duration-150">
                        <div class="flex items-center justify-between">
                            <div>
                                <a href="view_applications.php?activity_id=<?php echo $activity['id']; ?>" class="text-sm font-medium text-[#10283f] hover:text-[#1a3b58] transition duration-150">
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </a>
                                <div class="text-xs text-gray-500 mt-1 flex items-center">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <span class="mr-3"><?php echo date('d M Y', strtotime($activity['event_date'])); ?></span>
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <span><?php echo htmlspecialchars($activity['location']); ?></span>
                                </div>
                            </div>
                            <div class="text-xs">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-gray-100 text-gray-800 font-medium">
                                    <i class="fas fa-users mr-1"></i> <?php echo $activity['application_count']; ?>
                                </span>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="text-center py-10">
                    <i class="fas fa-calendar-plus text-gray-300 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-500 mb-4">Belum ada kegiatan yang dibuat</p>
                    <a href="create_activity.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-[#10283f] hover:bg-[#1a3b58] transition-all duration-200">
                        <i class="fas fa-plus-circle mr-2"></i> Buat Kegiatan
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="bg-white shadow-lg sm:rounded-xl overflow-hidden hover:shadow-xl transition-all duration-200">
            <div class="px-6 py-5 flex justify-between items-center">
                <h2 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                    <i class="fas fa-user-friends text-[#10283f] mr-2"></i>
                    Pendaftaran Terbaru
                </h2>
                <a href="manage_activities.php" class="text-sm text-[#10283f] hover:text-[#1a3b58] transition duration-150 flex items-center">
                    Kelola Pendaftar
                    <i class="fas fa-chevron-right ml-1 text-xs"></i>
                </a>
            </div>
            <div class="border-t border-gray-200 px-6 py-5">
                <?php if($latest_applications && $latest_applications->num_rows > 0): ?>
                <ul role="list" class="divide-y divide-gray-200">
                    <?php while($application = $latest_applications->fetch_assoc()): 
                        $status_class = '';
                        $status_text = '';
                        $status_icon = '';
                        
                        switch($application['status']) {
                            case 'approved':
                                $status_class = 'bg-green-100 text-green-800';
                                $status_text = 'Diterima';
                                $status_icon = '<i class="fas fa-check-circle mr-1"></i>';
                                break;
                            case 'rejected':
                                $status_class = 'bg-red-100 text-red-800';
                                $status_text = 'Ditolak';
                                $status_icon = '<i class="fas fa-times-circle mr-1"></i>';
                                break;
                            default:
                                $status_class = 'bg-yellow-100 text-yellow-800';
                                $status_text = 'Menunggu';
                                $status_icon = '<i class="fas fa-clock mr-1"></i>';
                        }
                    ?>
                    <li class="py-4 hover:bg-gray-50 rounded-lg px-2 transition duration-150">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-900 flex items-center">
                                    <i class="fas fa-user mr-2 text-[#10283f]"></i>
                                    <?php echo htmlspecialchars($application['user_name']); ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1 flex items-center">
                                    <span>untuk</span>
                                    <a href="view_applications.php?activity_id=<?php echo $application['activity_id']; ?>" class="text-[#10283f] hover:text-[#1a3b58] ml-1 transition duration-150">
                                        <?php echo htmlspecialchars($application['title']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                    <?php echo $status_icon; ?> <?php echo $status_text; ?>
                                </span>
                                <div class="text-gray-400 mt-1 text-center flex items-center justify-end">
                                    <i class="far fa-clock mr-1"></i>
                                    <?php echo date('d/m/Y', strtotime($application['applied_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="text-center py-10">
                    <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
                    <p class="text-sm text-gray-500">Belum ada pendaftaran</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>

<script>
// Enhanced Realistic Wave Animation with Canvas
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('waveCanvas');
    const ctx = canvas.getContext('2d');
    
    // Set canvas dimensions to match parent
    function resizeCanvas() {
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }
    
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);
    
    // More realistic wave properties
    const waves = [
        {
            points: [],
            count: 7,
            speed: 0.015,
            height: 15,
            color: 'rgba(255, 255, 255, 0.4)',
            offset: 0
        },
        {
            points: [],
            count: 6,
            speed: 0.02,
            height: 10,
            color: 'rgba(255, 255, 255, 0.3)',
            offset: 0.3
        },
        {
            points: [],
            count: 5,
            speed: 0.025,
            height: 8,
            color: 'rgba(255, 255, 255, 0.25)',
            offset: 0.6
        }
    ];
    
    // Generate initial wave points
    waves.forEach(wave => {
        const segmentWidth = canvas.width / (wave.count - 1);
        for (let i = 0; i < wave.count; i++) {
            wave.points.push({
                x: i * segmentWidth,
                y: canvas.height * 0.8,
                originY: canvas.height * 0.8,
                speed: 0.1 + Math.random() * 0.2,
                amplitude: wave.height * (0.8 + Math.random() * 0.4),
                phase: Math.random() * Math.PI * 2
            });
        }
    });
    
    function drawCurvedPath(points, color) {
        if (points.length < 2) return;
        
        ctx.beginPath();
        ctx.moveTo(0, canvas.height);
        ctx.lineTo(points[0].x, points[0].y);
        
        // Draw smooth curve through points
        for (let i = 0; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            ctx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
        }
        
        // Last point
        ctx.quadraticCurveTo(
            points[points.length - 1].x, 
            points[points.length - 1].y, 
            canvas.width, 
            points[points.length - 1].y
        );
        
        ctx.lineTo(canvas.width, canvas.height);
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();
    }
    
    function updatePoints(time) {
        waves.forEach((wave, waveIndex) => {
            wave.offset += wave.speed;
            
            wave.points.forEach((point, i) => {
                // Create smooth, realistic wave motion
                point.phase += point.speed * 0.05;
                point.y = point.originY + Math.sin(point.phase + wave.offset + i * 0.5) * point.amplitude;
            });
        });
    }
    
    function drawWaves(time) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        updatePoints(time);
        
        // Draw waves from back to front
        for (let i = waves.length - 1; i >= 0; i--) {
            drawCurvedPath(waves[i].points, waves[i].color);
        }
        
        requestAnimationFrame(drawWaves);
    }
    
    drawWaves(0);
});

// Function to update time and greeting
function updateTime() {
    const now = new Date();
    
    // Format hour and time
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    
    // Display time with blinking colon
    const blinkColon = seconds % 2 === 0 ? ':' : ' ';
    document.getElementById('currentTime').innerHTML = 
        `<i class="fas fa-clock mr-1"></i>${hours}${blinkColon}${minutes}${blinkColon}${seconds} WIB`;
    
    // Determine greeting based on time with appropriate icon
    let greeting, icon;
    if (hours >= 5 && hours < 11) {
        greeting = "Selamat Pagi,";
        icon = '<i class="fas fa-sun"></i>';
    } else if (hours >= 11 && hours < 15) {
        greeting = "Selamat Siang,";
        icon = '<i class="fas fa-sun"></i>';
    } else if (hours >= 15 && hours < 18) {
        greeting = "Selamat Sore,";
        icon = '<i class="fas fa-cloud-sun"></i>';
    } else {
        greeting = "Selamat Malam,";
        icon = '<i class="fas fa-moon"></i>';
    }
    
    document.getElementById('greetingText').innerHTML = greeting;
    document.getElementById('greetingIcon').innerHTML = icon;
}

// Initial call
updateTime();

// Update time every second
setInterval(updateTime, 1000);
</script>