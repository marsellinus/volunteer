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

// Check if certificates table exists before querying it
$table_check = $conn->query("SHOW TABLES LIKE 'certificates'");
if($table_check->num_rows > 0) {
    $certificates_query = $conn->query("
        SELECT COUNT(*) as certificates_count
        FROM certificates
        WHERE user_id = $user_id
    ");

    if($certificates_result = $certificates_query->fetch_assoc()) {
        $stats['certificates'] = $certificates_result['certificates_count'];
    }
}

$page_title = 'Profil Saya - VolunteerHub';
include '../../includes/header_user.php';
?>

<!-- Apply global background -->
<div class="min-h-screen bg-[#ffffff]">
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 pt-20 mt-10">
        <div class="md:grid md:grid-cols-3 md:gap-8">
            <!-- Left Column: Profile Info -->
            <div class="md:col-span-1 space-y-6">
                <!-- Profile Summary Card -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden transition-all duration-300 hover:shadow-xl">
                    <div class="px-6 py-5 bg-gradient-to-r from-[#10283f] to-[#1a3c5e] text-white">
                        <h3 class="text-lg font-semibold">Profil Volunteer</h3>
                        <p class="mt-1 text-sm text-gray-200">Informasi personal Anda</p>
                    </div>
                    <div class="px-6 py-6">
                        <div class="flex items-center mb-6">
                            <div class="flex-shrink-0 h-20 w-20 rounded-full bg-gradient-to-br from-[#10283f] to-[#2c5282] flex items-center justify-center text-white text-2xl font-medium shadow-md">
                                <?php echo strtoupper(substr($user['name'] ?? $user_name, 0, 2)); ?>
                            </div>
                            <div class="ml-5">
                                <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>

                        <dl class="space-y-4">
                            <?php if(!empty($user['bio'])): ?>
                            <div class="pb-3 border-b border-gray-100">
                                <dt class="text-sm font-medium text-gray-500">Bio</dt>
                                <dd class="mt-2 text-sm text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($user['bio']); ?></dd>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($user['skills'])): ?>
                            <div class="pb-3 border-b border-gray-100">
                                <dt class="text-sm font-medium text-gray-500">Keterampilan</dt>
                                <dd class="mt-2">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach(explode(',', $user['skills']) as $skill): ?>
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-[#10283f]/10 text-[#10283f]">
                                                <?php echo htmlspecialchars(trim($skill)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </dd>
                            </div>
                            <?php endif; ?>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Bergabung sejak</dt>
                                <dd class="mt-1 text-sm text-gray-700"><?php echo date('d F Y', strtotime($user['created_at'])); ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Volunteer Stats Card -->
                <div class="bg-white shadow-lg rounded-xl overflow-hidden transition-all duration-300 hover:shadow-xl">
                    <div class="px-6 py-5 bg-gradient-to-r from-[#10283f] to-[#1a3c5e] text-white">
                        <h3 class="text-lg font-semibold">Statistik Volunteer</h3>
                        <p class="mt-1 text-sm text-gray-200">Aktivitas volunteer Anda</p>
                    </div>
                    <div class="px-6 py-6">
                        <dl class="grid grid-cols-2 gap-6">
                            <div class="px-4 py-3 bg-blue-50 rounded-lg border-l-4 border-[#10283f]">
                                <dt class="text-sm font-medium text-gray-500">Total Pendaftaran</dt>
                                <dd class="mt-1 text-2xl font-bold text-[#10283f]"><?php echo $stats['total_applications']; ?></dd>
                            </div>
                            <div class="px-4 py-3 bg-green-50 rounded-lg border-l-4 border-green-500">
                                <dt class="text-sm font-medium text-gray-500">Diterima</dt>
                                <dd class="mt-1 text-2xl font-bold text-green-600"><?php echo $stats['approved_applications']; ?></dd>
                            </div>
                            <div class="px-4 py-3 bg-yellow-50 rounded-lg border-l-4 border-yellow-500">
                                <dt class="text-sm font-medium text-gray-500">Menunggu</dt>
                                <dd class="mt-1 text-2xl font-bold text-yellow-600"><?php echo $stats['pending_applications']; ?></dd>
                            </div>
                            <div class="px-4 py-3 bg-purple-50 rounded-lg border-l-4 border-[#10283f]">
                                <dt class="text-sm font-medium text-gray-500">Piagam</dt>
                                <dd class="mt-1 text-2xl font-bold text-[#10283f]"><?php echo $stats['certificates']; ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Right Column: Profile Form -->
            <div class="mt-8 md:mt-0 md:col-span-2">
                <form action="profile.php" method="POST" class="bg-white shadow-lg rounded-xl overflow-hidden transition-all duration-300 hover:shadow-xl">
                    <div class="px-6 py-5 bg-gradient-to-r from-gray-50 to-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800">Edit Profil</h3>
                        <p class="mt-1 text-sm text-gray-500">Perbarui informasi profil Anda</p>
                    </div>

                    <?php if($success): ?>
                        <div class="mx-6 mt-4 flex p-4 rounded-lg bg-green-50">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800"><?php echo $success; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($error): ?>
                        <div class="mx-6 mt-4 flex p-4 rounded-lg bg-red-50">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800"><?php echo $error; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="px-6 py-6 space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>" required 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#10283f] focus:ring-[#10283f] sm:text-sm">
                        </div>

                        <div>
                            <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
                            <textarea id="bio" name="bio" rows="4" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#10283f] focus:ring-[#10283f] sm:text-sm"
                                ><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <p class="mt-2 text-xs text-gray-500">Tuliskan tentang diri Anda secara singkat.</p>
                        </div>

                        <div>
                            <label for="skills" class="block text-sm font-medium text-gray-700">Keterampilan</label>
                            <input type="text" name="skills" id="skills" value="<?php echo htmlspecialchars($user['skills'] ?? ''); ?>" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#10283f] focus:ring-[#10283f] sm:text-sm">
                            <p class="mt-2 text-xs text-gray-500">Masukkan keterampilan yang Anda miliki, dipisahkan dengan koma (misal: Public Speaking, Desain Grafis, Mengajar).</p>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-800">Ubah Password</h3>
                            <p class="mt-1 text-xs text-gray-500">Kosongkan jika Anda tidak ingin mengubah password.</p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700">Password Saat Ini</label>
                                <input type="password" name="current_password" id="current_password" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#10283f] focus:ring-[#10283f] sm:text-sm">
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">Password Baru</label>
                                <input type="password" name="new_password" id="new_password" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#10283f] focus:ring-[#10283f] sm:text-sm">
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#10283f] focus:ring-[#10283f] sm:text-sm">
                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 flex justify-end">
                        <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-[#10283f] hover:bg-[#1a3c5e] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f] transition-all duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>
</div>