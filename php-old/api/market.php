<?php
session_start();
require_once 'db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");

$action  = $_GET['action'] ?? '';

if (!isset($_SESSION['user_id'])) {
    sendResponse('error', null, 'Unauthorized');
}
$user_id = $_SESSION['user_id'];

// =====================================================================
// GET
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.avatar, u.is_verified
            FROM market m
            JOIN users u ON m.seller_id = u.id
            WHERE m.status = 'active'
            ORDER BY m.created_at DESC LIMIT 60
        ");
        $stmt->execute();
        sendResponse('success', $stmt->fetchAll());
    }

    elseif ($action === 'get' && isset($_GET['id'])) {
        $item_id = intval($_GET['id']);
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.avatar, u.is_verified
            FROM market m
            JOIN users u ON m.seller_id = u.id
            WHERE m.id = ? LIMIT 1
        ");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        if ($item) sendResponse('success', $item);
        sendResponse('error', null, 'Item not found');
    }

    elseif ($action === 'my_listings') {
        $stmt = $pdo->prepare("SELECT * FROM market WHERE seller_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        sendResponse('success', $stmt->fetchAll());
    }
}

// =====================================================================
// POST
// =====================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // CSRF guard
    if (empty($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        sendResponse('error', null, 'CSRF token invalid');
    }

    // ── Create listing ────────────────────────────────────────────────
    if (!$action || $action === 'create') {
        $title       = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $price       = floatval($input['price'] ?? 0);
        $category    = trim($input['category'] ?? '');
        $imageBase64 = $input['image'] ?? '';

        if (!$title || !$description || $price <= 0 || !$category) {
            sendResponse('error', null, 'All fields are required');
        }

        // Save image if provided
        $imageUrl = '';
        if ($imageBase64) {
            if (preg_match('/^data:image\/(\w+);base64,/', $imageBase64, $m)) {
                $ext       = strtolower($m[1]);
                $imageData = substr($imageBase64, strpos($imageBase64, ',') + 1);
                $uploadDir = dirname(__DIR__) . '/assets/market/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $filename  = 'mkt_' . $user_id . '_' . time() . '.' . $ext;
                file_put_contents($uploadDir . $filename, base64_decode($imageData));
                $imageUrl  = 'assets/market/' . $filename;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO market (seller_id, title, description, price, category, image, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        if ($stmt->execute([$user_id, $title, $description, $price, $category, $imageUrl])) {
            sendResponse('success', ['id' => $pdo->lastInsertId()], 'Listing created!');
        }
        sendResponse('error', null, 'Failed to create listing');
    }

    // ── Purchase item ─────────────────────────────────────────────────
    elseif ($action === 'purchase') {
        $item_id = intval($input['item_id'] ?? 0);
        if (!$item_id) sendResponse('error', null, 'Invalid item');

        // Fetch item
        $item_stmt = $pdo->prepare('SELECT * FROM market WHERE id = ? AND status = "active" LIMIT 1');
        $item_stmt->execute([$item_id]);
        $item = $item_stmt->fetch();
        if (!$item) sendResponse('error', null, 'Item not found or already sold');
        if ($item['seller_id'] == $user_id) sendResponse('error', null, 'You cannot buy your own listing');

        $price = floatval($item['price']);

        // Check buyer wallet
        $buyer_stmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $buyer_stmt->execute([$user_id]);
        $buyer = $buyer_stmt->fetch();
        if ($buyer['wallet_balance'] < $price) {
            sendResponse('error', null, 'Insufficient wallet balance. Please fund your wallet.');
        }

        // Deduct from buyer
        $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?')->execute([$price, $user_id]);

        // Credit seller (minus 10% platform fee)
        $sellerPayout = $price * 0.90;
        $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$sellerPayout, $item['seller_id']]);

        // Log payments
        $pdo->prepare("INSERT INTO payments (type, payer_id, recipient_id, amount) VALUES ('market_purchase', ?, ?, ?)")
            ->execute([$user_id, $item['seller_id'], $price]);

        // Notify seller
        $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'market', ?)")
            ->execute([$item['seller_id'], "Someone purchased your listing: {$item['title']} — ₦" . number_format($sellerPayout, 2) . ' credited.']);

        // Mark item as sold
        $pdo->prepare("UPDATE market SET status = 'sold' WHERE id = ?")->execute([$item_id]);

        sendResponse('success', null, 'Purchase successful! Check your profile for details 🎉');
    }

    // ── Delete listing ────────────────────────────────────────────────
    elseif ($action === 'delete') {
        $item_id = intval($input['item_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE market SET status = "removed" WHERE id = ? AND seller_id = ?');
        if ($stmt->execute([$item_id, $user_id]) && $stmt->rowCount()) {
            sendResponse('success', null, 'Listing removed');
        }
        sendResponse('error', null, 'Not found or not authorised');
    }
}
?>
