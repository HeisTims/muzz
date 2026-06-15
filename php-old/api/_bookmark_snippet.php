<?php
// Add bookmark endpoint to feed.php API
session_start();
require_once 'db.php';
require_once 'smtp.php';
require_once 'email_templates.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bookmark_post') {
    if (!$user_id) sendResponse('error', null, 'Unauthorized');
    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = intval($input['post_id'] ?? 0);
    
    // Check if already bookmarked
    $check = $pdo->prepare('SELECT id FROM bookmarks WHERE user_id = ? AND post_id = ?');
    $check->execute([$user_id, $post_id]);
    
    if ($check->fetch()) {
        $pdo->prepare('DELETE FROM bookmarks WHERE user_id = ? AND post_id = ?')->execute([$user_id, $post_id]);
        sendResponse('success', null, 'Removed from saved');
    } else {
        $pdo->prepare('INSERT INTO bookmarks (user_id, post_id) VALUES (?, ?)')->execute([$user_id, $post_id]);
        sendResponse('success', null, 'Post saved!');
    }
}
