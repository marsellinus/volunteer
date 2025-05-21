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

// Process search terms for relevance calculation if search is not empty
$relevance_select = '';
if (!empty($search)) {
    $search_terms = explode(' ', $conn->real_escape_string($search));
    $relevance_formula = [];
    
    foreach ($search_terms as $term) {
        if (strlen($term) < 2) continue; // Skip very short terms
        
        $relevance_formula[] = "IF(va.title LIKE '%$term%', 10, 0)"; // Title matches are most important
        $relevance_formula[] = "IF(va.category LIKE '%$term%', 5, 0)"; // Category matches
        $relevance_formula[] = "IF(va.description LIKE '%$term%', 3, 0)"; // Description matches
        $relevance_formula[] = "IF(va.location LIKE '%$term%', 3, 0)"; // Location matches
        $relevance_formula[] = "IF(o.name LIKE '%$term%', 2, 0)"; // Organizer name matches
    }
    
    if (!empty($relevance_formula)) {
        $relevance_select = ", (" . implode(" + ", $relevance_formula) . ") AS relevance";
    }
}

// Build advanced search query WITH relevance calculation already included
$query = "SELECT va.*, o.name as organization_name, 
         (SELECT COUNT(*) FROM applications WHERE activity_id = va.id) as application_count
         $relevance_select 
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
        if (!empty($search) && !empty($relevance_select)) {
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

// All categories now use color-specific backgrounds for visual differentiation
$category_colors = [
    'Education' => 'bg-blue-100 text-blue-800',
    'Environment' => 'bg-green-100 text-green-800',
    'Health' => 'bg-red-100 text-red-800',
    'Community Service' => 'bg-yellow-100 text-yellow-800',
    'Animal Welfare' => 'bg-purple-100 text-purple-800',
    'Arts & Culture' => 'bg-pink-100 text-pink-800',
    'Disaster Relief' => 'bg-orange-100 text-orange-800',
    'Human Rights' => 'bg-indigo-100 text-indigo-800',
    'Sports' => 'bg-teal-100 text-teal-800',
    'Technology' => 'bg-gray-100 text-gray-800'
];

// Images for categories
$category_images = [
    'Education' => 'education.jpg',
    'Environment' => 'environment.jpg',
    'Health' => 'health.jpg',
    'Community Service' => 'community.jpg',
    'Animal Welfare' => 'animal.jpg',
    'Arts & Culture' => 'arts.jpg',
    'Disaster Relief' => 'disaster.jpg',
    'Human Rights' => 'human-rights.jpg',
    'Sports' => 'sports.jpg',
    'Technology' => 'technology.jpg'
];

// Get category color
function getCategoryColor($category) {
    global $category_colors;
    return isset($category_colors[$category]) ? $category_colors[$category] : 'bg-gray-100 text-gray-800';
}

// Get category image
function getCategoryImage($category) {
    global $category_images;
    return isset($category_images[$category]) ? $category_images[$category] : 'default.jpg';
}

$page_title = 'Cari Kegiatan Volunteer - VolunteerHub';
include '../../includes/header_user.php';
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Inter Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Helvetica:wght@300;400;500;600;700&display=swap');
        
        html, body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            background-color: #e5e5e5 !important;
            margin: 0;
            padding: 0;
        }

        /* Override Tailwind's bg-white if necessary */
        .bg-override {
            background-color: #e5e5e5 !important;
        }

        /* 3D Transform styles */
        .transform-3d {
            transform-style: preserve-3d;
        }
        
        /* Backdrop styles */
        .hero-backdrop {
            background-color: rgba(12, 29, 45, 0.94);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        
        /* Black background for marquee */
        .marquee-bg {
            background-color: #000000;
        }
    </style>
</head>
<body class="bg-[#e5e5e5]" style="background-color: #e5e5e5;">

<!-- Main Content with Header Spacing Fix -->
<main class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8 mt-16 md:mt-20">
    <!-- Hero Section with 3D Marquee Background -->
<div class="relative overflow-hidden rounded-2xl shadow-2xl mb-16">
    <!-- 3D Marquee Background -->
    <div class="absolute inset-0 overflow-hidden marquee-bg">
        <?php
        // Images array for the 3D marquee using local assets
        $images = [
            "/VOLUNTEER-MAIN/assets/volunteer1.jpg",
            "/VOLUNTEER-MAIN/assets/volunteer2.jpg",
            "/VOLUNTEER-MAIN/assets/volunteer3.jpg",
            "/VOLUNTEER-MAIN/assets/volunteer4.jpg",
            "/VOLUNTEER-MAIN/assets/volunteer5.jpg",
            "/VOLUNTEER-MAIN/assets/volunteer6.jpg",
            "/VOLUNTEER-MAIN/assets/volunteer7.jpg"
        ];
        
        // Repeat the images to ensure we have enough
        $repeatedImages = [];
        for ($i = 0; $i < 5; $i++) {
            $repeatedImages = array_merge($repeatedImages, $images);
        }
        $images = array_slice($repeatedImages, 0, 35); // Ensure we have enough images
        
        // Split the images array into 4 equal parts
        $chunkSize = ceil(count($images) / 4);
        $chunks = array_chunk($images, $chunkSize);
        ?>
        
        <div class="mx-auto block h-[600px] overflow-hidden">
            <div class="flex size-full items-center justify-center">
                <div class="size-[1720px] shrink-0 scale-50 sm:scale-75 lg:scale-100">
                    <div 
                        id="marqueeContainer"
                        class="relative top-96 right-[50%] grid size-full origin-top-left grid-cols-4 gap-8 transform-3d"
                        style="transform: rotateX(55deg) rotateY(0deg) rotateZ(-45deg); transform-style: preserve-3d;">
                        
                        <?php foreach ($chunks as $colIndex => $subarray): ?>
                            <div class="flex flex-col items-start gap-8 marquee-column" data-column="<?php echo $colIndex; ?>">
                                <!-- Grid line vertical -->
                                <div 
                                    class="absolute -left-4 h-full w-[1px] z-30"
                                    style="
                                        background-image: linear-gradient(to bottom, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.2) 50%, transparent 0, transparent);
                                        background-size: 1px 5px;
                                        top: -40px;
                                        height: calc(100% + 80px);
                                    ">
                                </div>
                                
                                <?php foreach ($subarray as $imageIndex => $image): ?>
                                    <div class="relative">
                                        <!-- Grid line horizontal -->
                                        <div 
                                            class="absolute -top-4 w-full h-[1px] z-30"
                                            style="
                                                background-image: linear-gradient(to right, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.2) 50%, transparent 0, transparent);
                                                background-size: 5px 1px;
                                                left: -10px;
                                                width: calc(100% + 20px);
                                            ">
                                        </div>
                                        
                                        <div class="marquee-image-container relative aspect-[970/700] rounded-lg overflow-hidden ring ring-white/10 hover:shadow-2xl transition-transform duration-300" style="transform-style: preserve-3d;">
                                            <img 
                                                src="<?php echo htmlspecialchars($image); ?>" 
                                                alt="Volunteer Activity Image" 
                                                class="marquee-image w-full h-full object-cover grayscale" 
                                                loading="lazy"
                                                onerror="this.onerror=null; this.src='/VOLUNTEER-MAIN/assets/volunteer1.jpg';">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Overlay with backdrop filter -->
    <div class="absolute inset-0 hero-backdrop"></div>
    
    <!-- Hero Content -->
    <div class="relative px-8 py-20 sm:px-16 sm:py-24 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl md:text-6xl bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-300">
            Temukan Kesempatan Volunteer
        </h1>
        <p class="mt-6 max-w-3xl mx-auto text-xl text-gray-300 font-light">
            Gabung dengan kegiatan volunteer sesuai minat dan kemampuan Anda
        </p>
        
        <!-- Quick Search Form -->
        <form action="search.php" method="GET" class="mt-12 max-w-2xl mx-auto">
            <div class="flex flex-col sm:flex-row shadow-2xl rounded-full overflow-hidden backdrop-blur-sm bg-white/5 border border-white/10">
                <div class="flex-grow">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Cari kesempatan volunteer..." class="w-full px-8 py-5 bg-transparent focus:outline-none focus:ring-0 text-white placeholder-gray-400 text-md">
                </div>
                <button type="submit" class="bg-white/90 backdrop-blur-sm text-[#10283f] hover:bg-white px-8 py-5 font-medium transition-all duration-300 flex items-center justify-center rounded-full m-1 sm:rounded-l-none sm:m-0">
                    <i class="fas fa-search mr-2"></i> Cari
                </button>
            </div>
        </form>
        
        <button id="advancedSearchToggle" class="mt-8 text-md text-gray-300 hover:text-white flex items-center mx-auto transition-colors duration-200 group bg-white/5 hover:bg-white/10 px-5 py-2 rounded-full backdrop-blur-sm">
            <i class="fas fa-sliders-h mr-2"></i> Filter Lanjutan
            <svg class="w-4 h-4 ml-1 transition-transform duration-300 group-hover:rotate-180" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd"></path>
            </svg>
        </button>
    </div>
