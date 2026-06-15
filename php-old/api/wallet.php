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
// GET: wallet balance
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'balance') {
        $stmt = $pdo->prepare('SELECT wallet_balance, monnify_account_number, monnify_bank_name FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        sendResponse('success', $stmt->fetch());
    }
}

// =====================================================================
// POST: fund & withdraw
// =====================================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // CSRF guard
    if (empty($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        sendResponse('error', null, 'CSRF token invalid');
    }

    // ── Fund wallet ──────────────────────────────────────────────────
    if ($action === 'fund') {
        $amount    = floatval($input['amount'] ?? 0);
        $reference = trim($input['reference'] ?? '');

        if ($amount <= 0) sendResponse('error', null, 'Invalid amount');

        // Prevent duplicate reference
        if ($reference) {
            $dup = $pdo->prepare('SELECT id FROM payments WHERE reference = ? LIMIT 1');
            $dup->execute([$reference]);
            if ($dup->fetch()) sendResponse('error', null, 'This payment reference has already been used');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$amount, $user_id]);
            $pdo->prepare("INSERT INTO payments (type, payer_id, amount, reference) VALUES ('wallet_funding', ?, ?, ?)")
                ->execute([$user_id, $amount, $reference]);

            // Notification
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)")
                ->execute([$user_id, '💰 Wallet funded with ₦' . number_format($amount, 2) . '. Balance updated!']);

            $pdo->commit();
            sendResponse('success', null, 'Wallet funded successfully! ₦' . number_format($amount, 2) . ' added.');
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse('error', null, 'Failed to fund wallet: ' . $e->getMessage());
        }
    }

    // ── Withdraw ─────────────────────────────────────────────────────
    elseif ($action === 'withdraw') {
        $amount         = floatval($input['amount'] ?? 0);
        $bank           = trim($input['bank'] ?? '');
        $account_number = trim($input['account_number'] ?? '');

        if ($amount < 500)   sendResponse('error', null, 'Minimum withdrawal is ₦500');
        if (!$bank)          sendResponse('error', null, 'Bank name is required');
        if (!$account_number) sendResponse('error', null, 'Account number is required');

        // Check balance
        $bal_stmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = ?');
        $bal_stmt->execute([$user_id]);
        $user = $bal_stmt->fetch();

        if ($user['wallet_balance'] < $amount) {
            sendResponse('error', null, 'Insufficient wallet balance');
        }

        $pdo->beginTransaction();
        try {
            // Deduct
            $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?')->execute([$amount, $user_id]);

            // Log withdrawal
            $pdo->prepare("INSERT INTO payments (type, payer_id, amount, reference) VALUES ('withdrawal', ?, ?, ?)")
                ->execute([$user_id, $amount, 'WD-' . time() . '-' . $user_id]);

            // Internal withdrawal request record
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)")
                ->execute([$user_id, '📤 Withdrawal of ₦' . number_format($amount, 2) . ' to ' . $bank . ' (' . $account_number . ') is being processed. Allow up to 24 hours.']);

            // Admin alert
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (1, 'withdrawal_request', ?)")
                ->execute(['User #' . $user_id . ' requested withdrawal of ₦' . number_format($amount, 2) . ' to ' . $bank . ' ' . $account_number]);

            $pdo->commit();
            sendResponse('success', null, 'Withdrawal of ₦' . number_format($amount, 2) . ' requested! We\'ll process within 24 hours. 💌');
        } catch (Exception $e) {
            $pdo->rollBack();
            sendResponse('error', null, 'Withdrawal failed: ' . $e->getMessage());
        }
    }

    // ── Monnify webhook confirmation (called from webhook.php) ────────
    elseif ($action === 'confirm_monnify') {
        $amount    = floatval($input['amount'] ?? 0);
        $reference = trim($input['reference'] ?? '');
        $target_id = intval($input['user_id'] ?? $user_id);

        if ($amount > 0) {
            // Duplicate check
            $dup = $pdo->prepare('SELECT id FROM payments WHERE reference = ? LIMIT 1');
            $dup->execute([$reference]);
            if (!$dup->fetch()) {
                $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$amount, $target_id]);
                $pdo->prepare("INSERT INTO payments (type, payer_id, amount, reference) VALUES ('wallet_funding', ?, ?, ?)")
                    ->execute([$target_id, $amount, $reference]);
                $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)")
                    ->execute([$target_id, '💰 ₦' . number_format($amount, 2) . ' received via bank transfer. Wallet updated!']);
            }
            sendResponse('success', null, 'Confirmed');
        }
        sendResponse('error', null, 'Invalid amount');
    }
}
?>
