<?php
session_start();
require_once 'db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', null, 'Unauthorized');
    }
    
    if ($action === 'list') {
        $stmt = $pdo->query('SELECT * FROM invites ORDER BY created_at DESC');
        $res = $stmt->fetchAll();
        
        if (count($res) === 0) {
            $seed_users = [
                ['user_id' => 2, 'username' => 'Sensual_Sandra', 'title' => 'Secret Pool Party 💦', 'description' => 'Looking for adventurous individuals to join a VIP pool party in Lekki Phase 1 tonight. Free drinks and premium vibes! Only 5 slots available.'],
                ['user_id' => 3, 'username' => 'SugarMummy_Rita', 'title' => 'Candlelit Dinner & Penthouse Vibes 🕯️', 'description' => 'Hosting a private penthouse dinner. Looking for young, charming, respectful companions to have great conversations and explore mutual desires.'],
                ['user_id' => 4, 'username' => 'Wild_West', 'title' => 'Late Night Intimate Meetup 😈', 'description' => 'Late night session. Hookups and bisexuals in the neighborhood welcome to show interest! ₦500 queue fee. Host picking max 5 partners.']
            ];
            
            foreach ($seed_users as $su) {
                $u_chk = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
                $u_chk->execute([$su['user_id']]);
                $chk = $u_chk->fetch();
                
                $uid = $chk ? $su['user_id'] : 1;
                $uname = $chk ? $chk['username'] : 'MuzeBot';
                
                $ins = $pdo->prepare('INSERT INTO invites (user_id, username, title, description, volunteers) VALUES (?, ?, ?, ?, ?)');
                $ins->execute([$uid, $uname, $su['title'], $su['description'], '[]']);
            }
            
            $stmt = $pdo->query('SELECT * FROM invites ORDER BY created_at DESC');
            $res = $stmt->fetchAll();
        }
        
        sendResponse('success', $res);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', null, 'Unauthorized');
    }
    
    $user_id = $_SESSION['user_id'];

    if ($action === 'create') {
        $title = $input['title'] ?? '';
        $description = $input['description'] ?? '';

        if (!$title || !$description) {
            sendResponse('error', null, 'Title and Description are required.');
        }

        // Deduct ₦1500
        $stmt = $pdo->prepare('SELECT wallet_balance, username FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user['wallet_balance'] < 1500) {
            sendResponse('error', null, 'Insufficient wallet balance. Creation of invite costs ₦1,500.');
        }

        $pdo->beginTransaction();
        try {
            // Deduct
            $upd = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - 1500 WHERE id = ?');
            $upd->execute([$user_id]);

            // Save transaction
            $pay = $pdo->prepare("INSERT INTO payments (type, payer_id, amount) VALUES ('invite_creation', ?, 1500)");
            $pay->execute([$user_id]);

            // Insert Invite
            $ins = $pdo->prepare('INSERT INTO invites (user_id, username, title, description, volunteers) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$user_id, $user['username'], $title, $description, '[]']);

            $pdo->commit();
            sendResponse('success', null, 'Invite created successfully! ₦1,500 charged.');
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse('error', null, 'Failed to create invite: ' . $e->getMessage());
        }
    }
    elseif ($action === 'join') {
        $invite_id = $input['invite_id'] ?? 0;

        // Deduct ₦500
        $stmt = $pdo->prepare('SELECT wallet_balance, username FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user['wallet_balance'] < 500) {
            sendResponse('error', null, 'Insufficient wallet balance. Expressing interest costs ₦500.');
        }

        // Fetch invite
        $inv_stmt = $pdo->prepare('SELECT * FROM invites WHERE id = ?');
        $inv_stmt->execute([$invite_id]);
        $invite = $inv_stmt->fetch();

        if (!$invite) {
            sendResponse('error', null, 'Invite not found.');
        }

        if ($invite['user_id'] == $user_id) {
            sendResponse('error', null, 'You cannot join your own invite queue.');
        }

        $volunteers = json_decode($invite['volunteers'] ?? '[]', true);
        
        // Check if already in queue
        foreach ($volunteers as $v) {
            if ($v['id'] == $user_id) {
                sendResponse('error', null, 'You already showed interest in this invite.');
            }
        }

        $pdo->beginTransaction();
        try {
            // Deduct
            $upd = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - 500 WHERE id = ?');
            $upd->execute([$user_id]);

            // Save transaction
            $pay = $pdo->prepare("INSERT INTO payments (type, payer_id, amount) VALUES ('invite_join', ?, 500)");
            $pay->execute([$user_id]);

            // Add creator notification
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'invite', ?)");
            $notif->execute([$invite['user_id'], "@" . $user['username'] . " showed interest in your event: " . $invite['title']]);

            // Append to queue
            $volunteers[] = [
                'id' => $user_id,
                'username' => $user['username'],
                'joined_at' => date('Y-m-d H:i:s'),
                'selected' => false
            ];

            $upd_inv = $pdo->prepare('UPDATE invites SET volunteers = ? WHERE id = ?');
            $upd_inv->execute([json_encode($volunteers), $invite_id]);

            $pdo->commit();
            sendResponse('success', null, 'Interest submitted! ₦500 charged.');
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse('error', null, 'Failed to join: ' . $e->getMessage());
        }
    }
    elseif ($action === 'select_volunteers') {
        $invite_id = $input['invite_id'] ?? 0;
        $selected_ids = $input['selected_ids'] ?? []; // Array of user IDs

        if (count($selected_ids) > 5) {
            sendResponse('error', null, 'You can pick a maximum of 5 partners.');
        }

        $inv_stmt = $pdo->prepare('SELECT * FROM invites WHERE id = ? AND user_id = ?');
        $inv_stmt->execute([$invite_id, $user_id]);
        $invite = $inv_stmt->fetch();

        if (!$invite) {
            sendResponse('error', null, 'Invite not found or unauthorized.');
        }

        $volunteers = json_decode($invite['volunteers'] ?? '[]', true);
        foreach ($volunteers as &$v) {
            if (in_array($v['id'], $selected_ids)) {
                $v['selected'] = true;

                // Notify volunteer
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'invite_selected', ?)");
                $notif->execute([$v['id'], "You have been CHOSEN by @" . $invite['username'] . " for: " . $invite['title'] . "! Start a whisper conversation!"]);
            } else {
                $v['selected'] = false;
            }
        }

        $upd = $pdo->prepare('UPDATE invites SET volunteers = ? WHERE id = ?');
        if ($upd->execute([json_encode($volunteers), $invite_id])) {
            sendResponse('success', null, 'Volunteers selected successfully!');
        } else {
            sendResponse('error', null, 'Failed to update selection.');
        }
    }
    // ── respond: accept or decline an incoming connection invite ──────
    elseif ($action === 'respond') {
        $invite_id = intval($input['invite_id'] ?? 0);
        $response  = $input['action'] ?? ''; // 'accept' or 'decline'

        if (!in_array($response, ['accept', 'decline'])) {
            sendResponse('error', null, 'Invalid response action');
        }

        // Verify invite belongs to this user as receiver
        $inv = $pdo->prepare('SELECT * FROM invites WHERE id = ? AND receiver_id = ?');
        $inv->execute([$invite_id, $user_id]);
        $invite = $inv->fetch();

        if (!$invite) {
            sendResponse('error', null, 'Invite not found or not authorised');
        }

        $newStatus = ($response === 'accept') ? 'accepted' : 'declined';
        $pdo->prepare('UPDATE invites SET status = ? WHERE id = ?')->execute([$newStatus, $invite_id]);

        if ($response === 'accept') {
            // Notify sender that their invite was accepted
            $me_stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $me_stmt->execute([$user_id]);
            $me = $me_stmt->fetch();

            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'invite_accepted', ?)")
                ->execute([
                    $invite['sender_id'],
                    '@' . ($me['username'] ?? 'Someone') . ' accepted your connection invite! Start whispering 💋'
                ]);
        }

        sendResponse('success', null, $response === 'accept' ? 'Connection accepted! 💋' : 'Invite declined.');
    }
    // ── send: send a direct connection invite to another user ─────────
    elseif ($action === 'send') {
        $receiver_id = intval($input['receiver_id'] ?? 0);
        $message     = trim($input['message'] ?? '');

        if (!$receiver_id || $receiver_id === $user_id) {
            sendResponse('error', null, 'Invalid receiver');
        }

        // Check if invite already exists
        $existing = $pdo->prepare('SELECT id FROM invites WHERE sender_id = ? AND receiver_id = ? AND status = "pending"');
        $existing->execute([$user_id, $receiver_id]);
        if ($existing->fetch()) {
            sendResponse('error', null, 'You already sent an invite to this person.');
        }

        $sender_stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $sender_stmt->execute([$user_id]);
        $sender = $sender_stmt->fetch();

        $pdo->prepare('INSERT INTO invites (sender_id, receiver_id, message, status, created_at) VALUES (?, ?, ?, "pending", NOW())')
            ->execute([$user_id, $receiver_id, $message]);

        // Notify receiver
        $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'invite', ?)")
            ->execute([$receiver_id, '@' . $sender['username'] . ' sent you a connection invite! 💕']);

        sendResponse('success', null, 'Invite sent! 💕');
    }
}
?>
