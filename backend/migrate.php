<?php
require_once 'config.php';

// Set content type to HTML for browser viewing
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Database Migration Tool</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Could not connect to database.");
    }
    
    // Check if column exists helper
    function columnExists($db, $table, $column) {
        $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->rowCount() > 0;
    }

    $queries = [];
    
    if (!columnExists($db, 'products', 'stock_quantity')) {
        $queries[] = "ALTER TABLE products ADD COLUMN stock_quantity INT DEFAULT 0 AFTER stock_status";
    }
    
    if (!columnExists($db, 'products', 'is_active')) {
        $queries[] = "ALTER TABLE products ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER stock_quantity";
    }
    
    if (!columnExists($db, 'products', 'sku')) {
        $queries[] = "ALTER TABLE products ADD COLUMN sku VARCHAR(100) DEFAULT NULL AFTER is_active";
    }
    
    if (!columnExists($db, 'products', 'images')) {
        $queries[] = "ALTER TABLE products ADD COLUMN images JSON DEFAULT NULL AFTER sku";
    }

    if (empty($queries)) {
        echo "<p style='color: green;'>Database is already up to date. No changes needed.</p>";
    } else {
        echo "<ul>";
        foreach ($queries as $query) {
            $db->exec($query);
            echo "<li style='color: blue;'>Successfully executed: <code>$query</code></li>";
        }
        echo "</ul>";
        echo "<p style='color: green; font-weight: bold;'>Migration completed successfully!</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Migration failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
