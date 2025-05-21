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

// Get unread notifications count
try {
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

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
        // Check if event date is after application deadline
        if(strtotime($event_date) <= strtotime($application_deadline)) {
            $error = "Tanggal kegiatan harus setelah batas waktu pendaftaran.";
        } else {
            // Handle image upload
            $image_path = $activity['images']; // Keep existing image path by default
            
            if(isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                // Define upload directory
                $upload_dir = '../../uploads/activities/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Get file extension
                $file_ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
                
                // Check if the file is an image
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if(in_array($file_ext, $allowed_extensions)) {
                    // Generate unique filename
                    $new_filename = uniqid('activity_') . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Move uploaded file
                    if(move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                        // Delete old image if exists and not the default
                        if(!empty($activity['images']) && file_exists('../../' . $activity['images'])) {
                            @unlink('../../' . $activity['images']);
                        }
                        
                        $image_path = 'uploads/activities/' . $new_filename;
                    } else {
                        $error = "Gagal mengunggah gambar. Silakan coba lagi.";
                    }
                } else {
                    $error = "Hanya file JPG, JPEG, PNG, dan GIF yang diperbolehkan untuk gambar sampul.";
                }
            }
            
            if(empty($error)) {
                // Update activity in database
                $stmt = $conn->prepare("UPDATE volunteer_activities 
                                      SET title = ?, description = ?, category = ?, 
                                          location = ?, event_date = ?, application_deadline = ?, 
                                          required_skills = ?, is_featured = ?, images = ?
                                      WHERE id = ? AND owner_id = ?");
                
                $stmt->bind_param("sssssssssii", 
                $title, $description, $category, $location,
                $event_date, $application_deadline, $required_skills, 
                $is_featured, $image_path, $activity_id, $owner_id);
                    
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
    }
}

// Get list of categories for dropdown (from existing activities)
$categories = $conn->query("SELECT DISTINCT category FROM volunteer_activities ORDER BY category");

$page_title = 'Edit Kegiatan - VolunteerHub';
include '../../includes/header_owner.php';
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 pt-20 mt-10">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="manage_activities.php" class="mr-4 text-[#10283f] hover:text-[#1e3a5f]">
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
    <div class="bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <form action="edit_activity.php?id=<?php echo $activity_id; ?>" method="post" enctype="multipart/form-data">
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-6">
                    <label for="title" class="block text-sm font-medium text-gray-700">
                        Judul *
                    </label>
                    <div class="mt-1">
                        <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($activity['title']); ?>" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
                
                <div class="sm:col-span-6">
                    <label for="cover_image" class="block text-sm font-medium text-gray-700">
                        Gambar Sampul
                    </label>
                    <div class="mt-1">
                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center" id="upload-container">
                                <?php if(!empty($activity['images']) && file_exists('../../' . $activity['images'])): ?>
                                    <!-- Show current image -->
                                    <div id="image-preview-container" class="mt-4">
                                        <img id="image-preview" src="../../<?php echo htmlspecialchars($activity['images']); ?>" alt="Current Image" class="max-h-40 mx-auto">
                                        <p id="file-name" class="text-sm text-gray-600 mt-2">Current image</p>
                                    </div>
                                    <svg class="mx-auto h-12 w-12 text-gray-400 hidden" id="upload-icon" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                <?php else: ?>
                                    <svg class="mx-auto h-12 w-12 text-gray-400" id="upload-icon" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <!-- Image preview container (hidden by default) -->
                                    <div id="image-preview-container" class="mt-4 hidden">
                                        <img id="image-preview" src="#" alt="Preview" class="max-h-40 mx-auto">
                                        <p id="file-name" class="text-sm text-gray-600 mt-2"></p>
                                    </div>
                                <?php endif; ?>
                                <div class="flex text-sm text-gray-600">
                                    <label for="cover_image" class="relative cursor-pointer bg-white rounded-md font-medium text-[#10283f] hover:text-[#1e3a5f] focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-[#10283f]">
                                        <span>Unggah gambar</span>
                                        <input id="cover_image" name="cover_image" type="file" accept="image/*" class="sr-only" onchange="previewImage()">
                                    </label>
                                    <p class="pl-1">atau seret dan lepas</p>
                                </div>
                                <p class="text-xs text-gray-500">
                                    PNG, JPG, GIF hingga 10MB
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Pilih gambar untuk mewakili kegiatan relawan ini. Ukuran yang direkomendasikan: 1200x600px.</p>
                </div>
                
                <div class="sm:col-span-3">
                    <label for="category" class="block text-sm font-medium text-gray-700">
                        Kategori *
                    </label>
                    <div class="mt-1">
                        <select id="category" name="category" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Pilih kategori</option>
                            <?php if($categories && $categories->num_rows > 0): ?>
                                <?php while($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($activity['category'] == $cat['category']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                            <option value="Education" <?php echo ($activity['category'] == 'Education') ? 'selected' : ''; ?>>Pendidikan</option>
                            <option value="Environment" <?php echo ($activity['category'] == 'Environment') ? 'selected' : ''; ?>>Lingkungan</option>
                            <option value="Health" <?php echo ($activity['category'] == 'Health') ? 'selected' : ''; ?>>Kesehatan</option>
                            <option value="Community Service" <?php echo ($activity['category'] == 'Community Service') ? 'selected' : ''; ?>>Layanan Masyarakat</option>
                            <option value="Animal Welfare" <?php echo ($activity['category'] == 'Animal Welfare') ? 'selected' : ''; ?>>Kesejahteraan Hewan</option>
                            <option value="Arts & Culture" <?php echo ($activity['category'] == 'Arts & Culture') ? 'selected' : ''; ?>>Seni & Budaya</option>
                            <option value="Disaster Relief" <?php echo ($activity['category'] == 'Disaster Relief') ? 'selected' : ''; ?>>Bantuan Bencana</option>
                            <option value="Human Rights" <?php echo ($activity['category'] == 'Human Rights') ? 'selected' : ''; ?>>Hak Asasi Manusia</option>
                            <option value="Sports" <?php echo ($activity['category'] == 'Sports') ? 'selected' : ''; ?>>Olahraga</option>
                            <option value="Technology" <?php echo ($activity['category'] == 'Technology') ? 'selected' : ''; ?>>Teknologi</option>
                            <option value="Other" <?php echo ($activity['category'] == 'Other') ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="location" class="block text-sm font-medium text-gray-700">
                        Lokasi *
                    </label>
                    <div class="mt-1">
                        <input type="text" name="location" id="location" value="<?php echo htmlspecialchars($activity['location']); ?>" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="event_date" class="block text-sm font-medium text-gray-700">
                        Tanggal Kegiatan *
                    </label>
                    <div class="mt-1">
                        <input type="date" name="event_date" id="event_date" value="<?php echo $activity['event_date']; ?>" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="application_deadline" class="block text-sm font-medium text-gray-700">
                        Batas Waktu Pendaftaran *
                    </label>
                    <div class="mt-1">
                        <input type="date" name="application_deadline" id="application_deadline" value="<?php echo $activity['application_deadline']; ?>" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-6">
                    <label for="required_skills" class="block text-sm font-medium text-gray-700">
                        Keterampilan yang Diperlukan
                    </label>
                    <div class="mt-1">
                        <textarea id="required_skills" name="required_skills" rows="3" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($activity['required_skills']); ?></textarea>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Daftar keterampilan yang diperlukan untuk kesempatan relawan ini.</p>
                </div>

                <div class="sm:col-span-6">
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Deskripsi *
                    </label>
                    <div class="mt-1">
                        <textarea id="description" name="description" rows="5" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($activity['description']); ?></textarea>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Deskripsi detail tentang kesempatan relawan termasuk tanggung jawab, manfaat, dan informasi relevan lainnya.</p>
                </div>

                <div class="sm:col-span-6">
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="is_featured" name="is_featured" type="checkbox" <?php echo ($activity['is_featured'] == 1) ? 'checked' : ''; ?> class="focus:ring-[#10283f] h-4 w-4 text-[#10283f] border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_featured" class="font-medium text-gray-700">Tampilkan kegiatan ini di halaman utama</label>
                            <p class="text-gray-500">Kegiatan unggulan akan muncul di halaman utama dan mendapatkan lebih banyak visibilitas.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <a href="manage_activities.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f]">
                    Batal
                </a>
                <button type="submit" name="update_activity" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#10283f] hover:bg-[#1e3a5f] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f]">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</main>

<!-- JavaScript for image preview functionality -->
<script>
function previewImage() {
    const fileInput = document.getElementById('cover_image');
    const previewContainer = document.getElementById('image-preview-container');
    const preview = document.getElementById('image-preview');
    const fileName = document.getElementById('file-name');
    const uploadIcon = document.getElementById('upload-icon');
    
    if (fileInput.files && fileInput.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Show preview container
            previewContainer.classList.remove('hidden');
            
            // Set image source
            preview.src = e.target.result;
            
            // Display file name
            fileName.textContent = fileInput.files[0].name;
            
            // Optionally hide the upload icon
            uploadIcon.classList.add('hidden');
        }
        
        reader.readAsDataURL(fileInput.files[0]);
    } else {
        // Only hide if no current image is displayed
        if (preview.src.includes('data:image')) {
            // Hide preview container if no file selected
            previewContainer.classList.add('hidden');
            
            // Show upload icon
            uploadIcon.classList.remove('hidden');
        }
    }
}
</script>

<?php include '../../includes/footer.php'; ?>