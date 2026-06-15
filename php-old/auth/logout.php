<?php
// EazyMUZE Secure PHP Logout Handler
session_start();
require_once dirname(__DIR__) . '/api/db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Set offline
    try {
        $pdo->prepare('UPDATE users SET is_online = 0 WHERE id = ?')->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Failed to set user offline: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>
