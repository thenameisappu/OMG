<?php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Executing Database Constraint Fix</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Could not connect to database.");
    }
    
    // Step 1: Drop the incorrect constraint
    echo "<p>Attempting to drop the incorrect constraint <code>fk_order_items_product</code>...</p>";
    try {
        $db->exec("ALTER TABLE order_items DROP FOREIGN KEY `fk_order_items_product`");
        echo "<p style='color: green;'>Successfully dropped foreign key constraint <code>fk_order_items_product</code>.</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Notice/Skip: Could not drop constraint (it might not exist or has a different name). Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Step 2: Clean up orphaned rows in order_items
    echo "<p>Cleaning up orphaned rows in <code>order_items</code> that refer to invalid products...</p>";
    try {
        $deletedCount = $db->exec("DELETE FROM order_items WHERE product_id NOT IN (SELECT id FROM products)");
        echo "<p style='color: green;'>Successfully deleted $deletedCount orphaned row(s) from <code>order_items</code>.</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error cleaning up order_items: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Step 3: Add the correct constraint referencing products(id)
    echo "<p>Attempting to add correct foreign key constraint to <code>products(id)</code>...</p>";
    try {
        $db->exec("ALTER TABLE order_items ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE");
        echo "<p style='color: green;'>Successfully added foreign key constraint referencing <code>products(id)</code>!</p>";
    } catch (Exception $e) {
        echo "<p style='color: red; font-weight: bold;'>Error adding constraint: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Step 4: Optional wishlist constraint fix
    echo "<p>Checking for incorrect constraints on the <code>wishlist</code> table...</p>";
    try {
        $fkWishQuery = "SELECT CONSTRAINT_NAME 
                        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'wishlist' 
                          AND COLUMN_NAME = 'product_id'
                          AND REFERENCED_TABLE_NAME = 'shop_products'";
        $fkWishStmt = $db->query($fkWishQuery);
        $fkWishRow = $fkWishStmt->fetch(PDO::FETCH_ASSOC);
        
        // Always clean up orphaned wishlist items regardless of whether the constraint was incorrect
        $deletedWishCount = $db->exec("DELETE FROM wishlist WHERE product_id NOT IN (SELECT id FROM products)");
        if ($deletedWishCount > 0) {
            echo "<p style='color: green;'>Successfully deleted $deletedWishCount orphaned row(s) from <code>wishlist</code>.</p>";
        }

        if ($fkWishRow) {
            $constraint = $fkWishRow['CONSTRAINT_NAME'];
            $db->exec("ALTER TABLE wishlist DROP FOREIGN KEY `$constraint`");
            $db->exec("ALTER TABLE wishlist ADD CONSTRAINT `fk_wishlist_product` FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE");
            echo "<p style='color: green;'>Successfully corrected wishlist foreign key constraint!</p>";
        } else {
            echo "<p style='color: green;'>Wishlist table constraint is already correct or does not reference <code>shop_products</code>.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>Notice/Skip: Wishlist constraint fix skipped: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<h3 style='color: green;'>Fix script completed execution!</h3>";

} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Execution failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
