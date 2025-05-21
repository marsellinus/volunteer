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

// Get unread notifications count
try {
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Set page title
$page_title = 'Buat Kegiatan Baru - VolunteerHub';

// Include the header
include '../../includes/header_owner.php';

$success_message = '';
$error_message = '';

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $location = $_POST['location'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $application_deadline = $_POST['application_deadline'] ?? '';
    $required_skills = $_POST['required_skills'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Validate required fields
    if(empty($title) || empty($category) || empty($location) || empty($event_date) || empty($application_deadline) || empty($description)) {
        $error_message = "Harap isi semua bidang yang wajib diisi.";
    } else {
        // Check if event date is after application deadline
        if(strtotime($event_date) <= strtotime($application_deadline)) {
            $error_message = "Tanggal kegiatan harus setelah batas waktu pendaftaran.";
        } else {
            // Handle image upload
            $image_path = '';
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
                        $image_path = 'uploads/activities/' . $new_filename;
                    } else {
                        $error_message = "Gagal mengunggah gambar. Silakan coba lagi.";
                    }
                } else {
                    $error_message = "Hanya file JPG, JPEG, PNG, dan GIF yang diperbolehkan untuk gambar sampul.";
                }
            }
            
            if(empty($error_message)) {
                // Insert the new activity
                $stmt = $conn->prepare("INSERT INTO volunteer_activities 
                                    (owner_id, title, category, location, event_date, application_deadline, required_skills, description, is_featured, images, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                                    
                                    $stmt->bind_param("isssssssis", $owner_id, $title, $category, $location, $event_date, $application_deadline, $required_skills, $description, $is_featured, $image_path);
                
                if($stmt->execute()) {
                    $success_message = "Kegiatan relawan berhasil dibuat!";
                } else {
                    $error_message = "Terjadi kesalahan saat membuat kegiatan relawan. Silakan coba lagi.";
                }
            }
        }
    }
}

// Get list of categories for dropdown (from existing activities)
$categories = $conn->query("SELECT DISTINCT category FROM volunteer_activities ORDER BY category");
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 pt-20 mt-10">
    <!-- Page header with floating card effect -->
    <div class="bg-gradient-to-r from-[#10283f] to-[#1e3a54] rounded-xl shadow-xl p-8 mb-8 text-white relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 opacity-10">
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <path fill="white" d="M45.3,-58C59.9,-50,73.5,-39.2,79.8,-24.7C86.2,-10.2,85.3,7.8,78.1,22.5C70.9,37.1,57.5,48.3,42.7,55.9C27.9,63.5,11.6,67.5,-3.7,72.1C-19,76.7,-38.1,82,-53,75.5C-67.9,69,-78.6,50.8,-80.8,32.9C-83,15,-76.6,-2.7,-71.5,-21.4C-66.3,-40.1,-62.4,-59.7,-50.3,-68.6C-38.2,-77.5,-17.8,-75.6,-0.5,-75C16.9,-74.3,33.8,-75,45.3,-58Z" transform="translate(100 100)" />
            </svg>
        </div>
        <h2 class="text-3xl font-bold relative z-10">Buat Kegiatan Relawan Baru</h2>
        <p class="mt-2 text-gray-100 opacity-90 max-w-2xl relative z-10">Buat kesempatan relawan baru dan bantu masyarakat menemukan cara untuk berkontribusi pada tujuan Anda.</p>
    </div>
    
    <?php if($success_message): ?>
        <div class="mt-6 rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">
                        <?php echo $success_message; ?>
                    </p>
                    <div class="mt-4">
                        <div class="-mx-2 -my-1.5 flex">
                            <a href="manage_activities.php" class="bg-green-50 px-2 py-1.5 rounded-md text-sm font-medium text-green-800 hover:bg-green-100">
                                Lihat Semua Kegiatan
                            </a>
                            <button type="button" onclick="location.reload();" class="ml-3 bg-green-50 px-2 py-1.5 rounded-md text-sm font-medium text-green-800 hover:bg-green-100">
                                Buat Kegiatan Lain
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error_message): ?>
        <div class="mt-6 rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">
                        <?php echo $error_message; ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-6 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
        <form action="create_activity.php" method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                <div class="sm:col-span-6">
                    <label for="title" class="block text-sm font-medium text-gray-700">
                        Judul *
                    </label>
                    <div class="mt-1">
                        <input type="text" name="title" id="title" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>
                
                <div class="sm:col-span-6">
                    <label for="cover_image" class="block text-sm font-medium text-gray-700">
                        Gambar Sampul
                    </label>
                    <div class="mt-1">
                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center" id="upload-container">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true" id="upload-icon">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="cover_image" class="relative cursor-pointer bg-white rounded-md font-medium text-[#10283f] hover:text-[#1e3a54] focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-[#10283f]">
                                        <span>Unggah gambar</span>
                                        <input id="cover_image" name="cover_image" type="file" accept="image/*" class="sr-only" onchange="previewImage()">
                                    </label>
                                    <p class="pl-1">atau seret dan lepas</p>
                                </div>
                                <p class="text-xs text-gray-500">
                                    PNG, JPG, GIF hingga 10MB
                                </p>
                                
                                <!-- Image preview container (hidden by default) -->
                                <div id="image-preview-container" class="mt-4 hidden">
                                    <img id="image-preview" src="#" alt="Preview" class="max-h-40 mx-auto">
                                    <p id="file-name" class="text-sm text-gray-600 mt-2"></p>
                                </div>
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
                            <?php if ($categories && $categories->num_rows > 0): ?>
                                <?php while ($cat = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>


                <div class="sm:col-span-3">
                    <label for="location" class="block text-sm font-medium text-gray-700">
                        Lokasi *
                    </label>
                    <div class="mt-1">
                        <input type="text" name="location" id="location" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="event_date" class="block text-sm font-medium text-gray-700">
                        Tanggal Kegiatan *
                    </label>
                    <div class="mt-1">
                        <input type="date" name="event_date" id="event_date" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label for="application_deadline" class="block text-sm font-medium text-gray-700">
                        Batas Waktu Pendaftaran *
                    </label>
                    <div class="mt-1">
                        <input type="date" name="application_deadline" id="application_deadline" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                </div>

                <div class="sm:col-span-6">
                    <label for="required_skills" class="block text-sm font-medium text-gray-700">
                        Keterampilan yang Diperlukan
                    </label>
                    <div class="mt-1">
                        <textarea id="required_skills" name="required_skills" rows="3" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Daftar keterampilan yang diperlukan untuk kesempatan relawan ini.</p>
                </div>

                <div class="sm:col-span-6">
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Deskripsi *
                    </label>
                    <div class="mt-1">
                        <textarea id="description" name="description" rows="5" required class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Deskripsi detail tentang kesempatan relawan termasuk tanggung jawab, manfaat, dan informasi relevan lainnya.</p>
                </div>

                <div class="sm:col-span-6">
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input id="is_featured" name="is_featured" type="checkbox" class="focus:ring-[#10283f] h-4 w-4 text-[#10283f] border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_featured" class="font-medium text-gray-700">Tampilkan kegiatan ini di halaman utama</label>
                            <p class="text-gray-500">Kegiatan unggulan akan muncul di halaman utama dan mendapatkan lebih banyak visibilitas.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="window.history.back()" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f]">
                    Batal
                </button>
                <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-[#10283f] hover:bg-[#1e3a54] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f]">
                    Buat Kegiatan
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
        // Hide preview container if no file selected
        previewContainer.classList.add('hidden');
        
        // Show upload icon
        uploadIcon.classList.remove('hidden');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>