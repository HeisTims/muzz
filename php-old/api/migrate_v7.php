<?php
// EazyMUZE v7.0 - Migration Script for Bookmarks & Message Reactions
require_once 'db.php';

header("Content-Type: application/json");

try {
    $reports = [];

    // 1. Create bookmarks table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bookmarks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            post_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_post (user_id, post_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $reports[] = "Created/Verified 'bookmarks' table.";
    } catch(Exception $e) {
        $reports[] = "Failed creating 'bookmarks' table: " . $e->getMessage();
    }

    // 2. Add reaction column to messages table
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN reaction VARCHAR(50) DEFAULT NULL");
        $reports[] = "Added 'reaction' column to 'messages' table.";
    } catch(Exception $e) {
        $reports[] = "'reaction' column already exists or skipped: " . $e->getMessage();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Migration v7.0 executed successfully.',
        'details' => $reports
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Migration v7.0 failed: ' . $e->getMessage()
    ]);
}
?>
