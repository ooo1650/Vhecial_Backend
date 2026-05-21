<?php
/**
 * One-time migration: change users.picture from VARCHAR(500) to LONGTEXT
 * Access via: https://<render-backend>/database/migrate_picture_column.php?secret=run_now
 * DELETE after running.
 */
if (($_GET['secret'] ?? '') !== 'run_now') {
    http_response_code(403); die('Forbidden');
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN picture LONGTEXT DEFAULT NULL");
    echo "✅ users.picture column changed to LONGTEXT successfully.";
} catch (PDOException $e) {
    echo "❌ Failed: " . $e->getMessage();
}
