<?php
include_once '../../config/database.php';
session_start();

// Check if owner is logged in
if(!isset($_SESSION['owner_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$owner_name = $_SESSION['owner_name'];

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kegiatan - VolunteerHub</title>
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
                            <a href="index.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-home mr-1"></i> Dashboard
                            </a>
                            <a href="create_activity.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-plus-circle mr-1"></i> Buat Kegiatan
                            </a>
                            <a href="manage_activities.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700">
                                <i class="fas fa-tasks mr-1"></i> Kelola Kegiatan
                            </a>
                            <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-user mr-1"></i> Profil
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($owner_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

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
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Hapus Kegiatan
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500" id="deleteConfirmText">
                                    Apakah Anda yakin ingin menghapus kegiatan ini? Tindakan ini tidak bisa dibatalkan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <a id="confirmDeleteButton" href="#" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Hapus
                    </a>
                    <button type="button" onclick="closeDeleteModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(activityId, activityTitle) {
            const modal = document.getElementById('deleteModal');
            const confirmText = document.getElementById('deleteConfirmText');
            const confirmButton = document.getElementById('confirmDeleteButton');
            
            confirmText.textContent = `Apakah Anda yakin ingin menghapus kegiatan "${activityTitle}"? Semua data terkait termasuk pendaftaran akan dihapus secara permanen.`;
            confirmButton.href = `manage_activities.php?delete=1&id=${activityId}`;
            
            modal.classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>