</div>
    
    <!-- Advanced Search Form (hidden by default) -->
    <!-- Advanced Search Form (hidden by default) -->
    <div id="advancedSearchForm" class="mb-16 hidden">
        <div class="bg-white shadow-xl rounded-2xl p-8 border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-filter mr-2 text-[#10283f]"></i>
                Filter Pencarian Lanjutan
            </h3>
            <form action="search.php" method="GET">
                <div class="grid grid-cols-1 gap-y-6 gap-x-6 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                            Kata Kunci
                        </label>
                        <div class="mt-1">
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-lg" placeholder="Masukkan kata kunci...">
                        </div>
                    </div>
                    
                    <div class="sm:col-span-3">
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                            Kategori
                        </label>
                        <div class="mt-1">
                            <select id="category" name="category" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-lg">
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
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-1">
                            Lokasi
                        </label>
                        <div class="mt-1">
                            <select id="location" name="location" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-lg">
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
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Mulai
                        </label>
                        <div class="mt-1">
                            <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="sm:col-span-2">
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">
                            Tanggal Selesai
                        </label>
                        <div class="mt-1">
                            <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="sm:col-span-2">
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">
                            Urutan
                        </label>
                        <div class="mt-1">
                            <select id="sort" name="sort" class="shadow-sm focus:ring-[#10283f] focus:border-[#10283f] block w-full sm:text-sm border-gray-300 rounded-lg">
                                <option value="date_asc" <?php echo ($sort == 'date_asc') ? 'selected' : ''; ?>>Tanggal (terlama)</option>
                                <option value="date_desc" <?php echo ($sort == 'date_desc') ? 'selected' : ''; ?>>Tanggal (terbaru)</option>
                                <option value="title_asc" <?php echo ($sort == 'title_asc') ? 'selected' : ''; ?>>Judul (A-Z)</option>
                                <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Baru ditambahkan</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <button type="reset" class="mr-4 inline-flex items-center px-5 py-2.5 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f] transition-colors duration-200">
                        Reset
                    </button>
                    <button type="submit" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-[#10283f] hover:bg-[#0a1828] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10283f] transition-all duration-200">
                        <i class="fas fa-search mr-2"></i> Cari
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if($recommendedActivities && $recommendedActivities->num_rows > 0 && empty($search) && empty($category) && empty($location)): ?>
    <div class="mb-16">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 flex items-center">
            <div class="w-10 h-10 bg-[#10283f] text-white rounded-xl flex items-center justify-center mr-3">
                <i class="fas fa-star text-sm"></i>
            </div>
            Rekomendasi Untuk Anda
        </h2>
        
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php 
            // Limit to just 3 cards
            $count = 0;
            while(($activity = $recommendedActivities->fetch_assoc()) && $count < 3): 
                $count++;
                $categoryColor = getCategoryColor($activity['category']);
                
                // Debug the image path (remove this in production)
                // echo "<!-- Image path: " . $activity['images'] . " -->";
                
                // Set the image path - directly use what's in database with proper prefix
                if (!empty($activity['images'])) {
                    $imagePath = "../../" . $activity['images']; // Go up two levels from current directory
                } else {
                    $imagePath = "../../uploads/activities/default.jpg"; // Default fallback
                }
            ?>
                <div class="bg-white overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 rounded-2xl flex flex-col border border-gray-100 group">
                    <div class="h-52 relative overflow-hidden">
                        <!-- Use activity image with correct path -->
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($activity['title']); ?>" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                        
                        <!-- Gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                        
                        <!-- Category badge -->
                        <div class="absolute top-4 left-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $categoryColor; ?>">
                                <?php echo htmlspecialchars($activity['category']); ?>
                            </span>
                        </div>
                        
                        <!-- Date badge -->
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
                            <?php echo htmlspecialchars($activity['organization_name']); ?>
                        </div>
                        
                        <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                            <?php echo htmlspecialchars(substr($activity['description'], 0, 150)) . '...'; ?>
                        </p>
                        
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-map-marker-alt text-[#10283f] mr-2"></i>
                            <?php echo htmlspecialchars($activity['location']); ?>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-users mr-1"></i>
                            <?php echo $activity['application_count']; ?> Pendaftar
                        </div>
                        
                        <a href="view_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg bg-[#10283f] hover:bg-[#1a3b5c] text-white shadow-sm transition-all duration-200">
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
<div>
    <h2 class="text-2xl font-bold text-gray-900 mb-8 flex items-center">
        <?php if(!empty($search) || !empty($category) || !empty($location) || !empty($date_from) || !empty($date_to)): ?>
            <div class="w-10 h-10 bg-[#10283f] text-white rounded-xl flex items-center justify-center mr-3 shadow-lg">
                <i class="fas fa-search text-sm"></i>
            </div>
            Hasil Pencarian
        <?php else: ?>
            <div class="w-10 h-10 bg-[#10283f] text-white rounded-xl flex items-center justify-center mr-3 shadow-lg">
                <i class="fas fa-hands-helping text-sm"></i>
            </div>
            Semua Lowongan Volunteer
        <?php endif; ?>
    </h2>
    
    <?php if($activities && $activities->num_rows > 0): ?>
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php while($activity = $activities->fetch_assoc()): 
                $categoryColor = getCategoryColor($activity['category']);
                
                // Set the image path - directly use what's in database with proper prefix
                if (!empty($activity['images'])) {
                    $imagePath = "../../" . $activity['images']; // Go up two levels from current directory
                } else {
                    $imagePath = "../../uploads/activities/default.jpg"; // Default fallback
                }
            ?>
                <div class="bg-white overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 rounded-2xl flex flex-col border border-gray-100 group">
                    <div class="h-52 relative overflow-hidden">
                        <!-- Use activity image from database -->
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                             alt="<?php echo htmlspecialchars($activity['title']); ?>" 
                             class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                        
                        <!-- Gradient overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                        
                        <!-- Category badge -->
                        <div class="absolute top-4 left-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?php echo $categoryColor; ?>">
                                <?php echo htmlspecialchars($activity['category']); ?>
                            </span>
                        </div>
                        
                        <!-- Date badge -->
                        <div class="absolute bottom-4 left-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white text-gray-800 shadow-md">
                                <i class="far fa-calendar-alt mr-1"></i>
                                <?php echo date('d M Y', strtotime($activity['event_date'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6 flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 hover:text-indigo-600 transition-colors duration-200 mb-2">
                            <?php echo htmlspecialchars($activity['title']); ?>
                        </h3>
                        
                        <div class="text-sm text-gray-500 mb-4">
                            <i class="fas fa-building mr-2"></i>
                            <?php echo htmlspecialchars($activity['organization_name']); ?>
                        </div>
                        
                        <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                            <?php echo htmlspecialchars(substr($activity['description'], 0, 150)) . '...'; ?>
                        </p>
                        
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-map-marker-alt text-[#10283f] mr-2"></i>
                            <?php echo htmlspecialchars($activity['location']); ?>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-users mr-1"></i>
                            <?php echo $activity['application_count']; ?> Pendaftar
                        </div>
                        
                        <a href="view_activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg bg-[#10283f] hover:bg-[#1a3b5c] text-white shadow-sm transition-all duration-200">
                            Lihat Detail
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="bg-white overflow-hidden shadow-lg rounded-2xl p-10 text-center border border-gray-100">
            <div class="flex flex-col items-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-search text-gray-400 text-3xl"></i>
                </div>
                
                <?php if($is_empty): ?>
                    <h3 class="text-xl text-gray-800 mb-4 font-medium">Belum ada kegiatan volunteer yang tersedia.</h3>
                    <p class="text-gray-600 mb-8">Jalankan script pengisian data contoh untuk mencoba fitur pencarian.</p>
                    <a href="../../setup/seed_data.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl shadow-lg text-white bg-[#10283f] hover:bg-[#1a3b5c] transition-colors duration-200">
                        <i class="fas fa-database mr-2"></i> Isi Database dengan Data Contoh
                    </a>
                <?php elseif(!empty($search) || !empty($category) || !empty($location) || !empty($date_from) || !empty($date_to)): ?>
                    <h3 class="text-xl text-gray-800 mb-6 font-medium">Tidak ada lowongan volunteer yang sesuai dengan kriteria pencarian Anda.</h3>
                    <a href="search.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl shadow-lg text-white bg-[#10283f] hover:bg-[#1a3b5c] transition-colors duration-200">
                        <i class="fas fa-undo mr-2"></i> Reset Pencarian
                    </a><?php else: ?>
                    <h3 class="text-xl text-gray-800 mb-4 font-medium">Tidak ada kegiatan volunteer yang tersedia.</h3>
                    <p class="text-gray-600 mb-6">Silakan coba pencarian dengan kata kunci yang berbeda.</p>
                    <a href="search.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl shadow-lg text-white bg-[#10283f] hover:bg-[#1a3b5c] transition-colors duration-200">
                        <i class="fas fa-undo mr-2"></i> Reset Pencarian
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
    
    <!-- Category Browsing Section (only visible when not searching) -->
    <?php if(empty($search) && empty($category) && empty($location) && empty($date_from) && empty($date_to)): ?>
    <div class="mt-20">
        <h2 class="text-2xl font-bold text-gray-900 mb-8 flex items-center">
            <div class="w-10 h-10 bg-[#10283f] text-white rounded-xl flex items-center justify-center mr-3 shadow-lg">
                <i class="fas fa-th-large text-sm"></i>
            </div>
            Jelajahi Berdasarkan Kategori
        </h2>
        
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
            <?php 
            // Fetch categories from database
            $sql = "SELECT DISTINCT category FROM volunteer_activities";
            $result = $conn->query($sql);
            
            // Define icons for each category from database
            $category_icons = [
                'Lingkungan' => 'fas fa-leaf',
                'Kemanusiaan' => 'fas fa-hands-helping',
                'Pendidikan' => 'fas fa-book',
                'Kesejahteraan Hewan' => 'fas fa-paw',
                'Bantuan Bencana' => 'fas fa-house-damage'
            ];
            
            // Color variations based on #10283f
            $colors = [
                'bg-gradient-to-br from-[#10283f] to-[#1a3d61]', 
                'bg-gradient-to-br from-[#10283f] to-[#144068]',
                'bg-gradient-to-br from-[#10283f] to-[#0e4373]',
                'bg-gradient-to-br from-[#10283f] to-[#0c5183]',
                'bg-gradient-to-br from-[#10283f] to-[#095f93]',
                'bg-gradient-to-br from-[#10283f] to-[#07709c]'
            ];
            
            $color_index = 0;
            
            // Loop through categories from database
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $cat_name = $row['category'];
                    $icon = isset($category_icons[$cat_name]) ? $category_icons[$cat_name] : 'fas fa-tag';
                    $color = $colors[$color_index % count($colors)];
                    $color_index++;
            ?>
                <a href="search.php?category=<?php echo urlencode($cat_name); ?>" class="flex flex-col items-center justify-center py-8 px-4 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 text-center border border-gray-100 bg-white group hover:scale-105">
                    <div class="w-16 h-16 rounded-full <?php echo $color; ?> flex items-center justify-center mb-4 text-white shadow-lg group-hover:rotate-12 transition-transform duration-300">
                        <i class="<?php echo $icon; ?> text-xl"></i>
                    </div>
                    <h3 class="text-base font-medium text-gray-900 group-hover:text-[#10283f] transition-colors duration-200">
                        <?php echo $cat_name; ?>
                    </h3>
                </a>
            <?php 
                }
            }
            ?>
        </div>
    </div>
<?php endif; ?>
</main>


<!-- JavaScript for Toggle Advanced Search -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const advancedSearchToggle = document.getElementById('advancedSearchToggle');
        const advancedSearchForm = document.getElementById('advancedSearchForm');
        
        advancedSearchToggle.addEventListener('click', function() {
            if (advancedSearchForm.classList.contains('hidden')) {
                advancedSearchForm.classList.remove('hidden');
                // Smooth animation
                advancedSearchForm.style.height = '0';
                advancedSearchForm.style.opacity = '0';
                setTimeout(() => {
                    advancedSearchForm.style.height = 'auto';
                    advancedSearchForm.style.opacity = '1';
                    advancedSearchForm.style.transition = 'opacity 0.3s ease-in-out';
                }, 10);
            } else {
                advancedSearchForm.style.opacity = '0';
                setTimeout(() => {
                    advancedSearchForm.classList.add('hidden');
                }, 300);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
    // Select all marquee columns
    const marqueeColumns = document.querySelectorAll('.marquee-column');
    
// Animation function for each column
function animateColumns() {
    marqueeColumns.forEach((column, index) => {
        // Get column index
        const colIndex = parseInt(column.getAttribute('data-column'));
        
        // Set initial position
        column.style.transform = `translateY(0px)`;
        
        // Set animation parameters based on column index
        const distance = colIndex % 2 === 0 ? 100 : -100;
        const duration = colIndex % 2 === 0 ? 10000 : 15000; // 10s or 15s
        
        // Animate column
        animateColumn(column, distance, duration);
    });
}

// Function to animate a single column with continuous oscillation
function animateColumn(element, distance, duration) {
    // Store animation properties in the element to maintain state
    element.animProps = {
        startTime: performance.now(),
        distance: distance,
        duration: duration
    };
    
    // Start the continuous animation loop
    requestAnimationFrame(function animate(now) {
        const props = element.animProps;
        const elapsed = now - props.startTime;
        
        // Calculate position using sine function for smooth oscillation (0 to 1 to 0)
        // The sine wave creates a continuous back and forth motion
        const cycle = (elapsed % props.duration) / props.duration;
        const position = props.distance * Math.sin(cycle * Math.PI * 2);
        
        // Apply the transform
        element.style.transform = `translateY(${position}px)`;
        
        // Continue the animation loop indefinitely
        requestAnimationFrame(animate);
    });
}

// Initialize the animation
animateColumns();
});
</script>

</body>
<?php include '../../includes/footer.php'; ?>
</html>
<?php
// Close database connection
$conn->close();
?>