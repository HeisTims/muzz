<?php
session_start();
require_once 'db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';
$provider = $_GET['provider'] ?? '';

// You will need to plug in your actual Client IDs and Secrets here
$keys = [
    'google' => [
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
        'secret' => 'YOUR_GOOGLE_SECRET'
    ],
    'facebook' => [
        'client_id' => 'YOUR_FB_CLIENT_ID',
        'secret' => 'YOUR_FB_SECRET'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'social_login') {
        $token = $input['token'] ?? '';
        $email = $input['email'] ?? '';
        $name = $input['name'] ?? '';
        $social_id = $input['social_id'] ?? '';
        
        if (!$email || !$social_id || !$provider) {
            sendResponse('error', null, 'Invalid payload');
        }
        
        // 1. In a real app, verify the token via Google/Facebook API here to prevent spoofing
        // e.g. curl to https://oauth2.googleapis.com/tokeninfo?id_token=$token
        $is_verified = true; // Assume verified for this demo structure
        
        if ($is_verified) {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Update social ID if not set
                if (empty($user[$provider.'_id'])) {
                    $upd = $pdo->prepare("UPDATE users SET {$provider}_id = ? WHERE id = ?");
                    $upd->execute([$social_id, $user['id']]);
                }
                
                $_SESSION['user_id'] = $user['id'];
                
                // Update online status
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $update = $pdo->prepare('UPDATE users SET is_online = 1, last_ip = ? WHERE id = ?');
                $update->execute([$ip, $user['id']]);

                unset($user['password']);
                sendResponse('success', $user, 'Social Login successful.');
            } else {
                // Create new user automatically
                $username = strtolower(explode(' ', $name)[0]) . '_' . rand(1000, 9999);
                $password = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT); // random pass
                $dob = '2000-01-01'; // Default
                $preference = 'straight';
                
                $ins = $pdo->prepare("INSERT INTO users (username, password, fullname, email, dob, preference, is_verified, {$provider}_id) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
                if ($ins->execute([$username, $password, $name, $email, $dob, $preference, $social_id])) {
                    $new_id = $pdo->lastInsertId();
                    $_SESSION['user_id'] = $new_id;
                    
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                    $stmt->execute([$new_id]);
                    $new_user = $stmt->fetch();
                    unset($new_user['password']);
                    
                    sendResponse('success', $new_user, 'Account created via Social Login.');
                } else {
                    sendResponse('error', null, 'Failed to create social account.');
                }
            }
        } else {
            sendResponse('error', null, 'Token verification failed.');
        }
    }
}
?>
