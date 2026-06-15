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
// POST: Support tickets & reports
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // CSRF
    if (empty($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        sendResponse('error', null, 'CSRF token invalid');
    }

    $type = $input['type'] ?? '';

    // ── Support ticket ───────────────────────────────────────────────
    if ($type === 'support') {
        $category = trim($input['category'] ?? '');
        $message  = trim($input['message'] ?? '');
        if (!$category || !$message) sendResponse('error', null, 'Fill all fields');

        // Store in support_tickets table
        $stmt = $pdo->prepare("
            INSERT INTO support_tickets (user_id, category, message, status)
            VALUES (?, ?, ?, 'open')
        ");
        $stmt->execute([$user_id, $category, $message]);

        // Also create admin notification
        $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (1, 'support', ?)")
            ->execute(["Support ticket from user #{$user_id}: [{$category}] {$message}"]);

        // Email confirmation (optional, non-blocking)
        try {
            require_once 'smtp.php';
            $userStmt = $pdo->prepare('SELECT email, username FROM users WHERE id = ?');
            $userStmt->execute([$user_id]);
            $u = $userStmt->fetch();
            if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                sendMuzeEmail(
                    $u['email'], $u['username'],
                    '💌 Support Ticket Received — EazyMUZE',
                    "<p>Hi {$u['username']},</p><p>We received your support request about <strong>{$category}</strong>. Our team will respond within 24 hours.</p><p>— The EazyMUZE Team</p>"
                );
            }
        } catch (Exception $e) {
            error_log('Help email error: ' . $e->getMessage());
        }

        sendResponse('success', null, 'Support ticket submitted! We\'ll respond via email within 24 hours 💌');
    }

    // ── Report a user ────────────────────────────────────────────────
    elseif ($type === 'report') {
        $username = trim($input['username'] ?? '');
        $reason   = trim($input['reason'] ?? '');
        if (!$username || !$reason) sendResponse('error', null, 'Fill all fields');

        // Find the reported user
        $reportedStmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $reportedStmt->execute([$username]);
        $reported = $reportedStmt->fetch();
        $reported_id = $reported ? $reported['id'] : 0;

        $pdo->prepare("INSERT INTO reports (reporter_id, reported_user_id, reported_username, reason, status) VALUES (?, ?, ?, ?, 'pending')")
            ->execute([$user_id, $reported_id, $username, $reason]);

        // Admin notification
        $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (1, 'report', ?)")
            ->execute(["User @{$username} reported: {$reason}"]);

        sendResponse('success', null, 'Report submitted. Our team will review within 24 hours. Thank you 🛡️');
    }
}
?>
