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
$status_color = '#6B7280'; // gray-500
$status_bg_color = '#F9FAFB'; // gray-50
$status_border_color = '#E5E7EB'; // gray-200
$status_icon = 'clock';
$status_text = 'Menunggu';
$status_progress = 33; // Progress bar percentage

// Set appropriate values based on application status
if (isset($application['status'])) {
    if ($application['status'] === 'approved') {
        $status_color = '#10B981'; // green-500
        $status_bg_color = '#ECFDF5'; // green-50
        $status_border_color = '#A7F3D0'; // green-200
        $status_icon = 'check-circle';
        $status_text = 'Diterima';
        $status_progress = 100;
    } elseif ($application['status'] === 'rejected') {
        $status_color = '#EF4444'; // red-500
        $status_bg_color = '#FEF2F2'; // red-50
        $status_border_color = '#FECACA'; // red-200
        $status_icon = 'times-circle';
        $status_text = 'Ditolak';
        $status_progress = 100;
    } elseif ($application['status'] === 'pending') {
        $status_color = '#F59E0B'; // amber-500
        $status_bg_color = '#FFFBEB'; // amber-50
        $status_border_color = '#FDE68A'; // amber-200
        $status_icon = 'clock';
        $status_text = 'Menunggu';
        $status_progress = 66;
    }
}

// Check if event has passed (for certificate eligibility)
$event_passed = strtotime($application['event_date']) < time();
$can_get_certificate = $event_passed && $application['status'] === 'approved';

$page_title = 'Detail Pendaftaran - VolunteerHub';
include '../../includes/header_user.php';
?>

