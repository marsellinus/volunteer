<?php
// Prevent headers already sent warning
ob_start();

// Include database connection - Using absolute path
$configPath = __DIR__ . '/../config/database.php';

// Check if the file exists and include it
if (file_exists($configPath)) {
    include_once $configPath;
} else {
    die("Database configuration file not found at: " . $configPath);
}

// Start session
session_start();

// Fetch featured volunteer activities
$featured = $conn->query("SELECT * FROM volunteer_activities WHERE is_featured = 1 LIMIT 6");

// Get the first featured activity for the hero card
$featuredHero = null;
if($featured && $featured->num_rows > 0) {
    $featuredHero = $featured->fetch_assoc();
    // Reset the pointer so we can use the same result set later
    $featured->data_seek(0);
}

function debugImagePath($path) {
    $absolutePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
    $exists = file_exists($absolutePath);
    $size = $exists ? filesize($absolutePath) : 'N/A';
    $permissions = $exists ? substr(sprintf('%o', fileperms($absolutePath)), -4) : 'N/A';
    
    return [
        'relative_path' => $path,
        'absolute_path' => $absolutePath,
        'exists' => $exists,
        'size' => $size,
        'permissions' => $permissions
    ];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunite - Temukan Kesempatan Berpartisipasi Sosial</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#10283f',       // Deep navy blue as requested
                        'primary-dark': '#081d2f',  // Slightly darker shade of #10283f
                        'primary-light': '#1a3a5a', // Slightly lighter shade of #10283f
                        'secondary': '#10283f',     // Using #10283f for secondary too
                        'secondary-dark': '#081d2f',
                        'secondary-light': '#1a3a5a',
                        'accent': '#10283f',        // Using #10283f for accent too
                        'text-dark': '#10283f'      // Kept as requested color
                    },
                    fontFamily: {
                        'sans': ['Helvetica Neue', 'Helvetica', 'Arial', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        /* Apply Helvetica Neue font family to the entire site */
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #e5e5e5;  /* Keeping the background as requested */
        }
        
        /* Feature card styles */
        .feature-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: transform 0.3s ease-in-out;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        /* Progress bar styles */
        .progress-container {
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 9999px;
        }
        
        /* Floating Navbar Styles - Keeping as requested */
        #floatingNav {
            transition: background-color 300ms ease-in-out, box-shadow 300ms ease-in-out;
            z-index: 50;
        }
        
        #navbarContainer {
            transition: transform 300ms ease-in-out, width 300ms ease-in-out, margin 300ms ease-in-out;
        }
    </style>
    <script>
        // JavaScript for handling navbar effects on scroll
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.getElementById('floatingNav');
            const navbarContainer = document.getElementById('navbarContainer');
            
            // Initialize default values for smooth transitions in both directions
            navbarContainer.style.width = '100%';
            navbarContainer.style.margin = '0 auto';
            
            // Apply explicit transitions for specific properties
            navbar.style.transition = 'background-color 300ms ease-in-out, box-shadow 300ms ease-in-out';
            navbarContainer.style.transition = 'transform 300ms ease-in-out, width 300ms ease-in-out, margin 300ms ease-in-out';
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 100) {
                    navbar.classList.add('bg-white/80', 'backdrop-blur-md', 'shadow-lg');
                    navbarContainer.classList.add('translate-y-4');
                    navbarContainer.classList.remove('w-full');
                    // Center the navbar by setting width to 2/3 and mx-auto
                    navbarContainer.style.width = '66%';
                    navbarContainer.style.margin = '0 auto';
                } else {
                    navbar.classList.remove('bg-white/80', 'backdrop-blur-md', 'shadow-lg');
                    navbarContainer.classList.remove('translate-y-4');
                    navbarContainer.classList.add('w-full');
                    // Set explicit values instead of empty strings
                    navbarContainer.style.width = '100%';
                    navbarContainer.style.margin = '0 auto';
                }
            });

            // Mobile menu toggle
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const closeMenu = document.getElementById('closeMenu');
            const mobileMenu = document.getElementById('mobileMenu');
            
            if (mobileMenuButton && closeMenu && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.remove('translate-x-full');
                    mobileMenu.classList.add('translate-x-0');
                });
                
                closeMenu.addEventListener('click', function() {
                    mobileMenu.classList.remove('translate-x-0');
                    mobileMenu.classList.add('translate-x-full');
                });
            }
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    if (mobileMenu && !mobileMenu.classList.contains('translate-x-full')) {
                        mobileMenu.classList.remove('translate-x-0');
                        mobileMenu.classList.add('translate-x-full');
                    }
                });
            });
        });

        
  let step = 0;
    </script>
