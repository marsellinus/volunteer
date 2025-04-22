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
$page_title = 'Profil Organisasi - VolunteerHub';

// Include the header
include '../../includes/header_owner.php';

// Get unread notifications count with error handling
try {
    $unread_count = getOwnerUnreadNotificationsCount($owner_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Get owner profile data
$stmt = $conn->prepare("SELECT * FROM owners WHERE owner_id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();

$success = '';
$error = '';

// Handle profile update
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $organization_name = $_POST['organization_name'] ?? '';
    $organization_description = $_POST['organization_description'] ?? '';
    $website = $_POST['website'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if(empty($name) || empty($organization_name)) {
        $error = "Nama dan nama organisasi tidak boleh kosong";
    } else {
        // Check if password needs update
        if(!empty($current_password) && !empty($new_password)) {
            // Verify current password
            if(password_verify($current_password, $owner['password'])) {
                // Validate new password
                if($new_password !== $confirm_password) {
                    $error = "Konfirmasi password baru tidak cocok";
                } elseif(strlen($new_password) < 6) {
                    $error = "Password baru minimal 6 karakter";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("UPDATE owners SET name = ?, organization_name = ?, organization_description = ?, website = ?, password = ? WHERE owner_id = ?");
                    $stmt->bind_param("sssssi", $name, $organization_name, $organization_description, $website, $hashed_password, $owner_id);
                }
            } else {
                $error = "Password saat ini salah";
            }
        } else {
            // Update profile without password
            $stmt = $conn->prepare("UPDATE owners SET name = ?, organization_name = ?, organization_description = ?, website = ? WHERE owner_id = ?");
            $stmt->bind_param("ssssi", $name, $organization_name, $organization_description, $website, $owner_id);
        }
        
        // Execute update if no error
        if(empty($error)) {
            if($stmt->execute()) {
                $success = "Profil berhasil diperbarui";
                
                // Update session variable
                $_SESSION['owner_name'] = $name;
                
                // Refresh owner data
                $stmt = $conn->prepare("SELECT * FROM owners WHERE owner_id = ?");
                $stmt->bind_param("i", $owner_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $owner = $result->fetch_assoc();
            } else {
                $error = "Gagal memperbarui profil. Silakan coba lagi.";
            }
        }
    }
}

// Get owner statistics
$stats = [
    'total_activities' => 0,
    'active_activities' => 0,
    'total_applications' => 0,
    'pending_applications' => 0,
    'approved_applications' => 0
];

$stats_query = $conn->query("
    SELECT COUNT(*) as total_activities
    FROM volunteer_activities 
    WHERE owner_id = $owner_id
");

if($stats_result = $stats_query->fetch_assoc()) {
    $stats['total_activities'] = $stats_result['total_activities'];
}

$active_query = $conn->query("
    SELECT COUNT(*) as active_count
    FROM volunteer_activities 
    WHERE owner_id = $owner_id 
    AND application_deadline >= CURDATE()
");

if($active_result = $active_query->fetch_assoc()) {
    $stats['active_activities'] = $active_result['active_count'];
}

$applications_query = $conn->query("
    SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications
    FROM applications a
    JOIN volunteer_activities va ON a.activity_id = va.id
    WHERE va.owner_id = $owner_id
");

if($apps_result = $applications_query->fetch_assoc()) {
    $stats['total_applications'] = $apps_result['total_applications'];
    $stats['approved_applications'] = $apps_result['approved_applications'];
    $stats['pending_applications'] = $apps_result['pending_applications'];
}
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <!-- Profile Summary -->
        <div class="md:col-span-1">
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-purple-700 text-white">
                    <h3 class="text-lg font-medium">Profil Organisasi</h3>
                    <p class="mt-1 text-sm text-indigo-100">Informasi tentang organisasi Anda</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 h-20 w-20 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 text-2xl">
                            <?php echo strtoupper(substr($owner['organization_name'] ?? $owner_name, 0, 2)); ?>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($owner['organization_name'] ?? 'Organisasi'); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($owner['email']); ?></p>
                            <p class="text-sm text-gray-500">PIC: <?php echo htmlspecialchars($owner_name); ?></p>
                            <?php if(!empty($owner['website'])): ?>
                            <a href="<?php echo htmlspecialchars($owner['website']); ?>" target="_blank" class="text-sm text-indigo-600 hover:text-indigo-500">
                                <i class="fas fa-external-link-alt mr-1"></i> <?php echo htmlspecialchars($owner['website']); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Statistik Organisasi</h4>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-6">
                            <div class="border-l-4 border-indigo-400 pl-2">
                                <dt class="text-sm text-gray-500">Total Kegiatan</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['total_activities']; ?></dd>
                            </div>
                            <div class="border-l-4 border-green-400 pl-2">
                                <dt class="text-sm text-gray-500">Kegiatan Aktif</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['active_activities']; ?></dd>
                            </div>
                            <div class="border-l-4 border-blue-400 pl-2">
                                <dt class="text-sm text-gray-500">Total Pendaftar</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['total_applications']; ?></dd>
                            </div>
                            <div class="border-l-4 border-yellow-400 pl-2">
                                <dt class="text-sm text-gray-500">Menunggu Review</dt>
                                <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['pending_applications']; ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="md:col-span-2">
            <form action="profile.php" method="POST" class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Profil Organisasi</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Perbarui informasi organisasi Anda</p>
                </div>

                <?php if($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mx-4 mt-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    <?php echo $success; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mx-4 mt-4">
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

                <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6 sm:col-span-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Nama PIC</label>
                            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($owner['name']); ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="organization_name" class="block text-sm font-medium text-gray-700">Nama Organisasi</label>
                            <input type="text" name="organization_name" id="organization_name" value="<?php echo htmlspecialchars($owner['organization_name'] ?? ''); ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <div class="col-span-6">
                            <label for="organization_description" class="block text-sm font-medium text-gray-700">Deskripsi Organisasi</label>
                            <textarea id="organization_description" name="organization_description" rows="4" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($owner['organization_description'] ?? ''); ?></textarea>
                            <p class="mt-2 text-sm text-gray-500">Jelaskan tentang organisasi Anda, visi, misi, dan fokus kegiatannya.</p>
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="website" class="block text-sm font-medium text-gray-700">Website</label>
                            <input type="url" name="website" id="website" value="<?php echo htmlspecialchars($owner['website'] ?? ''); ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="https://www.example.org">
                        </div>

                        <div class="col-span-6 border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-medium text-gray-900">Ubah Password</h3>
                            <p class="mt-1 text-sm text-gray-500">Kosongkan jika Anda tidak ingin mengubah password.</p>
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Password Saat Ini</label>
                            <input type="password" name="current_password" id="current_password" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="new_password" class="block text-sm font-medium text-gray-700">Password Baru</label>
                            <input type="password" name="new_password" id="new_password" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <div class="col-span-6 sm:col-span-4">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-save mr-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