<!-- Additional CSS for modern UI -->
<style>
  :root {
    --primary: #10283f;
    --primary-light: #203c56;
    --primary-dark: #0a1928;
    --primary-transparent: rgba(16, 40, 63, 0.05);
    --secondary: #4F46E5;
    --accent: #F97316;
  }
  
  .glass-effect {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
  }

  .primary-gradient {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
  }
  
  .progress-bar {
    height: 6px;
    background-color: #e5e7eb;
    border-radius: 9999px;
    overflow: hidden;
    position: relative;
  }
  
  .progress-bar-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 9999px;
    transition: width 0.5s ease;
  }
  
  .timeline-item {
    position: relative;
    padding-left: 28px;
  }
  
  .timeline-item::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e5e7eb;
  }
  
  .timeline-item:last-child::before {
    display: none;
  }
  
  .timeline-dot {
    position: absolute;
    left: 0;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background-color: #fff;
    border: 2px solid #e5e7eb;
  }
  
  .timeline-dot.active {
    border-color: var(--primary);
    background-color: var(--primary);
  }
  
  .floating-card {
    transition: all 0.3s ease;
  }
  
  .floating-card:hover {
    transform: translateY(-4px);
  }
  
  .btn-primary {
    background-color: var(--primary);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
  }
  
  .btn-primary:hover {
    background-color: var(--primary-light);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    transform: translateY(-2px);
  }
  
  .btn-secondary {
    background-color: white;
    color: var(--primary);
    border: 1px solid #e5e7eb;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
  }
  
  .btn-secondary:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  }
  
  .scrolling-touch {
    -webkit-overflow-scrolling: touch;
  }
  
  .nav-tab {
    padding: 1rem;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
  }
  
  .nav-tab:hover {
    color: var(--primary);
  }
  
  .nav-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
  }
  
  /* Custom animations */
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  @keyframes slideInUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }
  
  @keyframes slideInRight {
    from { transform: translateX(20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  
  .animate-fadeIn {
    animation: fadeIn 0.5s ease forwards;
  }
  
  .animate-slideInUp {
    animation: slideInUp 0.5s ease forwards;
  }
  
  .animate-slideInRight {
    animation: slideInRight 0.5s ease forwards;
  }
  
  .delay-100 { animation-delay: 0.1s; }
  .delay-200 { animation-delay: 0.2s; }
  .delay-300 { animation-delay: 0.3s; }
</style>

<!-- Main Content -->
<main class="min-h-screen bg-gray-50 pt-24 pb-12 pt-20 mt-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between opacity-0 animate-fadeIn" style="animation-delay: 0.2s; animation-fill-mode: forwards;">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Detail Pendaftaran Volunteer</h1>
        <p class="mt-1 text-gray-500">Lihat dan kelola informasi pendaftaran kegiatan volunteer Anda</p>
      </div>
      
      <div class="mt-4 md:mt-0">
        <a href="my_applications.php" class="btn-secondary">
          <i class="fas fa-arrow-left mr-2"></i>
          Kembali
        </a>
      </div>
    </div>
    
    <!-- Application Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Activity Card -->
        <div class="bg-white rounded-2xl shadow overflow-hidden opacity-0 animate-slideInUp" style="animation-delay: 0.3s; animation-fill-mode: forwards;">
          <div class="primary-gradient p-6">
            <div class="flex justify-between items-start">
              <div>
                <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($application['title']); ?></h2>
                <p class="mt-1 text-blue-100">Kegiatan Volunteer</p>
              </div>
              <div class="flex space-x-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white" style="color: <?php echo $status_color; ?>">
                  <i class="fas fa-<?php echo $status_icon; ?> mr-1.5"></i>
                  <?php echo $status_text; ?>
                </span>
              </div>
            </div>
            
            <div class="mt-6 grid sm:grid-cols-3 gap-4">
              <div class="flex items-center text-white">
                <div class="flex-shrink-0 p-2 bg-white bg-opacity-20 rounded-lg">
                  <i class="fas fa-map-marker-alt text-white"></i>
                </div>
                <div class="ml-3">
                  <p class="text-xs font-medium text-blue-100">Lokasi</p>
                  <p class="text-sm text-white"><?php echo htmlspecialchars($application['location']); ?></p>
                </div>
              </div>
              
              <div class="flex items-center text-white">
                <div class="flex-shrink-0 p-2 bg-white bg-opacity-20 rounded-lg">
                  <i class="far fa-calendar-alt text-white"></i>
                </div>
                <div class="ml-3">
                  <p class="text-xs font-medium text-blue-100">Tanggal Kegiatan</p>
                  <p class="text-sm text-white"><?php echo date('d M Y', strtotime($application['event_date'])); ?></p>
                </div>
              </div>
              
              <div class="flex items-center text-white">
                <div class="flex-shrink-0 p-2 bg-white bg-opacity-20 rounded-lg">
                  <i class="fas fa-user-tie text-white"></i>
                </div>
                <div class="ml-3">
                  <p class="text-xs font-medium text-blue-100">Penyelenggara</p>
                  <p class="text-sm text-white"><?php echo htmlspecialchars($application['organization_name']); ?></p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Application Progress -->
          <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between mb-2">
              <h3 class="text-sm font-medium text-gray-500">Status Pendaftaran</h3>
              <span class="text-sm font-medium" style="color: <?php echo $status_color; ?>"><?php echo $status_text; ?></span>
            </div>
            
            <div class="progress-bar">
              <div class="progress-bar-fill" style="width: <?php echo $status_progress; ?>%; background-color: <?php echo $status_color; ?>"></div>
            </div>
            
            <div class="mt-6">
              <div class="timeline-item pb-6">
                <div class="timeline-dot active"></div>
                <div class="ml-6">
                  <p class="text-sm font-medium text-gray-900">Pendaftaran Dikirim</p>
                  <p class="text-xs text-gray-500"><?php echo date('d M Y, H:i', strtotime($application['applied_at'])); ?></p>
                </div>
              </div>
              
              <div class="timeline-item pb-6">
                <div class="timeline-dot <?php echo ($application['status'] !== 'pending') ? 'active' : ''; ?>"></div>
                <div class="ml-6">
                  <p class="text-sm font-medium text-gray-900">Review Penyelenggara</p>
                  <p class="text-xs text-gray-500">
                    <?php if ($application['status'] === 'pending'): ?>
                      Sedang dalam proses review
                    <?php else: ?>
                      Selesai review
                    <?php endif; ?>
                  </p>
                </div>
              </div>
              
              <div class="timeline-item">
                <div class="timeline-dot <?php echo ($application['status'] === 'approved' || $application['status'] === 'rejected') ? 'active' : ''; ?>"></div>
                <div class="ml-6">
                  <p class="text-sm font-medium text-gray-900">Keputusan Final</p>
                  <p class="text-xs text-gray-500">
                    <?php if ($application['status'] === 'approved'): ?>
                      Pendaftaran diterima
                    <?php elseif ($application['status'] === 'rejected'): ?>
                      Pendaftaran ditolak
                    <?php else: ?>
                      Menunggu keputusan
                    <?php endif; ?>
                  </p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Message Section -->
          <div class="p-6">
            <h3 class="text-base font-medium text-gray-900 mb-4">Pesan Pendaftaran</h3>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
              <div class="whitespace-pre-line text-gray-700">
                <?php echo nl2br(htmlspecialchars($application['message'])); ?>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Status Card -->
        <div class="bg-white rounded-2xl shadow p-6 opacity-0 animate-slideInUp" style="animation-delay: 0.4s; animation-fill-mode: forwards;">
          <h3 class="text-base font-medium text-gray-900 mb-4">Informasi Status</h3>
          
          <div class="p-4 rounded-xl" style="background-color: <?php echo $status_bg_color; ?>; border: 1px solid <?php echo $status_border_color; ?>;">
            <div class="flex">
              <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full" style="background-color: <?php echo $status_color; ?>;">
                <i class="fas fa-<?php echo $status_icon; ?> text-white"></i>
              </div>
              <div class="ml-4">
                <h4 class="text-sm font-medium" style="color: <?php echo $status_color; ?>;"><?php echo $status_text; ?></h4>
                <div class="mt-1 text-sm text-gray-600">
                  <?php if($application['status'] === 'pending'): ?>
                    Pendaftaran Anda sedang dalam proses review oleh penyelenggara. Kami akan memberitahu Anda segera setelah ada keputusan.
                  <?php elseif($application['status'] === 'approved'): ?>
                    Selamat! Anda telah diterima untuk berpartisipasi dalam kegiatan ini. Silakan periksa email Anda untuk informasi lebih lanjut tentang persiapan dan detail kegiatan.
                  <?php elseif($application['status'] === 'rejected'): ?>
                    Maaf, pendaftaran Anda tidak dapat diterima untuk kegiatan ini. Jangan berkecil hati, Anda dapat mencoba mendaftar untuk kegiatan lain yang sesuai dengan minat dan keterampilan Anda.
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          
          <?php if($application['status'] === 'approved' && $can_get_certificate): ?>
          <div class="mt-6">
            <a href="generate_certificate.php?id=<?php echo $application_id; ?>" class="btn-primary">
              <i class="fas fa-certificate mr-2"></i>
              Download Piagam Partisipasi
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Organization Card -->
        <div class="bg-white rounded-2xl shadow p-6 opacity-0 animate-slideInRight" style="animation-delay: 0.5s; animation-fill-mode: forwards;">
          <h3 class="text-base font-medium text-gray-900 mb-4">Informasi Penyelenggara</h3>
          
          <div class="flex items-center mb-4">
            <div class="flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center primary-gradient">
              <span class="text-white text-lg font-bold"><?php echo substr($application['organization_name'], 0, 1); ?></span>
            </div>
            <div class="ml-3">
              <h4 class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($application['organization_name']); ?></h4>
              <p class="text-sm text-gray-500">Penyelenggara</p>
            </div>
          </div>
          
          <?php if(!empty($application['organization_description'])): ?>
          <div class="mt-4">
            <h5 class="text-sm font-medium text-gray-700 mb-1">Tentang Penyelenggara</h5>
            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($application['organization_description']); ?></p>
          </div>
          <?php endif; ?>
          
          <?php if(!empty($application['website'])): ?>
          <div class="mt-4">
            <a href="<?php echo htmlspecialchars($application['website']); ?>" target="_blank" class="text-sm font-medium flex items-center" style="color: var(--primary);">
              <i class="fas fa-external-link-alt mr-1.5"></i>
              Kunjungi Website
            </a>
          </div>
          <?php endif; ?>
        </div>
        
        <!-- Helpful Information Card -->
        <div class="bg-white rounded-2xl shadow p-6 opacity-0 animate-slideInRight" style="animation-delay: 0.6s; animation-fill-mode: forwards;">
          <h3 class="text-base font-medium text-gray-900 mb-4">Informasi Bantuan</h3>
          
          <div class="space-y-4">
            <div class="p-3 rounded-lg bg-blue-50 flex">
              <div class="flex-shrink-0 text-blue-500">
                <i class="fas fa-info-circle"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm text-blue-800">Butuh bantuan terkait pendaftaran ini? Hubungi penyelenggara melalui kontak yang tertera pada halaman kegiatan.</p>
              </div>
            </div>
            
            <?php if($application['status'] === 'approved'): ?>
            <div class="p-3 rounded-lg bg-green-50 flex">
              <div class="flex-shrink-0 text-green-500">
                <i class="fas fa-check-circle"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm text-green-800">Jangan lupa untuk memeriksa email Anda secara berkala untuk informasi terbaru tentang kegiatan.</p>
              </div>
            </div>
            <?php endif; ?>
            
            <div class="p-3 rounded-lg border border-gray-200 flex">
              <div class="flex-shrink-0 text-gray-500">
                <i class="fas fa-question-circle"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm text-gray-700">Ada pertanyaan lain? Kunjungi <a href="../help/faq.php" class="underline" style="color: var(--primary);">Pusat Bantuan</a> kami.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include '../../includes/footer.php'; ?>