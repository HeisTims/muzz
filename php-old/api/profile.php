<?php
session_start();
require_once 'db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';

if (!isset($_SESSION['user_id'])) {
    sendResponse('error', null, 'Unauthorized');
}
$user_id = $_SESSION['user_id'];

// =====================================================================
// GET: Fetch a single post by id (used by profile grid detail view)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_post' || isset($_GET['post_id'])) {
        $post_id = intval($_GET['id'] ?? $_GET['post_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT p.*, u.username, u.avatar, u.is_verified
            FROM posts p JOIN users u ON p.user_id = u.id
            WHERE p.id = ? LIMIT 1
        ");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        if ($post) sendResponse('success', $post);
        sendResponse('error', null, 'Post not found');
    }
}

// =====================================================================
// POST: All profile mutation actions
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── CSRF guard ──────────────────────────────────────────────────
    if (empty($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        sendResponse('error', null, 'CSRF token invalid');
    }

    // ── Update profile fields ────────────────────────────────────────
    if ($action === 'update') {
        $username   = trim($input['username'] ?? '');
        $bio        = trim($input['bio'] ?? '');
        $location   = trim($input['location'] ?? '');
        $preference = trim($input['preference'] ?? '');
        $password   = trim($input['password'] ?? '');

        if (empty($username)) sendResponse('error', null, 'Username is required');

        // Check username uniqueness (excluding self)
        $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $chk->execute([$username, $user_id]);
        if ($chk->fetch()) sendResponse('error', null, 'That username is already taken');

        $params = [$username, $bio, $location, $preference, $user_id];
        $sql    = 'UPDATE users SET username=?, bio=?, location=?, preference=? WHERE id=?';

        if (!empty($password)) {
            if (strlen($password) < 6) sendResponse('error', null, 'Password must be at least 6 characters');
            $hash   = password_hash($password, PASSWORD_BCRYPT);
            $sql    = 'UPDATE users SET username=?, bio=?, location=?, preference=?, password=? WHERE id=?';
            $params = [$username, $bio, $location, $preference, $hash, $user_id];
        }

        if ($pdo->prepare($sql)->execute($params)) {
            sendResponse('success', null, 'Profile updated 💋');
        }
        sendResponse('error', null, 'Update failed');
    }

    // ── Upload / change avatar ───────────────────────────────────────
    elseif ($action === 'update_avatar') {
        $base64 = $input['avatar'] ?? '';
        if (empty($base64)) sendResponse('error', null, 'No image provided');

        // Strip data URI prefix
        $imageData = $base64;
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $ext       = strtolower($matches[1]);
            $imageData = substr($base64, strpos($base64, ',') + 1);
        } else {
            $ext = 'jpg';
        }

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            sendResponse('error', null, 'Unsupported image format');
        }

        $decoded = base64_decode($imageData);
        if (!$decoded) sendResponse('error', null, 'Invalid image data');

        // Save to assets/avatars/
        $uploadDir = dirname(__DIR__) . '/assets/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;
        file_put_contents($filepath, $decoded);

        $avatarUrl = 'assets/avatars/' . $filename;
        $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$avatarUrl, $user_id]);
        sendResponse('success', ['avatar' => $avatarUrl], 'Avatar updated!');
    }

    // ── Delete account ───────────────────────────────────────────────
    elseif ($action === 'delete_account') {
        // Soft-delete: anonymise and deactivate
        $pdo->prepare("UPDATE users SET 
            username = CONCAT('deleted_', id),
            email = CONCAT('deleted_', id, '@eazymuze.com'),
            avatar = '',
            bio = '',
            password = '',
            wallet_balance = 0,
            is_active = 0
            WHERE id = ?")->execute([$user_id]);

        session_destroy();
        sendResponse('success', null, 'Account deleted');
    }
}
?>
