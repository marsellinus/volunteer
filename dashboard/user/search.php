<?php
include_once '../../config/database.php';
include_once '../../logic/recommendation.php';
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Enhanced search with categories, filters, and sorting
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';

// Log search for recommendation
if (!empty($search) || !empty($category) || !empty($location)) {
    $searchTerms = trim("$search $category $location");
    if (!empty($searchTerms)) {
        logSearchQuery($user_id, $searchTerms, $conn);
    }
}

// Build advanced search query
$query = "SELECT va.*, o.name as organization_name 
          FROM volunteer_activities va 
          JOIN owners o ON va.owner_id = o.owner_id 
          WHERE va.application_deadline >= CURDATE() ";

// Add search filters
if (!empty($search)) {
    $query .= "AND (va.title LIKE '%" . $conn->real_escape_string($search) . "%' 
               OR va.description LIKE '%" . $conn->real_escape_string($search) . "%'
               OR o.name LIKE '%" . $conn->real_escape_string($search) . "%') ";
}

if (!empty($category)) {
    $query .= "AND va.category = '" . $conn->real_escape_string($category) . "' ";
}

if (!empty($location)) {
    $query .= "AND va.location LIKE '%" . $conn->real_escape_string($location) . "%' ";
}

if (!empty($date_from)) {
    $query .= "AND va.event_date >= '" . $conn->real_escape_string($date_from) . "' ";
}

if (!empty($date_to)) {
    $query .= "AND va.event_date <= '" . $conn->real_escape_string($date_to) . "' ";
}

// Add sorting options
switch($sort) {
    case 'date_asc':
        $query .= "ORDER BY va.event_date ASC";
        break;
    case 'date_desc':
        $query .= "ORDER BY va.event_date DESC";
        break;
    case 'title_asc':
        $query .= "ORDER BY va.title ASC";
        break;
    case 'newest':
        $query .= "ORDER BY va.created_at DESC";
        break;
    default:
        $query .= "ORDER BY va.event_date ASC";
}

// Execute query
$activities = $conn->query($query);

// Get all available categories
$categories = $conn->query("SELECT DISTINCT category FROM volunteer_activities ORDER BY category");

// Get all available locations
$locations = $conn->query("SELECT DISTINCT location FROM volunteer_activities ORDER BY location");

