<?php
// EazyMUZE Secure PHP Login Handler
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// If already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/api/smtp.php';
require_once dirname(__DIR__) . '/api/email_templates.php';

// Define esc() helper if header hasn't loaded yet
if (!function_exists('esc')) {
    function esc($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security session expired. Please reload the page.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Prevent session fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];

                // Update last_seen and IP
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                try {
                    $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?')->execute([$user['id']]);
                } catch (Exception $e) { /* column may not exist yet */ }

                // Track Session Fingerprints
                $_SESSION['fingerprint_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $ip_parts = explode('.', $ip);
                $_SESSION['fingerprint_ip'] = (count($ip_parts) >= 2) ? $ip_parts[0] . '.' . $ip_parts[1] : $ip;

                // --- Send Login Alert Email ---
                $time      = date('D, d M Y H:i:s T');
                $emailBody = emailTemplate_Login($username, $ip, $time);
                try {
                    sendMuzeEmail($user['email'], $username, '🔐 New Login Detected — EazyMUZE', $emailBody);
                } catch (Exception $mailEx) {
                    error_log("Login alert email not sent: " . $mailEx->getMessage());
                }

                // Redirect to main feed page
                header('Location: ../index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}

$no_auth_check = true;
require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="auth-container" style="min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px;">
    <div class="glass-panel auth-box fade-in-up" id="loginBox" style="width: 100%; max-width: 400px; text-align: center; padding: 30px; border-radius: 24px;">
        <div style="position: relative; display: inline-block; margin-bottom: 25px; margin-top: 10px;">
            <!-- Logo -->
            <img src="../assets/img/logo.png" style="width: 130px; display: block; margin: 0 auto; filter: drop-shadow(0 0 15px rgba(255, 42, 109, 0.5));">
            <!-- Slanted floating ring -->
            <img src="../assets/img/353997.png" style="position: absolute; width: 75px; right: -40px; bottom: -15px; transform: rotate(18deg); filter: drop-shadow(0 0 10px rgba(255, 42, 109, 0.45)); animation: slantFloat 3.5s ease-in-out infinite;">
        </div>
        
        <h2 style="color: var(--neon-pink); margin-bottom: 5px; font-weight: 800; font-family:'Outfit', sans-serif;">Enter the Temple</h2>
        <p style="color:var(--text-secondary); font-size:0.85rem; margin-bottom: 25px;">Indulge your darkest desires...</p>
        
        <?php if (!empty($error)): ?>
            <div style="background: rgba(192, 57, 43, 0.15); border: 1px solid var(--blood-moon); color: #ff7675; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; text-align: left;">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" style="text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo esc($_SESSION['csrf_token']); ?>">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <input type="text" name="username" placeholder="Username" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <input type="password" name="password" placeholder="Password" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 5px;">Unlock Desires 💋</button>
        </form>
        
        <p style="margin-top: 25px; font-size: 0.9rem; color: var(--text-secondary); cursor: pointer;" onclick="window.location.href='register.php'">New here? Join the Temple</p>
    </div>
</div>

<style>
    @keyframes slantFloat {
        0% { transform: translateY(0) rotate(18deg); }
        50% { transform: translateY(-8px) rotate(15deg); }
        100% { transform: translateY(0) rotate(18deg); }
    }
</style>
<?php 
require_once dirname(__DIR__) . '/includes/footer.php'; 
?>
