<?php
// auth/logout.php - User Logout Handler
// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL for redirects
$BASE_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Store user type before destroying session (for redirect purposes)
$user_type = $_SESSION['user_type'] ?? null;
$user_name = $_SESSION['first_name'] ?? 'User';

// Unset all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Start a new session for the logout message
session_start();
$_SESSION['success_message'] = "You have been successfully logged out. Thank you for using Pet Care Guide!";

// Redirect based on user type or to home page
$redirect_url = $BASE_URL . 'index.php';

// Optional: Different redirect based on user type
switch ($user_type) {
    case 'admin':
        $redirect_url = $BASE_URL . 'index.php';
        break;
    case 'shelter':
        $redirect_url = $BASE_URL . 'index.php';
        break;
    case 'adopter':
        $redirect_url = $BASE_URL . 'index.php';
        break;
    default:
        $redirect_url = $BASE_URL . 'index.php';
        break;
}

// Redirect to login page or home page
header('Location: ' . $redirect_url);
exit();
?>