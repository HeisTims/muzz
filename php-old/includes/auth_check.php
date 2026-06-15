<?php
// EazyMUZE Authentication Checker

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Session Hijacking Protection
if (isset($_SESSION['user_id'])) {
    // Verify User Agent and IP Subnet (first two octets to allow minor IP changes on mobile)
    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip_parts = explode('.', $user_ip);
    $ip_subnet = (count($ip_parts) >= 2) ? $ip_parts[0] . '.' . $ip_parts[1] : $user_ip;
    
    if (!isset($_SESSION['fingerprint_ua']) || !isset($_SESSION['fingerprint_ip'])) {
        $_SESSION['fingerprint_ua'] = $current_ua;
        $_SESSION['fingerprint_ip'] = $ip_subnet;
    } elseif ($_SESSION['fingerprint_ua'] !== $current_ua || $_SESSION['fingerprint_ip'] !== $ip_subnet) {
        // Potential session hijacking, destroy session
        session_destroy();
        unset($_SESSION['user_id']);
    }
}

// Redirect if unauthorized
if (!isset($_SESSION['user_id'])) {
    // Check if it is an AJAX request
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
               || (isset($_SERVER['CONTENT_TYPE']) && strpos(strtolower($_SERVER['CONTENT_TYPE']), 'application/json') !== false)
               || (isset($_GET['action']))
               || (strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Please login to enter the Temple.']);
        exit;
    } else {
        // Determine root relative URL for login
        $root = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        // Redirect to login.php
        $login_url = dirname($_SERVER['SCRIPT_NAME']);
        if (substr($login_url, -1) !== '/') $login_url .= '/';
        
        // If script is in auth subfolder, login.php is direct
        if (strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false) {
            header('Location: login.php');
        } else {
            header('Location: auth/login.php');
        }
        exit;
    }
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Update last_seen on every authenticated page load (online status)
if (isset($_SESSION['user_id']) && !defined('SKIP_LAST_SEEN')) {
    // Only update once per 30s to reduce DB write pressure
    $lastSeenKey = 'last_seen_ts';
    if (!isset($_SESSION[$lastSeenKey]) || (time() - $_SESSION[$lastSeenKey]) > 30) {
        try {
            // Use $GLOBALS to safely access $pdo regardless of include scope
            $lsPdo = $GLOBALS['pdo'] ?? null;
            if ($lsPdo) {
                $lsPdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$_SESSION['user_id']]);
            }
        } catch (Exception $e) { /* non-fatal */ }
        $_SESSION[$lastSeenKey] = time();
    }
}

// Helper to escape HTML safely for output (XSS protection)
if (!function_exists('esc')) {
    function esc($text) {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}
?>
