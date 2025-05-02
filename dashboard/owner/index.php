<?php
include_once '../../config/database.php';
session_start();

// Check if owner is logged in
if(!isset($_SESSION['owner_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$owner_name = $_SESSION['owner_name'];

// Get all activities created by this owner
$activities = $conn->query("SELECT * FROM volunteer_activities WHERE owner_id = $owner_id ORDER BY created_at DESC");

// Get recent applications for owner's activities
$applications = $conn->query("SELECT a.*, u.name as user_name, va.title as activity_title 
                            FROM applications a 
                            JOIN users u ON a.user_id = u.user_id 
                            JOIN volunteer_activities va ON a.activity_id = va.id 
                            WHERE va.owner_id = $owner_id 
                            ORDER BY a.applied_at DESC 
                            LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - VolunteerHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-indigo-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-xl font-bold text-white">VolunteerHub</h1>
                        </div>
                        <div class="ml-6 flex items-center space-x-4">
                            <a href="index.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <a href="create_activity.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">Create Activity</a>
                            <a href="manage_activities.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">Manage Activities</a>
                            <a href="profile.php" class="text-white hover:text-indigo-100 px-3 py-2 rounded-md text-sm font-medium">Profile</a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">Hello, <?php echo htmlspecialchars($owner_name); ?></span>
                        <a href="../../auth/logout.php" class="bg-indigo-700 hover:bg-indigo-800 text-white px-3 py-2 rounded-md text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="px-4 py-6 sm:px-0">
                <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars($owner_name); ?>!</h2>
                <p class="mt-1 text-sm text-gray-600">Manage your volunteer activities and applicants here.</p>
            </div>
            
            <!-- Quick Stats -->
            <div class="px-4 py-6 sm:px-0">
                <dl class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div class="px-4 py-5 bg-white shadow rounded-lg overflow-hidden sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Activities</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900"><?php echo $activities->num_rows; ?></dd>
                    </div>
                    <div class="px-4 py-5 bg-white shadow rounded-lg overflow-hidden sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">Upcoming Events</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900">
                            <?php
                                $upcoming = $conn->query("SELECT COUNT(*) as count FROM volunteer_activities WHERE owner_id = $owner_id AND event_date >= CURDATE()");
                                $upcoming_count = $upcoming->fetch_assoc();
                                echo $upcoming_count['count'];
                            ?>
                        </dd>
                    </div>
                    <div class="px-4 py-5 bg-white shadow rounded-lg overflow-hidden sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Applicants</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900">
                            <?php
                                $applicants = $conn->query("SELECT COUNT(*) as count FROM applications a 
                                                          JOIN volunteer_activities va ON a.activity_id = va.id 
                                                          WHERE va.owner_id = $owner_id");
                                $applicants_count = $applicants->fetch_assoc();
                                echo $applicants_count['count'];
                            ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- My Activities Section -->
            <div class="px-4 py-6 sm:px-0">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">My Activities</h3>
                    <a href="create_activity.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="-ml-0.5 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Activity
                    </a>
                </div>
                <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-md">
                    <ul role="list" class="divide-y divide-gray-200">
                        <?php if($activities && $activities->num_rows > 0): ?>
                            <?php while($activity = $activities->fetch_assoc()): ?>
                                <li>
                                    <a href="edit_activity.php?id=<?php echo $activity['id']; ?>" class="block hover:bg-gray-50">
                                        <div class="px-4 py-4 sm:px-6">
                                            <div class="flex items-center justify-between">
                                                <div class="sm:flex sm:justify-between w-full">
                                                    <p class="text-sm font-medium text-indigo-600 truncate">
                                                        <?php echo htmlspecialchars($activity['title']); ?>
                                                    </p>
                                                    <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span>
                                                            <?php echo date('M d, Y', strtotime($activity['event_date'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2 sm:flex sm:justify-between">
                                                <div class="sm:flex">
                                                    <p class="flex items-center text-sm text-gray-500">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <?php echo htmlspecialchars($activity['location']); ?>
                                                    </p>
                                                </div>
                                                <?php
                                                    $applicationCount = $conn->query("SELECT COUNT(*) as count FROM applications WHERE activity_id = " . $activity['id']);
                                                    $appCount = $applicationCount->fetch_assoc();
                                                    $status = "Active";
                                                    $status_color = "green";
                                                    
                                                    if(strtotime($activity['event_date']) < time()) {
                                                        $status = "Past";
                                                        $status_color = "gray";
                                                    } elseif(strtotime($activity['application_deadline']) < time()) {
                                                        $status = "Closed";
                                                        $status_color = "yellow";
                                                    }
                                                ?>
                                                <div class="mt-2 flex items-center text-sm text-gray-500 sm:mt-0">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 mr-2">
                                                        <?php echo $status; ?>
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?php echo $appCount['count']; ?> Applicants
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="px-4 py-5 sm:px-6">
                                <p class="text-gray-500 text-center">You haven't created any volunteer activities yet.</p>
                                <div class="mt-4 flex justify-center">
                                    <a href="create_activity.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Create Your First Activity
                                    </a>
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="mt-8 px-4 py-6 sm:px-0">
                <h3 class="text-lg font-semibold text-gray-900">Recent Applications</h3>
                <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-md">
                    <ul role="list" class="divide-y divide-gray-200">
                        <?php if($applications && $applications->num_rows > 0): ?>
                            <?php while($app = $applications->fetch_assoc()): ?>
                                <li>
                                    <a href="view_application.php?id=<?php echo $app['id']; ?>" class="block hover:bg-gray-50">
                                        <div class="px-4 py-4 sm:px-6">
                                            <div class="flex items-center justify-between">
                                                <div class="text-sm font-medium text-indigo-600 truncate">
                                                    <?php echo htmlspecialchars($app['user_name']); ?> - <?php echo htmlspecialchars($app['activity_title']); ?>
                                                </div>
                                                <div class="ml-2 flex-shrink-0 flex">
                                                    <?php
                                                        $status_color = 'gray';
                                                        if($app['status'] == 'approved') $status_color = 'green';
                                                        elseif($app['status'] == 'rejected') $status_color = 'red';
                                                        elseif($app['status'] == 'pending') $status_color = 'yellow';
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex justify-between">
                                                <div class="flex items-center text-sm text-gray-500">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                    </svg>
                                                    Applied on <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="px-4 py-5 sm:px-6">
                                <p class="text-gray-500 text-center">No applications received yet.</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
