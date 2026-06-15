<?php
// =====================================================================
// EazyMUZE — Database Migration v8
// New tables: market, kv_store, support_tickets, reports
// New columns: messages.image_url, messages.reaction, users.last_seen, users.is_active
// =====================================================================
require_once 'db.php';

$migrations = [

    // ── messages: image sharing & reactions ──────────────────────────
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS image_url MEDIUMTEXT DEFAULT NULL",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS reaction VARCHAR(10) DEFAULT NULL",

    // ── users: last seen (online status) & active flag ───────────────
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen DATETIME DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(120) DEFAULT NULL",

    // ── kv_store: lightweight typing indicator & flags ───────────────
    "CREATE TABLE IF NOT EXISTS kv_store (
        k          VARCHAR(120) NOT NULL PRIMARY KEY,
        val        TEXT         NOT NULL DEFAULT '',
        expires_at DATETIME     NOT NULL DEFAULT (NOW() + INTERVAL 1 HOUR),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── market: user-created listings ───────────────────────────────
    "CREATE TABLE IF NOT EXISTS market (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        seller_id   INT UNSIGNED NOT NULL,
        title       VARCHAR(255) NOT NULL,
        description TEXT         NOT NULL,
        price       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        category    VARCHAR(80)  NOT NULL DEFAULT 'Other',
        image       MEDIUMTEXT   DEFAULT NULL,
        status      ENUM('active','sold','removed') NOT NULL DEFAULT 'active',
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_seller (seller_id),
        INDEX idx_status  (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // ── support_tickets ───────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS support_tickets (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        category    VARCHAR(80)  NOT NULL,
        message     TEXT         NOT NULL,
        status      ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
        admin_reply TEXT         DEFAULT NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME     ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user   (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // ── reports ───────────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS reports (
        id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reporter_id          INT UNSIGNED NOT NULL,
        reported_user_id     INT UNSIGNED DEFAULT NULL,
        reported_username    VARCHAR(80)  NOT NULL,
        reason               TEXT         NOT NULL,
        status               ENUM('pending','reviewed','actioned') NOT NULL DEFAULT 'pending',
        admin_notes          TEXT         DEFAULT NULL,
        created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reporter  (reporter_id),
        INDEX idx_reported  (reported_user_id),
        INDEX idx_status    (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // ── bookmarks (ensure exists) ─────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS bookmarks (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        post_id    INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bookmark (user_id, post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── reactions (ensure exists) ─────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS reactions (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    INT UNSIGNED NOT NULL,
        post_id    INT UNSIGNED NOT NULL,
        reaction   VARCHAR(10)  NOT NULL DEFAULT '❤️',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reaction (user_id, post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ── Seed initial last_seen for existing users ─────────────────────
    "UPDATE users SET last_seen = NOW() WHERE last_seen IS NULL",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        $short = substr($sql, 0, 80);
        $results[] = ['sql' => $short, 'status' => 'OK'];
    } catch (PDOException $e) {
        $results[] = ['sql' => substr($sql, 0, 80), 'status' => 'ERROR', 'msg' => $e->getMessage()];
    }
}

header('Content-Type: application/json');
echo json_encode(['migration' => 'v8', 'results' => $results, 'time' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT);
?>
