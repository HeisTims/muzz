<?php
// run_updates.php
// EazyMUZE v2.5 - Bulletproof Database Migration Runner

header('Content-Type: text/html; charset=utf-8');
echo "<div style='font-family:sans-serif; max-width:800px; margin:20px auto; padding:20px; background:#111; color:#eee; border-radius:12px; border:1px solid #ff2a6d;'>";
echo "<h2 style='color:#ff2a6d; border-bottom:2px solid #ff2a6d; padding-bottom:10px; margin-top:0;'>EazyMUZE Database Migration & Diagnostic Tool 💋</h2>";

require_once 'api/db.php';

function addColumnIfNeeded($pdo, $table, $column, $definition) {
    try {
        // Query to check if column exists
        $rs = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($rs->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD `$column` $definition");
            echo "<li style='color:green;'>✓ Added column <b>`$column`</b> to table <b>`$table`</b></li>";
        } else {
            echo "<li style='color:#888;'>• Column <b>`$column`</b> already exists in <b>`$table`</b></li>";
        }
    } catch (Exception $e) {
        echo "<li style='color:#e74c3c;'>❌ Error adding column <b>`$column`</b> to <b>`$table`</b>: " . htmlspecialchars($e->getMessage()) . "</li>";
    }
}

function createTableIfNeeded($pdo, $sql, $tableName) {
    try {
        $pdo->exec($sql);
        echo "<li style='color:green;'>✓ Table <b>`$tableName`</b> verified/created.</li>";
    } catch (Exception $e) {
        echo "<li style='color:#e74c3c;'>❌ Error checking/creating table <b>`$tableName`</b>: " . htmlspecialchars($e->getMessage()) . "</li>";
    }
}

echo "<h3>Running migrations...</h3>";
echo "<ul style='line-height:1.6;'>";

// 1. Create missing tables
createTableIfNeeded($pdo, "CREATE TABLE IF NOT EXISTS kv_store (
    k          VARCHAR(120) NOT NULL PRIMARY KEY,
    val        TEXT         NOT NULL DEFAULT '',
    expires_at DATETIME     NOT NULL DEFAULT (NOW() + INTERVAL 1 HOUR),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'kv_store');

createTableIfNeeded($pdo, "CREATE TABLE IF NOT EXISTS market (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'market');

createTableIfNeeded($pdo, "CREATE TABLE IF NOT EXISTS support_tickets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'support_tickets');

createTableIfNeeded($pdo, "CREATE TABLE IF NOT EXISTS reports (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'reports');

createTableIfNeeded($pdo, "CREATE TABLE IF NOT EXISTS bookmarks (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    post_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookmark (user_id, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'bookmarks');

createTableIfNeeded($pdo, "CREATE TABLE IF NOT EXISTS reactions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    post_id    INT UNSIGNED NOT NULL,
    reaction   VARCHAR(10)  NOT NULL DEFAULT '❤️',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reaction (user_id, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'reactions');

// 2. Add missing columns to users table
addColumnIfNeeded($pdo, 'users', 'last_seen', 'DATETIME DEFAULT NULL');
addColumnIfNeeded($pdo, 'users', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
addColumnIfNeeded($pdo, 'users', 'bio', 'TEXT DEFAULT NULL');
addColumnIfNeeded($pdo, 'users', 'location', 'VARCHAR(120) DEFAULT NULL');
addColumnIfNeeded($pdo, 'users', 'phone', 'VARCHAR(20) DEFAULT NULL');

// 3. Add missing columns to messages table
addColumnIfNeeded($pdo, 'messages', 'image_url', 'MEDIUMTEXT DEFAULT NULL');
addColumnIfNeeded($pdo, 'messages', 'reaction', 'VARCHAR(10) DEFAULT NULL');

// 4. Add missing columns to invites table
addColumnIfNeeded($pdo, 'invites', 'sender_id', 'INT UNSIGNED DEFAULT NULL');
addColumnIfNeeded($pdo, 'invites', 'receiver_id', 'INT UNSIGNED DEFAULT NULL');
addColumnIfNeeded($pdo, 'invites', 'message', 'TEXT DEFAULT NULL');
addColumnIfNeeded($pdo, 'invites', 'status', "ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending'");

// 5. Add missing columns to payments table
addColumnIfNeeded($pdo, 'payments', 'reference', 'VARCHAR(120) DEFAULT NULL');

// 6. Perform seed and cleanup tasks
try {
    $pdo->exec("UPDATE users SET last_seen = NOW() WHERE last_seen IS NULL");
    echo "<li style='color:green;'>✓ Seeded initial `last_seen` values for existing users.</li>";
} catch (Exception $e) {}

echo "</ul>";

echo "<h3>Diagnostic Table Check:</h3>";
function checkTableColumns($pdo, $tableName) {
    echo "<p style='margin: 8px 0;'>Table <b>`$tableName`</b>: ";
    try {
        $q = $pdo->query("DESCRIBE `$tableName`");
        $cols = $q->fetchAll(PDO::FETCH_COLUMN);
        echo "<span style='color:#2ecc71;'>" . implode(', ', $cols) . "</span>";
    } catch (Exception $e) {
        echo "<span style='color:#e74c3c;'>NOT FOUND OR ERROR: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    echo "</p>";
}

checkTableColumns($pdo, 'users');
checkTableColumns($pdo, 'invites');
checkTableColumns($pdo, 'messages');
checkTableColumns($pdo, 'market');

echo "<h3 style='color:#ff2a6d; border-top:1px solid rgba(255,42,109,0.3); padding-top:15px;'>Migration complete! Please reload Explore, Invites, Whispers, or Temple.</h3>";
echo "</div>";
?>
