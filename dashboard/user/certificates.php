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

// Get unread notifications count - with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Check if certificate columns exist
$columnsExist = true;
try {
    $result = $conn->query("SHOW COLUMNS FROM applications LIKE 'certificate_generated'");
    $columnsExist = ($result->num_rows > 0);
} catch (Exception $e) {
    $columnsExist = false;
}

// Get completed volunteer activities (approved applications + past events)
$certificates_query = "SELECT a.*, va.title, va.category, va.event_date, va.description, o.name as organizer_name, o.organization_name
                       FROM applications a 
                       JOIN volunteer_activities va ON a.activity_id = va.id 
                       JOIN owners o ON va.owner_id = o.owner_id
                       WHERE a.user_id = $user_id 
                       AND a.status = 'approved'
                       AND va.event_date < CURDATE()
                       ORDER BY va.event_date DESC";
                       
$certificates = $conn->query($certificates_query);
$has_certificates = ($certificates && $certificates->num_rows > 0);

// Generate certificate download
if($columnsExist && isset($_GET['generate']) && isset($_GET['id'])) {
    $application_id = intval($_GET['id']);
    
    // Verify this application belongs to current user
    $stmt = $conn->prepare("SELECT a.*, va.title, va.event_date, o.organization_name 
                           FROM applications a 
                           JOIN volunteer_activities va ON a.activity_id = va.id 
                           JOIN owners o ON va.owner_id = o.owner_id
                           WHERE a.id = ? AND a.user_id = ? AND a.status = 'approved'");
    $stmt->bind_param("ii", $application_id, $user_id);
    $stmt->execute();
    $cert_result = $stmt->get_result();
    
    if($cert_result->num_rows > 0) {
        $cert_data = $cert_result->fetch_assoc();
        
        // Record certificate generation
        $stmt = $conn->prepare("UPDATE applications SET certificate_generated = 1, certificate_date = CURDATE() WHERE id = ?");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        
        $certificate_url = "generate_certificate.php?id=" . $application_id;
        header("Location: $certificate_url");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat & Piagam Volunteer - VolunteerHub</title>
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
                            <a href="certificates.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700">
                                <i class="fas fa-certificate mr-1"></i> Piagam
                            </a>
                            <a href="notifications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium relative">
                                <i class="fas fa-bell mr-1"></i> Notifikasi
                                <?php if($unread_count > 0): ?>
                                <span class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                    <?php echo $unread_count; ?>
                                </span>
                                <?php endif; ?>
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
            <?php if (!$columnsExist): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">Database Update Required</p>
                <p>The certificate feature requires a database update. Please run the setup script: <code>setup/update_applications_table.php</code></p>
                <p class="mt-3">
                    <a href="../../setup/update_applications_table.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded inline-block mt-2">Run Update Script</a>
                </p>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="bg-gradient-to-r from-purple-700 to-indigo-600 rounded-lg shadow-lg px-6 py-8 mb-8 text-white">
                <div class="md:flex md:items-center md:justify-between">
                    <div class="md:flex-1">
                        <h1 class="text-3xl font-bold mb-2">Piagam & Sertifikat Volunteer</h1>
                        <p class="text-purple-100">Unduh dan bagikan bukti partisipasi Anda dalam kegiatan volunteer</p>
                    </div>
                    <div class="mt-4 md:mt-0 md:ml-6">
                        <i class="fas fa-certificate text-5xl text-yellow-300 opacity-80"></i>
                    </div>
                </div>
            </div>
            
            <!-- Certificates List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Daftar Piagam Tersedia</h2>
                    
                    <?php if($has_certificates): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Kegiatan Volunteer
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Penyelenggara
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tanggal
                                        </th>
                                        <?php if($columnsExist): ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <?php endif; ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while($cert = $certificates->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['title']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($cert['category']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($cert['organization_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($cert['organizer_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900"><?php echo date('d M Y', strtotime($cert['event_date'])); ?></div>
                                            </td>
                                            <?php if($columnsExist): ?>
                                            <td class="px-6 py-4">
                                                <?php if(!empty($cert['certificate_generated'])): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Diterbitkan pada <?php echo date('d/m/Y', strtotime($cert['certificate_date'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Siap dibuat
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4 text-right text-sm font-medium">
                                                <a href="generate_certificate.php?id=<?php echo $cert['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                    <span class="inline-flex items-center px-3 py-1.5 border border-indigo-600 rounded-md">
                                                        <i class="fas fa-download mr-1"></i> Download
                                                    </span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <img src="https://illustrations.popsy.co/amber/student-with-certificate.svg" alt="No certificates" class="mx-auto h-48 w-auto mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-1">Belum ada piagam tersedia</h3>
                            <p class="text-gray-500 mb-6">Selesaikan kegiatan volunteer terlebih dahulu untuk mendapatkan piagam penghargaan.</p>
                            <a href="search.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                                <i class="fas fa-search mr-2"></i> Cari Kegiatan Volunteer
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
