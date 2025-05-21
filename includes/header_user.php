<?php
// Include notifications functions if not already included
if(!function_exists('getUserUnreadNotificationsCount')) {
    include_once '../../includes/notifications.php';
}

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get current page filename for highlighting the active menu item
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread notifications count with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Volunite'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <?php if (isset($extra_head)) echo $extra_head; ?>
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
                navbar.classList.remove('bg-white');
                navbarContainer.classList.add('translate-y-4');
                navbarContainer.classList.remove('w-full');
                // Center the navbar by setting width to 2/3 and mx-auto
                navbarContainer.style.width = '66%';
                navbarContainer.style.margin = '0 auto';
            } else {
                navbar.classList.remove('bg-white/80', 'backdrop-blur-md', 'shadow-lg');
                navbar.classList.add('bg-white');
                navbarContainer.classList.remove('translate-y-4');
                navbarContainer.classList.add('w-full');
                // Set explicit values instead of empty strings
                navbarContainer.style.width = '100%';
                navbarContainer.style.margin = '0 auto';
            }
        });
    });
</script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Floating Navigation -->
        <div id="navbarContainer" class="fixed top-5 left-0 right-0 z-40 flex justify-center w-full transition-all duration-300 ease-in-out">
            <nav id="floatingNav" class="bg-white rounded-full transition-all duration-300 ease-in-out w-full max-w-7xl mx-auto px-6 shadow-lg">
                <div class="flex justify-between h-16 items-center">
                    <!-- Logo section -->
                    <div class="flex-shrink-0 flex items-center w-1/4">
                        <div class="flex items-center space-x-2">
                            <img src="../../assets/logo.png" alt="Volunite Logo" class="h-8 w-auto">
                            <h1 class="text-xl font-bold text-[#10283f]">Volunite</h1>
                        </div>
                    </div>
                    
                    <!-- Centered Navigation Items -->
                    <div class="flex-grow flex justify-center">
                        <div class="hidden md:flex items-center space-x-2">
                            <a href="search.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium relative group <?php echo ($current_page == 'search.php') ? 'bg-gray-100' : ''; ?>">
                                <span class="absolute inset-0 bg-gray-100 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></span>
                                <span class="relative">Cari</span>
                            </a>
                            <a href="my_applications.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium relative group <?php echo ($current_page == 'my_applications.php' || $current_page == 'view_application.php') ? 'bg-gray-100' : ''; ?>">
                                <span class="absolute inset-0 bg-gray-100 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></span>
                                <span class="relative">Lamaran</span>
                            </a>
                            <a href="certificates.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium relative group <?php echo ($current_page == 'certificates.php' || $current_page == 'generate_certificate.php') ? 'bg-gray-100' : ''; ?>">
                                <span class="absolute inset-0 bg-gray-100 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></span>
                                <span class="relative">Piagam</span>
                            </a>
                            <a href="notifications.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium relative group <?php echo ($current_page == 'notifications.php') ? 'bg-gray-100' : ''; ?>">
                                <span class="absolute inset-0 bg-gray-100 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></span>
                                <span class="relative">Notifikasi</span>
                                <?php if($unread_count > 0): ?>
                                <span class="absolute -top-2 right-0 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                    <?php echo $unread_count; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <a href="profile.php" class="text-[#10283f] hover:text-gray-600 px-3 py-2 rounded-md text-sm font-medium relative group <?php echo ($current_page == 'profile.php') ? 'bg-gray-100' : ''; ?>">
                                <span class="absolute inset-0 bg-gray-100 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></span>
                                <span class="relative">Profil</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- User info and logout button -->
                    <div class="flex items-center justify-end w-1/4">
                        <span class="text-[#10283f] mr-4 hidden md:inline">Hello, <?php echo htmlspecialchars($user_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-[#10283f] hover:bg-[#10283f]/80 text-white px-3 py-2 rounded-full text-sm font-medium transition-colors duration-300 transform hover:-translate-y-0.5">
                            <span class="hidden md:inline">Logout</span>
                            <i class="fas fa-sign-out-alt md:hidden"></i>
                        </a>
                    </div>
                </div>
            </nav>
        </div>
        
        <!-- Mobile Menu Button - Shows only on small screens -->
        <div class="fixed bottom-4 right-4 md:hidden z-50">
            <button id="mobileMenuButton" class="bg-[#10283f] text-white p-3 rounded-full shadow-lg">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation Menu -->
        <div id="mobileMenu" class="fixed inset-0 bg-[#10283f]/95 z-50 transform translate-x-full transition-transform duration-300 md:hidden">
            <div class="flex flex-col h-full p-6">
                <div class="flex justify-between items-center mb-10">
                    <div class="flex items-center space-x-2">
                        <img src="../../assets/logo.png" alt="Volunite Logo" class="h-8 w-auto">
                        <h1 class="text-xl font-bold text-white">Volunite</h1>
                    </div>
                    <button id="closeMenu" class="text-white p-2">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <div class="flex flex-col space-y-4">
                    <a href="search.php" class="text-white hover:bg-[#10283f]/80 px-4 py-3 rounded-lg text-lg font-medium flex items-center <?php echo ($current_page == 'search.php') ? 'bg-[#10283f]/80' : ''; ?>">
                        Cari
                    </a>
                    <a href="my_applications.php" class="text-white hover:bg-[#10283f]/80 px-4 py-3 rounded-lg text-lg font-medium flex items-center <?php echo ($current_page == 'my_applications.php' || $current_page == 'view_application.php') ? 'bg-[#10283f]/80' : ''; ?>">
                        Lamaran
                    </a>
                    <a href="certificates.php" class="text-white hover:bg-[#10283f]/80 px-4 py-3 rounded-lg text-lg font-medium flex items-center <?php echo ($current_page == 'certificates.php' || $current_page == 'generate_certificate.php') ? 'bg-[#10283f]/80' : ''; ?>">
                        Piagam
                    </a>
                    <a href="notifications.php" class="text-white hover:bg-[#10283f]/80 px-4 py-3 rounded-lg text-lg font-medium flex items-center <?php echo ($current_page == 'notifications.php') ? 'bg-[#10283f]/80' : ''; ?> relative">
                        Notifikasi
                        <?php if($unread_count > 0): ?>
                        <span class="absolute top-2 right-24 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                            <?php echo $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="text-white hover:bg-[#10283f]/80 px-4 py-3 rounded-lg text-lg font-medium flex items-center <?php echo ($current_page == 'profile.php') ? 'bg-[#10283f]/80' : ''; ?>">
                        Profil
                    </a>
                    <div class="mt-auto pt-10">
                        <p class="text-white mb-4">Hello, <?php echo htmlspecialchars($user_name); ?></p>
                        <a href="../../auth/logout.php" class="bg-white text-[#10283f] px-4 py-3 rounded-lg text-lg font-medium flex items-center justify-center">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Mobile menu toggle
            document.addEventListener('DOMContentLoaded', function() {
                const mobileMenuButton = document.getElementById('mobileMenuButton');
                const closeMenu = document.getElementById('closeMenu');
                const mobileMenu = document.getElementById('mobileMenu');
                
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.remove('translate-x-full');
                    mobileMenu.classList.add('translate-x-0');
                });
                
                closeMenu.addEventListener('click', function() {
                    mobileMenu.classList.remove('translate-x-0');
                    mobileMenu.classList.add('translate-x-full');
                });
            });

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
            // Enhanced glassmorphism effect
            navbar.classList.add('bg-white/80', 'backdrop-blur-md', 'shadow-lg');
            navbar.classList.remove('bg-white');
            
            // Additional glassmorphism styles
            navbar.style.border = '1px solid rgba(255, 255, 255, 0.18)'; 
            navbar.style.boxShadow = '0 10px 25px -5px rgba(16, 40, 63, 0.1), 0 8px 10px -6px rgba(16, 40, 63, 0.08)';
            
            navbarContainer.classList.add('translate-y-4');
            navbarContainer.classList.remove('w-full');
            // Center the navbar by setting width to 2/3 and mx-auto
            navbarContainer.style.width = '66%';
            navbarContainer.style.margin = '0 auto';
        } else {
            // Default state
            navbar.classList.remove('bg-white/80', 'backdrop-blur-md', 'shadow-lg');
            navbar.classList.add('bg-white');
            
            // Remove glassmorphism styles
            navbar.style.border = 'none';
            navbar.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)';
            
            navbarContainer.classList.remove('translate-y-4');
            navbarContainer.classList.add('w-full');
            // Set explicit values instead of empty strings
            navbarContainer.style.width = '100%';
            navbarContainer.style.margin = '0 auto';
        }
    });
    
    // Trigger the scroll event once on load to apply the correct initial state
    window.dispatchEvent(new Event('scroll'));
});
        </script>