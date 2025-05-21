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
<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 pt-20 mt-10">
    <!-- Page Header with Gradient Background -->
    <div class="relative rounded-xl overflow-hidden bg-gradient-to-r from-[#10283f] to-[#1e4976] shadow-lg mb-6">
        <div class="relative py-8 px-6 md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h1 class="text-2xl font-bold leading-7 text-white sm:text-3xl sm:truncate">
                    Kelola Kegiatan Volunteer
                </h1>
                <p class="mt-2 text-sm text-white text-opacity-80">
                    Lihat dan kelola semua kegiatan volunteer yang Anda buat.
                </p>
                <div class="mt-3 flex items-center">
                    <span class="text-white text-opacity-80 text-sm">
                        <i class="fas fa-calendar-alt mr-2"></i> <?php echo date('d F Y'); ?>
                    </span>
                    <span class="mx-3 text-white text-opacity-50">|</span>
                    <span class="flex items-center text-white text-opacity-80 text-sm">
                        <i class="fas fa-user-circle mr-2"></i> <?php echo htmlspecialchars($owner_name); ?>
                    </span>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:ml-4">
                <a href="create_activity.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-[#10283f] bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-white focus:ring-offset-[#10283f] transition-all duration-200">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Kegiatan
                </a>
            </div>
        </div>
    </div>

    <!-- Message Notifications with Animation -->
    <?php if($message): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-md shadow-sm animate-fadeIn">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-green-700">
                        <?php echo $message; ?>
                    </p>
                </div>
                <div class="ml-auto">
                    <button type="button" class="text-green-400 hover:text-green-500" onclick="this.parentElement.parentElement.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-md shadow-sm animate-fadeIn">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400 text-lg"></i>
                </div>
                <div class="ml-3">
                    <p class="text-red-700">
                        <?php echo $error; ?>
                    </p>
                </div>
                <div class="ml-auto">
                    <button type="button" class="text-red-400 hover:text-red-500" onclick="this.parentElement.parentElement.parentElement.style.display='none'">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <nav class="flex" aria-label="Tabs">
            <a href="manage_activities.php?filter=all" class="<?php echo $filter == 'all' ? 'bg-[#10283f]/5 border-[#10283f] text-[#10283f]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'; ?> flex-1 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-center transition-all duration-200">
                <i class="fas fa-th-list mr-2"></i> Semua Kegiatan
            </a>
            <a href="manage_activities.php?filter=upcoming" class="<?php echo $filter == 'upcoming' ? 'bg-[#10283f]/5 border-[#10283f] text-[#10283f]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'; ?> flex-1 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-center transition-all duration-200">
                <i class="fas fa-calendar-day mr-2"></i> Kegiatan Mendatang
            </a>
            <a href="manage_activities.php?filter=past" class="<?php echo $filter == 'past' ? 'bg-[#10283f]/5 border-[#10283f] text-[#10283f]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'; ?> flex-1 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-center transition-all duration-200">
                <i class="fas fa-history mr-2"></i> Kegiatan Selesai
            </a>
            <a href="manage_activities.php?filter=featured" class="<?php echo $filter == 'featured' ? 'bg-[#10283f]/5 border-[#10283f] text-[#10283f]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 hover:bg-gray-50'; ?> flex-1 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-center transition-all duration-200">
                <i class="fas fa-star mr-2"></i> Kegiatan Unggulan
            </a>
        </nav>
    </div>

    <?php if($activities && $activities->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php while($activity = $activities->fetch_assoc()): 
                $isPast = strtotime($activity['event_date']) < time();
                $isClosed = strtotime($activity['application_deadline']) < time();
                $canGenerateCertificates = $isPast && $activity['approved_count'] > 0;
                
                // Calculate days remaining
                $eventDate = new DateTime($activity['event_date']);
                $today = new DateTime();
                $daysRemaining = $today->diff($eventDate)->days;
                $isUpcoming = $eventDate > $today;
                
                // Determine if the image path is a full URL
                $isUrl = !empty($activity['images']) && (strpos($activity['images'], 'http://') === 0 || strpos($activity['images'], 'https://') === 0);
                
                // Set image path based on type (URL or file path)
                if ($isUrl) {
                    $imagePath = $activity['images'];
                } else {
                    // Try with ../../ prefix first (most likely to work based on your directory structure)
                    $imagePath = !empty($activity['images']) ? "../../" . ltrim($activity['images'], '/') : "../../uploads/activities/default.jpg";
                }
            ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md group">
                    <!-- Card Header with Activity Image -->
                    <div class="relative h-40">
                        <!-- Activity Image -->
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($activity['title']); ?>" class="w-full h-full object-cover">
                        
                        <!-- Overlay for text readability -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-black/20"></div>
                        
                        <!-- Status Badge -->
                        <?php
                            $statusClass = "bg-green-100 text-green-800 border-green-200";
                            $statusText = "Aktif";
                            $statusIcon = "fa-check-circle";
                            
                            if($isPast) {
                                $statusClass = "bg-gray-100 text-gray-800 border-gray-200";
                                $statusText = "Selesai";
                                $statusIcon = "fa-flag-checkered";
                            } elseif($isClosed) {
                                $statusClass = "bg-yellow-100 text-yellow-800 border-yellow-200";
                                $statusText = "Pendaftaran Ditutup";
                                $statusIcon = "fa-lock";
                            }
                        ?>
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border <?php echo $statusClass; ?>">
                                <i class="fas <?php echo $statusIcon; ?> mr-1"></i> <?php echo $statusText; ?>
                            </span>
                        </div>
                        
                        <!-- Featured Badge -->
                        <?php if($activity['is_featured']): ?>
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                    <i class="fas fa-star mr-1"></i> Unggulan
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Activity Title -->
                        <div class="absolute bottom-0 left-0 right-0 p-4">
                            <h3 class="text-lg font-semibold text-white truncate">
                                <?php echo htmlspecialchars($activity['title']); ?>
                            </h3>
                            <p class="text-sm text-white/80 mt-1 truncate">
                                <?php echo htmlspecialchars(substr($activity['description'], 0, 60)) . (strlen($activity['description']) > 60 ? '...' : ''); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="p-4">
                        <!-- Activity Info -->
                        <div class="flex flex-wrap gap-y-2">
                            <div class="w-full flex items-center text-sm text-gray-600">
                                <i class="fas fa-calendar flex-shrink-0 mr-2 h-4 w-4 text-[#10283f]/70"></i>
                                <?php echo date('d M Y', strtotime($activity['event_date'])); ?>
                                <?php if($isUpcoming): ?>
                                    <span class="ml-1 text-xs text-[#10283f]/60">(<?php echo $daysRemaining; ?> hari lagi)</span>
                                <?php endif; ?>
                            </div>
                            <div class="w-full flex items-center text-sm text-gray-600 mt-1">
                                <i class="fas fa-map-marker-alt flex-shrink-0 mr-2 h-4 w-4 text-[#10283f]/70"></i>
                                <?php echo htmlspecialchars($activity['location']); ?>
                            </div>
                        </div>
                        
                        <!-- Participant Stats -->
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                            <div class="flex items-center">
                                <div class="flex -space-x-2 mr-2">
                                    <?php for($i = 0; $i < min(3, $activity['approved_count']); $i++): ?>
                                        <div class="h-7 w-7 rounded-full bg-[#10283f]/<?php echo 90 - ($i * 20); ?> flex items-center justify-center text-xs text-white border border-white">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endfor; ?>
                                    <?php if($activity['approved_count'] > 3): ?>
                                        <div class="h-7 w-7 rounded-full bg-[#10283f]/20 flex items-center justify-center text-xs text-[#10283f] border border-white">
                                            +<?php echo $activity['approved_count'] - 3; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium text-[#10283f]"><?php echo $activity['approved_count']; ?></span> Diterima
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium text-[#10283f]"><?php echo $activity['application_count']; ?></span> Pendaftar
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Menu Dropdown -->
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="p-2 rounded-full hover:bg-gray-100 focus:outline-none" type="button">
                                    <i class="fas fa-ellipsis-v text-gray-500"></i>
                                </button>
                                <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10" style="display: none;">
                                    <div class="py-1">
                                        <a href="view_applications.php?activity_id=<?php echo $activity['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-users mr-2 text-[#10283f]"></i> Lihat Pendaftar
                                        </a>
                                        <a href="edit_activity.php?id=<?php echo $activity['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2 text-blue-600"></i> Edit Kegiatan
                                        </a>
                                        <?php if($canGenerateCertificates): ?>
                                            <a href="generate_certificates.php?activity_id=<?php echo $activity['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-certificate mr-2 text-green-600"></i> Generate Piagam
                                            </a>
                                        <?php endif; ?>
                                        <?php if($activity['is_featured']): ?>
                                            <a href="manage_activities.php?feature=0&id=<?php echo $activity['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-star mr-2 text-yellow-500"></i> Hapus Unggulan
                                            </a>
                                        <?php else: ?>
                                            <a href="manage_activities.php?feature=1&id=<?php echo $activity['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="far fa-star mr-2 text-yellow-500"></i> Jadikan Unggulan
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" onclick="confirmDelete(<?php echo $activity['id']; ?>, '<?php echo addslashes($activity['title']); ?>')" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 w-full text-left">
                                            <i class="fas fa-trash mr-2"></i> Hapus Kegiatan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Footer -->
                    <div class="bg-gray-50 px-4 py-3 border-t border-gray-100">
                        <div class="flex justify-between">
                            <a href="view_applications.php?activity_id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 bg-[#10283f]/10 hover:bg-[#10283f]/20 text-xs font-medium rounded-md text-[#10283f] transition-colors duration-200">
                                <i class="fas fa-users mr-1"></i> Lihat Pendaftar
                            </a>
                            <a href="edit_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-1 bg-blue-50 hover:bg-blue-100 text-xs font-medium rounded-md text-blue-700 transition-colors duration-200">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-16 bg-white rounded-xl shadow-sm">
            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-clipboard-list text-gray-400 text-4xl"></i>
            </div>
            <h3 class="mt-2 text-lg font-medium text-gray-900">Tidak ada kegiatan</h3>
            <p class="mt-1 text-gray-500 max-w-md mx-auto">
                <?php if($filter != 'all'): ?>
                    Tidak ada kegiatan yang sesuai dengan filter yang dipilih.
                <?php else: ?>
                    Mulai dengan membuat kegiatan volunteer pertama Anda.
                <?php endif; ?>
            </p>
            <div class="mt-6">
                <a href="create_activity.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#10283f] hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f] transition-all duration-200">
                    <i class="fas fa-plus mr-2"></i> Buat Kegiatan
                </a>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Modal for delete confirmation -->
<div id="deleteModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    <div class="relative bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mx-auto mb-4">
            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
        </div>
        <h3 class="text-lg font-medium text-center text-gray-900 mb-2">Konfirmasi Hapus</h3>
        <p class="text-center text-gray-500 mb-4" id="deleteConfirmText">Apakah Anda yakin ingin menghapus kegiatan ini?</p>
        <div class="flex justify-center space-x-4">
            <button id="cancelDeleteBtn" type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-400 transition-colors">
                Batal
            </button>
            <a id="confirmDeleteBtn" href="#" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors">
                Ya, Hapus
            </a>
        </div>
    </div>
</div>

<style>
/* Animation for notifications */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.3s ease-out forwards;
}
</style>

<script>
// Alpine.js for dropdowns
document.addEventListener('alpine:init', () => {
    Alpine.data('dropdown', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        }
    }));
});

// Delete confirmation modal
function confirmDelete(activityId, activityTitle) {
    const modal = document.getElementById('deleteModal');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelDeleteBtn');
    const confirmText = document.getElementById('deleteConfirmText');
    
    confirmText.textContent = `Apakah Anda yakin ingin menghapus kegiatan "${activityTitle}"? Tindakan ini tidak dapat dibatalkan.`;
    confirmBtn.href = `manage_activities.php?delete=1&id=${activityId}`;
    
    modal.classList.remove('hidden');
    
    cancelBtn.onclick = function() {
        modal.classList.add('hidden');
    };
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });
}

// Add script for Alpine.js if not already included
if (typeof Alpine === 'undefined') {
    document.addEventListener('DOMContentLoaded', function() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.10.3/cdn.min.js';
        script.defer = true;
        document.head.appendChild(script);
    });
}
</script>

<?php include '../../includes/footer.php'; ?>