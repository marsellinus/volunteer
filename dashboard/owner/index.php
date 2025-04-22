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

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <!-- Database Setup Notification (if notifications table doesn't exist) -->
    <?php if(!isset($table_check) || $table_check->num_rows == 0): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong>Notifikasi tidak tersedia.</strong> Database perlu diperbarui.
                </p>
                <p class="text-sm text-yellow-700 mt-2">
                    <a href="../../setup/create_notifications_table.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                        Klik disini untuk memperbarui database
                    </a>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-purple-700 to-indigo-700 rounded-lg shadow-lg px-6 py-8 mb-8 text-white">
        <h1 class="text-3xl font-bold mb-2">Selamat Datang, <?php echo htmlspecialchars($owner_name); ?>!</h1>
        <p class="text-indigo-100">Kelola kegiatan volunteer Anda dan temukan relawan yang tepat.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="create_activity.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md bg-white text-indigo-700 hover:bg-indigo-50">
                <i class="fas fa-plus-circle mr-2"></i> Buat Kegiatan Baru
            </a>
            <a href="manage_activities.php" class="inline-flex items-center px-4 py-2 border border-white text-sm font-medium rounded-md text-white hover:bg-indigo-600">
                <i class="fas fa-tasks mr-2"></i> Kelola Kegiatan
            </a>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Active Activities -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                        <i class="fas fa-calendar-check text-white"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Kegiatan Aktif
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900">
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
            <div class="bg-gray-50 px-4 py-4 sm:px-6">
                <a href="manage_activities.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Lihat Detail <i class="fas fa-arrow-right text-xs ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Pending Applications -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <i class="fas fa-hourglass-half text-white"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Pendaftaran Menunggu
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900">
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
            <div class="bg-gray-50 px-4 py-4 sm:px-6">
                <a href="manage_activities.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Tinjau Semua <i class="fas fa-arrow-right text-xs ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Total Applications -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Total Pendaftaran
                            </dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900">
                                    <?php echo $stats['total_applications']; ?>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-4 sm:px-6">
                <span class="text-sm text-gray-500">
                    Dari semua kegiatan
                </span>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-2">Aksi Cepat</h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500">
                    <p>Kelola kegiatan & pendaftar dengan cepat</p>
                </div>
                <div class="mt-5">
                    <a href="create_activity.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none mb-2 w-full justify-center">
                        <i class="fas fa-plus-circle mr-2"></i> Kegiatan Baru
                    </a>
                    <a href="manage_activities.php?filter=pending" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none w-full justify-center">
                        <i class="fas fa-clipboard-check mr-2"></i> Tinjau Pendaftaran
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities & Applications -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <!-- Recent Activities -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Kegiatan Terbaru</h2>
                <a href="manage_activities.php" class="text-sm text-indigo-600 hover:text-indigo-500">
                    Lihat Semua
                </a>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <?php if($latest_activities && $latest_activities->num_rows > 0): ?>
                <ul role="list" class="divide-y divide-gray-200">
                    <?php while($activity = $latest_activities->fetch_assoc()): ?>
                    <li class="py-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <a href="view_applications.php?activity_id=<?php echo $activity['id']; ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </a>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span class="mr-2"><?php echo date('d M Y', strtotime($activity['event_date'])); ?></span>
                                    <span><?php echo htmlspecialchars($activity['location']); ?></span>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-800">
                                    <?php echo $activity['application_count']; ?> Pendaftar
                                </span>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-sm text-gray-500">Belum ada kegiatan yang dibuat</p>
                    <a href="create_activity.php" class="mt-3 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                        <i class="fas fa-plus-circle mr-2"></i> Buat Kegiatan
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Applications -->
        <div class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Pendaftaran Terbaru</h2>
                <a href="manage_activities.php" class="text-sm text-indigo-600 hover:text-indigo-500">
                    Kelola Pendaftar
                </a>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <?php if($latest_applications && $latest_applications->num_rows > 0): ?>
                <ul role="list" class="divide-y divide-gray-200">
                    <?php while($application = $latest_applications->fetch_assoc()): 
                        $status_class = '';
                        $status_text = '';
                        
                        switch($application['status']) {
                            case 'approved':
                                $status_class = 'bg-green-100 text-green-800';
                                $status_text = 'Diterima';
                                break;
                            case 'rejected':
                                $status_class = 'bg-red-100 text-red-800';
                                $status_text = 'Ditolak';
                                break;
                            default:
                                $status_class = 'bg-yellow-100 text-yellow-800';
                                $status_text = 'Menunggu';
                        }
                    ?>
                    <li class="py-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($application['user_name']); ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span>untuk</span>
                                    <a href="view_applications.php?activity_id=<?php echo $application['activity_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                        <?php echo htmlspecialchars($application['title']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                                <div class="text-gray-400 mt-1 text-center">
                                    <?php echo date('d/m/Y', strtotime($application['applied_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-sm text-gray-500">Belum ada pendaftaran</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include_once '../../includes/footer.php'; ?>
