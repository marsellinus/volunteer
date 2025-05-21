<?php
ob_start();
include_once '../config/database.php';
session_start();

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/user/");
    exit;
} elseif(isset($_SESSION['owner_id'])) {
    header("Location: ../dashboard/owner/");
    exit;
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Silahkan isi semua kolom";
    } elseif ($password !== $confirm_password) {
        $error = "Password tidak cocok";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid";
    } else {
        // Determine which table to insert into based on role
        $table = ($role === 'user') ? 'users' : 'owners';
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT email FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email sudah terdaftar";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO $table (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success = "Pendaftaran berhasil. Silahkan masuk ke akun Anda.";
            } else {
                $error = "Pendaftaran gagal. Silahkan coba lagi nanti.";
            }
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
    <title>Register - Volunite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Helvetica:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            background-color: #e5e5e5;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* 3D Transform styles */
        .transform-3d {
            transform-style: preserve-3d;
        }
        
        /* Backdrop styles */
        .hero-backdrop {
            background-color: rgba(16, 40, 63, 0.85);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        
        /* Black background for marquee */
        .marquee-bg {
            background-color: #000000;
        }
        
        .register-container {
            position: relative;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
        }
        
        .register-form-container {
            position: relative;
            z-index: 10;
        }
        
        /* Custom focus styles for inputs */
        input:focus {
            outline: none;
            border-color: #10283f !important;
            box-shadow: 0 0 0 3px rgba(16, 40, 63, 0.2) !important;
        }
    </style>
    <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#10283f',
              hover: '#1a3b58',
            }
          }
        }
      }
    }
    </script>
</head>
<body class="bg-gray-100">
    <div class="register-container flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
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
            for ($i = 0; $i < 8; $i++) {
                $repeatedImages = array_merge($repeatedImages, $images);
            }
            $images = array_slice($repeatedImages, 0, 60); // Ensure we have enough images
            
            // Split the images array into 4 equal parts
            $chunkSize = ceil(count($images) / 4);
            $chunks = array_chunk($images, $chunkSize);
            ?>
            
            <div class="mx-auto block h-[100vh] w-full overflow-hidden">
                <div class="flex size-full items-center justify-center">
                    <div class="size-[2400px] shrink-0 scale-50 sm:scale-75 lg:scale-100">
                        <div 
                            id="marqueeContainer"
                            class="relative top-96 right-[80%] grid size-full origin-top-left grid-cols-4 gap-6 transform-3d"
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
                                                    class="marquee-image w-full h-full object-cover object-center grayscale" 
                                                    loading="lazy"
                                                    onerror="this.onerror=null; this.src='/VOLUNTEER-MAIN/assets/volunteer1.jpg';"
                                                    style="min-width: 100%; min-height: 100%;">
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
        
        <!-- Registration Form -->
        <div class="register-form-container max-w-md w-full space-y-8 bg-white p-10 rounded-xl shadow-xl bg-opacity-95">
            <div>
                <h1 class="text-center text-3xl font-bold" style="color: #10283f;">Volunite</h1>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Buat Akun Baru</h2>
            </div>
            
            <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo $error; ?></p>
            </div>
            <?php endif; ?>
            
            <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert">
                <p class="font-bold">Sukses</p>
                <p><?php echo $success; ?></p>
                <p class="mt-2">
                    <a href="login.php" class="font-medium" style="color: #10283f;">Masuk sekarang</a>
                </p>
            </div>
            <?php else: ?>
            
            <form class="mt-8 space-y-6" action="register.php" method="POST">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="name" class="sr-only">Nama Lengkap</label>
                        <input id="name" name="name" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:z-10 sm:text-sm" placeholder="Nama Lengkap">
                    </div>
                    <div>
                        <label for="email" class="sr-only">Alamat Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:z-10 sm:text-sm" placeholder="Alamat Email">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:z-10 sm:text-sm" placeholder="Password">
                    </div>
                    <div>
                        <label for="confirm_password" class="sr-only">Konfirmasi Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:z-10 sm:text-sm" placeholder="Konfirmasi Password">
                    </div>
                </div>
                
                <div class="flex items-center justify-center space-x-8">
                    <div class="flex items-center">
                        <input id="role-user" name="role" type="radio" value="user" checked class="h-5 w-5 border-gray-300" style="color: #10283f; accent-color: #10283f;">
                        <label for="role-user" class="ml-2 block text-sm text-gray-900">
                            Daftar sebagai Pencari Volunteer
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input id="role-owner" name="role" type="radio" value="owner" class="h-5 w-5 border-gray-300" style="color: #10283f; accent-color: #10283f;">
                        <label for="role-owner" class="ml-2 block text-sm text-gray-900">
                            Daftar sebagai Penyedia
                        </label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white transition-colors duration-200" style="background-color: #10283f; hover:background-color: #1a3b58;">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="color: rgba(255,255,255,0.7);">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        Daftar
                    </button>
                </div>
            </form>
            
            <?php endif; ?>
            
            <div class="text-center">
                <p class="mt-2 text-center text-sm text-gray-600">
                    Sudah memiliki akun?
                    <a href="login.php" class="font-medium hover:text-opacity-80" style="color: #10283f;">
                        Masuk sekarang
                    </a>
                </p>
                <p class="mt-2 text-center text-sm text-gray-600">
                    <a href="../public/index.php" class="font-medium hover:text-opacity-80" style="color: #10283f;">
                        Kembali ke beranda
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form animation
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
            
            // Select all marquee columns
            const marqueeColumns = document.querySelectorAll('.marquee-column');
            
            // Animation function for each column
            function animateColumns() {
                marqueeColumns.forEach((column, index) => {
                    // Get column index
                    const colIndex = parseInt(column.getAttribute('data-column'));
                    
                    // Set animation distance and duration based on column index
                    const distance = colIndex % 2 === 0 ? 1000 : 500;
                    const duration = 15000 + (colIndex * 3000); // Varying durations for different columns
                    
                    // Animate column
                    animateColumn(column, distance, duration);
                });
            }
            
            // Function to animate a single column
            function animateColumn(element, distance, duration) {
                const startPosition = -1000; // Start above the viewport
                element.style.transform = `translateY(${startPosition}px)`;
                
                // Create a continuous infinite animation
                function animate() {
                    element.animate(
                        [
                            { transform: `translateY(${startPosition}px)` },
                            { transform: `translateY(${distance}px)` }
                        ],
                        {
                            duration: duration,
                            iterations: Infinity,
                            direction: 'alternate',
                            easing: 'ease-in-out'
                        }
                    );
                }
                
                animate();
            }
            
            // Start animation with slight delay to ensure DOM is ready
            setTimeout(animateColumns, 500);
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>