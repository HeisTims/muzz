<?php
// EazyMUZE Dynamic PHP Register Handler
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// If already logged in, redirect
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
require_once dirname(__DIR__) . '/api/monnify.php';

// Define esc() helper if header hasn't loaded yet
if (!function_exists('esc')) {
    function esc($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $fullname   = trim($_POST['fullname'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $dob        = $_POST['dob'] ?? '';
        $preference = $_POST['preference'] ?? '';
        $bio        = trim($_POST['bio'] ?? '');
        $consent    = isset($_POST['consent']);

        if (!$consent) {
            $error = 'You must be 18+ and consent to proceed.';
        } elseif (empty($username) || empty($password) || empty($email) || empty($phone) || empty($dob) || empty($preference)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Check if username or email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $verificationToken = bin2hex(random_bytes(32));

                $stmt = $pdo->prepare('INSERT INTO users (username, password, fullname, email, phone, dob, preference, bio, email_verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$username, $hashed_password, $fullname, $email, $phone, $dob, $preference, $bio, $verificationToken])) {
                    $user_id = $pdo->lastInsertId();

                    // --- Create Monnify Reserved Account ---
                    $accountRef = 'EAZYMUZE-' . strtoupper($username) . '-' . $user_id;
                    try {
                        $monnifyAccount = monnify_createReservedAccount($accountRef, $username, $email, $phone);
                        if ($monnifyAccount && !empty($monnifyAccount['accountNumber'])) {
                            $pdo->prepare('UPDATE users SET monnify_ref = ?, monnify_account_number = ?, monnify_bank_name = ?, monnify_bank_code = ? WHERE id = ?')
                                ->execute([
                                    $accountRef,
                                    $monnifyAccount['accountNumber'],
                                    $monnifyAccount['bankName'],
                                    $monnifyAccount['bankCode'],
                                    $user_id
                                ]);
                        }
                    } catch (Exception $e) {
                        error_log("Monnify Reservation failed for user: " . $username . " - " . $e->getMessage());
                    }

                    // --- Send Verification Email ---
                    $verifyLink = 'https://eazymuze.ng/api/auth.php?action=verify_email&token=' . $verificationToken;
                    $emailBody  = emailTemplate_Registration($username, $verifyLink);
                    try {
                        sendMuzeEmail($email, $username, '💋 Welcome to EazyMUZE — Verify Your Temple Access', $emailBody);
                    } catch (Exception $mailEx) {
                        error_log("Verification email failure: " . $mailEx->getMessage());
                    }

                    // Automatic Login after Registration
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;

                    // Track session fingerprints
                    $_SESSION['fingerprint_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ip_parts = explode('.', $ip);
                    $_SESSION['fingerprint_ip'] = (count($ip_parts) >= 2) ? $ip_parts[0] . '.' . $ip_parts[1] : $ip;

                    $success = 'Initiation initiated! Welcome to the Temple.';
                    header('Refresh: 2; URL=../index.php');
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}

$no_auth_check = true;
require_once dirname(__DIR__) . '/includes/header.php';
?>
<div class="auth-container" style="min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px;">
    <div class="glass-panel auth-box fade-in-up" id="registerBox" style="width: 100%; max-width: 400px; text-align: center; padding: 30px; border-radius: 24px;">
        <div style="position: relative; display: inline-block; margin-bottom: 25px; margin-top: 10px;">
            <img src="../assets/img/logo.png" style="width: 130px; display: block; margin: 0 auto; filter: drop-shadow(0 0 15px rgba(255, 42, 109, 0.5));">
            <img src="../assets/img/353997.png" style="position: absolute; width: 75px; right: -40px; bottom: -15px; transform: rotate(18deg); filter: drop-shadow(0 0 10px rgba(255, 42, 109, 0.45)); animation: slantFloat 3.5s ease-in-out infinite;">
        </div>
        
        <h2 style="color: var(--neon-pink); margin-bottom: 20px; font-weight: 800;">Initiation</h2>
        
        <?php if (!empty($error)): ?>
            <div style="background: rgba(192, 57, 43, 0.15); border: 1px solid var(--blood-moon); color: #ff7675; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; text-align: left;">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; color: #2ecc71; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; text-align: left;">
                <i class="fas fa-check-circle"></i> <?php echo esc($success); ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" id="regForm" style="text-align: left;">
            <input type="hidden" name="csrf_token" value="<?php echo esc($_SESSION['csrf_token']); ?>">

            <!-- Step 1: Base Credentials -->
            <div id="step1" class="step-div active-step">
                <div class="form-group" style="margin-bottom: 15px;">
                    <input type="text" id="regUser" name="username" placeholder="Username" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <input type="password" id="regPass" name="password" placeholder="Password (Min 8 Characters)" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <input type="email" id="regEmail" name="email" placeholder="Email" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <input type="tel" id="regPhone" name="phone" placeholder="Phone Number (e.g. 08012345678)" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <input type="text" id="regName" name="fullname" placeholder="Full Name" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 5px; display: block;">Date of Birth</label>
                    <input type="date" id="regDob" name="dob" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem;">
                </div>
                <button type="button" class="btn-primary" style="width: 100%;" onclick="validateAndGoToStep2()">Next Step 💋</button>
            </div>

            <!-- Step 2: Preferences & Age consent -->
            <div id="step2" class="step-div" style="display: none;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <select name="preference" required style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: #1a0b12; color: white; outline: none; font-size: 0.95rem;">
                        <option value="">Select Preference</option>
                        <option value="straight">Straight</option>
                        <option value="gay">Gay</option>
                        <option value="lesbian">Lesbian</option>
                        <option value="bisexual">Bisexual</option>
                        <option value="sugar_mummy">Sugar Mummy</option>
                        <option value="sugar_daddy">Sugar Daddy</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <textarea name="bio" placeholder="Short Bio (Describe your desires...)" rows="3" style="width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid var(--glass-border); background: rgba(255, 255, 255, 0.04); color: white; outline: none; font-size: 0.95rem; resize: none;"></textarea>
                </div>
                
                <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-bottom: 25px;">
                    <input type="checkbox" id="ageConsent" name="consent" required style="width: auto; cursor: pointer; scale: 1.1;">
                    <label for="ageConsent" style="font-size: 0.8rem; color: var(--text-secondary); cursor: pointer; line-height: 1.4;">I am over 18 years old. I consent to mature content and respect other members.</label>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn-primary" style="flex: 1; background: #333; box-shadow: none;" onclick="goToStep1()">Back</button>
                    <button type="submit" class="btn-primary" style="flex: 1;">Register 💋</button>
                </div>
            </div>
        </form>
        
        <p style="margin-top: 25px; font-size: 0.9rem; color: var(--text-secondary); cursor: pointer;" onclick="window.location.href='login.php'">Already initiated? Enter.</p>
    </div>
</div>

<script>
    function validateAndGoToStep2() {
        const user = document.getElementById('regUser').value.trim();
        const pass = document.getElementById('regPass').value;
        const email = document.getElementById('regEmail').value.trim();
        const phone = document.getElementById('regPhone').value.trim();
        const name = document.getElementById('regName').value.trim();
        const dob = document.getElementById('regDob').value;

        if (!user || !pass || !email || !phone || !name || !dob) {
            window.utils.showToast('Please fill all credentials first', 'error');
            return;
        }

        if (pass.length < 8) {
            window.utils.showToast('Password must be at least 8 characters long', 'error');
            return;
        }

        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
    }

    function goToStep1() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';
    }
</script>

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
