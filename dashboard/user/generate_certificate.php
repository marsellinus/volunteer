<?php
require_once '../../config/database.php';
include_once '../../includes/notifications.php'; // Add this line
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get unread notifications count - with error handling
try {
    $unread_count = getUserUnreadNotificationsCount($user_id, $conn);
} catch (Exception $e) {
    $unread_count = 0;
}

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($application_id <= 0) {
    header("Location: certificates.php");
    exit;
}

// Get certificate data
$stmt = $conn->prepare("
    SELECT a.*, va.title, va.event_date, va.description, va.category, 
           u.name as participant_name, o.organization_name, o.name as organizer_name
    FROM applications a 
    JOIN volunteer_activities va ON a.activity_id = va.id 
    JOIN users u ON a.user_id = u.user_id
    JOIN owners o ON va.owner_id = o.owner_id
    WHERE a.id = ? AND a.user_id = ? AND a.status = 'approved' AND va.event_date <= CURDATE()
");

$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: certificates.php");
    exit;
}

$certificate_data = $result->fetch_assoc();

// Check if certificate columns exist in the table
$columnsExist = true;
try {
    // Try to record that certificate was generated
    $stmt = $conn->prepare("UPDATE applications SET certificate_generated = 1, certificate_date = CURDATE() WHERE id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    // Columns don't exist
    $columnsExist = false;
}

// Generate a unique certificate ID
$certificate_id = "VH" . date('Y') . str_pad($application_id, 6, '0', STR_PAD_LEFT);

// Format dates
$event_date = date('d F Y', strtotime($certificate_data['event_date']));
$issue_date = date('d F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Participation - <?php echo htmlspecialchars($certificate_data['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Tangerine:wght@700&display=swap');
        
        .certificate-container {
            width: 1000px;
            height: 700px;
            position: relative;
            margin: 0 auto;
            background-color: #fff;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .certificate {
            position: absolute;
            width: 100%;
            height: 100%;
            padding: 50px;
            box-sizing: border-box;
            border: 20px solid #8667f0;
            background-color: #fff;
            font-family: 'Montserrat', sans-serif;
            color: #333;
            background-image: linear-gradient(135deg, rgba(219, 234, 254, 0.3) 25%, transparent 25%, transparent 50%, rgba(219, 234, 254, 0.3) 50%, rgba(219, 234, 254, 0.3) 75%, transparent 75%, transparent);
            background-size: 100px 100px;
        }
        
        .certificate-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .certificate-title {
            font-family: 'Tangerine', cursive;
            font-size: 80px;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        
        .certificate-subtitle {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
        }
        
        .certificate-body {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .participant-name {
            font-size: 48px;
            font-weight: 700;
            color: #4f46e5;
            margin: 20px 0;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            display: inline-block;
        }
        
        .certificate-text {
            font-size: 18px;
            line-height: 1.5;
            margin: 20px 100px;
        }
        
        .certificate-event {
            font-size: 24px;
            font-weight: 600;
            color: #4f46e5;
            margin: 15px 0;
        }
        
        .certificate-date {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .certificate-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 100px;
        }
        
        .signature-container {
            text-align: center;
        }
        
        .signature {
            border-top: 2px solid #333;
            padding-top: 10px;
            width: 200px;
            display: inline-block;
            text-align: center;
        }
        
        .certificate-id {
            position: absolute;
            bottom: 20px;
            right: 50px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php if (!$columnsExist): ?>
    <div class="container mx-auto py-4 px-4">
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
            <p class="font-bold">Database Update Required</p>
            <p>The certificate feature requires a database update. Please run the setup script: <code>setup/update_applications_table.php</code></p>
            <p class="mt-3">
                <a href="../../setup/update_applications_table.php" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded inline-block mt-2">Run Update Script</a>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="container mx-auto py-8 px-4">
        <div class="mb-6 flex justify-between items-center">
            <a href="certificates.php" class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke daftar piagam
            </a>
            
            <button onclick="printCertificate()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i class="fas fa-print mr-2"></i> Cetak Piagam
            </button>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Piagam Penghargaan</h2>
            <p class="mb-4">Piagam ini diberikan kepada <strong><?php echo htmlspecialchars($certificate_data['participant_name']); ?></strong> atas partisipasinya dalam kegiatan volunteer <strong><?php echo htmlspecialchars($certificate_data['title']); ?></strong>.</p>
            
            <div class="bg-gray-50 rounded-md p-4 mb-4">
                <div class="flex flex-col sm:flex-row sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-500">ID Piagam:</p>
                        <p class="font-medium"><?php echo $certificate_id; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tanggal Terbit:</p>
                        <p class="font-medium"><?php echo $issue_date; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Tanggal Kegiatan:</p>
                        <p class="font-medium"><?php echo $event_date; ?></p>
                    </div>
                </div>
            </div>
            
            <p class="text-sm text-gray-500">Silahkan cetak piagam ini atau simpan sebagai PDF untuk dokumentasi Anda.</p>
        </div>
        
        <!-- Certificate Preview -->
        <div id="certificate" class="certificate-container mb-10">
            <div class="certificate">
                <div class="certificate-header">
                    <h1 class="certificate-title">Certificate of Appreciation</h1>
                    <h2 class="certificate-subtitle">This certificate is presented to</h2>
                </div>
                
                <div class="certificate-body">
                    <div class="participant-name"><?php echo htmlspecialchars($certificate_data['participant_name']); ?></div>
                    
                    <div class="certificate-text">
                        For dedicated participation and valuable contribution as a volunteer in:
                    </div>
                    
                    <div class="certificate-event"><?php echo htmlspecialchars($certificate_data['title']); ?></div>
                    
                    <div class="certificate-date">on <?php echo $event_date; ?></div>
                </div>
                
                <div class="certificate-footer">
                    <div class="signature-container">
                        <div class="signature">
                            <strong><?php echo htmlspecialchars($certificate_data['organizer_name']); ?></strong>
                            <div class="text-sm">Event Organizer</div>
                        </div>
                    </div>
                    
                    <div class="signature-container">
                        <div class="signature">
                            <strong>VolunteerHub</strong>
                            <div class="text-sm">Platform Administrator</div>
                        </div>
                    </div>
                </div>
                
                <div class="certificate-id">
                    Certificate ID: <?php echo $certificate_id; ?> | Issued on: <?php echo $issue_date; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function printCertificate() {
            const originalContents = document.body.innerHTML;
            const certificateContents = document.getElementById('certificate').outerHTML;
            
            document.body.innerHTML = `
                <style>
                    body { margin: 0; padding: 0; }
                    .certificate-container { box-shadow: none; margin: 0; }
                    @media print {
                        @page { size: landscape; margin: 0; }
                        body { margin: 0; }
                        .certificate-container { width: 100%; height: 100vh; }
                    }
                </style>
                ${certificateContents}
            `;
            
            window.print();
            
            // Restore original contents after printing
            document.body.innerHTML = originalContents;
        }
    </script>
</body>
</html>
