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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Hub - Find Your Perfect Volunteering Opportunity</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-indigo-600">VolunteerHub</h1>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="../dashboard/user" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="../auth/logout.php" class="ml-4 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Logout</a>
                    <?php elseif(isset($_SESSION['owner_id'])): ?>
                        <a href="../dashboard/owner" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="../auth/logout.php" class="ml-4 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Logout</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                        <a href="../auth/register.php" class="ml-4 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-indigo-700">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:py-24 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">Make a Difference Today</h1>
            <p class="mt-6 text-xl text-indigo-100 max-w-3xl mx-auto">Join thousands of volunteers who are changing their communities. Find the perfect volunteering opportunity that matches your skills and interests.</p>
            <div class="mt-10">
                <a href="../auth/register.php" class="px-8 py-3 border border-transparent text-base font-medium rounded-md text-indigo-700 bg-white hover:bg-indigo-50 md:py-4 md:text-lg md:px-10">Get Started</a>
            </div>
        </div>
    </div>

    <!-- Featured Opportunities Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-8">Featured Opportunities</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if($featured && $featured->num_rows > 0): ?>
                <?php while($activity = $featured->fetch_assoc()): ?>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($activity['title']); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['category']); ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-gray-600"><?php echo substr(htmlspecialchars($activity['description']), 0, 100) . '...'; ?></p>
                            </div>
                            <div class="mt-5 flex justify-between items-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <?php echo date('M d, Y', strtotime($activity['event_date'])); ?>
                                </span>
                                <a href="../public/activity.php?id=<?php echo $activity['id']; ?>" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-3 text-center py-10">
                    <p class="text-gray-500">No featured volunteer activities available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="bg-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h2 class="text-3xl font-extrabold text-gray-900 text-center mb-12">How It Works</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-lg font-medium text-gray-900">1. Sign Up</h3>
                    <p class="mt-2 text-base text-gray-600">Create your free account and complete your profile to get personalized recommendations.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-lg font-medium text-gray-900">2. Find Opportunities</h3>
                    <p class="mt-2 text-base text-gray-600">Browse through available volunteer opportunities or get matched with the perfect one.</p>
                </div>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-lg font-medium text-gray-900">3. Apply & Volunteer</h3>
                    <p class="mt-2 text-base text-gray-600">Apply for the opportunity, get accepted, and start making a positive impact.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800">
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
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>
