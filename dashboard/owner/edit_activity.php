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

// Get activity ID from query parameter
$activity_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Activity not found or invalid ID
if($activity_id <= 0) {
    header("Location: manage_activities.php");
    exit;
}

// Check if activity belongs to the owner
$stmt = $conn->prepare("SELECT * FROM volunteer_activities WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $activity_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();

// Activity not found or doesn't belong to this owner
if($result->num_rows === 0) {
    header("Location: manage_activities.php");
    exit;
}

$activity = $result->fetch_assoc();

// Handle form submission
$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_activity'])) {
    // Get form data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = $_POST['location'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $application_deadline = $_POST['application_deadline'] ?? '';
    $required_skills = $_POST['required_skills'] ?? '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate required fields
    if(empty($title) || empty($description) || empty($category) || 
       empty($location) || empty($event_date) || empty($application_deadline)) {
        $error = "Semua field yang bertanda * harus diisi.";
    } else {
        // Update activity in database
        $stmt = $conn->prepare("UPDATE volunteer_activities 
                              SET title = ?, description = ?, category = ?, 
                                  location = ?, event_date = ?, application_deadline = ?, 
                                  required_skills = ?, is_featured = ?
                              WHERE id = ? AND owner_id = ?");
        
        $stmt->bind_param("sssssssiis", 
            $title, $description, $category, $location,
            $event_date, $application_deadline, $required_skills, 
            $is_featured, $activity_id, $owner_id);
            
        if($stmt->execute()) {
            $success = "Kegiatan berhasil diperbarui!";
            
            // Refresh activity data
            $stmt = $conn->prepare("SELECT * FROM volunteer_activities WHERE id = ?");
            $stmt->bind_param("i", $activity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $activity = $result->fetch_assoc();
        } else {
            $error = "Gagal memperbarui kegiatan. Silahkan coba lagi.";
        }
    }
}

$page_title = 'Edit Kegiatan - VolunteerHub';
include '../../includes/header_owner.php';
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="manage_activities.php" class="mr-4 text-indigo-600 hover:text-indigo-800">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Edit Kegiatan Volunteer</h1>
            </div>
        </div>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if($success): ?>
    <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700"><?php echo $success; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?php echo $error; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Edit Form -->
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <form action="edit_activity.php?id=<?php echo $activity_id; ?>" method="post">
            <div class="px-4 py-5 sm:p-6">
                <div class="grid grid-cols-6 gap-6">
                    <!-- Title -->
                    <div class="col-span-6 sm:col-span-4">
                        <label for="title" class="block text-sm font-medium text-gray-700">Judul Kegiatan *</label>
                        <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($activity['title']); ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Category -->
                    <div class="col-span-6 sm:col-span-3">
                        <label for="category" class="block text-sm font-medium text-gray-700">Kategori *</label>
                        <select id="category" name="category" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="" disabled>Pilih kategori</option>
                            <option value="Education" <?php echo ($activity['category'] == 'Education') ? 'selected' : ''; ?>>Education</option>
                            <option value="Environment" <?php echo ($activity['category'] == 'Environment') ? 'selected' : ''; ?>>Environment</option>
                            <option value="Health" <?php echo ($activity['category'] == 'Health') ? 'selected' : ''; ?>>Health</option>
                            <option value="Community Service" <?php echo ($activity['category'] == 'Community Service') ? 'selected' : ''; ?>>Community Service</option>
                            <option value="Animal Welfare" <?php echo ($activity['category'] == 'Animal Welfare') ? 'selected' : ''; ?>>Animal Welfare</option>
                            <option value="Arts & Culture" <?php echo ($activity['category'] == 'Arts & Culture') ? 'selected' : ''; ?>>Arts & Culture</option>
                            <option value="Disaster Relief" <?php echo ($activity['category'] == 'Disaster Relief') ? 'selected' : ''; ?>>Disaster Relief</option>
                            <option value="Human Rights" <?php echo ($activity['category'] == 'Human Rights') ? 'selected' : ''; ?>>Human Rights</option>
                            <option value="Sports" <?php echo ($activity['category'] == 'Sports') ? 'selected' : ''; ?>>Sports</option>
                            <option value="Technology" <?php echo ($activity['category'] == 'Technology') ? 'selected' : ''; ?>>Technology</option>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="col-span-6 sm:col-span-3">
                        <label for="location" class="block text-sm font-medium text-gray-700">Lokasi *</label>
                        <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($activity['location']); ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Event Date -->
                    <div class="col-span-6 sm:col-span-3">
                        <label for="event_date" class="block text-sm font-medium text-gray-700">Tanggal Kegiatan *</label>
                        <input type="date" name="event_date" id="event_date" value="<?php echo $activity['event_date']; ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>

                    <!-- Application Deadline -->
                    <div class="col-span-6 sm:col-span-3">
                        <label for="application_deadline" class="block text-sm font-medium text-gray-700">Deadline Pendaftaran *</label>
                        <input type="date" name="application_deadline" id="application_deadline" value="<?php echo $activity['application_deadline']; ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>

                    <!-- Description -->
                    <div class="col-span-6">
                        <label for="description" class="block text-sm font-medium text-gray-700">Deskripsi Kegiatan *</label>
                        <textarea id="description" name="description" rows="6" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($activity['description']); ?></textarea>
                        <p class="mt-2 text-sm text-gray-500">Jelaskan detail tentang kegiatan, tujuan, manfaat, dan informasi penting lainnya.</p>
                    </div>
                    
                    <!-- Required Skills -->
                    <div class="col-span-6">
                        <label for="required_skills" class="block text-sm font-medium text-gray-700">Keterampilan yang Diperlukan</label>
                        <input type="text" name="required_skills" id="required_skills" value="<?php echo htmlspecialchars($activity['required_skills']); ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        <p class="mt-2 text-sm text-gray-500">Pisahkan dengan koma. Contoh: Komunikasi, Bahasa Inggris, Fotografi</p>
                    </div>

                    <!-- Featured Option -->
                    <div class="col-span-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="is_featured" name="is_featured" type="checkbox" <?php echo ($activity['is_featured'] == 1) ? 'checked' : ''; ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="is_featured" class="font-medium text-gray-700">Jadikan sebagai kegiatan unggulan</label>
                                <p class="text-gray-500">Kegiatan unggulan akan ditampilkan di halaman utama dan direkomendasikan kepada volunteer.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                <a href="manage_activities.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 mr-3">
                    Batal
                </a>
                <button type="submit" name="update_activity" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>