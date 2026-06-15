<?php
// EazyMUZE v6.0 - Migration Script to add missing columns safely
require_once 'db.php';

header("Content-Type: application/json");

try {
    $reports = [];

    // 1. Add gender column if not exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT 'female'");
        $reports[] = "Added 'gender' column.";
    } catch(Exception $e) {
        $reports[] = "'gender' column already exists or skipped: " . $e->getMessage();
    }

    // 2. Add role column if not exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
        $reports[] = "Added 'role' column.";
    } catch(Exception $e) {
        $reports[] = "'role' column already exists or skipped: " . $e->getMessage();
    }

    // 3. Add has_used_free_read column if not exists
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN has_used_free_read BOOLEAN DEFAULT FALSE");
        $reports[] = "Added 'has_used_free_read' column.";
    } catch(Exception $e) {
        $reports[] = "'has_used_free_read' column already exists or skipped: " . $e->getMessage();
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Migration v6.0 executed successfully.',
        'details' => $reports
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Migration v6.0 failed: ' . $e->getMessage()
    ]);
}
?>
