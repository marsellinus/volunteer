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
$query = "SELECT va.*, o.name as organization_name, 
         (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count 
         FROM volunteer_activities va 
         JOIN owners o ON va.owner_id = o.owner_id 
         WHERE va.application_deadline >= CURDATE() ";

// Add advanced search filters
if (!empty($search)) {
    $search_terms = explode(' ', $conn->real_escape_string($search));
    $search_conditions = [];
    
    foreach ($search_terms as $term) {
        if (strlen($term) < 2) continue; // Skip very short terms
        
        $search_conditions[] = "(va.title LIKE '%$term%' OR 
                                va.description LIKE '%$term%' OR 
                                va.category LIKE '%$term%' OR 
                                va.location LIKE '%$term%' OR
                                o.name LIKE '%$term%' OR
                                o.organization_name LIKE '%$term%')";
    }
    
    if (!empty($search_conditions)) {
        $query .= "AND (" . implode(' OR ', $search_conditions) . ") ";
    }
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

// Add relevance scoring for better sorting
if (!empty($search)) {
    $relevance_formula = [];
    foreach ($search_terms as $term) {
        if (strlen($term) < 2) continue;
        
        $relevance_formula[] = "IF(va.title LIKE '%$term%', 10, 0)"; // Title matches are most important
        $relevance_formula[] = "IF(va.category LIKE '%$term%', 5, 0)"; // Category matches
        $relevance_formula[] = "IF(va.description LIKE '%$term%', 3, 0)"; // Description matches
        $relevance_formula[] = "IF(va.location LIKE '%$term%', 3, 0)"; // Location matches
        $relevance_formula[] = "IF(o.name LIKE '%$term%', 2, 0)"; // Organizer name matches
    }
    
    $relevance_score = "(" . implode(" + ", $relevance_formula) . ")";
    $query .= ", $relevance_score AS relevance ";
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
        // If we have a search term, prioritize relevance
        if (!empty($search)) {
            $query .= "ORDER BY relevance DESC, va.event_date ASC";
        } else {
            $query .= "ORDER BY va.event_date ASC";
        }
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

// Get unread notifications count with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

// Check if the volunteer_activities table is empty
$check_empty = $conn->query("SELECT COUNT(*) as count FROM volunteer_activities");
$is_empty = ($check_empty && $check_empty->fetch_assoc()['count'] == 0);

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
function getCategoryColor($category) {
    global $category_colors;
    return isset($category_colors[$category]) ? $category_colors[$category] : 'gray';
}

$page_title = 'Cari Kegiatan Volunteer - VolunteerHub';
include '../../includes/header_user.php';
?>

<!-- Main Content -->
<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <!-- Search Banner -->
    <div class="px-4 py-10 sm:px-6 bg-gradient-to-r from-indigo-700 via-purple-700 to-pink-600 rounded-2xl shadow-xl text-white mb-10 relative overflow-hidden">
        <div class="absolute inset-0 bg-pattern opacity-10"></div>
        <div class="relative z-10 max-w-4xl mx-auto text-center">
            <h2 class="text-3xl md:text-4xl font-extrabold mb-4 text-white">Temukan Kesempatan Volunteer</h2>
            <p class="text-xl text-indigo-100 mb-8 max-w-3xl mx-auto">Gabung dengan kegiatan volunteer sesuai minat dan kemampuan Anda</p>
            
            <!-- Quick Search Form -->
            <form action="search.php" method="GET" class="mt-4">
                <div class="flex flex-col md:flex-row gap-2">
                    <div class="flex-grow">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari kesempatan volunteer..." class="w-full px-6 py-4 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 text-gray-800 text-lg shadow-md">
                    </div>
                    <button type="submit" class="px-8 py-4 bg-indigo-800 hover:bg-indigo-900 rounded-r-lg transition-colors duration-300 shadow-md">
                        <i class="fas fa-search mr-2"></i> Cari
                    </button>
                </div>
            </form>
            
            <button id="advancedSearchToggle" class="mt-4 text-md text-indigo-200 hover:text-white flex items-center mx-auto">
                <i class="fas fa-sliders-h mr-2"></i> Filter Lanjutan
                <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd"></path>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Advanced Search Form (hidden by default) -->
    <div id="advancedSearchForm" class="px-4 py-6 sm:px-0 mb-8 hidden">
        <div class="bg-white shadow-lg rounded-xl px-6 py-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <i class="fas fa-filter mr-2 text-indigo-600"></i>
                Filter Pencarian Lanjutan
            </h3>
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
    
    <!-- Recommended Section (only shown if no search parameters) -->
    <?php if($recommendedActivities && $recommendedActivities->num_rows > 0 && empty($search) && empty($category) && empty($location)): ?>
    <div class="mt-6 px-4 py-6 sm:px-0">
        <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <div class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center mr-2">
                <i class="fas fa-star text-sm"></i>
            </div>
            Rekomendasi Untuk Anda
        </h3>
        
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <?php while($activity = $recommendedActivities->fetch_assoc()): 
                $color = getCategoryColor($activity['category']);
            ?>
                <div class="bg-white overflow-hidden shadow-lg hover:shadow-2xl transition-shadow duration-300 rounded-xl flex flex-col">
                    <div class="h-40 bg-gradient-to-r from-<?php echo $color; ?>-500 to-<?php echo $color; ?>-600 relative overflow-hidden">
                        <div class="absolute inset-0 flex items-center justify-center text-white text-opacity-30 font-bold text-4xl">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <div class="absolute bottom-0 w-full p-4 bg-gradient-to-t from-black to-transparent">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white text-gray-800 shadow-md">
                                <?php echo date('d M Y', strtotime($activity['event_date'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-5 flex-grow">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 hover:text-indigo-600"><?php echo htmlspecialchars($activity['title']); ?></h4>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 mt-2">
                                    <?php echo htmlspecialchars($activity['category']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-sm text-gray-600 line-clamp-3"><?php echo substr(htmlspecialchars($activity['description']), 0, 100) . '...'; ?></p>
                        </div>
                        <div class="mt-4 flex items-center text-sm text-gray-500">
                            <i class="fas fa-map-marker-alt text-<?php echo $color; ?>-500 mr-1"></i>
                            <?php echo htmlspecialchars($activity['location']); ?>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-gray-50 text-right border-t">
                        <a href="view_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition duration-150">
                            Lihat Detail
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Search Results -->
    <div class="px-4 sm:px-0">
        <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
            <?php if(!empty($search) || !empty($category) || !empty($location) || !empty($date_from) || !empty($date_to)): ?>
                <div class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center mr-2">
                    <i class="fas fa-search text-sm"></i>
                </div>
                Hasil Pencarian
            <?php else: ?>
                <div class="w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center mr-2">
                    <i class="fas fa-list text-sm"></i>
                </div>
                Semua Lowongan Volunteer
            <?php endif; ?>
        </h3>
        
        <?php if($activities && $activities->num_rows > 0): ?>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php while($activity = $activities->fetch_assoc()): 
                    $color = getCategoryColor($activity['category']);
                ?>
                    <div class="bg-white overflow-hidden shadow-lg hover:shadow-2xl transition-shadow duration-300 rounded-xl flex flex-col">
                        <div class="h-40 bg-gradient-to-r from-<?php echo $color; ?>-500 to-<?php echo $color; ?>-600 relative overflow-hidden">
                            <div class="absolute inset-0 flex items-center justify-center text-white text-opacity-30 font-bold text-4xl">
                                <i class="fas fa-hands-helping"></i>
                            </div>
                            <div class="absolute bottom-0 w-full p-4 bg-gradient-to-t from-black to-transparent">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-white text-gray-800 shadow-md">
                                    <?php echo date('d M Y', strtotime($activity['event_date'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-5 flex-grow">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 hover:text-indigo-600"><?php echo htmlspecialchars($activity['title']); ?></h4>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800 mt-2">
                                        <?php echo htmlspecialchars($activity['category']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 line-clamp-3"><?php echo substr(htmlspecialchars($activity['description']), 0, 100) . '...'; ?></p>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-gray-500">
                                <i class="fas fa-map-marker-alt text-<?php echo $color; ?>-500 mr-1"></i>
                                <?php echo htmlspecialchars($activity['location']); ?>
                            </div>
                        </div>
                        <div class="px-5 py-3 bg-gray-50 text-right border-t">
                            <a href="view_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm transition duration-150">
                                Lihat Detail
                                <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white overflow-hidden shadow rounded-lg p-8 text-center">
                <div class="flex flex-col items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <?php if($is_empty): ?>
                        <p class="text-gray-500 text-lg mb-4">Belum ada kegiatan volunteer yang tersedia.</p>
                        <p class="text-gray-500 mb-4">Jalankan script pengisian data contoh untuk mencoba fitur pencarian.</p>
                        <a href="../../setup/seed_data.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                            <i class="fas fa-database mr-2"></i> Isi Database dengan Data Contoh
                        </a>
                    <?php elseif(!empty($search) || !empty($category) || !empty($location) || !empty($date_from) || !empty($date_to)): ?>
                        <p class="text-gray-500 text-lg mb-4">Tidak ada lowongan volunteer yang sesuai dengan kriteria pencarian Anda.</p>
                        <a href="search.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
                            <i class="fas fa-undo mr-2"></i> Reset Pencarian
                        </a>
                    <?php else: ?>
                        <p class="text-gray-500 text-lg mb-4">Belum ada lowongan volunteer yang tersedia saat ini.</p>
                        <p class="text-gray-500">Coba lagi nanti atau ubah kriteria pencarian Anda.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../../includes/footer.php'; ?>

<style>
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.bg-pattern {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath fill-rule='evenodd' d='M0 0h40v40H0V0zm40 40h40v40H40V40zm0-40h2l-2 2V0zm0 4l4-4h2l-6 6V4zm0 4l8-8h2L40 10V8zm0 4L52 0h2L40 14v-2zm0 4L56 0h2L40 18v-2zm0 4L60 0h2L40 22v-2zm0 4L64 0h2L40 26v-2zm0 4L68 0h2L40 30v-2zm0 4L72 0h2L40 34v-2zm0 4L76 0h2L40 38v-2zm0 4L80 0v2L42 40h-2zm4 0L80 4v2L46 40h-2zm4 0L80 8v2L50 40h-2zm4 0l28-28v2L54 40h-2zm4 0l24-24v2L58 40h-2zm4 0l20-20v2L62 40h-2zm4 0l16-16v2L66 40h-2zm4 0l12-12v2L70 40h-2zm4 0l8-8v2l-6 6h-2zm4 0l4-4v2l-2 2h-2z'/%3E%3C/g%3E%3C/svg%3E");
}
</style>

<script>
    // Advanced search toggle functionality
    document.getElementById('advancedSearchToggle').addEventListener('click', function() {
        const advancedForm = document.getElementById('advancedSearchForm');
        advancedForm.classList.toggle('hidden');
        
        // Scroll to form when opened
        if (!advancedForm.classList.contains('hidden')) {
            advancedForm.scrollIntoView({ behavior: 'smooth' });
        }
    });
</script>