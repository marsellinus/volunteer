<?php
ob_start();
include_once '../config/database.php';
session_start();

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/user/search.php");
    exit;
} elseif(isset($_SESSION['owner_id'])) {
    header("Location: ../dashboard/owner/");
    exit;
}

$error = '';

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Enhanced validation with additional security checks
    if (empty($email) || empty($password)) {
        $error = "Silahkan isi email dan password";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid";
    } else {
        // Determine which table to query based on role
        $table = ($role === 'user') ? 'users' : 'owners';
        $id_column = ($role === 'user') ? 'user_id' : 'owner_id';
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT $id_column, name, email, password FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                if ($role === 'user') {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['last_activity'] = time(); // For session timeout
                    header("Location: ../dashboard/user/search.php");
                } else {
                    $_SESSION['owner_id'] = $user['owner_id'];
                    $_SESSION['owner_name'] = $user['name'];
                    $_SESSION['owner_email'] = $user['email'];
                    $_SESSION['last_activity'] = time(); // For session timeout
                    header("Location: ../dashboard/owner/");
                }
                exit;
            } else {
                $error = "Password salah";
                // Log failed login attempt for security
                error_log("Failed login attempt: $email at " . date('Y-m-d H:i:s'));
            }
        } else {
            $error = "Akun tidak ditemukan";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VolunteerHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .login-container {
            background-image: url('https://source.unsplash.com/random/1200x800/?volunteer');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 login-container">
        <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-xl shadow-xl bg-opacity-95">
            <div>
                <h1 class="text-center text-3xl font-extrabold text-indigo-600">VolunteerHub</h1>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Masuk ke Akun Anda</h2>
            </div>
            
            <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo $error; ?></p>
            </div>
            <?php endif; ?>
            
            <form class="mt-8 space-y-6" action="login.php" method="POST">
                <input type="hidden" name="remember" value="true">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="email" class="sr-only">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Email address">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Password">
                    </div>
                </div>
                
                <div class="flex items-center justify-center space-x-8">
                    <div class="flex items-center">
                        <input id="role-user" name="role" type="radio" value="user" checked class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <label for="role-user" class="ml-2 block text-sm text-gray-900">
                            Login sebagai Pencari Volunteer
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input id="role-owner" name="role" type="radio" value="owner" class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                        <label for="role-owner" class="ml-2 block text-sm text-gray-900">
                            Login sebagai Penyedia
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        Masuk
                    </button>
                </div>
            </form>
            
            <div class="text-center">
                <p class="mt-2 text-center text-sm text-gray-600">
                    Belum memiliki akun?
                    <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Daftar sekarang
                    </a>
                </p>
                <p class="mt-2 text-center text-sm text-gray-600">
                    <a href="../public/index.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Kembali ke beranda
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Add animation effects to form elements
        document.addEventListener('DOMContentLoaded', function() {
            const formElements = document.querySelectorAll('input, button');
            formElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
