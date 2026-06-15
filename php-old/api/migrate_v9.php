<?php
// =====================================================================
// EazyMUZE — Migration v9: invites sender/receiver schema + payments.reference
// =====================================================================
require_once 'db.php';

$migrations = [
    // ── invites: add sender_id, receiver_id, message, status columns ──
    "ALTER TABLE invites ADD COLUMN IF NOT EXISTS sender_id   INT UNSIGNED DEFAULT NULL",
    "ALTER TABLE invites ADD COLUMN IF NOT EXISTS receiver_id INT UNSIGNED DEFAULT NULL",
    "ALTER TABLE invites ADD COLUMN IF NOT EXISTS message     TEXT DEFAULT NULL",
    "ALTER TABLE invites ADD COLUMN IF NOT EXISTS status      ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending'",

    // ── payments: add reference for duplicate-payment prevention ──────
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS reference VARCHAR(120) DEFAULT NULL",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",

    // ── users: ensure phone column exists ─────────────────────────────
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL",

    // ── messages: ensure image_url exists (safe repeat) ───────────────
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS image_url MEDIUMTEXT DEFAULT NULL",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS reaction  VARCHAR(10) DEFAULT NULL",

    // ── Update last_seen for all users without it ─────────────────────
    "UPDATE users SET last_seen = NOW() WHERE last_seen IS NULL",

    // ── Clean expired kv_store rows ───────────────────────────────────
    "DELETE FROM kv_store WHERE expires_at < NOW()",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['sql' => substr($sql, 0, 90), 'status' => 'OK'];
    } catch (PDOException $e) {
        $results[] = ['sql' => substr($sql, 0, 90), 'status' => 'SKIP/ERROR', 'msg' => $e->getMessage()];
    }
}

header('Content-Type: application/json');
echo json_encode(['migration' => 'v9', 'results' => $results, 'time' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
?>
