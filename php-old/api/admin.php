<?php
session_start();
require_once 'db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

require_once dirname(__DIR__) . '/includes/env.php';

$admin_pin = getenv('ADMIN_PIN') ?: 'admin123';
$provided_pin = $_GET['pin'] ?? ($input['pin'] ?? '');

if ($provided_pin !== $admin_pin) {
    sendResponse('error', null, 'Unauthorized. Invalid PIN.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // READ Operations
    if ($action === 'users') {
        $stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
        sendResponse('success', $stmt->fetchAll());
    } elseif ($action === 'posts') {
        $stmt = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC');
        sendResponse('success', $stmt->fetchAll());
    } elseif ($action === 'stories') {
        $stmt = $pdo->query('SELECT * FROM stories ORDER BY created_at DESC');
        sendResponse('success', $stmt->fetchAll());
    } elseif ($action === 'messages') {
        $stmt = $pdo->query('
            SELECT m.*, u1.username as sender, u2.username as receiver
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            ORDER BY m.timestamp DESC LIMIT 100
        ');
        sendResponse('success', $stmt->fetchAll());
    } elseif ($action === 'ads') {
        $stmt = $pdo->query('SELECT * FROM ads ORDER BY created_at DESC');
        sendResponse('success', $stmt->fetchAll());
    } elseif ($action === 'orders') {
        $stmt = $pdo->query('SELECT * FROM black_market_orders ORDER BY created_at DESC');
        sendResponse('success', $stmt->fetchAll());
    } elseif ($action === 'stats') {
        $total_users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $new_users = $pdo->query('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()')->fetchColumn();
        $total_posts = $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        $total_messages = $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        $total_invites = $pdo->query('SELECT COUNT(*) FROM invites')->fetchColumn();
        $total_revenue = $pdo->query('SELECT SUM(amount) FROM payments')->fetchColumn() ?: 0.00;
        
        sendResponse('success', [
            'total_users' => $total_users,
            'new_users' => $new_users,
            'total_posts' => $total_posts,
            'total_messages' => $total_messages,
            'total_invites' => $total_invites,
            'total_revenue' => $total_revenue
        ]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CREATE, UPDATE, DELETE Operations
    if ($action === 'delete_user') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'User deleted');
        sendResponse('error', null, 'Failed to delete user');
    } elseif ($action === 'update_user') {
        $id = $input['id'] ?? 0;
        $username = $input['username'] ?? '';
        $fullname = $input['fullname'] ?? '';
        $email = $input['email'] ?? '';
        $phone = $input['phone'] ?? '';
        $dob = $input['dob'] ?? '';
        $preference = $input['preference'] ?? 'straight';
        $gender = $input['gender'] ?? 'female';
        $role = $input['role'] ?? 'user';
        $location = $input['location'] ?? '';
        $bio = $input['bio'] ?? '';
        $wallet_balance = $input['wallet_balance'] ?? 0.00;
        $is_verified = isset($input['is_verified']) ? intval($input['is_verified']) : 0;
        $email_verified = isset($input['email_verified']) ? intval($input['email_verified']) : 0;
        $monnify_account_number = $input['monnify_account_number'] ?? '';
        $monnify_bank_name = $input['monnify_bank_name'] ?? '';
        
        $stmt = $pdo->prepare('
            UPDATE users 
            SET username = ?, fullname = ?, email = ?, phone = ?, dob = ?, 
                preference = ?, gender = ?, role = ?, location = ?, bio = ?, 
                wallet_balance = ?, is_verified = ?, email_verified = ?, 
                monnify_account_number = ?, monnify_bank_name = ? 
            WHERE id = ?
        ');
        if($stmt->execute([
            $username, $fullname, $email, $phone, $dob, 
            $preference, $gender, $role, $location, $bio, 
            $wallet_balance, $is_verified, $email_verified, 
            $monnify_account_number, $monnify_bank_name, 
            $id
        ])) {
            sendResponse('success', null, 'User updated successfully');
        } else {
            sendResponse('error', null, 'Failed to update user');
        }
    } elseif ($action === 'update_user_wallet') {
        $id = $input['id'] ?? 0;
        $amount = $input['amount'] ?? 0;
        $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
        if($stmt->execute([$amount, $id])) sendResponse('success', null, 'Wallet updated');
    } elseif ($action === 'delete_post') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'Post deleted');
    } elseif ($action === 'delete_story') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('DELETE FROM stories WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'Story deleted');
    } elseif ($action === 'delete_message') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'Message deleted');
    } elseif ($action === 'create_ad') {
        $image = $input['image'] ?? '';
        $caption = $input['caption'] ?? '';
        $link = $input['link'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO ads (image, caption, link) VALUES (?, ?, ?)');
        if($stmt->execute([$image, $caption, $link])) sendResponse('success', null, 'Ad created');
    } elseif ($action === 'toggle_ad') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('UPDATE ads SET is_active = NOT is_active WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'Ad status toggled');
    } elseif ($action === 'delete_ad') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('DELETE FROM ads WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'Ad deleted');
    } elseif ($action === 'approve_order') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('UPDATE black_market_orders SET status = "Approved", tracking_step = 2, seller = "Escrow-Verified Courier", escrow_status = "funded" WHERE id = ?');
        if($stmt->execute([$id])) {
            $ord_stmt = $pdo->prepare('SELECT o.user_id, u.email FROM black_market_orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?');
            $ord_stmt->execute([$id]);
            $ord = $ord_stmt->fetch();
            
            if ($ord) {
                // Insert Notification
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'order_approved', ?)");
                $notif->execute([$ord['user_id'], "Your Black Market request has been APPROVED by the Admin! Check order tracking."]);
                
                sendResponse('success', null, "Order Approved! Simulated email notification successfully dispatched to " . $ord['email']);
            } else {
                sendResponse('success', null, 'Order Approved');
            }
        } else {
            sendResponse('error', null, 'Failed to approve order');
        }
    } elseif ($action === 'delete_order') {
        $id = $input['id'] ?? 0;
        $stmt = $pdo->prepare('DELETE FROM black_market_orders WHERE id = ?');
        if($stmt->execute([$id])) sendResponse('success', null, 'Order deleted');
    }
    // ── Resolve support ticket ────────────────────────────────────────
    elseif ($action === 'resolve_ticket') {
        $id = intval($input['id'] ?? 0);
        if ($pdo->prepare("UPDATE support_tickets SET status = 'resolved' WHERE id = ?")->execute([$id])) {
            sendResponse('success', null, 'Ticket resolved');
        }
        sendResponse('error', null, 'Failed');
    }
    // ── Mark report actioned ──────────────────────────────────────────
    elseif ($action === 'action_report') {
        $id = intval($input['id'] ?? 0);
        if ($pdo->prepare("UPDATE reports SET status = 'actioned' WHERE id = ?")->execute([$id])) {
            sendResponse('success', null, 'Report actioned');
        }
        sendResponse('error', null, 'Failed');
    }
}
// ── GET: market listings for admin ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'market') {
    $stmt = $pdo->query("
        SELECT m.*, u.username FROM market m
        LEFT JOIN users u ON m.seller_id = u.id
        ORDER BY m.created_at DESC LIMIT 100
    ");
    sendResponse('success', $stmt->fetchAll());
}
?>
