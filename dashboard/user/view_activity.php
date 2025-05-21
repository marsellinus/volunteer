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

// Get unread notifications count with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

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

// Define status variables with default values
$status_color = 'gray';
$status_icon = 'clock';
$status_text = 'Menunggu';

// Set appropriate values based on application status
if ($has_applied && isset($application['status'])) {
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

// Add similar activities recommendation
$similar_activities_query = "
    SELECT va.*, o.name as organization_name
    FROM volunteer_activities va
    JOIN owners o ON va.owner_id = o.owner_id
    WHERE va.id != $activity_id
    AND va.application_deadline >= CURDATE() 
    AND (
        va.category = '" . $conn->real_escape_string($activity['category']) . "'
        OR va.location = '" . $conn->real_escape_string($activity['location']) . "'
        OR va.owner_id = " . intval($activity['owner_id']) . "
    )
    ORDER BY 
        CASE 
            WHEN va.category = '" . $conn->real_escape_string($activity['category']) . "' AND va.location = '" . $conn->real_escape_string($activity['location']) . "' THEN 1
            WHEN va.category = '" . $conn->real_escape_string($activity['category']) . "' THEN 2
            WHEN va.location = '" . $conn->real_escape_string($activity['location']) . "' THEN 3
            ELSE 4
        END,
        va.event_date ASC
    LIMIT 3
";

$similar_activities = $conn->query($similar_activities_query);

// Add random colors for category tags
$category_colors = [
    'Education' => 'blue',
    'Environment' => 'green',
    'Health' => 'red',
    'Community Service' => 'purple',
    'Animal Welfare' => 'yellow',
    'Arts & Culture' => 'pink',
    'Disaster Relief' => 'orange',
    'Human Rights' => 'indigo',
    'Sports' => 'teal',
    'Technology' => 'cyan'
];

// Get default color for categories not in the list
$color = isset($category_colors[$activity['category']]) ? $category_colors[$activity['category']] : 'gray';

$page_title = htmlspecialchars($activity['title']) . ' - VolunteerHub';
$extra_head = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const applyButton = document.getElementById("applyButton");
        const applicationForm = document.getElementById("applicationForm");
        
        if (applyButton && applicationForm) {
            applyButton.addEventListener("click", function() {
                applicationForm.classList.remove("hidden");
                applicationForm.scrollIntoView({ behavior: "smooth" });
            });
        }
    });
</script>
';

include '../../includes/header_user.php';
?>

