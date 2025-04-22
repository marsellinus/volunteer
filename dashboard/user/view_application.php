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

// Get application ID from URL parameter
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($application_id <= 0) {
    header("Location: my_applications.php");
    exit;
}

// Get application details with activity and owner info
$stmt = $conn->prepare("
    SELECT a.*, va.title, va.description, va.location, va.event_date, va.category,
           o.name as organization_name, o.organization_description, o.website
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

// Define status variables with default values
$status_color = 'gray';
$status_icon = 'clock';
$status_text = 'Menunggu';

// Set appropriate values based on application status
if (isset($application['status'])) {
    if ($application['status'] === 'approved') {
        $status_color = 'green';
        $status_icon = 'check-circle';
        $status_text = 'Diterima';
    } elseif ($application['status'] === 'rejected') {
        $status_color = 'red';
        $status_icon = 'times-circle';
        $status_text = 'Ditolak';
    } elseif ($application['status'] === 'pending') {
        $status_color = 'yellow';
        $status_icon = 'clock';
        $status_text = 'Menunggu';
    }
}

// Check if event has passed (for certificate eligibility)
$event_passed = strtotime($application['event_date']) < time();
$can_get_certificate = $event_passed && $application['status'] === 'approved';

$page_title = 'Detail Pendaftaran - VolunteerHub';
include '../../includes/header_user.php';
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="my_applications.php" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke daftar lamaran
        </a>
    </div>

    <!-- Application Details Card -->
    <div class="bg-white shadow-lg rounded-xl overflow-hidden mb-6">
        <!-- Header with status -->
        <div class="bg-gradient-to-r from-indigo-700 to-purple-700 px-6 py-4 text-white">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold">Detail Pendaftaran Volunteer</h2>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white text-<?php echo $status_color; ?>-700">
                    <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i> <?php echo $status_text; ?>
                </span>
            </div>
        </div>

        <!-- Activity Details -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-start">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($application['title']); ?></h3>
                    <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:space-x-6">
                        <div class="mt-2 flex items-center text-sm text-gray-500">
                            <i class="fas fa-map-marker-alt mr-1.5 text-gray-400"></i>
                            <?php echo htmlspecialchars($application['location']); ?>
                        </div>
                        <div class="mt-2 flex items-center text-sm text-gray-500">
                            <i class="far fa-calendar-alt mr-1.5 text-gray-400"></i>
                            <?php echo date('d M Y', strtotime($application['event_date'])); ?>
                        </div>
                        <div class="mt-2 flex items-center text-sm text-gray-500">
                            <i class="fas fa-user-tie mr-1.5 text-gray-400"></i>
                            <?php echo htmlspecialchars($application['organization_name']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Details -->
        <div class="px-6 py-4">
            <div class="mb-6">
                <h4 class="text-md font-medium text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-paper-plane mr-2 text-indigo-500"></i>
                    Informasi Pendaftaran
                </h4>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">
                        <span class="font-medium">Didaftarkan pada:</span> <?php echo date('d M Y, H:i', strtotime($application['applied_at'])); ?>
                    </p>
                    <div class="mt-3">
                        <p class="text-sm font-medium text-gray-500">Pesan Pendaftaran:</p>
                        <div class="mt-1 whitespace-pre-line text-sm text-gray-700 bg-white p-3 border border-gray-200 rounded-md">
                            <?php echo nl2br(htmlspecialchars($application['message'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Details -->
            <div class="mb-6">
                <h4 class="text-md font-medium text-gray-700 mb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2 text-indigo-500"></i>
                    Status Pendaftaran
                </h4>
                <div class="bg-<?php echo $status_color; ?>-50 rounded-lg p-4 border-l-4 border-<?php echo $status_color; ?>-500">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-<?php echo $status_icon; ?> text-<?php echo $status_color; ?>-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-<?php echo $status_color; ?>-800">
                                <?php echo $status_text; ?>
                            </p>
                            <div class="mt-1 text-sm text-<?php echo $status_color; ?>-700">
                                <?php if($application['status'] === 'pending'): ?>
                                    Pendaftaran Anda sedang dalam proses review oleh penyelenggara.
                                <?php elseif($application['status'] === 'approved'): ?>
                                    Selamat! Anda telah diterima untuk berpartisipasi dalam kegiatan ini.
                                <?php elseif($application['status'] === 'rejected'): ?>
                                    Maaf, pendaftaran Anda tidak dapat diterima untuk kegiatan ini.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <?php if($application['status'] === 'approved'): ?>
                <div class="mt-6 flex flex-col sm:flex-row sm:justify-between items-center border-t border-gray-200 pt-6">
                    <p class="text-sm text-gray-500">
                        Apabila Anda tidak dapat mengikuti kegiatan ini, mohon hubungi penyelenggara.
                    </p>
                    
                    <?php if($can_get_certificate): ?>
                    <a href="generate_certificate.php?id=<?php echo $application_id; ?>" class="mt-3 sm:mt-0 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150">
                        <i class="fas fa-certificate mr-2"></i> Download Piagam Partisipasi
                    </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>
