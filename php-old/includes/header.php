<?php
// EazyMUZE Premium Dynamic Header Template

// ── ALWAYS define esc() FIRST so it is available everywhere ──────────
if (!function_exists('esc')) {
    function esc($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

// Determine path prefix for subdirectories
$path_prefix = '';
if (strpos($_SERVER['SCRIPT_NAME'], '/auth/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/manage-portal/') !== false) {
    $path_prefix = '../';
}

// ── Always start session (needed for CSRF token on login/register) ───
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check authorization
if (!isset($no_auth_check) || !$no_auth_check) {
    require_once dirname(__DIR__) . '/includes/auth_check.php';
    require_once dirname(__DIR__) . '/api/db.php';
    
    // Fetch fresh user data
    $user_id = $_SESSION['user_id'];
    $u_stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $u_stmt->execute([$user_id]);
    $currentUser = $u_stmt->fetch();
    
    if (!$currentUser) {
        session_destroy();
        header('Location: ' . $path_prefix . 'auth/login.php');
        exit;
    }
    
    // Auto-provision Monnify virtual account if missing
    if (empty($currentUser['monnify_account_number'])) {
        require_once dirname(__DIR__) . '/api/monnify.php';
        $accountRef = 'EAZYMUZE-' . strtoupper($currentUser['username']) . '-' . $user_id;
        try {
            $monnifyAccount = monnify_createReservedAccount($accountRef, $currentUser['username'], $currentUser['email'], $currentUser['phone'] ?: '08011112222');
            if ($monnifyAccount && !empty($monnifyAccount['accountNumber'])) {
                $pdo->prepare('UPDATE users SET monnify_ref = ?, monnify_account_number = ?, monnify_bank_name = ?, monnify_bank_code = ? WHERE id = ?')
                    ->execute([
                        $accountRef,
                        $monnifyAccount['accountNumber'],
                        $monnifyAccount['bankName'],
                        $monnifyAccount['bankCode'],
                        $user_id
                    ]);
                // Refresh local variable
                $currentUser['monnify_account_number'] = $monnifyAccount['accountNumber'];
                $currentUser['monnify_bank_name'] = $monnifyAccount['bankName'];
                $currentUser['monnify_ref'] = $accountRef;
            }
        } catch (Throwable $e) {
            error_log("Auto-provision failed: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="EazyMUZE is a premium, erotic social networking platform. Find your muze, share secrets, and explore your desires safely.">
    <meta name="keywords" content="social network, dating, premium, erotica, adult, messaging, whisper">
    
    <title><?php echo isset($page_title) ? esc($page_title) : "EazyMUZE — Find Your Muze 💋"; ?></title>
    
    <!-- Favicon & PWA -->
    <link rel="icon" type="image/png" href="<?php echo $path_prefix; ?>assets/img/logo1.png">
    <link rel="manifest" href="<?php echo $path_prefix; ?>manifest.json">
    <meta name="theme-color" content="#ff2a6d">
    <link rel="apple-touch-icon" href="<?php echo $path_prefix; ?>assets/img/logo1.png">
    
    <!-- SEO & OpenGraph Meta Tags -->
    <meta property="og:title" content="EazyMUZE - Find Your Muze 💋">
    <meta property="og:description" content="EazyMUZE is a premium, erotic social networking platform. Find your muze, share secrets, and explore your desires safely.">
    <meta property="og:image" content="<?php echo $path_prefix; ?>assets/img/logo.png">
    <meta property="og:type" content="website">
    
    <!-- Typography & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $path_prefix; ?>css/style.css">
    
    <style>
        /* Modernized Active status green ring/dots for Instagram standard */
        .status-dot-active {
            width: 10px;
            height: 10px;
            background-color: #2ecc71;
            border-radius: 50%;
            border: 2px solid var(--velvet-bg);
            box-shadow: 0 0 8px #2ecc71;
        }
        
        .active-user-ring {
            border: 2px solid #2ecc71 !important;
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.4) !important;
        }
        
        /* Hearts animation overlay for double tap like */
        .double-tap-heart {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            color: #ff2a6d;
            font-size: 6rem;
            text-shadow: 0 0 30px rgba(255,42,109,0.8);
            pointer-events: none;
            z-index: 100;
            animation: heartFade 0.8s ease-out;
        }
        @keyframes heartFade {
            0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
            15% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.9; }
            30% { transform: translate(-50%, -50%) scale(1); opacity: 0.9; }
            80% { transform: translate(-50%, -50%) scale(1.4); opacity: 0.9; }
            100% { transform: translate(-50%, -50%) scale(1.6); opacity: 0; }
        }
        
        /* Message reactions indicator */
        .msg-reaction-badge {
            position: absolute;
            bottom: -8px;
            right: 12px;
            background: var(--obsidian);
            border: 1px solid var(--neon-pink);
            border-radius: 12px;
            padding: 2px 6px;
            font-size: 0.75rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
            z-index: 5;
            display: flex;
            align-items: center;
        }
        
        .chat-sent .msg-reaction-badge {
            right: auto;
            left: 12px;
        }
    </style>
</head>
<body>
    <div class="ambient-bg">
        <div class="glowing-orb orb-1"></div>
        <div class="glowing-orb orb-2"></div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <main id="appContainer">
        <?php if (!isset($no_header) || !$no_header): ?>
        <header class="app-header">
            <div class="app-logo" onclick="window.location.href='<?php echo $path_prefix; ?>index.php'" style="cursor: pointer;">
                <img src="<?php echo $path_prefix; ?>assets/img/logo.png" alt="EazyMUZE Logo" style="height: 50px; border-radius: 8px;">
            </div>
            <div style="position: relative;" onclick="toggleNotificationsDropdown()">
                <i class="fas fa-bell" style="color: #f1c40f; font-size: 1.2rem; cursor: pointer; text-shadow: 0 0 10px rgba(241, 196, 15, 0.5);"></i>
                <span id="notifBadge" style="position:absolute; top:-5px; right:-5px; background:var(--neon-pink); color:white; font-size:0.6rem; padding:2px 5px; border-radius:50%; display:none;">0</span>
            </div>
        </header>

        <!-- Notification Dropdown -->
        <div id="notifDropdown" class="glass-panel slide-in" style="display:none; position:fixed; top:70px; right:calc(50% - 230px); width:300px; max-height:400px; overflow-y:auto; z-index:1000; left: auto; max-width: calc(100% - 40px);">
            <h3 style="margin-bottom:10px; color:var(--neon-pink);">Notifications</h3>
            <div id="notifList">
                <p style="color:grey; font-size:0.8rem;">No new whispers.</p>
            </div>
        </div>
        
        <!-- Spacer -->
        <div style="height: 10px;"></div>
        <?php endif; ?>
