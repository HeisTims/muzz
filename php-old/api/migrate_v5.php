<?php
// EazyMUZE v5.0 - Migration Script
require_once 'db.php';

try {
    // Add phone column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20)");
    } catch(Exception $e) {}

    // Add email_verified column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE");
    } catch(Exception $e) {}

    // Add email_verification_token column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verification_token VARCHAR(255)");
    } catch(Exception $e) {}

    // Add monnify_ref column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_ref VARCHAR(255)");
    } catch(Exception $e) {}

    // Add monnify_account_number column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_account_number VARCHAR(20)");
    } catch(Exception $e) {}

    // Add monnify_bank_name column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_bank_name VARCHAR(100)");
    } catch(Exception $e) {}

    // Add monnify_bank_code column
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN monnify_bank_code VARCHAR(20)");
    } catch(Exception $e) {}

    echo "Migration v5.0 completed successfully!";
} catch (Exception $e) {
    echo "Migration v5.0 error: " . $e->getMessage();
}
?>
