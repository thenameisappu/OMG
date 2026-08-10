<?php
/**
 * migrate_archive.php
 *
 * Safely adds the is_archived, archived_at, and archived_by columns
 * to the orders table if they do not already exist.
 *
 * Run once: Visit /backend/migrate_archive.php in your browser
 * (while logged in as admin), or run via CLI: php migrate_archive.php
 *
 * This script NEVER drops or modifies existing data.
 */

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isCli = (php_sapi_name() === 'cli');

// Security: only allow admin or CLI
if (!$isCli && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    http_response_code(403);
    die("Access denied. Please log in as admin first.");
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    die("Database connection failed.");
}

$results = [];

/**
 * Checks if a column exists in a table and adds it if not.
 */
function addColumnIfMissing($db, $table, $column, $definition, &$results)
{
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE :col");
    $stmt->execute([':col' => $column]);
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $results[] = "✅ Added column `$table`.`$column` ($definition)";
    } else {
        $results[] = "ℹ️  Column `$table`.`$column` already exists — skipped.";
    }
}

try {
    // Add is_archived
    addColumnIfMissing(
        $db,
        'orders',
        'is_archived',
        'TINYINT(1) NOT NULL DEFAULT 0',
        $results
    );

    // Add archived_at
    addColumnIfMissing(
        $db,
        'orders',
        'archived_at',
        'DATETIME DEFAULT NULL',
        $results
    );

    // Add archived_by
    addColumnIfMissing(
        $db,
        'orders',
        'archived_by',
        'VARCHAR(100) DEFAULT NULL',
        $results
    );

    // Add an index on is_archived for query performance
    $idxCheck = $db->query("SHOW INDEX FROM `orders` WHERE Key_name = 'idx_orders_is_archived'");
    if ($idxCheck && $idxCheck->rowCount() === 0) {
        $db->exec("ALTER TABLE `orders` ADD INDEX `idx_orders_is_archived` (`is_archived`)");
        $results[] = "✅ Added index `idx_orders_is_archived` on `orders`.`is_archived`";
    } else {
        $results[] = "ℹ️  Index `idx_orders_is_archived` already exists — skipped.";
    }

    $results[] = "";
    $results[] = "✅ Migration completed successfully.";
    $results[] = "You can now delete or disable this file.";

} catch (Exception $e) {
    $results[] = "❌ Migration failed: " . $e->getMessage();
}

if ($isCli) {
    foreach ($results as $line) {
        echo $line . "\n";
    }
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>OMG Migration</title>';
    echo '<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;line-height:1.8;}';
    echo 'h2{color:#f59e0b;}pre{background:#1e293b;padding:1rem;border-radius:8px;}</style></head><body>';
    echo '<h2>OMG Admin — Archive Migration</h2><pre>';
    foreach ($results as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo '</pre></body></html>';
}
