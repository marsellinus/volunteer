<?php
include_once '../../config/database.php';
include_once '../../logic/recommendation.php';
include_once '../../includes/notifications.php';
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get activity ID from URL parameter
$activity_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($activity_id <= 0) {
    header("Location: search.php");
    exit;
}

// Log this activity view for recommendation system
logActivityView($user_id, $activity_id, $conn);

// Get activity details
$stmt = $conn->prepare("SELECT va.*, o.name as owner_name, o.organization_name 
                       FROM volunteer_activities va 
                       JOIN owners o ON va.owner_id = o.owner_id 
                       WHERE va.id = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: search.php");
    exit;
}

$activity = $result->fetch_assoc();

// Check if user has already applied
$stmt = $conn->prepare("SELECT id, status, message FROM applications WHERE user_id = ? AND activity_id = ?");
$stmt->bind_param("ii", $user_id, $activity_id);
$stmt->execute();
$application_result = $stmt->get_result();
$has_applied = ($application_result->num_rows > 0);
$application = $has_applied ? $application_result->fetch_assoc() : null;

// Process application submission
$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if($has_applied) {
        $error_message = "Anda sudah mendaftar untuk kegiatan volunteer ini.";
    } else {
        $message = $_POST['message'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $experience = $_POST['experience'] ?? '';
        
        // Combine all application information
        $applicationDetails = "Telepon: $phone\n\nPengalaman: $experience\n\nPesan: $message";
        
        $stmt = $conn->prepare("INSERT INTO applications (user_id, activity_id, message, status, applied_at) VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iis", $user_id, $activity_id, $applicationDetails);
        
        if($stmt->execute()) {
            $success_message = "Pendaftaran Anda berhasil dikirim! Panitia akan meninjau lamaran Anda segera.";
            $has_applied = true;
            $application = [
                'status' => 'pending',
                'message' => $applicationDetails
            ];
            
            // Send notification to the user
            createUserNotification(
                $user_id,
                "Pendaftaran Terkirim",
                "Pendaftaran Anda untuk kegiatan \"" . $activity['title'] . "\" telah berhasil dikirim dan sedang menunggu persetujuan.",
                "success",
                "view_application.php?id=" . $conn->insert_id,
                $conn
            );
            
            // Send notification to the owner
            createOwnerNotification(
                $activity['owner_id'],
                "Pendaftaran Baru",
                "Ada pendaftar baru untuk kegiatan \"" . $activity['title'] . "\". Silakan tinjau pendaftaran ini.",
                "info",
                "view_applications.php?activity_id=" . $activity_id,
                $conn
            );
        } else {
            $error_message = "Gagal mengirim pendaftaran. Silakan coba lagi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activity['title']); ?> - VolunteerHub</title>
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
                            <a href="search.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-search mr-1"></i> Cari
                            </a>
                            <a href="my_applications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-clipboard-list mr-1"></i> Lamaran
                            </a>
                            <a href="certificates.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-certificate mr-1"></i> Piagam
                            </a>
                            <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-user mr-1"></i> Profil
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($user_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="javascript:history.back()" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke hasil pencarian
                </a>
            </div>

            <?php if($success_message): ?>
                <div class="rounded-md bg-green-50 p-4 mb-6 border border-green-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                <?php echo $success_message; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="rounded-md bg-red-50 p-4 mb-6 border border-red-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400 text-lg"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                                <?php echo $error_message; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($activity['title']); ?></h2>
                            <p class="mt-1 max-w-2xl text-sm text-indigo-100">
                                <?php echo htmlspecialchars($activity['organization_name']); ?> - <?php echo htmlspecialchars($activity['owner_name']); ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                            <?php echo htmlspecialchars($activity['category']); ?>
                        </span>
                    </div>
                </div>
                <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                    <dl class="sm:divide-y sm:divide-gray-200">
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Lokasi</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                    <?php echo htmlspecialchars($activity['location']); ?>
                                </div>
                            </dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Tanggal Kegiatan</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="flex items-center">
                                    <i class="far fa-calendar-alt text-indigo-500 mr-2"></i>
                                    <?php echo date('d F Y', strtotime($activity['event_date'])); ?>
                                </div>
                            </dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Batas Pendaftaran</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="flex items-center">
                                    <i class="fas fa-hourglass-half text-yellow-500 mr-2"></i>
                                    <?php echo date('d F Y', strtotime($activity['application_deadline'])); ?>
                                    
                                    <?php
                                    $today = new DateTime();
                                    $deadline = new DateTime($activity['application_deadline']);
                                    $interval = $today->diff($deadline);
                                    if ($deadline < $today) {
                                        echo '<span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Pendaftaran ditutup</span>';
                                    } elseif ($interval->days <= 3) {
                                        echo '<span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Segera ditutup</span>';
                                    }
                                    ?>
                                </div>
                            </dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Keterampilan yang Dibutuhkan</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php if (!empty($activity['required_skills'])): ?>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (explode(',', $activity['required_skills']) as $skill): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo trim(htmlspecialchars($skill)); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-500 italic">Tidak ada keterampilan khusus yang dibutuhkan</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Deskripsi</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <p class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                            </dd>
                        </div>
                    </dl>
                </div>
                
                <?php if($has_applied): ?>
                    <?php 
                        $status_color = 'gray';
                        $status_text = 'Menunggu';
                        $status_icon = 'clock';
                        
                        if($application['status'] == 'approved') {
                            $status_color = 'green';
                            $status_text = 'Diterima';
                            $status_icon = 'check-circle';
                        } elseif($application['status'] == 'rejected') {
                            $status_color = 'red';
                            $status_text = 'Ditolak';
                            $status_icon = 'times-circle';
                        } elseif($application['status'] == 'pending') {
                            $status_color = 'yellow';
                            $status_text = 'Menunggu';
                            $status_icon = 'clock';
                        }
                    ?>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <div class="bg-<?php echo $status_color; ?>-50 border border-<?php echo $status_color; ?>-200 p-4 rounded-md mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-<?php echo $status_icon; ?> text-<?php echo $status_color; ?>-400 text-lg"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-<?php echo $status_color; ?>-800">Status Pendaftaran: <?php echo $status_text; ?></h3>
                                    
                                    <?php if($application['status'] == 'pending'): ?>
                                        <p class="mt-2 text-sm text-<?php echo $status_color; ?>-700">
                                            Pendaftaran Anda sedang ditinjau oleh panitia. Kami akan memberi tahu Anda jika ada pembaruan.
                                        </p>
                                    <?php elseif($application['status'] == 'approved'): ?>
                                        <p class="mt-2 text-sm text-<?php echo $status_color; ?>-700">
                                            Selamat! Anda telah diterima untuk kegiatan volunteer ini. Panitia akan menghubungi Anda dengan detail lebih lanjut melalui email.
                                        </p>
                                    <?php elseif($application['status'] == 'rejected'): ?>
                                        <p class="mt-2 text-sm text-<?php echo $status_color; ?>-700">
                                            Maaf, pendaftaran Anda tidak terpilih untuk kesempatan ini. Jangan menyerah dan terus mencari kesempatan volunteer lainnya yang sesuai dengan minat Anda.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h4 class="text-md font-medium text-gray-900 mb-2">Detail Pendaftaran Anda:</h4>
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                <pre class="text-sm text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($application['message']); ?></pre>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Daftar untuk Kesempatan Volunteer Ini</h3>
                        
                        <?php if(strtotime($activity['application_deadline']) < time()): ?>
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            Batas waktu pendaftaran untuk kegiatan ini telah berakhir.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form action="view_activity.php?id=<?php echo $activity_id; ?>" method="POST" class="space-y-6">
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Nomor Telepon</label>
                                    <div class="mt-1">
                                        <input type="tel" id="phone" name="phone" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Nomor telepon yang dapat dihubungi...">
                                    </div>
                                </div>

                                <div>
                                    <label for="experience" class="block text-sm font-medium text-gray-700">Pengalaman Volunteer (opsional)</label>
                                    <div class="mt-1">
                                        <textarea id="experience" name="experience" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Ceritakan pengalaman volunteer Anda sebelumnya jika ada..."></textarea>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="message" class="block text-sm font-medium text-gray-700">Mengapa Anda Ingin Bergabung?</label>
                                    <div class="mt-1">
                                        <textarea id="message" name="message" rows="4" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Ceritakan motivasi Anda mendaftar sebagai volunteer..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="agreement" name="agreement" type="checkbox" required class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="agreement" class="font-medium text-gray-700">Saya menyatakan bahwa semua informasi yang saya berikan adalah benar dan saya siap berpartisipasi dalam kegiatan ini.</label>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" name="apply" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-300">
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Pendaftaran
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
