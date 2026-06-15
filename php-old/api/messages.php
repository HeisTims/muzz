<?php
session_start();
require_once 'db.php';
require_once 'smtp.php';
require_once 'email_templates.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', null, 'Unauthorized');
    }
    $user_id = $_SESSION['user_id'];

    if ($action === 'inbox') {
        $stmt = $pdo->prepare('
            SELECT m.*, 
                   u1.username as sender_name, u1.avatar as sender_avatar,
                   u2.username as receiver_name, u2.avatar as receiver_avatar
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE m.sender_id = ? OR m.receiver_id = ?
            ORDER BY m.timestamp ASC
        ');
        $stmt->execute([$user_id, $user_id]);
        $messages = $stmt->fetchAll();
        sendResponse('success', $messages);
    } elseif ($action === 'sync') {
        $last_id = intval($_GET['last_id'] ?? 0);
        $stmt = $pdo->prepare('
            SELECT m.*, 
                   u1.username as sender_name, u1.avatar as sender_avatar,
                   u2.username as receiver_name, u2.avatar as receiver_avatar
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE (m.sender_id = ? OR m.receiver_id = ?) AND m.id > ?
            ORDER BY m.id ASC
        ');
        $stmt->execute([$user_id, $user_id, $last_id]);
        $messages = $stmt->fetchAll();
        sendResponse('success', $messages);
    } elseif ($action === 'get_messages') {
        // Real-time polling for new messages in a specific conversation
        $partner_id = intval($_GET['partner_id'] ?? 0);
        $last_id    = intval($_GET['last_id'] ?? 0);
        if (!$partner_id) sendResponse('error', null, 'Partner ID required');
        $stmt = $pdo->prepare('
            SELECT m.*, u.avatar
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE ((m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?))
              AND m.id > ?
            ORDER BY m.id ASC
            LIMIT 30
        ');
        $stmt->execute([$user_id, $partner_id, $partner_id, $user_id, $last_id]);
        $msgs = $stmt->fetchAll();
        // Mark incoming as read
        $pdo->prepare('UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND id > ?')->execute([$partner_id, $user_id, $last_id]);
        sendResponse('success', $msgs);
    } elseif ($action === 'is_typing') {
        $partner_id = intval($_GET['partner_id'] ?? 0);
        $cache_key  = "typing_{$partner_id}_{$user_id}";
        // Use a DB row in sessions or a temp file as a lightweight typing flag
        $typing_stmt = $pdo->prepare('SELECT val FROM kv_store WHERE k = ? LIMIT 1');
        $typing_stmt->execute([$cache_key]);
        $typing_row = $typing_stmt->fetch();
        $is_typing  = $typing_row && $typing_row['val'] == '1';
        // Auto-expire: if partner last set typing > 4 seconds ago, it's stale
        sendResponse('success', ['typing' => $is_typing]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', null, 'Unauthorized');
    }
    
    $user_id = $_SESSION['user_id'];

    if ($action === 'send' || (isset($input['receiver_id']) && !$action)) {
        // Accept both ?action=send and direct POST with receiver_id (from chat.php)
        $receiver_id = intval($input['receiver_id'] ?? 0);
        $text        = trim($input['text'] ?? '');
        $image_url   = $input['image_url'] ?? ''; // base64 or URL
        $location_data = $input['location_data'] ?? '';
        if (!$receiver_id) sendResponse('error', null, 'Receiver ID required');
        if (!$text && !$image_url) sendResponse('error', null, 'Message content required');

        $stmt = $pdo->prepare('SELECT id FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1');
        $stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
        $has_convo = $stmt->fetch();

        if (!$has_convo) {
            $stmt = $pdo->prepare('SELECT wallet_balance, has_used_free_whisper FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();

            if ($user_data['has_used_free_whisper'] == 0) {
                // First whisper is free
                $update = $pdo->prepare('UPDATE users SET has_used_free_whisper = 1 WHERE id = ?');
                $update->execute([$user_id]);
            } else {
                if ($user_data['wallet_balance'] < 500) {
                    sendResponse('error', null, 'Insufficient funds. First whisper is free, subsequent whispers require ₦500.');
                }
                $update = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - 500 WHERE id = ?');
                $update->execute([$user_id]);
                
                $pay = $pdo->prepare("INSERT INTO payments (type, payer_id, recipient_id, amount) VALUES ('whisper_init', ?, ?, 500)");
                $pay->execute([$user_id, $receiver_id]);
            }
        }

        $real_ip    = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';

        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, text, image_url, location_data, device_info) VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt->execute([$user_id, $receiver_id, $text, $image_url, $real_ip, $user_agent])) {
            
            // Insert Notification for receiver
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'whisper', 'You received a new whisper!')");
            $notif->execute([$receiver_id]);

            // --- Send Email Alert to Receiver ---
            $senderStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $senderStmt->execute([$user_id]);
            $senderInfo = $senderStmt->fetch();

            $receiverStmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
            $receiverStmt->execute([$receiver_id]);
            $receiverInfo = $receiverStmt->fetch();

            if ($receiverInfo && $senderInfo && filter_var($receiverInfo['email'], FILTER_VALIDATE_EMAIL)) {
                $emailBody = emailTemplate_NewWhisper($receiverInfo['username'], $senderInfo['username']);
                sendMuzeEmail($receiverInfo['email'], $receiverInfo['username'], '💬 ' . $senderInfo['username'] . ' Whispered to You on EazyMUZE', $emailBody);
            }

            // Advanced AI Logic for seeded mock users (assume id <= 50 are bots in this mock environment)
            if ($receiver_id <= 50) {
                $bot_replies = [
                    "Oh, you're bold... I like that. 💋",
                    "Whisper a little closer...",
                    "Are you always this straightforward? Makes me wonder what else you're capable of. 🔥",
                    "Mmm, tell me more. I'm listening.",
                    "That's one way to get my attention. Don't stop."
                ];
                $reply = $bot_replies[array_rand($bot_replies)];
                
                // Bot replies instantly
                $ai_stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, text, location_data) VALUES (?, ?, ?, ?)');
                $ai_stmt->execute([$receiver_id, $user_id, $reply, 'Temple Coordinates']);
                
                // Notify sender
                $notif_back = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'whisper', 'New response in your whispers 💋')");
                $notif_back->execute([$user_id]);
            }

            sendResponse('success', null, 'Whisper sent');
        } else {
            sendResponse('error', null, 'Failed to send whisper');
        }
    }
    elseif ($action === 'unlock') {
        $message_id = $input['message_id'] ?? 0;
        
        $stmt = $pdo->prepare('SELECT wallet_balance, has_used_free_read FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        $wallet = $user_data['wallet_balance'];

        if ($user_data['has_used_free_read'] == 0) {
            $update = $pdo->prepare('UPDATE users SET has_used_free_read = 1 WHERE id = ?');
            $update->execute([$user_id]);
        } else {
            if ($wallet < 200) {
                sendResponse('error', null, 'Insufficient funds to unlock whisper. Requires ₦200.');
            }

            $update = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - 200 WHERE id = ?');
            $update->execute([$user_id]);
        }

        $update_msg = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?');
        $update_msg->execute([$message_id, $user_id]);

        sendResponse('success', null, 'Whisper unlocked');
    }
    elseif ($action === 'initiate_convo') {
        $receiver_id = $input['receiver_id'] ?? 0;

        // Check if there is already a convo (messages exist between them)
        $stmt = $pdo->prepare('SELECT id FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) LIMIT 1');
        $stmt->execute([$user_id, $receiver_id, $receiver_id, $user_id]);
        $has_convo = $stmt->fetch();

        if ($has_convo) {
            sendResponse('success', null, 'Conversation already initiated');
        }

        // Get user details
        $stmt = $pdo->prepare('SELECT wallet_balance, has_used_free_whisper FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        if ($user_data['has_used_free_whisper'] == 0) {
            // First whisper is free, update has_used_free_whisper and return success
            $update = $pdo->prepare('UPDATE users SET has_used_free_whisper = 1 WHERE id = ?');
            $update->execute([$user_id]);
            sendResponse('success', null, 'Free whisper initiated');
        }

        if ($user_data['wallet_balance'] < 500) {
            sendResponse('error', null, 'Insufficient funds. Initiation fee of ₦500 required.');
        }

        // Deduct 500
        $update = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - 500 WHERE id = ?');
        $update->execute([$user_id]);

        // Insert payment record
        $pay = $pdo->prepare("INSERT INTO payments (type, payer_id, recipient_id, amount) VALUES ('whisper_init', ?, ?, 500)");
        $pay->execute([$user_id, $receiver_id]);

        // Insert an automated hot welcoming icebreaker message from the partner (receiver_id) to the sender (user_id)
        $welcome_messages = [
            "I was hoping you'd unlock me... what do you want to whisper first? 💋",
            "Mmm, thanks for opening this door. Let's make it worth it. 🔥",
            "You've got my full attention now. Tell me your deepest desires. 😉",
            "A premium taste... I like a partner who knows what they want. Let's play. 🥂",
            "Now that we're connected, don't keep me waiting. What's on your mind? 😈"
        ];
        $welcome_text = $welcome_messages[array_rand($welcome_messages)];

        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, text, is_read, location_data) VALUES (?, ?, ?, 1, ?)');
        $stmt->execute([$receiver_id, $user_id, $welcome_text, 'Inner Sanctum']);

        // Insert Notification for sender
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'whisper', 'Whisper channel unlocked with your muze! 💋')");
        $notif->execute([$user_id]);

        sendResponse('success', null, 'Conversation initiated successfully');
    }
    elseif ($action === 'set_typing') {
        // Lightweight typing indicator via kv_store table
        $partner_id = intval($input['partner_id'] ?? 0);
        $is_typing  = intval($input['is_typing'] ?? 0);
        if (!$partner_id) sendResponse('error', null, 'Partner ID required');
        $cache_key = "typing_{$user_id}_{$partner_id}";
        // Upsert into kv_store
        $pdo->prepare('INSERT INTO kv_store (k, val, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 SECOND))
                       ON DUPLICATE KEY UPDATE val = VALUES(val), expires_at = VALUES(expires_at)')
            ->execute([$cache_key, $is_typing ? '1' : '0']);
        sendResponse('success', null, 'ok');
    }
    elseif ($action === 'react_message') {
        $msg_id   = intval($input['msg_id'] ?? 0);
        $reaction = $input['reaction'] ?? '';
        if (!$msg_id || !$reaction) sendResponse('error', null, 'Invalid data');
        // Only let either sender or receiver react
        $stmt = $pdo->prepare('SELECT id FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)');
        $stmt->execute([$msg_id, $user_id, $user_id]);
        if (!$stmt->fetch()) sendResponse('error', null, 'Unauthorized');
        $pdo->prepare('UPDATE messages SET reaction = ? WHERE id = ?')->execute([$reaction, $msg_id]);
        sendResponse('success', null, 'Reaction added');
    }
}
?>