</head>
<body class="bg-[#e5e5e5] font-Helvetica">

 <!-- Floating Navigation -->
<div id="navbarContainer" class="fixed top-5 left-0 right-0 z-40 flex justify-center w-full transition-all duration-300 ease-in-out">
  <nav id="floatingNav" class="bg-white rounded-full transition-all duration-300 ease-in-out w-full max-w-7xl mx-auto px-6">
    <div class="flex justify-between h-16">
      <div class="flex w-full items-center justify-between">
        <div class="flex-shrink-0 flex items-center">
          <!-- Logo added to the left of the name -->
          <img src="../assets/logo.png" alt="Volunite Logo" class="h-8 w-auto mr-2">
          <h1 class="text-xl font-bold">Volunite</h1>
        </div>
        <!-- Desktop Nav Centered -->
        <div class="hidden md:flex md:items-center md:space-x-6 mx-auto">
          <a href="#hero" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Beranda</a>
          <a href="#events" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Acara</a>
          <a href="#guide" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Panduan</a>
          <a href="#about" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Tentang Kami</a>
        </div>
        <div class="flex items-center">
          <?php if(isset($_SESSION['user_id'])): ?>
            <a href="../dashboard/user" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
            <a href="../auth/logout.php" class="ml-4 bg-[#10283f] hover:bg-primary-dark text-white px-3 py-2 rounded-full text-sm font-medium transition hover:-translate-y-0.5">Keluar</a>
          <?php elseif(isset($_SESSION['owner_id'])): ?>
            <a href="../dashboard/owner" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
            <a href="../auth/logout.php" class="ml-4 bg-secondary hover:bg-secondary-dark text-white px-3 py-2 rounded-full text-sm font-medium transition hover:-translate-y-0.5">Keluar</a>
          <?php else: ?>
            <a href="../auth/login.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium">Masuk</a>
            <a href="../auth/register.php" class="ml-4 bg-[#10283f] hover:bg-primary-dark text-white px-3 py-2 rounded-full text-sm font-medium transition hover:-translate-y-0.5">Daftar</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
</div>

<!-- Mobile Menu Button -->
<div class="fixed bottom-4 right-4 md:hidden z-50">
  <button id="mobileMenuButton" class="bg-[#10283f] text-white p-3 rounded-full shadow-lg">
    <i class="fas fa-bars"></i>
  </button>
</div>

