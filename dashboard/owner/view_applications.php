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

// Verify that this activity belongs to the owner
$stmt = $conn->prepare("SELECT * FROM volunteer_activities WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $activity_id, $owner_id);
$stmt->execute();
$activity_result = $stmt->get_result();

if($activity_result->num_rows === 0) {
    header("Location: manage_activities.php");
    exit;
}

$activity = $activity_result->fetch_assoc();

// Handle application status updates
$message = '';
$error = '';

if(isset($_POST['update_status']) && isset($_POST['application_id']) && isset($_POST['status'])) {
    $application_id = intval($_POST['application_id']);
    $status = $_POST['status'];
    
    if(!in_array($status, ['pending', 'approved', 'rejected'])) {
        $error = "Status tidak valid";
    } else {
        // Verify that this application belongs to the activity
        $stmt = $conn->prepare("SELECT a.*, u.user_id, va.title FROM applications a 
                              JOIN volunteer_activities va ON a.activity_id = va.id 
                              JOIN users u ON a.user_id = u.user_id
                              WHERE a.id = ? AND va.id = ?");
        $stmt->bind_param("ii", $application_id, $activity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 0) {
            $error = "Aplikasi tidak ditemukan";
        } else {
            $app_data = $result->fetch_assoc();
            $old_status = $app_data['status'];
            
            // Update the application status
            $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $application_id);
            
            if($stmt->execute()) {
                $status_text = $status == 'approved' ? 'diterima' : ($status == 'rejected' ? 'ditolak' : 'pending');
                $message = "Status aplikasi berhasil diubah menjadi $status_text";
                
                // Only create notification if status actually changed
                if($old_status != $status) {
                    // Create notification for user
                    $notification_title = '';
                    $notification_message = '';
                    $notification_type = 'info';
                    
                    if($status == 'approved') {
                        $notification_title = "Pendaftaran Diterima";
                        $notification_message = "Selamat! Pendaftaran Anda untuk kegiatan \"" . $app_data['title'] . "\" telah diterima.";
                        $notification_type = "success";
                    } else if($status == 'rejected') {
                        $notification_title = "Pendaftaran Ditolak";
                        $notification_message = "Maaf, pendaftaran Anda untuk kegiatan \"" . $app_data['title'] . "\" tidak diterima.";
                        $notification_type = "danger";
                    } else {
                        $notification_title = "Status Pendaftaran Berubah";
                        $notification_message = "Status pendaftaran Anda untuk kegiatan \"" . $app_data['title'] . "\" telah diubah menjadi menunggu.";
                    }
                    
                    createUserNotification(
                        $app_data['user_id'],
                        $notification_title,
                        $notification_message,
                        $notification_type,
                        "view_application.php?id=" . $application_id,
                        $conn
                    );
                }
            } else {
                $error = "Gagal mengubah status aplikasi";
            }
        }
    }
}

// Filter mechanism
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_clause = '';

switch($filter) {
    case 'pending':
        $filter_clause = "AND a.status = 'pending'";
        break;
    case 'approved':
        $filter_clause = "AND a.status = 'approved'";
        break;
    case 'rejected':
        $filter_clause = "AND a.status = 'rejected'";
        break;
}

// Get all applications for this activity with user details
$applications_query = "
    SELECT a.*, u.name as user_name, u.email as user_email, u.skills
    FROM applications a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.activity_id = $activity_id
    $filter_clause
    ORDER BY a.applied_at DESC
";

$applications = $conn->query($applications_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftar Kegiatan - VolunteerHub</title>
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
                <a href="manage_activities.php" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke daftar kegiatan
                </a>
            </div>

            <!-- Activity Summary -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium">Pendaftar Kegiatan</h3>
                            <p class="mt-1 max-w-2xl text-sm text-indigo-100">
                                <?php echo htmlspecialchars($activity['title']); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-indigo-100">
                                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('d F Y', strtotime($activity['event_date'])); ?>
                            </p>
                            <p class="text-xs text-indigo-200 mt-1">
                                <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($activity['location']); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-4 py-4 sm:px-6 bg-gray-50 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                        <div class="flex space-x-2 mb-4 sm:mb-0">
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                                <i class="fas fa-users mr-1"></i>
                                <?php
                                    $count_query = $conn->query("SELECT COUNT(*) as total FROM applications WHERE activity_id = $activity_id");
                                    $count_result = $count_query->fetch_assoc();
                                    echo $count_result['total'] . ' Pendaftar';
                                ?>
                            </span>
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-1"></i>
                                <?php
                                    $approved_query = $conn->query("SELECT COUNT(*) as total FROM applications WHERE activity_id = $activity_id AND status = 'approved'");
                                    $approved_result = $approved_query->fetch_assoc();
                                    echo $approved_result['total'] . ' Diterima';
                                ?>
                            </span>
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-clock mr-1"></i>
                                <?php
                                    $pending_query = $conn->query("SELECT COUNT(*) as total FROM applications WHERE activity_id = $activity_id AND status = 'pending'");
                                    $pending_result = $pending_query->fetch_assoc();
                                    echo $pending_result['total'] . ' Menunggu';
                                ?>
                            </span>
                        </div>
                        
                        <!-- Generate Certificate Button if event has passed -->
                        <?php if(strtotime($activity['event_date']) < time() && $approved_result['total'] > 0): ?>
                        <div>
                            <a href="generate_certificates.php?activity_id=<?php echo $activity_id; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i class="fas fa-certificate mr-2"></i> Generate Piagam
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">
                                <?php echo $message; ?>
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

            <!-- Filter Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex" aria-label="Tabs">
                    <a href="view_applications.php?activity_id=<?php echo $activity_id; ?>&filter=all" class="<?php echo $filter == 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                        Semua Pendaftar
                    </a>
                    <a href="view_applications.php?activity_id=<?php echo $activity_id; ?>&filter=pending" class="<?php echo $filter == 'pending' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                        Menunggu Review
                    </a>
                    <a href="view_applications.php?activity_id=<?php echo $activity_id; ?>&filter=approved" class="<?php echo $filter == 'approved' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm mr-8">
                        Diterima
                    </a>
                    <a href="view_applications.php?activity_id=<?php echo $activity_id; ?>&filter=rejected" class="<?php echo $filter == 'rejected' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Ditolak
                    </a>
                </nav>
            </div>

            <!-- Applications List -->
            <?php if($applications && $applications->num_rows > 0): ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                    <ul class="divide-y divide-gray-200">
                        <?php while($application = $applications->fetch_assoc()): 
                            // Determine status color and icon
                            $status_color = 'gray';
                            $status_text = 'Menunggu';
                            $status_icon = 'clock';
                            
                            if($application['status'] === 'approved') {
                                $status_color = 'green';
                                $status_text = 'Diterima';
                                $status_icon = 'check-circle';
                            } elseif($application['status'] === 'rejected') {
                                $status_color = 'red';
                                $status_text = 'Ditolak';
                                $status_icon = 'times-circle';
                            } elseif($application['status'] === 'pending') {
                                $status_color = 'yellow';
                                $status_text = 'Menunggu';
                                $status_icon = 'clock';
                            }
                        ?>
                        <li>
                            <div class="px-4 py-4 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span class="font-medium text-gray-600"><?php echo strtoupper(substr($application['user_name'], 0, 2)); ?></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-indigo-600"><?php echo htmlspecialchars($application['user_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($application['user_email']); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800">
                                            <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i> <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <div class="sm:flex sm:justify-between">
                                        <!-- Skills -->
                                        <?php if(!empty($application['skills'])): ?>
                                        <div class="mb-2 sm:mb-0">
                                            <p class="text-xs text-gray-500 mb-1">Keterampilan:</p>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach(explode(',', $application['skills']) as $skill): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?php echo htmlspecialchars(trim($skill)); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Application date -->
                                        <div class="text-sm text-gray-500">
                                            <p>Mendaftar pada: <?php echo date('d M Y H:i', strtotime($application['applied_at'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Application message -->
                                    <div class="mt-3 text-sm text-gray-700">
                                        <button type="button" onclick="toggleMessage('message-<?php echo $application['id']; ?>')" class="text-indigo-600 hover:text-indigo-900 focus:outline-none flex items-center">
                                            <i class="fas fa-envelope mr-1"></i> Lihat pesan
                                            <svg class="h-4 w-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div id="message-<?php echo $application['id']; ?>" class="hidden mt-2 p-3 bg-gray-50 rounded-md">
                                            <p class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($application['message'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action buttons -->
                                <div class="mt-4 flex flex-col sm:flex-row sm:justify-end">
                                    <form method="POST" action="view_applications.php?activity_id=<?php echo $activity_id; ?>" class="mt-2 sm:mt-0 sm:ml-3 flex">
                                        <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        
                                        <select name="status" class="mr-2 block w-full sm:w-auto pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                            <option value="pending" <?php echo $application['status'] === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                            <option value="approved" <?php echo $application['status'] === 'approved' ? 'selected' : ''; ?>>Terima</option>
                                            <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Tolak</option>
                                        </select>
                                        
                                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Perbarui
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Tidak ada pendaftar</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php if($filter != 'all'): ?>
                                Tidak ada pendaftar dengan status tersebut.
                            <?php else: ?>
                                Belum ada yang mendaftar untuk kegiatan ini.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function toggleMessage(messageId) {
            const messageElement = document.getElementById(messageId);
            if (messageElement.classList.contains('hidden')) {
                messageElement.classList.remove('hidden');
            } else {
                messageElement.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