// Get recommended activities (if no search is performed)
if (empty($search) && empty($category) && empty($location) && empty($date_from) && empty($date_to)) {
    $recommendedActivities = getVolunteerRecommendations($user_id, $conn);
} else {
    $recommendedActivities = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Lowongan Volunteer - VolunteerHub</title>
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
                            <a href="search.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium bg-indigo-700">
                                <i class="fas fa-search mr-1"></i> Cari
                            </a>
                            <a href="my_applications.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">
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
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-300">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Search Banner -->
            <div class="px-4 py-8 sm:px-0 bg-gradient-to-r from-indigo-700 to-purple-700 rounded-lg shadow-lg text-white mb-8">
                <div class="max-w-4xl mx-auto text-center">
                    <h2 class="text-3xl font-bold mb-4">Cari Kesempatan Volunteer</h2>
                    <p class="text-indigo-100 mb-6">Temukan kesempatan volunteer yang sesuai dengan minat dan kemampuan Anda.</p>
                    
                    <!-- Quick Search Form -->
                    <form action="search.php" method="GET" class="mt-2">
                        <div class="flex flex-col md:flex-row gap-2">
                            <div class="flex-grow">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari kesempatan volunteer..." class="w-full px-4 py-2 rounded-l-md focus:outline-none focus:ring-2 focus:ring-indigo-300 text-gray-800">
                            </div>
                            <button type="submit" class="px-6 py-2 bg-indigo-800 hover:bg-indigo-900 rounded-r-md transition-colors duration-300">
                                <i class="fas fa-search mr-1"></i> Cari
                            </button>
                        </div>
                    </form>
                    
                    <button id="advancedSearchToggle" class="mt-3 text-sm text-indigo-200 hover:text-white">
                        <i class="fas fa-sliders-h mr-1"></i> Filter Lanjutan
                    </button>
                </div>
            </div>
            
            <!-- Advanced Search Form (hidden by default) -->
            <div id="advancedSearchForm" class="px-4 py-6 sm:px-0 mb-8 hidden">
                <div class="bg-white shadow-md rounded-lg px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Pencarian Lanjutan</h3>
                    <form action="search.php" method="GET">
                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="search" class="block text-sm font-medium text-gray-700">
                                    Kata Kunci
                                </label>
                                <div class="mt-1">
                                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Masukkan kata kunci...">
                                </div>
                            </div>
                            
                            <div class="sm:col-span-3">
                                <label for="category" class="block text-sm font-medium text-gray-700">
                                    Kategori
                                </label>
                                <div class="mt-1">
                                    <select id="category" name="category" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        <option value="">Semua Kategori</option>
                                        <?php if($categories && $categories->num_rows > 0): 
                                            while($cat = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($cat['category'] == $category) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['category']); ?>
                                                </option>
                                            <?php endwhile; 
                                        endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="sm:col-span-3">
                                <label for="location" class="block text-sm font-medium text-gray-700">
                                    Lokasi
                                </label>
                                <div class="mt-1">
                                    <select id="location" name="location" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        <option value="">Semua Lokasi</option>
                                        <?php if($locations && $locations->num_rows > 0):
                                            while($loc = $locations->fetch_assoc()): ?>
                                                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo ($loc['location'] == $location) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($loc['location']); ?>
                                                </option>
                                            <?php endwhile;
                                        endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="sm:col-span-2">
                                <label for="date_from" class="block text-sm font-medium text-gray-700">
                                    Tanggal Mulai
                                </label>
                                <div class="mt-1">
                                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                            
                            <div class="sm:col-span-2">
                                <label for="date_to" class="block text-sm font-medium text-gray-700">
                                    Tanggal Selesai
                                </label>
                                <div class="mt-1">
                                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                </div>
                            </div>
                            
                            <div class="sm:col-span-2">
                                <label for="sort" class="block text-sm font-medium text-gray-700">
                                    Urutan
                                </label>
                                <div class="mt-1">
                                    <select id="sort" name="sort" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        <option value="date_asc" <?php echo ($sort == 'date_asc') ? 'selected' : ''; ?>>Tanggal (terlama)</option>
                                        <option value="date_desc" <?php echo ($sort == 'date_desc') ? 'selected' : ''; ?>>Tanggal (terbaru)</option>
                                        <option value="title_asc" <?php echo ($sort == 'title_asc') ? 'selected' : ''; ?>>Judul (A-Z)</option>
                                        <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Baru ditambahkan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-5 flex justify-end">
                            <button type="reset" class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Reset
                            </button>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <i class="fas fa-search mr-1"></i> Cari
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Search Results -->
            <div class="px-4 sm:px-0">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <?php if(!empty($search) || !empty($category) || !empty($location) || !empty($date_from) || !empty($date_to)): ?>
                            Hasil Pencarian
                        <?php else: ?>
                            Kesempatan Volunteer Terbaru
                        <?php endif; ?>
                    </h3>
                    <?php if($activities): ?>
                    <p class="text-gray-500 text-sm"><?php echo $activities->num_rows; ?> kegiatan ditemukan</p>
                    <?php endif; ?>
                </div>
                
                <?php if($activities && $activities->num_rows > 0): ?>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 mb-8">
                        <?php while($activity = $activities->fetch_assoc()): ?>
                            <div class="bg-white overflow-hidden shadow-md rounded-lg hover:shadow-lg transition-shadow duration-300">
                                <div class="p-6">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <span class="inline-block px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800 rounded-full mb-2"><?php echo htmlspecialchars($activity['category']); ?></span>
                                            <h4 class="text-lg font-semibold text-gray-900 mb-1 line-clamp-2"><?php echo htmlspecialchars($activity['title']); ?></h4>
                                            <p class="text-sm text-gray-500 mb-3">oleh <?php echo htmlspecialchars($activity['organization_name']); ?></p>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600 line-clamp-3"><?php echo substr(htmlspecialchars($activity['description']), 0, 120) . '...'; ?></p>
                                    </div>
                                    <div class="flex flex-col space-y-2 text-sm">
                                        <div class="flex items-center text-gray-500">
                                            <i class="fas fa-map-marker-alt w-5"></i>
                                            <span><?php echo htmlspecialchars($activity['location']); ?></span>
                                        </div>
                                        <div class="flex items-center text-gray-500">
                                            <i class="far fa-calendar-alt w-5"></i>
                                            <span><?php echo date('d M Y', strtotime($activity['event_date'])); ?></span>
                                        </div>
                                        <div class="flex items-center text-gray-500">
                                            <i class="fas fa-clock w-5"></i>
                                            <span>Deadline: <?php echo date('d M Y', strtotime($activity['application_deadline'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="mt-5">
                                        <a href="view_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center justify-center w-full px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-300">
                                            <i class="fas fa-info-circle mr-1"></i> Lihat Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white overflow-hidden shadow rounded-lg p-8 text-center">
                        <img src="https://illustrations.popsy.co/amber/not-found.svg" alt="Not Found" class="w-64 h-64 mx-auto mb-6">
                        <p class="text-gray-500 text-lg mb-4">Tidak ada lowongan volunteer yang sesuai dengan kriteria pencarian Anda.</p>
                        <a href="search.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-redo mr-2"></i> Reset Pencarian
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 mt-12">
            <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
                <div class="md:flex md:items-center md:justify-between">
                    <div class="flex justify-center md:order-2">
                        <p class="text-center text-gray-400">&copy; 2023 VolunteerHub. All rights reserved.</p>
                    </div>
                    <div class="mt-8 md:mt-0 md:order-1">
                        <p class="text-center text-base text-gray-400">Made with ❤️ for community impact</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <script>
        document.getElementById('advancedSearchToggle').addEventListener('click', function() {
            const advancedForm = document.getElementById('advancedSearchForm');
            advancedForm.classList.toggle('hidden');
            
            // Scroll to form when opened
            if (!advancedForm.classList.contains('hidden')) {
                advancedForm.scrollIntoView({ behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>