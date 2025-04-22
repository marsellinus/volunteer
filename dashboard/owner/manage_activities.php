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

// Set page title
$page_title = 'Kelola Kegiatan - VolunteerHub';

// Include the header
include '../../includes/header_owner.php';

// Get unread notifications count with error handling
try {
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Handle activity deletion
$message = '';
$error = '';

if(isset($_GET['delete']) && isset($_GET['id'])) {
    $activity_id = intval($_GET['id']);
    
    // Verify this activity belongs to the current owner
    $stmt = $conn->prepare("SELECT id FROM volunteer_activities WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $activity_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        // Delete the activity
        $stmt = $conn->prepare("DELETE FROM volunteer_activities WHERE id = ?");
        $stmt->bind_param("i", $activity_id);
        
        if($stmt->execute()) {
            $message = "Kegiatan berhasil dihapus.";
        } else {
            $error = "Gagal menghapus kegiatan. Coba lagi nanti.";
        }
    } else {
        $error = "Anda tidak memiliki izin untuk menghapus kegiatan ini.";
    }
}

// Handle activity status change
if(isset($_GET['feature']) && isset($_GET['id'])) {
    $activity_id = intval($_GET['id']);
    $feature_status = ($_GET['feature'] == '1') ? 1 : 0;
    
    // Verify this activity belongs to the current owner
    $stmt = $conn->prepare("SELECT id FROM volunteer_activities WHERE id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $activity_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        // Update the feature status
        $stmt = $conn->prepare("UPDATE volunteer_activities SET is_featured = ? WHERE id = ?");
        $stmt->bind_param("ii", $feature_status, $activity_id);
        
        if($stmt->execute()) {
            $message = $feature_status ? "Kegiatan berhasil diatur sebagai unggulan." : "Kegiatan dihapus dari daftar unggulan.";
        } else {
            $error = "Gagal mengubah status kegiatan. Coba lagi nanti.";
        }
    } else {
        $error = "Anda tidak memiliki izin untuk mengubah status kegiatan ini.";
    }
}

// Get all activities created by this owner with application counts
$activities = $conn->query("
    SELECT va.*, 
           (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count,
           (SELECT COUNT(*) FROM applications WHERE activity_id = va.id AND status = 'approved') as approved_count
    FROM volunteer_activities va 
    WHERE va.owner_id = $owner_id 
    ORDER BY va.created_at DESC
");

// Filter mechanism
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

switch($filter) {
    case 'upcoming':
        $activities = $conn->query("
            SELECT va.*, 
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count,
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id AND status = 'approved') as approved_count
            FROM volunteer_activities va 
            WHERE va.owner_id = $owner_id 
            AND va.event_date > CURDATE() 
            ORDER BY va.event_date ASC
        ");
        break;
    case 'past':
        $activities = $conn->query("
            SELECT va.*, 
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count,
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id AND status = 'approved') as approved_count
            FROM volunteer_activities va 
            WHERE va.owner_id = $owner_id 
            AND va.event_date <= CURDATE() 
            ORDER BY va.event_date DESC
        ");
        break;
    case 'featured':
        $activities = $conn->query("
            SELECT va.*, 
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count,
                   (SELECT COUNT(*) FROM applications WHERE activity_id = va.id AND status = 'approved') as approved_count
            FROM volunteer_activities va 
            WHERE va.owner_id = $owner_id 
            AND va.is_featured = 1
            ORDER BY va.created_at DESC
        ");
        break;
}
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Kelola Kegiatan Volunteer
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Lihat dan kelola semua kegiatan volunteer yang Anda buat.
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="create_activity.php" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-plus mr-2"></i>
                Tambah Kegiatan
            </a>
        </div>
    </div>

    <?php if($message): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">
                        <?php echo $message; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">
                        <?php echo $error; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex" aria-label="Tabs">
            <a href="manage_activities.php?filter=all" class="<?php echo $filter == 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                Semua Kegiatan
            </a>
            <a href="manage_activities.php?filter=upcoming" class="<?php echo $filter == 'upcoming' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                Kegiatan Mendatang
            </a>
            <a href="manage_activities.php?filter=past" class="<?php echo $filter == 'past' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                Kegiatan Selesai
            </a>
            <a href="manage_activities.php?filter=featured" class="<?php echo $filter == 'featured' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                Kegiatan Unggulan
            </a>
        </nav>
    </div>

    <!-- Activities List -->
    <?php if($activities && $activities->num_rows > 0): ?>
        <div class="bg-white shadow overflow-hidden sm:rounded-md mb-8">
            <ul role="list" class="divide-y divide-gray-200">
                <?php while($activity = $activities->fetch_assoc()): 
                    $isPast = strtotime($activity['event_date']) < time();
                    $isClosed = strtotime($activity['application_deadline']) < time();
                    $canGenerateCertificates = $isPast && $activity['approved_count'] > 0;
                ?>
                    <li>
                        <div class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="sm:flex sm:justify-between w-full">
                                    <div>
                                        <div class="flex items-center">
                                            <p class="text-md font-medium text-indigo-600 truncate">
                                                <?php echo htmlspecialchars($activity['title']); ?>
                                            </p>
                                            <?php if($activity['is_featured']): ?>
                                                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-star mr-1 text-yellow-500"></i> Unggulan
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-2 flex">
                                            <div class="flex items-center text-sm text-gray-500 mr-4">
                                                <i class="fas fa-calendar flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400"></i>
                                                <?php echo date('d M Y', strtotime($activity['event_date'])); ?>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-500">
                                                <i class="fas fa-map-marker-alt flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400"></i>
                                                <?php echo htmlspecialchars($activity['location']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                        <p class="whitespace-nowrap mr-4">
                                            <i class="fas fa-users mr-1"></i>
                                            <?php echo $activity['application_count']; ?> Pendaftar
                                        </p>
                                        <p class="whitespace-nowrap">
                                            <i class="fas fa-check-circle mr-1 text-green-500"></i>
                                            <?php echo $activity['approved_count']; ?> Diterima
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex justify-between">
                                <div>
                                    <?php
                                        $statusClass = "bg-green-100 text-green-800";
                                        $statusText = "Aktif";
                                        
                                        if($isPast) {
                                            $statusClass = "bg-gray-100 text-gray-800";
                                            $statusText = "Selesai";
                                        } elseif($isClosed) {
                                            $statusClass = "bg-yellow-100 text-yellow-800";
                                            $statusText = "Pendaftaran Ditutup";
                                        }
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <?php if($canGenerateCertificates): ?>
                                        <a href="generate_certificates.php?activity_id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200">
                                            <i class="fas fa-certificate mr-1"></i> Piagam
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="view_applications.php?activity_id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200">
                                        <i class="fas fa-users mr-1"></i> Pendaftar
                                    </a>
                                    
                                    <a href="edit_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                    
                                    <?php if($activity['is_featured']): ?>
                                        <a href="manage_activities.php?feature=0&id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200">
                                            <i class="fas fa-star mr-1"></i> Hapus Unggulan
                                        </a>
                                    <?php else: ?>
                                        <a href="manage_activities.php?feature=1&id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200">
                                            <i class="far fa-star mr-1"></i> Jadikan Unggulan
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" onclick="confirmDelete(<?php echo $activity['id']; ?>, '<?php echo addslashes($activity['title']); ?>')" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200">
                                        <i class="fas fa-trash mr-1"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-lg shadow">
            <i class="fas fa-clipboard-list text-gray-400 text-5xl mb-4"></i>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada kegiatan</h3>
            <p class="mt-1 text-sm text-gray-500">
                <?php if($filter != 'all'): ?>
                    Tidak ada kegiatan yang sesuai dengan filter yang dipilih.
                <?php else: ?>
                    Mulai dengan membuat kegiatan volunteer pertama Anda.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="create_activity.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-plus mr-2"></i> Buat Kegiatan
                </a>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include '../../includes/footer.php'; ?>