<!-- Main Content with Modern Design -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 mt-20 pt-10">
    <!-- Back Button with Hover Effect -->
    <div class="mb-6">
        <a href="search.php" class="group inline-flex items-center text-sm text-[#10283f] hover:text-[#10283f]/80 transition-all duration-300">
            <span class="inline-block transform group-hover:-translate-x-1 transition-transform duration-300 ease-in-out">
                <i class="fas fa-arrow-left mr-2"></i>
            </span>
            <span>Kembali ke pencarian</span>
        </a>
    </div>

    <!-- Enhanced Activity Hero Banner with Parallax Effect -->
    <div class="rounded-2xl shadow-2xl text-white mb-8 overflow-hidden relative">
        <?php
        if (!empty($activity['images'])) {
            $imagePath = "../../" . $activity['images'];
        } else {
            $imagePath = "../../uploads/activities/default.jpg";
        }
        ?>
        <!-- Image Background with Overlay and Parallax Effect -->
        <div class="absolute inset-0 parallax-bg">
            <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($activity['title']); ?>" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-r from-[#10283f]/100 to-[#10283f]/90"></div>
        </div>

        <div class="px-8 py-10 md:px-12 md:py-16 relative z-10">
            <div class="flex flex-col md:flex-row justify-between">
                <div class="md:max-w-3xl">
                    <div class="flex flex-wrap items-center gap-3 mb-5">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-white text-[#10283f] shadow-md transform hover:scale-105 transition-transform duration-300">
                            <?php echo htmlspecialchars($activity['category']); ?>
                        </span>
                        <?php if(strtotime($activity['application_deadline']) >= time()): ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-green-100 text-green-800 shadow-sm">
                                <i class="far fa-clock mr-1"></i> Pendaftaran Terbuka
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-red-100 text-red-800 shadow-sm">
                                <i class="fas fa-times-circle mr-1"></i> Pendaftaran Ditutup
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4 tracking-tight"><?php echo htmlspecialchars($activity['title']); ?></h1>
                    
                    <div class="flex flex-col sm:flex-row sm:items-center sm:flex-wrap gap-4 mb-8">
                        <div class="flex items-center px-3 py-2 bg-white/10 backdrop-blur-sm rounded-lg shadow-inner">
                            <i class="fas fa-map-marker-alt mr-2 text-white/80"></i>
                            <span><?php echo htmlspecialchars($activity['location']); ?></span>
                        </div>
                        <div class="flex items-center px-3 py-2 bg-white/10 backdrop-blur-sm rounded-lg shadow-inner">
                            <i class="far fa-calendar-alt mr-2 text-white/80"></i>
                            <span><?php echo date('d F Y', strtotime($activity['event_date'])); ?></span>
                        </div>
                        <div class="flex items-center px-3 py-2 bg-white/10 backdrop-blur-sm rounded-lg shadow-inner">
                            <i class="fas fa-hourglass-end mr-2 text-white/80"></i>
                            <span>Tutup: <?php echo date('d F Y', strtotime($activity['application_deadline'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="flex items-center mt-2">
                        <div class="flex-shrink-0 h-14 w-14 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-white border border-white/30 shadow-lg">
                            <i class="fas fa-building text-lg"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-white/80">Diselenggarakan oleh:</p>
                            <p class="text-xl font-semibold"><?php echo htmlspecialchars($activity['organization_name']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 md:mt-0 flex items-center justify-center md:justify-end">
                    <?php if(!$has_applied && strtotime($activity['application_deadline']) >= time()): ?>
                        <button id="applyButton" class="group inline-flex items-center px-8 py-4 border border-white/30 text-lg font-medium rounded-xl shadow-xl text-white bg-white/10 hover:bg-white/20 backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-white transition-all duration-300 cursor-pointer">
                            <i class="fas fa-paper-plane mr-3 group-hover:translate-x-1 transition-transform duration-300"></i> 
                            <span>Daftar Sekarang</span>
                        </button>
                    <?php elseif($has_applied): ?>
                        <div class="text-center">
                            <div class="inline-flex items-center px-6 py-4 border-2 border-white/40 backdrop-blur-sm rounded-xl text-lg shadow-xl">
                                <i class="fas fa-check-circle mr-3"></i> Anda Sudah Terdaftar
                            </div>
                            <div class="mt-3">
                            <?php if(isset($application['id'])): ?>
                            <a href="view_application.php?id=<?php echo $application['id']; ?>" class="inline-flex items-center text-white hover:text-white/80 transition-colors duration-300">
                                <i class="fas fa-eye mr-2"></i> Lihat Status
                                <i class="fas fa-chevron-right ml-1 text-xs"></i>
                            </a>
                            <?php else: ?>
                            <span class="inline-flex items-center text-white hover:text-white/80 transition-colors duration-300">
                                <i class="fas fa-check-circle mr-2"></i> Pendaftaran Terkirim
                            </span>
                            <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="inline-flex items-center px-6 py-4 border-2 border-white/40 rounded-xl text-lg backdrop-blur-sm shadow-xl">
                            <i class="fas fa-times-circle mr-3"></i> Pendaftaran Ditutup
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area with Card Hover Effects -->
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Left Column - Details -->
        <div class="w-full lg:w-2/3">
            <!-- Description with Enhanced Card Design -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden mb-8 border border-gray-100 hover:shadow-2xl transition-shadow duration-300">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center">
                    <div class="w-10 h-10 bg-[#10283f]/10 text-[#10283f] rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Deskripsi Kegiatan</h3>
                </div>
                <div class="p-6 lg:p-8">
                    <div class="prose prose-lg prose-[#10283f] max-w-none">
                        <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                    </div>
                </div>
            </div>
            
           <!-- Required Skills with Enhanced Card Design -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden mb-8 border border-gray-100 hover:shadow-2xl transition-shadow duration-300">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center">
                    <div class="w-10 h-10 bg-[#10283f]/10 text-[#10283f] rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Keterampilan yang Diperlukan</h3>
                </div>
                <div class="p-6 lg:p-8">
                    <div class="prose prose-lg prose-[#10283f] max-w-none">
                        <?php echo nl2br(htmlspecialchars($activity['required_skills'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Application Form and Info -->
        <div class="w-full lg:w-1/3">
            <!-- Application Status with Modern Design -->
            <?php if($has_applied): ?>
                <div class="bg-white shadow-xl rounded-2xl overflow-hidden mb-8 border-l-4 border-<?php echo $status_color; ?>-500 hover:shadow-2xl transition-shadow duration-300">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/80 flex items-center">
                        <div class="w-10 h-10 bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-<?php echo $status_icon; ?>"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">Status Pendaftaran</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-5">
                            <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800">
                                <i class="fas fa-<?php echo $status_icon; ?> mr-2"></i> <?php echo $status_text; ?>
                            </span>
                        </div>
                        <p class="text-gray-600 mb-5">
                            <?php if($application['status'] === 'pending'): ?>
                                Pendaftaran Anda sedang ditinjau. Kami akan memberi tahu Anda ketika ada pembaruan.
                            <?php elseif($application['status'] === 'approved'): ?>
                                Selamat! Anda telah diterima sebagai volunteer untuk kegiatan ini.
                            <?php elseif($application['status'] === 'rejected'): ?>
                                Maaf, pendaftaran Anda tidak dapat diterima untuk kesempatan ini.
                            <?php endif; ?>
                        </p>
                        <?php if(isset($application['id'])): ?>
                        <a href="view_application.php?id=<?php echo $application['id']; ?>" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-md text-white bg-[#10283f] hover:bg-[#10283f]/90 transition-colors duration-300">
                            <i class="fas fa-eye mr-2"></i> Lihat Detail Pendaftaran
                        </a>
                        <?php else: ?>
                        <button type="button" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-md text-white bg-[#10283f] hover:bg-[#10283f]/90 transition-colors duration-300">
                            <i class="fas fa-check-circle mr-2"></i> Pendaftaran Terkirim
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Modern Application Form -->
            <?php if(!$has_applied && strtotime($activity['application_deadline']) >= time()): ?>
                <div id="applicationForm" class="bg-white shadow-xl rounded-2xl overflow-hidden mb-8 <?php echo isset($_POST['apply']) ? '' : 'hidden'; ?> hover:shadow-2xl transition-shadow duration-300">
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/80 flex items-center">
                        <div class="w-10 h-10 bg-[#10283f]/10 text-[#10283f] rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900">Daftar Volunteer</h3>
                    </div>
                    <div class="p-6">
                        <?php if($success_message): ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-lg">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700">
                                            <?php echo $success_message; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if($error_message): ?>
                            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-lg">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-circle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700">
                                            <?php echo $error_message; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="view_activity.php?id=<?php echo $activity_id; ?>" method="POST">
                            <div class="space-y-6">
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-phone-alt text-gray-400"></i>
                                        </div>
                                        <input 
                                            type="text" 
                                            name="phone" 
                                            id="phone" 
                                            class="block w-full pl-10 rounded-lg border-gray-300 focus:ring-[#10283f] focus:border-[#10283f] sm:text-sm transition-shadow duration-300" 
                                            placeholder="Masukkan nomor telepon Anda" 
                                            required
                                        >
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="experience" class="block text-sm font-medium text-gray-700 mb-1">Pengalaman Volunteer</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 pt-3 pointer-events-none">
                                            <i class="fas fa-user-clock text-gray-400"></i>
                                        </div>
                                        <textarea 
                                            name="experience" 
                                            id="experience" 
                                            rows="3" 
                                            class="block w-full pl-10 rounded-lg border-gray-300 focus:ring-[#10283f] focus:border-[#10283f] sm:text-sm transition-shadow duration-300" 
                                            placeholder="Ceritakan pengalaman volunteer Anda (opsional)"
                                        ></textarea>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Pesan Tambahan</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 pt-3 pointer-events-none">
                                            <i class="fas fa-comment-alt text-gray-400"></i>
                                        </div>
                                        <textarea 
                                            name="message" 
                                            id="message" 
                                            rows="4" 
                                            class="block w-full pl-10 rounded-lg border-gray-300 focus:ring-[#10283f] focus:border-[#10283f] sm:text-sm transition-shadow duration-300" 
                                            placeholder="Tuliskan mengapa Anda tertarik dengan kegiatan ini"
                                        ></textarea>
                                    </div>
                                </div>
                                
                                <div class="pt-3">
                                    <button 
                                        type="submit" 
                                        name="apply" 
                                        class="w-full inline-flex justify-center items-center px-5 py-3 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-[#10283f] hover:bg-[#10283f]/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f] transition-all duration-300 transform hover:-translate-y-0.5"
                                    >
                                        <i class="fas fa-paper-plane mr-2"></i> Kirim Pendaftaran
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Organization Info with Modern Design -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden mb-8 border border-gray-100 hover:shadow-2xl transition-shadow duration-300">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center">
                    <div class="w-10 h-10 bg-[#10283f]/10 text-[#10283f] rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900">Tentang Penyelenggara</h3>
                </div>
                <div class="p-6">
                    <h4 class="font-semibold text-gray-900 text-lg mb-3"><?php echo htmlspecialchars($activity['organization_name']); ?></h4>
                    
                    <?php if(!empty($activity['organization_description'])): ?>
                        <p class="text-gray-600 mb-5"><?php echo htmlspecialchars($activity['organization_description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if(!empty($activity['website'])): ?>
                        <a href="<?php echo htmlspecialchars($activity['website']); ?>" target="_blank" class="inline-flex items-center px-4 py-2 rounded-lg text-[#10283f] border border-[#10283f]/20 hover:bg-[#10283f]/5 transition-colors duration-300">
                            <i class="fas fa-globe mr-2"></i> Kunjungi Website
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Similar Activities Section with Modern Cards -->
    <?php if($similar_activities && $similar_activities->num_rows > 0): ?>
    <div class="mt-16">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 flex items-center">
            <div class="w-10 h-10 bg-indigo-600 text-white rounded-full flex items-center justify-center mr-3 shadow-md">
                <i class="fas fa-th-list"></i>
            </div>
            Kegiatan Serupa
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php while($similar = $similar_activities->fetch_assoc()): 
                $similarColor = isset($category_colors[$similar['category']]) ? $category_colors[$similar['category']] : 'gray';
            ?>
                <div class="bg-white overflow-hidden shadow-xl hover:shadow-2xl transition-all duration-300 rounded-2xl flex flex-col transform hover:-translate-y-1">
                    <div class="h-36 bg-gradient-to-r from-<?php echo $similarColor; ?>-500 to-<?php echo $similarColor; ?>-600 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center text-white text-opacity-30 font-bold text-5xl">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <div class="absolute bottom-0 w-full p-4 bg-gradient-to-t from-black/80 to-transparent">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white text-gray-800 shadow-md">
                                <?php echo date('d M Y', strtotime($similar['event_date'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-5 flex-grow">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 hover:text-indigo-600 transition-colors duration-300"><?php echo htmlspecialchars($similar['title']); ?></h4>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-<?php echo $similarColor; ?>-100 text-<?php echo $similarColor; ?>-800 mt-2">
                                <?php echo htmlspecialchars($similar['category']); ?>
                            </span>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-gray-500">
                            <i class="fas fa-map-marker-alt text-<?php echo $similarColor; ?>-500 mr-2"></i>
                            <?php echo htmlspecialchars($similar['location']); ?>
                        </div>
                    </div>
                    <div class="px-5 py-4 bg-gray-50 text-right border-t">
                        <a href="view_activity.php?id=<?php echo $similar['id']; ?>" class="inline-flex items-center px-4 py-2 border border-indigo-600 text-sm font-medium rounded-lg text-indigo-600 bg-white hover:bg-indigo-50 transition-all duration-300">
                            Lihat Detail <i class="fas fa-arrow-right ml-1 group-hover:translate-x-1 transition-transform duration-300"></i>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Add Parallax Effect Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Simple parallax effect for the hero banner
        window.addEventListener('scroll', function() {
            const scrollPosition = window.scrollY;
            const parallaxElements = document.querySelectorAll('.parallax-bg');
            
            parallaxElements.forEach(function(element) {
                // Move the background at a different rate than the scroll
                element.style.transform = 'translateY(' + scrollPosition * 0.15 + 'px)';
            });
        });
        
        // Toggle application form visibility
        const applyButton = document.getElementById('applyButton');
        const applicationForm = document.getElementById('applicationForm');
        
        if (applyButton && applicationForm) {
            applyButton.addEventListener('click', function() {
                applicationForm.classList.remove('hidden');
                applicationForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    });
    </script>
</main>
<?php include '../../includes/footer.php'; ?>
