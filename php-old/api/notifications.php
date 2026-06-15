<?php
session_start();
require_once 'db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';

if (!isset($_SESSION['user_id'])) {
    sendResponse('error', null, 'Unauthorized');
}
$user_id = $_SESSION['user_id'];

// Get all notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
        $stmt->execute([$user_id]);
        sendResponse('success', $stmt->fetchAll());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'mark_read') {
        $notif_id = $input['id'] ?? 0;
        if ($notif_id === 'all') {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$notif_id, $user_id]);
        }
        sendResponse('success', null, 'Marked as read');
    }
    
    // Custom endpoint to simulate a push notification to self or others
    elseif ($action === 'simulate_push') {
        $title = $input['title'] ?? 'EazyMUZE 💋';
        $body = $input['message'] ?? 'Someone just adored your desire!';
        
        // Log in the notification database table
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'push', ?)");
        $stmt->execute([$user_id, $body]);
        
        sendResponse('success', null, 'Notification registered');
    }
}
?>
