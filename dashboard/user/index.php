<?php
include_once '../../config/database.php';
include_once '../../logic/recommendation.php';
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

// Redirect to search page - dashboard is now just a summary view
header("Location: search.php");
exit;
?>
