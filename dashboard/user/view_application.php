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

// Get application ID from URL parameter
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($application_id <= 0) {
    header("Location: my_applications.php");
    exit;
}

// Get application details with activity and owner info
$stmt = $conn->prepare("
    SELECT a.*, va.*, o.name as owner_name, o.organization_name, o.email as owner_email, o.website 
    FROM applications a 
    JOIN volunteer_activities va ON a.activity_id = va.id 
    JOIN owners o ON va.owner_id = o.owner_id
    WHERE a.id = ? AND a.user_id = ?
");
$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: my_applications.php");
    exit;
}

$application = $result->fetch_assoc();

// Translate application status
$status_text = 'Pending Review';
$status_class = 'yellow';
$status_icon = 'clock';

if($application['status'] === 'approved') {
    $status_text = 'Diterima';
    $status_class = 'green';
    $status_icon = 'check-circle';
} elseif($application['status'] === 'rejected') {
    $status_text = 'Ditolak';
    $status_class = 'red';
    $status_icon = 'times-circle';
} elseif($application['status'] === 'pending') {
    $status_text = 'Menunggu';
    $status_class = 'yellow';
    $status_icon = 'clock';
}

// Check if event has passed (for certificate eligibility)
$event_passed = strtotime($application['event_date']) < time();
$can_get_certificate = $event_passed && $application['status'] === 'approved';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pendaftaran - VolunteerHub</title>
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
                            <a href="my_applications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700">
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
                <a href="my_applications.php" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke daftar pendaftaran
                </a>
            </div>

            <!-- Application Status Banner -->
            <div class="bg-<?php echo $status_class; ?>-50 border border-<?php echo $status_class; ?>-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-<?php echo $status_icon; ?> text-<?php echo $status_class; ?>-400 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-<?php echo $status_class; ?>-800">
                            Status Pendaftaran: <?php echo $status_text; ?>
                        </h3>
                        <div class="mt-2 text-sm text-<?php echo $status_class; ?>-700">
                            <?php if($application['status'] === 'pending'): ?>
                                <p>Pendaftaran Anda sedang ditinjau oleh panitia. Kami akan memberi tahu Anda jika ada pembaruan.</p>
                            <?php elseif($application['status'] === 'approved'): ?>
                                <p>Selamat! Anda telah diterima untuk kegiatan volunteer ini. Panitia akan menghubungi Anda dengan detail lebih lanjut melalui email.</p>
                            <?php elseif($application['status'] === 'rejected'): ?>
                                <p>Maaf, pendaftaran Anda tidak terpilih untuk kesempatan ini. Jangan menyerah dan terus mencari kesempatan volunteer lainnya yang sesuai dengan minat Anda.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-purple-700 text-white">
                    <h3 class="text-lg leading-6 font-medium">Detail Kegiatan Volunteer</h3>
                    <p class="mt-1 max-w-2xl text-sm text-indigo-100">Informasi tentang kegiatan yang Anda daftar</p>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Judul Kegiatan</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($application['title']); ?></dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Penyelenggara</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo htmlspecialchars($application['organization_name']); ?> - <?php echo htmlspecialchars($application['owner_name']); ?>
                                <?php if(!empty($application['website'])): ?>
                                    <div class="mt-1">
                                        <a href="<?php echo htmlspecialchars($application['website']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-500">
                                            <i class="fas fa-external-link-alt mr-1"></i> Website
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Lokasi</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                                    <?php echo htmlspecialchars($application['location']); ?>
                                </div>
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Tanggal Kegiatan</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo date('d F Y', strtotime($application['event_date'])); ?>
                                <?php 
                                if($event_passed) {
                                    echo '<span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Kegiatan telah selesai</span>';
                                } else {
                                    $days_left = ceil((strtotime($application['event_date']) - time()) / (60 * 60 * 24));
                                    echo '<span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">' . $days_left . ' hari lagi</span>';
                                }
                                ?>
                            </dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Kategori</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($application['category']); ?></dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Deskripsi Kegiatan</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="prose prose-sm max-w-none">
                                    <?php echo nl2br(htmlspecialchars($application['description'])); ?>
                                </div>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Detail Pendaftaran Anda</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Informasi yang Anda berikan saat mendaftar</p>
                </div>
                <div class="border-t border-gray-200">
                    <dl>
                        <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Tanggal Pendaftaran</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <?php echo date('d F Y H:i', strtotime($application['applied_at'])); ?>
                            </dd>
                        </div>
                        <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Pesan Anda</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                <div class="prose prose-sm max-w-none">
                                    <?php echo nl2br(htmlspecialchars($application['message'])); ?>
                                </div>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <?php if($can_get_certificate): ?>
            <div class="mt-6 flex justify-center">
                <a href="certificates.php?generate=1&id=<?php echo $application_id; ?>" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-certificate mr-2"></i> Download Piagam Partisipasi
                </a>
            </div>
            <?php endif; ?>

            <?php if($application['status'] === 'pending'): ?>
            <div class="mt-6 flex justify-end">
                <a href="cancel_application.php?id=<?php echo $application_id; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" onclick="return confirm('Apakah Anda yakin ingin membatalkan pendaftaran ini?')">
                    <i class="fas fa-times-circle mr-2"></i> Batalkan Pendaftaran
                </a>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
