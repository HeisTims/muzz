<?php
// EazyMUZE v4.0 - Migration Script
require_once 'db.php';

try {
    // Add comments to posts safely
    try {
        $pdo->exec("ALTER TABLE posts ADD COLUMN comments JSON");
    } catch(Exception $e) {}
    
    // Ensure black_market_orders table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS black_market_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        items JSON NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) DEFAULT 'Placed',
        tracking_step INT DEFAULT 1,
        escrow_status VARCHAR(50) DEFAULT 'funded',
        seller VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Add escrow status to black market orders safely if not already present
    try {
        $pdo->exec("ALTER TABLE black_market_orders ADD COLUMN escrow_status VARCHAR(50) DEFAULT 'funded'");
    } catch(Exception $e) {}
    
    // Ensure invites table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        volunteers JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Add device_info to messages table safely
    try {
        $pdo->exec("ALTER TABLE messages ADD COLUMN device_info VARCHAR(255) DEFAULT 'Unknown Device'");
    } catch(Exception $e) {}
    
    echo "v4.0 Migration completed successfully!";
} catch (Exception $e) {
    echo "Migration notice: " . $e->getMessage();
}
?>