<!-- Mobile Navigation Menu -->
<div id="mobileMenu" class="fixed inset-0 bg-white/95 z-50 transform translate-x-full transition-transform duration-300 md:hidden">
  <div class="flex flex-col h-full p-6">
    <div class="flex justify-between items-center mb-10">
      <!-- Logo added to the mobile menu as well -->
      <div class="flex items-center">
        <img src="../assets/logo.png" alt="Volunite Logo" class="h-8 w-auto mr-2">
        <h1 class="text-xl font-bold text-[#10283f]">Volunite</h1>
      </div>
      <button id="closeMenu" class="text-[#10283f] p-2">
        <i class="fas fa-times text-2xl"></i>
      </button>
    </div>
    <div class="flex flex-col space-y-4">
      <a href="#hero" class="text-[#10283f] hover:bg-gray-100 px-4 py-3 rounded-lg text-lg font-medium flex items-center">
        <i class="fas fa-home mr-3 w-6"></i> Beranda
      </a>
      <a href="#events" class="text-[#10283f] hover:bg-gray-100 px-4 py-3 rounded-lg text-lg font-medium flex items-center">
        <i class="fas fa-calendar-alt mr-3 w-6"></i> Acara
      </a>
      <a href="#guide" class="text-[#10283f] hover:bg-gray-100 px-4 py-3 rounded-lg text-lg font-medium flex items-center">
        <i class="fas fa-book mr-3 w-6"></i> Panduan
      </a>
      <a href="#about" class="text-[#10283f] hover:bg-gray-100 px-4 py-3 rounded-lg text-lg font-medium flex items-center">
        <i class="fas fa-info-circle mr-3 w-6"></i> Tentang Kami
      </a>

      <?php if(isset($_SESSION['user_id']) || isset($_SESSION['owner_id'])): ?>
        <a href="<?php echo isset($_SESSION['user_id']) ? '../dashboard/user' : '../dashboard/owner'; ?>" class="text-[#10283f] hover:bg-gray-100 px-4 py-3 rounded-lg text-lg font-medium flex items-center">
          <i class="fas fa-user-circle mr-3 w-6"></i> Dashboard
        </a>
        <a href="../auth/logout.php" class="bg-[#10283f] text-white px-4 py-3 rounded-lg text-lg font-medium flex items-center justify-center mt-4">
          <i class="fas fa-sign-out-alt mr-2"></i> Keluar
        </a>
      <?php else: ?>
        <a href="../auth/login.php" class="text-[#10283f] hover:bg-gray-100 px-4 py-3 rounded-lg text-lg font-medium flex items-center">
          <i class="fas fa-sign-in-alt mr-3 w-6"></i> Masuk
        </a>
        <a href="../auth/register.php" class="bg-[#10283f] text-white px-4 py-3 rounded-lg text-lg font-medium flex items-center justify-center mt-4">
          <i class="fas fa-user-plus mr-2"></i> Daftar
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Hero Section -->
<div id="hero" class="relative bg-[#e5e5e5] min-h-screen flex items-center scroll-mt-20 overflow-hidden pt-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 py-16 md:py-24">
    <div class="flex flex-col items-center text-center max-w-3xl mx-auto">
      <span class="inline-block py-1 px-3 rounded-full text-xs uppercase tracking-wider font-semibold bg-white text-[#10283f] mb-6">Menciptakan Perubahan</span>
      <h1 class="text-5xl md:text-7xl font-medium leading-tight">
        <span class="text-[#10283f]">Wujudkan </span>
        <span class="text-[#10283f]">Perubahan </span>
        <span class="text-[#10283f]">Bersama!</span>
      </h1>
      <p class="text-[#10283f] text-sm md:text-base mt-8 max-w-2xl leading-relaxed italic">
        Jadilah bagian dari sesuatu yang bermakna. Bergabunglah dengan komunitas relawan kami yang berdedikasi untuk memberikan dampak positif dalam kehidupan mereka yang paling membutuhkan. Bersama, kita bisa menciptakan perubahan nyata, satu langkah kecil demi masa depan yang lebih baik bagi semua.
      </p>
      <div class="mt-12 flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-6">
        <a href="#" class="px-8 py-4 bg-[#10283f] text-white rounded-xl font-medium hover:bg-primary-dark transition-all duration-300 transform hover:-translate-y-1 hover:shadow-xl shadow-lg">Mulai Menjadi Relawan</a>
        <a href="#" class="px-8 py-4 bg-white text-[#10283f] border border-[#10283f] rounded-xl font-medium hover:bg-gray-100 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-xl shadow-lg">Pelajari Lebih Lanjut</a>
      </div>
      
      <div class="mt-16 w-[1230px] h-[400px] relative overflow-hidden rounded-lg shadow-xl">
        <img src="../assets/stock1.jpg" alt="Relawan dalam aksi"
            class="w-full h-full object-cover object-top">
        <!-- Dark gradient overlay from bottom -->
        <div class="absolute inset-0 bg-gradient-to-t from-[#10283f] via-[#10283f]/60 to-transparent"></div>
        
        <!-- Statistics overlay -->
        <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
          <div class="flex justify-between items-center">
            <div class="text-center flex-1">
              <p class="text-3xl md:text-4xl font-bold">10,000+</p>
              <p class="text-sm md:text-base">Total Relawan</p>
            </div>
            <div class="text-center flex-1">
              <p class="text-3xl md:text-4xl font-bold">Seluruh Indonesia</p>
              <p class="text-sm md:text-base">Lokasi</p>
            </div>
            <div class="text-center flex-1">
              <p class="text-3xl md:text-4xl font-bold">250+</p>
              <p class="text-sm md:text-base">Event Terlaksana</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Events Section - Modernized with full-bleed image cards -->
