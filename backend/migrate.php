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

    // Check if table exists helper
    function tableExists($db, $table) {
        try {
            $result = $db->query("SELECT 1 FROM `$table` LIMIT 1");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
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

    // Fix foreign keys referencing shop_products
    if (tableExists($db, 'order_items')) {
        $fkOrderQuery = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
                         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                         WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'order_items' 
                           AND COLUMN_NAME = 'product_id'";
        $fkOrderStmt = $db->query($fkOrderQuery);
        $fkOrderRows = $fkOrderStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fkOrderRows as $row) {
            if ($row['REFERENCED_TABLE_NAME'] === 'shop_products') {
                $constraint = $row['CONSTRAINT_NAME'];
                $queries[] = "ALTER TABLE order_items DROP FOREIGN KEY `$constraint`";
                // Delete orphaned records that would prevent constraint generation
                $queries[] = "DELETE FROM order_items WHERE product_id NOT IN (SELECT id FROM products)";
                $queries[] = "ALTER TABLE order_items ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE";
            }
        }
    }

    if (tableExists($db, 'wishlist')) {
        $fkWishQuery = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'wishlist' 
                          AND COLUMN_NAME = 'product_id'";
        $fkWishStmt = $db->query($fkWishQuery);
        $fkWishRows = $fkWishStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($fkWishRows as $row) {
            if ($row['REFERENCED_TABLE_NAME'] === 'shop_products') {
                $constraint = $row['CONSTRAINT_NAME'];
                $queries[] = "ALTER TABLE wishlist DROP FOREIGN KEY `$constraint`";
                // Delete orphaned records that would prevent constraint generation
                $queries[] = "DELETE FROM wishlist WHERE product_id NOT IN (SELECT id FROM products)";
                $queries[] = "ALTER TABLE wishlist ADD CONSTRAINT `fk_wishlist_product` FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE";
            }
        }
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
