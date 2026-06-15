<?php
session_start();
require_once 'db.php';
require_once 'smtp.php';
require_once 'email_templates.php';
require_once 'monnify.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'register') {
        $username   = trim($input['username'] ?? '');
        $password   = $input['password'] ?? '';
        $fullname   = trim($input['fullname'] ?? '');
        $email      = trim($input['email'] ?? '');
        $phone      = trim($input['phone'] ?? '');
        $dob        = $input['dob'] ?? '';
        $preference = $input['preference'] ?? '';
        $bio        = trim($input['bio'] ?? '');

        if (!$username || !$password || !$email || !$phone || !$dob || !$preference) {
            sendResponse('error', null, 'Missing required fields.');
        }

        // Check if username or email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            sendResponse('error', null, 'Username or email already exists.');
        }

        $hashed_password      = password_hash($password, PASSWORD_DEFAULT);
        $verificationToken    = bin2hex(random_bytes(32));

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
                } else {
                    error_log("Monnify Reserved Account setup skipped or failed for user: " . $username);
                }
            } catch (Exception $e) {
                error_log("Monnify Reservation exception: " . $e->getMessage());
            }

            // --- Send Verification Email ---
            $verifyLink = 'https://eazymuze.ng/api/auth.php?action=verify_email&token=' . $verificationToken;
            $emailBody  = emailTemplate_Registration($username, $verifyLink);
            try {
                sendMuzeEmail($email, $username, '💋 Welcome to EazyMUZE — Verify Your Temple Access', $emailBody);
            } catch (Exception $mailEx) {
                error_log("Verification email not sent: " . $mailEx->getMessage());
            }

            // Fetch user to return
            $stmt = $pdo->prepare('SELECT id, username, fullname, email, phone, dob, preference, location, bio, avatar, is_verified, email_verified, is_online, wallet_balance, streak_count, streak_last_active, monnify_account_number, monnify_bank_name, has_used_free_whisper, has_used_free_read FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $_SESSION['user_id'] = $user['id'];
            sendResponse('success', $user, 'Registration successful. Check your email to verify your account! 💋');
        } else {
            sendResponse('error', null, 'Registration failed.');
        }
    }

    elseif ($action === 'login') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];

            // Update online status and IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $pdo->prepare('UPDATE users SET is_online = 1, last_ip = ? WHERE id = ?')->execute([$ip, $user['id']]);

            // --- Send Login Alert Email ---
            $time      = date('D, d M Y H:i:s T');
            $emailBody = emailTemplate_Login($username, $ip, $time);
            try {
                sendMuzeEmail($user['email'], $username, '🔐 New Login Detected — EazyMUZE', $emailBody);
            } catch (Exception $mailEx) {
                error_log("Login alert email not sent: " . $mailEx->getMessage());
            }

            unset($user['password']);
            sendResponse('success', $user, 'Login successful.');
        } else {
            sendResponse('error', null, 'Invalid username or password.');
        }
    }

    elseif ($action === 'kyc') {
        if (!isset($_SESSION['user_id'])) sendResponse('error', null, 'Unauthorized');
        $kyc_id     = $input['kyc_id'] ?? '';
        $kyc_selfie = $input['kyc_selfie'] ?? '';
        if (!$kyc_id || !$kyc_selfie) sendResponse('error', null, 'Both ID and Selfie are required.');
        $stmt = $pdo->prepare('UPDATE users SET kyc_id = ?, kyc_selfie = ? WHERE id = ?');
        if ($stmt->execute([$kyc_id, $kyc_selfie, $_SESSION['user_id']])) {
            sendResponse('success', null, 'KYC submitted successfully. Awaiting approval.');
        } else {
            sendResponse('error', null, 'Failed to submit KYC.');
        }
    }

    elseif ($action === 'logout') {
        if (isset($_SESSION['user_id'])) {
            $pdo->prepare('UPDATE users SET is_online = 0 WHERE id = ?')->execute([$_SESSION['user_id']]);
        }
        session_destroy();
        sendResponse('success', null, 'Logged out.');
    }

    elseif ($action === 'update_profile') {
        if (!isset($_SESSION['user_id'])) sendResponse('error', null, 'Unauthorized');
        $user_id  = $_SESSION['user_id'];
        $location = $input['location'] ?? '';
        $bio      = $input['bio'] ?? '';
        $stmt = $pdo->prepare('UPDATE users SET location = ?, bio = ? WHERE id = ?');
        if ($stmt->execute([$location, $bio, $user_id])) {
            sendResponse('success', null, 'Profile updated successfully.');
        } else {
            sendResponse('error', null, 'Failed to update profile.');
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'session') {
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare('SELECT id, username, fullname, email, phone, dob, preference, location, bio, avatar, is_verified, email_verified, is_online, wallet_balance, streak_count, streak_last_active, monnify_account_number, monnify_bank_name, has_used_free_whisper, has_used_free_read FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) sendResponse('success', $user);
        }
        sendResponse('error', null, 'No active session.');
    }

    elseif ($action === 'verify_email') {
        $token = $_GET['token'] ?? '';
        if (!$token) {
            echo "<h2 style='color:red;font-family:sans-serif;'>Invalid verification link.</h2>"; exit;
        }
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email_verification_token = ?');
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare('UPDATE users SET email_verified = 1, email_verification_token = NULL WHERE id = ?')->execute([$user['id']]);
            echo "<!DOCTYPE html><html><head><title>EazyMUZE Verified</title></head><body style='background:#0a0406;color:#ff2a6d;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;flex-direction:column;'>
                <div style='font-size:3rem;'>💋</div>
                <h1 style='color:white;'>Temple Access Granted!</h1>
                <p style='color:#e89ec0;'>Your email has been verified, <strong>{$user['username']}</strong>. Welcome to the inner sanctum.</p>
                <a href='https://eazymuze.ng' style='margin-top:20px;background:linear-gradient(135deg,#ff4d85,#ff2a6d);color:white;padding:14px 30px;border-radius:30px;text-decoration:none;font-weight:bold;'>Enter the Temple 🔥</a>
            </body></html>";
        } else {
            echo "<h2 style='color:red;font-family:sans-serif;text-align:center;margin-top:40px;'>Token not found or already used.</h2>";
        }
        exit;
    }
}