<div id="events" class="bg-[#e5e5e5] rounded-t-3xl overflow-hidden max-w-7xl mx-auto pt-20 mt-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex justify-center items-center mb-12">
            <h2 class="text-5xl md:text-6xl font-bold text-[#10283f] relative inline-block px-10 py-4">
                <span class="relative z-10">Events</span>
                <span class="absolute inset-0 border-2 border-[#10283f] rounded-lg transform translate-x-2 translate-y-2"></span>
                <span class="absolute inset-0 bg-white border-2 border-[#10283f] rounded-lg"></span>
            </h2>
        </div>
        
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php if($featured && $featured->num_rows > 0): ?>
                <?php 
                while($activity = $featured->fetch_assoc()): 
                    $categoryBg = "bg-[#e9edf5] text-[#10283f]";
                    
                    // Determine if the image path is a full URL
                    $isUrl = !empty($activity['images']) && (strpos($activity['images'], 'http://') === 0 || strpos($activity['images'], 'https://') === 0);
                    
                    // Set image path based on type (URL or file path)
                    if ($isUrl) {
                        $imagePath = $activity['images'];
                    } else {
                        // Try with ../../ prefix first (most likely to work based on your directory structure)
                        $imagePath = !empty($activity['images']) ? "../../" . ltrim($activity['images'], '/') : "../../uploads/activities/default.jpg";
                    }
                ?>
                    <div class="bg-white overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 rounded-2xl flex flex-col border border-gray-100 group">
                        <div class="h-52 relative overflow-hidden">
                            <img 
                                src="<?php echo htmlspecialchars($imagePath); ?>" 
                                alt="<?php echo htmlspecialchars($activity['title']); ?>" 
                                class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
                                onerror="if(this.src !== '../<?php echo ltrim($activity['images'] ?? '', '/'); ?>') this.src='../<?php echo ltrim($activity['images'] ?? '', '/'); ?>'; else this.src='../../uploads/activities/default.jpg';"
                            >
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                            
                            <div class="absolute top-4 left-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#e9edf5] text-[#10283f]">
                                    <?php echo htmlspecialchars($activity['category']); ?>
                                </span>
                            </div>
                            
                            <div class="absolute bottom-4 left-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white text-gray-800 shadow-md">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <?php echo date('d M Y', strtotime($activity['event_date'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="p-6 flex-grow">
                            <h3 class="text-lg font-bold text-gray-900 hover:text-[#10283f] transition-colors duration-200 mb-2">
                                <?php echo htmlspecialchars($activity['title']); ?>
                            </h3>
                            
                            <div class="text-sm text-gray-500 mb-4">
                                <i class="fas fa-building mr-2"></i>
                                <?php echo htmlspecialchars($activity['organization_name'] ?? 'Organization'); ?>
                            </div>
                            
                            <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                                <?php echo htmlspecialchars(substr($activity['description'], 0, 150)) . '...'; ?>
                            </p>
                            
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt text-[#10283f] mr-2"></i>
                                <?php echo htmlspecialchars($activity['location'] ?? 'Location'); ?>
                            </div>
                        </div>
                        
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-users mr-1"></i>
                                <?php echo isset($activity['application_count']) ? $activity['application_count'] : '0'; ?> Pendaftar
                            </div>
                            
                            <a href="../public/activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg bg-[#10283f] hover:bg-[#1a3b5c] text-white shadow-sm transition-all duration-200">
                                Lihat Detail
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-1 sm:col-span-2 lg:col-span-3 text-center py-10">
                    <div class="bg-white rounded-lg shadow-md p-8">
                        <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500 text-lg">Belum ada kegiatan relawan yang ditampilkan saat ini.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Fakta Volunteer Section - Styled with heading below image and proper card styling -->
<div id="fakta-volunteer" class="bg-[#e5e5e5] overflow-hidden max-w-7xl mx-auto py-20 pt-20 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Left Side - Image and Heading -->
            <div class="md:w-1/3 flex flex-col">
                <div class="rounded-3xl overflow-hidden shadow-lg mb-4">
                    <img 
                        src="../assets/stock1.jpg" 
                        alt="Volunteers in Indonesia" 
                        class="w-full h-full object-cover"
                    >
                </div>
                
                <!-- Heading below image -->
                <div class="mt-6">
                    <h2 class="text-5xl font-bold text-[#10283f]">
                        Fakta Volunteer<br>di Indonesia
                    </h2>
                </div>
            </div>
            
            <!-- Right Side - Facts Cards -->
            <div class="md:w-2/3">
                <div class="flex flex-col space-y-6">
                    <!-- First Card - Outline Only -->
                    <div class="rounded-2xl bg-[#e5e5e5] p-6 border-2 border-[#10283f]">
                        <h3 class="text-2xl font-bold text-[#10283f] mb-3">Tingkat Partisipasi</h3>
                        <p class="text-gray-700">
                            Menurut data terbaru, sekitar 10% masyarakat Indonesia terlibat dalam kegiatan relawan, dengan peningkatan signifikan terjadi setelah bencana alam besar.
                        </p>
                    </div>
                    
                    <!-- Second Card - Fill Style -->
                    <div class="rounded-2xl bg-[#10283f] p-6 shadow-sm">
                        <h3 class="text-2xl font-bold text-white mb-3">Dampak Ekonomi</h3>
                        <p class="text-white">
                            Kegiatan sukarelawan di Indonesia berkontribusi sekitar Rp 25 triliun per tahun terhadap ekonomi nasional melalui nilai jasa dan waktu yang disumbangkan.
                        </p>
                    </div>
                    
                    <!-- Third Card - Outline Only -->
                    <div class="rounded-2xl bg-[#e5e5e5] p-6 border-2 border-[#10283f]">
                        <h3 class="text-2xl font-bold text-[#10283f] mb-3">Sebaran Regional</h3>
                        <p class="text-gray-700">
                            Relawan paling aktif tersebar di Pulau Jawa (45%), diikuti Sumatera (20%), Kalimantan (12%), Sulawesi (10%), dan daerah Indonesia Timur (13%).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="guide" class="bg-[#e5e5e5] min-h-screen w-full font-helvetica">
  <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 relative">

    <!-- Our Process Title - BIGGER -->
    <div class="absolute top-0 left-0 border border-[#10283f] rounded-xl px-12 py-8">
      <h2 class="text-7xl font-semibold text-[#10283f]">Proses Kami</h2>
    </div>

    <!-- Stair layout - BIGGER square cards climbing from left to right, positioned from right side -->
    <div class="flex justify-end mt-48 mb-36">
      <!-- 1. Identify (bottom) -->
      <div class="mt-72 mr-8">
        <div class="bg-[#10283f] text-white rounded-2xl p-8 w-[250px] h-[250px] flex items-center shadow-md">
          <p class="text-2xl italic font-medium leading-snug">Cari<br>kesempatan<br>relawan</p>
        </div>
      </div>

      <!-- 2. Develop -->
      <div class="mt-48 mr-8">
        <div class="bg-white border border-[#10283f] text-[#10283f] rounded-2xl p-8 w-[250px] h-[250px] flex items-center shadow-md">
          <p class="text-2xl italic font-medium leading-snug">Ajukan<br>lamaran<br>online</p>
        </div>
      </div>

      <!-- 3. Collaborate -->
      <div class="mt-24 mr-8">
        <div class="bg-[#10283f] text-white rounded-2xl p-8 w-[250px] h-[250px] flex items-center shadow-md">
          <p class="text-2xl italic font-medium leading-snug">Ikuti<br>orientasi<br>relawan</p>
        </div>
      </div>

      <!-- 4. Share (top) -->
      <div class="mt-0">
        <div class="bg-white border border-[#10283f] text-[#10283f] rounded-2xl p-8 w-[250px] h-[250px] flex items-center shadow-md">
          <p class="text-2xl italic font-medium leading-snug">Mulai<br>kegiatan<br>relawan</p>
        </div>
      </div>
    </div>

    <!-- How We Work - now positioned much higher at the top-right -->
    <div class="absolute bottom-0 right-0">
      <div class="bg-[#e5e5e5] border border-[#10283f] rounded-2xl px-8 py-5 w-[550px] text-center shadow-md">
        <p class="text-2xl italic text-[#10283f] mb-2">Proses Relawan</p>
        <p class="text-sm text-[#10283f]">Bergabunglah dengan kami untuk membantu komunitas yang membutuhkan. Kami menerima relawan yang berdedikasi untuk membuat perubahan positif.</p>
      </div>
    </div>

  </div>
</div>

<!-- About Us Section -->
<div class="bg-[#e5e5e5] py-16 font-helvetica pt-20">
  <div class="container mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col lg:flex-row gap-6 items-stretch justify-between">
      <!-- Left Side (Mission & Vision) -->
      <div class="flex flex-col lg:flex-row gap-4">
        <!-- Mission Card -->
        <div class="bg-[#10283f] p-6 rounded-lg h-full w-[300px]">
          <h2 class="text-xl italic font-medium mb-3 text-[#e5e5e5]">Our Mission</h2>
          <p class="text-xs text-[#e5e5e5] leading-relaxed">
          Volunite memudahkan siapa pun menemukan kegiatan volunteer yang sesuai dengan minat dan keterampilan mereka, memberikan kesempatan bagi individu untuk berkontribusi secara langsung dalam berbagai proyek sosial yang berdampak, serta mendukung aksi nyata untuk masyarakat dengan menciptakan perubahan positif yang berkelanjutan.
          </p>
        </div>

        <!-- Vision Card -->
        <div class="bg-[#e5e5e5] text-gray-800 p-6 rounded-lg border border-[#10283f] h-full w-[300px]">
          <h2 class="text-xl text-[#10283f] italic font-medium mb-3">Our Vision</h2>
          <p class="text-xs text-[#10283f] leading-relaxed">
          Menjadi jembatan utama antara relawan dan organisasi sosial di Indonesia, memfasilitasi kolaborasi yang efektif untuk menciptakan perubahan positif bersama yang berdampak luas, serta menginspirasi lebih banyak orang untuk terlibat dalam aksi sosial yang memberikan manfaat bagi masyarakat.
          </p>
        </div>
      </div>
      
      <!-- Who We Are -->
      <div class="max-w-sm w-full flex flex-col justify-between h-full">
        <h2 class="text-8xl font-medium text-[#10283f] mb-3 text-right leading-snug">
          Who We <br>Are
        </h2>
        <p class="text-sm text-[#10283f] text-right leading-snug">
          Kami adalah platform pencarian event volunteer yang menghubungkan semangat kebaikan dengan aksi nyata di lapangan. Kami mempermudah relawan dan organisasi sosial untuk bekerja sama, menciptakan dampak positif yang nyata.
        </p>
      </div>

    </div>
  </div>
</div>



    <!-- Footer with #e5e5e5 Background - Updated to Indonesian -->
    <footer class="bg-[#e5e5e5] text-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <div class="flex justify-center md:order-2">
                    <p class="text-center text-[#10283f]">&copy; 2023 VolunteerHub. Hak Cipta Dilindungi.</p>
                </div>
                <div class="mt-8 md:mt-0 md:order-1">
                    <p class="text-center text-base text-[#10283f]">Dibuat dengan ❤️ untuk dampak komunitas</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>