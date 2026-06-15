<?php
// =====================================================================
// EazyMUZE — Admin Login (manage-portal/login.php)
// =====================================================================
session_start();
require_once dirname(__DIR__) . '/includes/env.php';

$error = '';
$admin_password = getenv('ADMIN_PASSWORD') ?: 'EazyAdmin2025!';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if (hash_equals($admin_password, $pass)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_ip']  = $_SERVER['REMOTE_ADDR'];
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid admin password. Access denied.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EazyMUZE Admin — Secure Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0a0406;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }
        .orb-1 { width: 400px; height: 400px; background: rgba(255,42,109,0.15); top: -100px; right: -100px; }
        .orb-2 { width: 300px; height: 300px; background: rgba(90,0,50,0.2); bottom: -80px; left: -80px; }
        .login-card {
            background: linear-gradient(135deg, rgba(20,10,15,0.98), rgba(45,15,30,0.98));
            border: 1px solid rgba(255,42,109,0.3);
            border-radius: 24px;
            padding: 40px 36px;
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 10;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 40px rgba(255,42,109,0.1);
        }
        .logo-wrap {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #ff2a6d, #b5006a);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            box-shadow: 0 0 30px rgba(255,42,109,0.4);
            font-size: 1.6rem;
        }
        h1 { font-size: 1.3rem; color: white; font-weight: 700; }
        p.subtitle { font-size: 0.82rem; color: rgba(255,255,255,0.4); margin-top: 4px; }
        .form-group { margin-bottom: 18px; position: relative; }
        .form-group label { display: block; font-size: 0.78rem; color: rgba(255,255,255,0.5); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-group input {
            width: 100%;
            padding: 13px 14px 13px 44px;
            border-radius: 12px;
            border: 1px solid rgba(255,42,109,0.2);
            background: rgba(255,255,255,0.04);
            color: white;
            font-size: 0.95rem;
            font-family: 'Outfit', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #ff2a6d; }
        .form-group .ico {
            position: absolute;
            left: 14px;
            bottom: 13px;
            color: rgba(255,42,109,0.5);
            font-size: 0.9rem;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ff2a6d, #b5006a);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(255,42,109,0.35);
            transition: all 0.2s;
            letter-spacing: 0.5px;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(255,42,109,0.5); }
        .error-msg {
            background: rgba(231,76,60,0.15);
            border: 1px solid rgba(231,76,60,0.4);
            border-radius: 10px;
            color: #e74c3c;
            padding: 10px 14px;
            font-size: 0.83rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .shield-badge {
            text-align: center;
            margin-top: 20px;
            font-size: 0.72rem;
            color: rgba(255,255,255,0.25);
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card">
        <div class="logo-wrap">
            <div class="logo-icon">🔐</div>
            <h1>Admin Portal</h1>
            <p class="subtitle">EazyMUZE Secure Control Panel</p>
        </div>

        <?php if ($error): ?>
        <div class="error-msg"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Admin Password</label>
                <i class="fas fa-lock ico"></i>
                <input type="password" name="password" id="adminPass" placeholder="Enter admin password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Access Control Panel
            </button>
        </form>

        <div class="shield-badge">
            <i class="fas fa-shield-alt"></i> Secured · All actions are logged · Unauthorised access is prohibited
        </div>
    </div>
</body>
</html>
