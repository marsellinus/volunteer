<?php
ob_start();
include_once '../config/database.php';
session_start();

// Get activity ID from URL parameter
$activity_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($activity_id <= 0) {
    header("Location: index.php");
    exit;
}

// Get activity details
$stmt = $conn->prepare("SELECT va.*, o.name as owner_name 
                       FROM volunteer_activities va 
                       JOIN owners o ON va.owner_id = o.owner_id 
                       WHERE va.id = ?");
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$activity = $result->fetch_assoc();

// Log activity view for logged-in users
if(isset($_SESSION['user_id']) && file_exists('../logic/recommendation.php')) {
    include_once '../logic/recommendation.php';
    logActivityView($_SESSION['user_id'], $activity_id, $conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activity['title']); ?> - VolunteerHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#10283f',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="index.php" class="text-xl font-bold text-primary">VolunteerHub</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="../dashboard/user" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="../auth/logout.php" class="ml-4 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-opacity-90">Logout</a>
                    <?php elseif(isset($_SESSION['owner_id'])): ?>
                        <a href="../dashboard/owner" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="../auth/logout.php" class="ml-4 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-opacity-90">Logout</a>
                    <?php else: ?>
                        <a href="../auth/login.php" class="text-gray-700 hover:text-primary px-3 py-2 rounded-md text-sm font-medium">Login</a>
                        <a href="../auth/register.php" class="ml-4 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-opacity-90">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center text-sm text-primary hover:text-opacity-80">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to home
            </a>
        </div>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($activity['title']); ?></h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Organized by <?php echo htmlspecialchars($activity['owner_name']); ?>
                </p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                <dl class="sm:divide-y sm:divide-gray-200">
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Category</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($activity['category']); ?></dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Location</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?php echo htmlspecialchars($activity['location']); ?></dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Event Date</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo date('F d, Y', strtotime($activity['event_date'])); ?>
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Application Deadline</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo date('F d, Y', strtotime($activity['application_deadline'])); ?>
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Required Skills</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <?php echo !empty($activity['required_skills']) ? htmlspecialchars($activity['required_skills']) : 'No specific skills required'; ?>
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Description</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <p><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                        </dd>
                    </div>
                </dl>
            </div>
            
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="../dashboard/user/view_activity.php?id=<?php echo $activity_id; ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary hover:bg-opacity-90">
                        Apply Now
                    </a>
                <?php else: ?>
                    <div class="bg-gray-50 p-4 rounded-md">
                        <p class="text-sm text-gray-700">You need to be logged in to apply for this volunteer opportunity.</p>
                        <div class="mt-3">
                            <a href="../auth/login.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-primary bg-primary bg-opacity-10 hover:bg-opacity-20">
                                Login
                            </a>
                            <a href="../auth/register.php" class="ml-3 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-opacity-90">
                                Register
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
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
</body>
</html>
<?php
ob_end_flush();
?>