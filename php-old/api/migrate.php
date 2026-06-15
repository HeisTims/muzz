<?php
// EazyMUZE v3.0 - Migration Script
require_once 'db.php';

try {
    // Modify users table
    $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255)");
    $pdo->exec("ALTER TABLE users ADD COLUMN facebook_id VARCHAR(255)");
    $pdo->exec("ALTER TABLE users ADD COLUMN has_used_free_whisper BOOLEAN DEFAULT 0");

    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create ads table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image VARCHAR(255) NOT NULL,
        caption TEXT,
        link VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert a sample ad
    $pdo->exec("INSERT INTO ads (image, caption, link) VALUES ('https://picsum.photos/seed/ad1/600/300', 'Sponsored: Get the best vibes with Muze Premium!', '#')");

    echo "Migration completed successfully!";
} catch (Exception $e) {
    // If columns already exist, it will throw an error, which we can ignore for now or output it
    echo "Migration notice: " . $e->getMessage();
}
?>
