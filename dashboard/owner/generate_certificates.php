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

// Get activity ID from URL parameter
$activity_id = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;

if($activity_id <= 0) {
    header("Location: manage_activities.php");
    exit;
}

// Verify the activity belongs to this owner
$stmt = $conn->prepare("SELECT * FROM volunteer_activities WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $activity_id, $owner_id);
$stmt->execute();
$activity_result = $stmt->get_result();

if($activity_result->num_rows === 0) {
    header("Location: manage_activities.php");
    exit;
}

$activity = $activity_result->fetch_assoc();

// Check if event has passed
$event_passed = strtotime($activity['event_date']) < time();
if(!$event_passed) {
    $error = "Piagam hanya dapat diterbitkan setelah kegiatan selesai.";
}

// Check if certificate columns exist
$columnsExist = true;
try {
    $result = $conn->query("SHOW COLUMNS FROM applications LIKE 'certificate_generated'");
    $columnsExist = ($result->num_rows > 0);
} catch (Exception $e) {
    $columnsExist = false;
}

// Process bulk certificate generation
$success = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificates'])) {
    $application_ids = isset($_POST['application_ids']) ? $_POST['application_ids'] : [];
    
    if(empty($application_ids)) {
        $error = "Tidak ada pendaftar yang dipilih.";
    } else {
        $generated_count = 0;
        
        foreach($application_ids as $app_id) {
            // Get volunteer and activity info for notification
            $stmt = $conn->prepare("SELECT a.id, u.user_id, va.title, va.id as activity_id 
                                  FROM applications a 
                                  JOIN volunteer_activities va ON a.activity_id = va.id 
                                  JOIN users u ON a.user_id = u.user_id
                                  WHERE a.id = ? AND a.status = 'approved'");
            $stmt->bind_param("i", $app_id);
            $stmt->execute();
            $cert_result = $stmt->get_result();
            
            if($cert_result->num_rows > 0) {
                $cert_data = $cert_result->fetch_assoc();
                
                // Update certificate status
                $stmt = $conn->prepare("UPDATE applications SET certificate_generated = 1, certificate_date = CURDATE() WHERE id = ?");
                $stmt->bind_param("i", $app_id);
                
                if($stmt->execute() && $stmt->affected_rows > 0) {
                    $generated_count++;
                    
                    // Send notification to the volunteer
                    createUserNotification(
                        $cert_data['user_id'],
                        "Piagam Tersedia",
                        "Piagam penghargaan untuk kegiatan \"" . $cert_data['title'] . "\" telah tersedia. Silakan unduh piagam Anda.",
                        "success",
                        "generate_certificate.php?id=" . $cert_data['id'],
                        $conn
                    );
                }
            }
        }
        
        if($generated_count > 0) {
            $success = "Berhasil menerbitkan $generated_count piagam untuk volunteer.";
        } else {
            $error = "Tidak ada piagam yang diterbitkan. Pastikan pendaftar telah disetujui.";
        }
    }
}

// Get approved applications for this activity
$applications_query = "
    SELECT a.*, u.name as user_name, u.email as user_email
    FROM applications a 
    JOIN users u ON a.user_id = u.user_id
    WHERE a.activity_id = $activity_id AND a.status = 'approved'
    ORDER BY a.applied_at DESC
";

$applications = $conn->query($applications_query);
$has_applications = ($applications && $applications->num_rows > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Piagam - VolunteerHub</title>
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
            <div class="mb-6">
                <a href="view_applications.php?activity_id=<?php echo $activity_id; ?>" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke daftar pendaftar
                </a>
            </div>

            <?php if (!$columnsExist): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">Database Update Required</p>
                <p>The certificate feature requires a database update. Please run the setup script: <code>setup/update_applications_table.php</code></p>
                <p class="mt-3">
                    <a href="../../setup/update_applications_table.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded inline-block mt-2">Run Update Script</a>
                </p>
            </div>
            <?php endif; ?>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-purple-700 text-white">
                    <h3 class="text-lg leading-6 font-medium">Terbitkan Piagam Volunteer</h3>
                    <p class="mt-1 max-w-2xl text-sm text-indigo-100">
                        <?php echo htmlspecialchars($activity['title']); ?> - <?php echo date('d F Y', strtotime($activity['event_date'])); ?>
                    </p>
                </div>
                <div class="px-4 py-5 sm:px-6">
                    <div class="text-sm text-gray-600 mb-4">
                        <p>Dengan menerbitkan piagam, peserta yang telah disetujui akan dapat mengunduh piagam mereka dari halaman sertifikat.</p>
                    </div>
                </div>
            </div>

            <?php if($success): ?>
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
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

            <?php if($event_passed): ?>
                <?php if($has_applications): ?>
                    <form action="generate_certificates.php?activity_id=<?php echo $activity_id; ?>" method="POST">
                        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                            <div class="px-4 py-5 sm:px-6 flex justify-between items-center bg-gray-50">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Daftar Volunteer Yang Disetujui</h3>
                                <div>
                                    <button type="button" onclick="selectAll()" class="text-sm text-indigo-600 hover:text-indigo-900">Pilih Semua</button>
                                    <span class="mx-2 text-gray-300">|</span>
                                    <button type="button" onclick="deselectAll()" class="text-sm text-indigo-600 hover:text-indigo-900">Batalkan Semua</button>
                                </div>
                            </div>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            <input type="checkbox" id="select-all" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Nama
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Email
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status Piagam
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while($application = $applications->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="application_ids[]" value="<?php echo $application['id']; ?>" class="app-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded" <?php echo $application['certificate_generated'] ? 'disabled' : ''; ?>>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($application['user_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($application['user_email']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if($application['certificate_generated']): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Diterbitkan pada <?php echo date('d/m/Y', strtotime($application['certificate_date'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Belum diterbitkan
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <div class="px-4 py-4 sm:px-6 bg-gray-50">
                                <button type="submit" name="generate_certificates" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i class="fas fa-certificate mr-2"></i> Terbitkan Piagam
                                </button>
                                <p class="mt-2 text-xs text-gray-500">Piagam akan tersedia untuk diunduh oleh volunteer setelah diterbitkan.</p>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-4 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada volunteer yang disetujui</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Anda perlu menyetujui pendaftar terlebih dahulu sebelum menerbitkan piagam.
                            </p>
                            <div class="mt-6">
                                <a href="view_applications.php?activity_id=<?php echo $activity_id; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i class="fas fa-users mr-2"></i> Kelola Pendaftar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Kegiatan belum selesai</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Piagam hanya dapat diterbitkan setelah tanggal kegiatan berakhir.
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            Tanggal kegiatan: <?php echo date('d F Y', strtotime($activity['event_date'])); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Handle select all functionality
        const selectAllCheckbox = document.getElementById('select-all');
        const appCheckboxes = document.querySelectorAll('.app-checkbox:not([disabled])');
        
        function selectAll() {
            appCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            selectAllCheckbox.checked = true;
        }
        
        function deselectAll() {
            appCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAllCheckbox.checked = false;
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    selectAll();
                } else {
                    deselectAll();
                }
            });
        }
    </script>
</body>
</html>
