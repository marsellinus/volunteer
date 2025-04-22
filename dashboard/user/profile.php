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

// Get unread notifications count with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Get user profile data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$success = '';
$error = '';

// Handle profile update
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $skills = $_POST['skills'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if(empty($name)) {
        $error = "Nama tidak boleh kosong";
    } else {
        // Check if password needs update
        if(!empty($current_password) && !empty($new_password)) {
            // Verify current password
            if(password_verify($current_password, $user['password'])) {
                // Validate new password
                if($new_password !== $confirm_password) {
                    $error = "Konfirmasi password baru tidak cocok";
                } elseif(strlen($new_password) < 6) {
                    $error = "Password baru minimal 6 karakter";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ?, skills = ?, password = ? WHERE user_id = ?");
                    $stmt->bind_param("ssssi", $name, $bio, $skills, $hashed_password, $user_id);
                }
            } else {
                $error = "Password saat ini salah";
            }
        } else {
            // Update profile without password
            $stmt = $conn->prepare("UPDATE users SET name = ?, bio = ?, skills = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $name, $bio, $skills, $user_id);
        }
        
        // Execute update if no error
        if(empty($error)) {
            if($stmt->execute()) {
                $success = "Profil berhasil diperbarui";
                
                // Update session variable
                $_SESSION['user_name'] = $name;
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error = "Gagal memperbarui profil. Silakan coba lagi.";
            }
        }
    }
}

// Get user statistics
$stats = [
    'total_applications' => 0,
    'approved_applications' => 0,
    'pending_applications' => 0,
    'upcoming_activities' => 0,
    'completed_activities' => 0,
    'certificates' => 0
];

$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_applications,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications
    FROM applications 
    WHERE user_id = $user_id
");

if($stats_result = $stats_query->fetch_assoc()) {
    $stats['total_applications'] = $stats_result['total_applications'];
    $stats['approved_applications'] = $stats_result['approved_applications'];
    $stats['pending_applications'] = $stats_result['pending_applications'];
}

$upcoming_query = $conn->query("
    SELECT COUNT(*) as upcoming_count
    FROM applications a
    JOIN volunteer_activities va ON a.activity_id = va.id
    WHERE a.user_id = $user_id 
    AND a.status = 'approved' 
    AND va.event_date > CURDATE()
");

if($upcoming_result = $upcoming_query->fetch_assoc()) {
    $stats['upcoming_activities'] = $upcoming_result['upcoming_count'];
}

$completed_query = $conn->query("
    SELECT COUNT(*) as completed_count
    FROM applications a
    JOIN volunteer_activities va ON a.activity_id = va.id
    WHERE a.user_id = $user_id 
    AND a.status = 'approved' 
    AND va.event_date <= CURDATE()
");

if($completed_result = $completed_query->fetch_assoc()) {
    $stats['completed_activities'] = $completed_result['completed_count'];
}

$certificates_query = $conn->query("
    SELECT COUNT(*) as certificates_count
    FROM certificates
    WHERE user_id = $user_id
");

if($certificates_result = $certificates_query->fetch_assoc()) {
    $stats['certificates'] = $certificates_result['certificates_count'];
}

$page_title = 'Profil Saya - VolunteerHub';
include '../../includes/header_user.php';
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <!-- Profile Summary -->
        <div class="md:col-span-1">
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-purple-700 text-white">
                    <h3 class="text-lg font-medium">Profil Volunteer</h3>
                    <p class="mt-1 text-sm text-indigo-100">Informasi personal Anda</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
                    <div class="flex items-center mb-6">
                        <div class="flex-shrink-0 h-24 w-24 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 text-3xl">
                            <?php echo strtoupper(substr($user['name'] ?? $user_name, 0, 2)); ?>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-medium text-gray-900"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>

                    <dl class="divide-y divide-gray-200">
                        <?php if(!empty($user['bio'])): ?>
                        <div class="py-4">
                            <dt class="text-sm font-medium text-gray-500">Bio</dt>
                            <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line"><?php echo htmlspecialchars($user['bio']); ?></dd>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($user['skills'])): ?>
                        <div class="py-4">
                            <dt class="text-sm font-medium text-gray-500 mb-2">Keterampilan</dt>
                            <dd class="mt-1">
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach(explode(',', $user['skills']) as $skill): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            <?php echo htmlspecialchars(trim($skill)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </dd>
                        </div>
                        <?php endif; ?>

                        <div class="py-4">
                            <dt class="text-sm font-medium text-gray-500">Bergabung sejak</dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo date('d F Y', strtotime($user['created_at'])); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Volunteer Stats -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-purple-700 text-white">
                    <h3 class="text-lg font-medium">Statistik Volunteer</h3>
                    <p class="mt-1 text-sm text-indigo-100">Aktivitas volunteer Anda</p>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:p-6">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-6">
                        <div class="border-l-4 border-indigo-400 pl-2">
                            <dt class="text-sm text-gray-500">Total Pendaftaran</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['total_applications']; ?></dd>
                        </div>
                        <div class="border-l-4 border-green-400 pl-2">
                            <dt class="text-sm text-gray-500">Diterima</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['approved_applications']; ?></dd>
                        </div>
                        <div class="border-l-4 border-yellow-400 pl-2">
                            <dt class="text-sm text-gray-500">Menunggu</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['pending_applications']; ?></dd>
                        </div>
                        <div class="border-l-4 border-purple-400 pl-2">
                            <dt class="text-sm text-gray-500">Piagam</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900"><?php echo $stats['certificates']; ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <div class="mt-5 md:mt-0 md:col-span-2">
            <form action="profile.php" method="POST" class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Edit Profil</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Perbarui informasi profil Anda</p>
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
                            <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <div class="col-span-6">
                            <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
                            <textarea id="bio" name="bio" rows="3" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <p class="mt-2 text-sm text-gray-500">Tuliskan tentang diri Anda secara singkat.</p>
                        </div>

                        <div class="col-span-6">
                            <label for="skills" class="block text-sm font-medium text-gray-700">Keterampilan</label>
                            <input type="text" name="skills" id="skills" value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <p class="mt-2 text-sm text-gray-500">Masukkan keterampilan yang Anda miliki, dipisahkan dengan koma (misal: Public Speaking, Desain Grafis, Mengajar).</p>
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
