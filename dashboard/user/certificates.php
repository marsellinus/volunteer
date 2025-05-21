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

$page_title = 'Piagam & Sertifikat - VolunteerHub';
include '../../includes/header_user.php';
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 pt-20 mt-10 flex-grow">
    <?php if (!$columnsExist): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p class="font-bold">Database Update Required</p>
        <p>The certificate feature requires a database update. Please run the setup script: <code>setup/update_applications_table.php</code></p>
        <p class="mt-3">
            <a href="../../setup/update_applications_table.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded inline-block mt-2">Run Update Script</a>
        </p>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-[#10283f] via-[#1a3c5d] to-[#10283f] rounded-2xl shadow-xl px-6 py-12 mb-8 text-white relative overflow-hidden">
        <div class="md:flex md:items-center md:justify-between relative z-10">
            <div class="md:flex-1">
                <h1 class="text-3xl font-bold mb-2">Piagam & Sertifikat Volunteer</h1>
                <p class="text-lg text-blue-100 max-w-2xl">Unduh dan bagikan bukti partisipasi Anda dalam kegiatan volunteer, sebagai bentuk apresiasi atas kontribusi yang telah Anda berikan.</p>
            </div>
            <div class="mt-6 md:mt-0 md:ml-6">
                <div class="w-20 h-20 bg-white bg-opacity-10 rounded-full flex items-center justify-center mx-auto md:mx-0">
                    <i class="fas fa-award text-4xl text-yellow-300"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Certificates List -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-[#e6edf2] text-[#10283f] rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-certificate"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-900">Daftar Piagam Tersedia</h2>
            </div>
        </div>
        
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
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-[#e6edf2] text-[#10283f] rounded-full flex items-center justify-center">
                                            <i class="fas fa-award"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cert['title']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cert['category']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($cert['organization_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($cert['organizer_name']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?php echo date('d M Y', strtotime($cert['event_date'])); ?></div>
                                </td>
                                <?php if($columnsExist): ?>
                                <td class="px-6 py-4">
                                    <?php if(!empty($cert['certificate_generated'])): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Tersedia
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i> Siap dibuat
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td class="px-6 py-4">
                                    <a href="generate_certificate.php?id=<?php echo $cert['id']; ?>" class="inline-flex items-center px-3 py-2 border border-[#10283f] rounded-md text-sm font-medium text-[#10283f] bg-white hover:bg-[#e6edf2] transition duration-150">
                                        <i class="fas fa-download mr-1"></i> Download
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-16">
                <img src="https://illustrations.popsy.co/amber/student-with-certificate.svg" alt="No certificates" class="mx-auto h-48 w-auto mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-1">Belum ada piagam tersedia</h3>
                <p class="text-gray-500 mb-6 max-w-md mx-auto">Selesaikan kegiatan volunteer terlebih dahulu untuk mendapatkan piagam penghargaan.</p>
                <a href="search.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#10283f] hover:bg-[#1a3c5d] transition duration-150">
                    <i class="fas fa-search mr-2"></i> Cari Kegiatan Volunteer
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<style>
/* Add these styles to fix the footer positioning and remove white space */

html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}

body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

main {
    flex: 1 0 auto;
}

footer {
    flex-shrink: 0;
    margin-top: 0;
}

/* Ensure there's no extra margin at the bottom */
main:after {
    content: none;
}
</style>